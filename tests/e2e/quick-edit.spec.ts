import { test, expect, wpCli } from './fixtures';

const TAX = 'cme_e2e_no_default';
const REST_BASE = 'cme_e2e_no_default_terms';

async function termIdBySlug(api, slug: string): Promise<number> {
	const response = await api.get(`/wp-json/wp/v2/${REST_BASE}?slug=${slug}`);
	expect(response.ok()).toBeTruthy();
	const body = await response.json();
	expect(body.length).toBe(1);
	return body[0].id as number;
}

/**
 * Quick Edit posts a checkbox-form to admin-ajax.php?action=inline-save —
 * a code path our existing REST + wp-cli specs don't exercise. The user
 * can tick multiple boxes (the standard WP UI doesn't enforce single
 * selection at the DOM level), and the only thing keeping the
 * single-term invariant intact is our `set_object_terms` action.
 *
 * Regression scenario this protects against: a refactor that gates
 * enforcement on a request-source check (e.g., `is_rest_request()`)
 * would silently let inline-save submissions persist multi-term state.
 */
test.describe('Quick Edit — single-term enforcement', () => {
	test('checking a second term in the inline form → only last-in-form term survives', async ({ page, api, createdPostIds }) => {
		const alphaId = await termIdBySlug(api, 'alpha');
		const betaId = await termIdBySlug(api, 'beta');

		// Seed via REST so the post starts with exactly [Alpha] on the
		// no_default tax. Posting cme_e2e terms is unnecessary — that tax has
		// default_term=General, which WP core auto-applies on create.
		const titleStamp = `Quick Edit ${Date.now()}`;
		const created = await api.post('/wp-json/wp/v2/posts', {
			data: { title: titleStamp, status: 'publish', cme_e2e_no_default_terms: [ alphaId ] },
		});
		expect(created.ok()).toBeTruthy();
		const postId = (await created.json()).id as number;
		createdPostIds.push(postId);

		await page.goto('/wp-admin/edit.php');

		// Open the Quick Edit form on the row whose title matches our seed.
		// Filtering by row first avoids accidentally hitting another post's
		// inline button if the edit list contains older e2e fixtures.
		const row = page.locator(`tr#post-${postId}`);
		await expect(row).toBeVisible();
		// WP positions .row-actions off-screen until the row receives focus
		// or hover, so a direct click on the Quick Edit button times out
		// "element is outside of the viewport". Hover reveals it first.
		await row.hover();
		await row.locator('button.editinline').click();

		const editForm = page.locator(`tr#edit-${postId}`);
		await expect(editForm).toBeVisible();

		// Pre-condition: Alpha already checked (REST seed). Beta unchecked.
		const alphaBox = editForm.locator(`input[name="tax_input[${TAX}][]"][value="${alphaId}"]`);
		const betaBox = editForm.locator(`input[name="tax_input[${TAX}][]"][value="${betaId}"]`);
		await expect(alphaBox).toBeChecked();
		await expect(betaBox).not.toBeChecked();

		// Tick Beta, leaving Alpha checked, so the form submits both.
		await betaBox.check();

		// Save round-trip waits on the inline-save AJAX response so the next
		// REST read observes the post-action state, not the pre-action one.
		await Promise.all([
			page.waitForResponse((r) => r.url().includes('admin-ajax.php') && r.request().method() === 'POST'),
			editForm.locator('button.save').click(),
		]);
		await expect(editForm).toBeHidden({ timeout: 10_000 });

		// Single-term enforcement: of_cme_enforce_single_term picks the LAST
		// id in the committed array, which mirrors form submission order
		// (alpha < beta alphabetically in the checklist), so beta survives.
		const response = await api.get(`/wp-json/wp/v2/posts/${postId}?context=edit`);
		expect(response.ok()).toBeTruthy();
		const body = await response.json();
		expect(body[REST_BASE]).toEqual([ betaId ]);
	});
});
