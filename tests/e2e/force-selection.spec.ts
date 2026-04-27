import { test, expect, wpCli } from './fixtures';

const E2E_TAX = 'cme_e2e';
const NO_DEFAULT_TAX = 'cme_e2e_no_default';
const E2E_OPTION = 'category-metabox-enhanced_cme_e2e';

const RADIO_FS_ON = JSON.stringify({
	type: 'radio',
	context: 'side',
	priority: 'default',
	metabox_title: 'E2E Taxonomy',
	indented: 1,
	allow_new_terms: 1,
	force_selection: 1,
});

/**
 * Reset the cme_e2e plugin option to a known baseline. Specs that mutate
 * the option (Settings page toggle, mode/FS swaps) call this in afterEach
 * so the next spec starts from a predictable state.
 */
function resetE2eOption() {
	wpCli(['option', 'update', E2E_OPTION, RADIO_FS_ON, '--format=json']);
}

async function fetchPostTerms(api, postId: number, restBase: string) {
	const response = await api.get(`/wp-json/wp/v2/posts/${postId}?context=edit`);
	expect(response.ok()).toBeTruthy();
	const body = await response.json();
	return body[restBase] as number[];
}

test.describe('Force selection — Block Editor', () => {
	test.afterEach(() => {
		resetE2eOption();
	});

	test('FS on + radio mode + publish with no panel interaction → default term assigned', async ({ page, api, createdPostIds }) => {
		await openPostNew(page, 'FS on default-term assignment');

		// Block Editor renders our PluginDocumentSettingPanel in the sidebar;
		// we don't need to expand it for this spec — only proving the
		// publish round-trip carries the registered default through to REST.
		await expect(page.locator('.of-cme-panel-cme_e2e')).toBeAttached({ timeout: 15_000 });

		const postId = await publishPost(page);
		createdPostIds.push(postId);

		const terms = await fetchPostTerms(api, postId, 'cme_e2e_terms');
		expect(terms.length).toBe(1);
		const term = await api.get(`/wp-json/wp/v2/cme_e2e_terms/${terms[0]}`).then((r) => r.json());
		expect(term.slug).toBe('general');
	});

	test('select mode + FS on → "— Select —" disappears once a term is picked', async ({ page }) => {
		wpCli(['option', 'update', E2E_OPTION, JSON.stringify({ ...JSON.parse(RADIO_FS_ON), type: 'select' }), '--format=json']);

		await openPostNew(page, 'Select mode FS on');
		const panel = await openCmePanel(page);

		const select = panel.locator('select');
		await expect(select).toBeVisible();

		// Initial state — no selection — exposes the "— Select —" placeholder.
		await expect(select.locator('option', { hasText: /Select/ })).toHaveCount(1);

		// Picking a term removes the placeholder from the DOM (FS-on path).
		await select.selectOption({ label: 'News' });
		await expect(select.locator('option', { hasText: /Select/ })).toHaveCount(0);
	});

	test('radio mode + FS off → Clear button clears selection', async ({ page }) => {
		wpCli(['option', 'update', E2E_OPTION, JSON.stringify({ ...JSON.parse(RADIO_FS_ON), force_selection: 0 }), '--format=json']);

		await openPostNew(page, 'Radio FS off Clear');
		const panel = await openCmePanel(page);

		await panel.locator('label').filter({ hasText: 'News' }).click();

		const clearButton = panel.getByRole('button', { name: 'Clear' });
		await expect(clearButton).toBeVisible();
		await clearButton.click();

		// After Clear: no radio is checked, and the button hides (it's gated
		// on `! forceSelection && selectedId`).
		await expect(panel.locator('input[name="of-cme-cme_e2e"]:checked')).toHaveCount(0);
		await expect(clearButton).toBeHidden();
	});
});

test.describe('Force selection — Settings page', () => {
	test.afterEach(() => {
		resetE2eOption();
	});

	test('toggling Force selection off persists across reload', async ({ page }) => {
		await page.goto('/wp-admin/options-general.php?page=category-metabox-enhanced&tab=cme_e2e');

		const checkbox = page.locator('input[name="category-metabox-enhanced_cme_e2e[force_selection]"]');
		await expect(checkbox).toBeChecked();

		await checkbox.uncheck();
		await page.locator('input[type="submit"][name="submit"]').click();
		await expect(page.locator('.notice-success, #setting-error-settings_updated')).toBeVisible({ timeout: 10_000 });

		await page.goto('/wp-admin/options-general.php?page=category-metabox-enhanced&tab=cme_e2e');
		await expect(page.locator('input[name="category-metabox-enhanced_cme_e2e[force_selection]"]')).not.toBeChecked();
	});
});

test.describe('Force selection — REST and programmatic regressions', () => {
	test('REST: empty cme_e2e_no_default_terms with FS on → substituted with first-by-name', async ({ api, createdPostIds }) => {
		// The 0.9.0 regression — the dead pre_set_object_terms hook — would
		// have left this post with []. Post-fix, our set_object_terms action
		// re-issues wp_set_object_terms with the resolved default.
		const response = await api.post('/wp-json/wp/v2/posts', {
			data: {
				title: 'REST empty FS on',
				status: 'publish',
				cme_e2e_no_default_terms: [],
			},
		});
		expect(response.ok()).toBeTruthy();
		const body = await response.json();
		createdPostIds.push(body.id);

		const term = await api.get(`/wp-json/wp/v2/cme_e2e_no_default_terms/${body.cme_e2e_no_default_terms[0]}`).then((r) => r.json());
		expect(term.slug).toBe('alpha');
	});

	test('wp_set_object_terms with [0] sentinel + FS on → substituted', async ({ api, createdPostIds }) => {
		// REST would reject [0] at the controller's term-permission check, so
		// we drive wp_set_object_terms directly through wp-cli — the same
		// path third-party plugins and import scripts take.
		const postId = await createPostViaRest(api);
		createdPostIds.push(postId);

		wpCli(['eval', `wp_set_object_terms(${postId}, [0], '${NO_DEFAULT_TAX}');`]);

		const terms = await fetchPostTerms(api, postId, 'cme_e2e_no_default_terms');
		expect(terms.length).toBe(1);
		const term = await api.get(`/wp-json/wp/v2/cme_e2e_no_default_terms/${terms[0]}`).then((r) => r.json());
		expect(term.slug).toBe('alpha');
	});

	test('wp_set_object_terms with slug array → resolves to that term, single-term invariant preserved', async ({ api, createdPostIds }) => {
		// WP resolves slugs to IDs inside wp_set_object_terms before our
		// set_object_terms action fires, so a slug like "beta" lands as the
		// resolved term ID and our handler sees a clean single-term assignment.
		const postId = await createPostViaRest(api);
		createdPostIds.push(postId);

		wpCli(['eval', `wp_set_object_terms(${postId}, ['beta'], '${NO_DEFAULT_TAX}');`]);

		const terms = await fetchPostTerms(api, postId, 'cme_e2e_no_default_terms');
		expect(terms.length).toBe(1);
		const term = await api.get(`/wp-json/wp/v2/cme_e2e_no_default_terms/${terms[0]}`).then((r) => r.json());
		expect(term.slug).toBe('beta');
	});
});

/**
 * Block Editor 6.x renders the post canvas inside an iframe (`<iframe
 * name="editor-canvas">`), so the title input is not in the page's main
 * document. Sidebar panels — including ours — stay in the main DOM.
 */
async function openPostNew(page, title: string) {
	await page.goto('/wp-admin/post-new.php');

	// Welcome guide may pop up on a fresh user; close if present, ignore if not.
	await page
		.locator('.edit-post-welcome-guide button[aria-label="Close"], .editor-welcome-guide button[aria-label="Close"]')
		.first()
		.click({ timeout: 2_000 })
		.catch(() => undefined);

	const canvas = page.frameLocator('iframe[name="editor-canvas"]');
	await canvas.locator('[aria-label="Add title"]').fill(title);
}

/**
 * PluginDocumentSettingPanel renders collapsed in some WP versions. Find
 * the panel header and expand it if needed before returning the panel
 * locator for assertions.
 */
async function openCmePanel(page) {
	const panel = page.locator('.of-cme-panel-cme_e2e');
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
	// First click opens the pre-publish panel; second click confirms.
	await page.locator('button.editor-post-publish-panel__toggle, button.editor-post-publish-button').first().click();
	await page.locator('button.editor-post-publish-button').click({ timeout: 10_000 });
	await expect(
		page.locator('.editor-post-publish-panel__postpublish, .components-snackbar').filter({ hasText: /publish/i }).first()
	).toBeVisible({ timeout: 15_000 });

	const postId = await page.evaluate(() => {
		// Block Editor exposes the current post id via the editor data store.
		const w = window as unknown as { wp?: { data?: { select?: (s: string) => { getCurrentPostId?: () => number } } } };
		return w.wp?.data?.select?.('core/editor')?.getCurrentPostId?.() ?? 0;
	});
	if (!postId) throw new Error('Could not read post id from core/editor store');
	return postId;
}

async function createPostViaRest(api): Promise<number> {
	const response = await api.post('/wp-json/wp/v2/posts', {
		data: { title: 'e2e seed post', status: 'publish' },
	});
	expect(response.ok()).toBeTruthy();
	return (await response.json()).id;
}
