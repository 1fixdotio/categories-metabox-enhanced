import { test, expect, gotoAdmin } from './fixtures';

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
 * Bulk Edit's "Edit" action POSTs the form to /wp-admin/edit.php — a
 * code path neither REST nor the inline-save AJAX handler exercises.
 *
 * It's also a path where the append-vs-replace distinction is subtle:
 * bulk_edit_posts() merges existing + new term IDs *in PHP* and then
 * calls edit_post(), which routes through wp_update_post() and lands
 * at wp_set_object_terms( ..., $append = false ). The "append" only
 * happens at the array-merge step; the storage call is replace, so
 * of_cme_enforce_single_term sees a >1-term commit and coerces to the
 * last id (matching the same semantics quick-edit and REST PATCH show).
 *
 * Concretely: posts pre-seeded with [Alpha], bulk-edited with Beta
 * checked, end up at [Beta] — not [Alpha, Beta], not [Alpha].
 *
 * Regression scenario this protects against: a refactor that broadens
 * the append-mode bail-out (helper-functions.php:109) — say, by also
 * skipping when `$old_tt_ids` is non-empty — would silently let bulk
 * edit ship multi-term state across many posts at once.
 */
test.describe('Bulk Edit — single-term enforcement', () => {
	test('ticking a term in bulk edit → every selected post is coerced to the merged-last term', async ({ page, api, createdPostIds }) => {
		const alphaId = await termIdBySlug(api, 'alpha');
		const betaId = await termIdBySlug(api, 'beta');

		// Two posts, each pre-seeded with [Alpha]. Bulk-editing them with
		// Beta exercises the per-post merge-and-replace inside bulk_edit_posts.
		const titleStamp = Date.now();
		const seeds = await Promise.all(
			[ 1, 2 ].map((n) =>
				api.post('/wp-json/wp/v2/posts', {
					data: { title: `Bulk Edit ${titleStamp}-${n}`, status: 'publish', cme_e2e_no_default_terms: [ alphaId ] },
				})
			)
		);
		for (const r of seeds) expect(r.ok()).toBeTruthy();
		const postIds = await Promise.all(seeds.map(async (r) => (await r.json()).id as number));
		createdPostIds.push(...postIds);

		await gotoAdmin(page, '/wp-admin/edit.php');

		// Tick the row checkbox for each seeded post.
		for (const postId of postIds) {
			await page.locator(`input[name="post[]"][value="${postId}"]`).check();
		}

		// Open the bulk-edit inline form via the top toolbar's bulk-action
		// dropdown. The bottom toolbar mirrors it; choose top for stability.
		await page.locator('select#bulk-action-selector-top').selectOption('edit');
		await page.locator('#doaction').click();

		const bulkForm = page.locator('tr#bulk-edit');
		await expect(bulkForm).toBeVisible();

		// Bulk-edit's hierarchical taxonomy panel renders without pre-checks
		// (the form represents a *delta* to apply to all posts, not the
		// current state). Ticking Beta tells WP "add Beta to each."
		const betaBox = bulkForm.locator(`input[name="tax_input[${TAX}][]"][value="${betaId}"]`);
		await expect(betaBox).toBeVisible();
		await expect(betaBox).not.toBeChecked();
		await betaBox.check();

		// Bulk Update is a normal form submit, not AJAX — wait for the
		// resulting navigation back to edit.php to settle before asserting.
		await Promise.all([
			page.waitForURL(/\/wp-admin\/edit\.php/),
			bulkForm.locator('input#bulk_edit').click(),
		]);

		// of_cme_enforce_single_term takes the last id in the merged array
		// (alpha appended-to-by beta → [alpha, beta]; last wins → beta).
		// Both posts must end exactly at [beta].
		for (const postId of postIds) {
			const response = await api.get(`/wp-json/wp/v2/posts/${postId}?context=edit`);
			expect(response.ok()).toBeTruthy();
			const body = await response.json();
			expect(body[REST_BASE]).toEqual([ betaId ]);
		}
	});
});
