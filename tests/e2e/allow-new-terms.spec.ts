import { test, expect, wpCli } from './fixtures';

const TAX = 'cme_e2e_no_default';
const REST_BASE = 'cme_e2e_no_default_terms';
const OPTION = 'category-metabox-enhanced_cme_e2e_no_default';
const PANEL_CLASS = `.of-cme-panel-${TAX}`;

const NEW_TERM_NAME = 'E2E Created';
const NEW_TERM_SLUG = 'e2e-created';

const RADIO_FS_ON_ALLOW_NEW = {
	type: 'radio',
	context: 'side',
	priority: 'default',
	metabox_title: 'E2E No Default',
	indented: 1,
	allow_new_terms: 1,
	force_selection: 1,
};

function setOption(overrides: Partial<typeof RADIO_FS_ON_ALLOW_NEW>): void {
	wpCli([
		'option',
		'update',
		OPTION,
		JSON.stringify({ ...RADIO_FS_ON_ALLOW_NEW, ...overrides }),
		'--format=json',
	]);
}

function resetOption(): void {
	wpCli(['option', 'update', OPTION, JSON.stringify(RADIO_FS_ON_ALLOW_NEW), '--format=json']);
}

/**
 * Idempotently delete the term created by the create-and-publish spec so
 * reruns start from a clean term list. wp_delete_term is a no-op when the
 * slug doesn't resolve, so this is safe even if the test failed before
 * creating the term.
 */
function deleteCreatedTerm(): void {
	wpCli([
		'eval',
		`$t = get_term_by('slug', '${NEW_TERM_SLUG}', '${TAX}'); if ($t) wp_delete_term($t->term_id, '${TAX}');`,
	]);
}

async function openPostNew(page, title: string): Promise<void> {
	await page.goto('/wp-admin/post-new.php');
	await page
		.locator('.edit-post-welcome-guide button[aria-label="Close"], .editor-welcome-guide button[aria-label="Close"]')
		.first()
		.click({ timeout: 2_000 })
		.catch(() => undefined);
	const canvas = page.frameLocator('iframe[name="editor-canvas"]');
	await canvas.locator('[aria-label="Add title"]').fill(title);
}

async function openPanel(page) {
	const panel = page.locator(PANEL_CLASS);
	await expect(panel).toBeAttached({ timeout: 15_000 });
	const toggle = panel.locator('button.components-panel__body-toggle').first();
	if ((await toggle.count()) > 0) {
		const expanded = await toggle.getAttribute('aria-expanded');
		if (expanded !== 'true') {
			await toggle.click();
		}
	}
	return panel;
}

async function publishPost(page): Promise<number> {
	await page.locator('button.editor-post-publish-panel__toggle, button.editor-post-publish-button').first().click();
	await page.locator('button.editor-post-publish-button').click({ timeout: 10_000 });
	await expect(
		page.locator('.editor-post-publish-panel__postpublish, .components-snackbar').filter({ hasText: /publish/i }).first()
	).toBeVisible({ timeout: 15_000 });
	const postId = await page.evaluate(() => {
		const w = window as unknown as { wp?: { data?: { select?: (s: string) => { getCurrentPostId?: () => number } } } };
		return w.wp?.data?.select?.('core/editor')?.getCurrentPostId?.() ?? 0;
	});
	if (!postId) throw new Error('Could not read post id from core/editor store');
	return postId;
}

test.describe('allow_new_terms — Block Editor panel', () => {
	test.afterEach(() => {
		resetOption();
	});

	test('allow_new_terms=1 → "Add new term" input + Add button are rendered', async ({ page }) => {
		await openPostNew(page, 'allow_new_terms on');
		const panel = await openPanel(page);

		const adder = panel.locator('.of-cme-add-term');
		await expect(adder).toBeVisible();
		await expect(adder.getByLabel('Add new term')).toBeVisible();
		await expect(adder.getByRole('button', { name: /^Add$/ })).toBeVisible();
	});

	test('allow_new_terms=0 → adder is absent (setting plumbs through to UI)', async ({ page }) => {
		setOption({ allow_new_terms: 0 });

		await openPostNew(page, 'allow_new_terms off');
		const panel = await openPanel(page);

		// The panel itself still renders; only the inline term-creation block
		// is gated. Asserting count=0 (rather than not-visible) catches
		// regressions where the block is hidden via CSS but still mounted.
		await expect(panel.locator('.of-cme-add-term')).toHaveCount(0);
	});
});

test.describe('allow_new_terms — create + publish round-trip', () => {
	test.afterEach(() => {
		deleteCreatedTerm();
		resetOption();
	});

	test('typing a name + Add → new term appears, can be selected, and persists on publish', async ({ page, api, createdPostIds }) => {
		// Pre-clean in case a prior failing run left the term behind.
		deleteCreatedTerm();

		await openPostNew(page, 'allow_new_terms create');
		const panel = await openPanel(page);

		const adder = panel.locator('.of-cme-add-term');
		await adder.getByLabel('Add new term').fill(NEW_TERM_NAME);
		await adder.getByRole('button', { name: /^Add$/ }).click();

		// New radio for the created term appears once useEntityRecords
		// re-resolves after saveEntityRecord.
		const newRadioLabel = panel.locator('label').filter({ hasText: NEW_TERM_NAME });
		await expect(newRadioLabel).toBeVisible({ timeout: 10_000 });

		await newRadioLabel.click();
		await expect(panel.locator(`input[name="of-cme-${TAX}"]:checked`)).toHaveCount(1);

		const postId = await publishPost(page);
		createdPostIds.push(postId);

		// Resolve through REST: the post should carry exactly the new term,
		// and the term's slug should match what WP normalized from the name.
		const response = await api.get(`/wp-json/wp/v2/posts/${postId}?context=edit`);
		expect(response.ok()).toBeTruthy();
		const body = await response.json();
		const termIds = body[REST_BASE] as number[];
		expect(termIds.length).toBe(1);

		const termResponse = await api.get(`/wp-json/wp/v2/${REST_BASE}/${termIds[0]}`);
		expect(termResponse.ok()).toBeTruthy();
		const term = await termResponse.json();
		expect(term.slug).toBe(NEW_TERM_SLUG);
		expect(term.name).toBe(NEW_TERM_NAME);
	});
});
