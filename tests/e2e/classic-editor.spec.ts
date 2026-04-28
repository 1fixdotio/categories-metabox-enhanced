import { test, expect, wpCli } from './fixtures';

const CLASSIC_TAX = 'cme_e2e_classic';
const CLASSIC_CPT = 'cme_e2e_classic_post';
const CLASSIC_OPTION = 'category-metabox-enhanced_cme_e2e_classic';

const RADIO_FS_ON = {
	type: 'radio',
	context: 'side',
	priority: 'default',
	metabox_title: 'E2E Classic Tax',
	indented: 1,
	allow_new_terms: 1,
	force_selection: 1,
};

function setOption(overrides: Partial<typeof RADIO_FS_ON>): void {
	wpCli([
		'option',
		'update',
		CLASSIC_OPTION,
		JSON.stringify({ ...RADIO_FS_ON, ...overrides }),
		'--format=json',
	]);
}

function resetOption(): void {
	wpCli(['option', 'update', CLASSIC_OPTION, JSON.stringify(RADIO_FS_ON), '--format=json']);
}

async function gotoNewClassicPost(page) {
	await page.goto(`/wp-admin/post-new.php?post_type=${CLASSIC_CPT}`);
	// Classic editor renders synchronously — no welcome guide / iframe to wait on.
	await expect(page.locator('#post')).toBeVisible({ timeout: 10_000 });
}

/**
 * Selectors below come straight from Taxonomy_Single_Term's render output:
 *   <ul id="<slug>checklist">                       — radio container
 *   <select id="<slug>checklist">                   — select container
 *   #taxonomy-<slug>-clear                          — hidden value="0" radio (FS off only)
 *   #<slug>_input_element                           — postbox wrapper (the meta box ID)
 * Encoding them here keeps the asserts readable even when the metabox slug changes.
 */
const RADIO_LIST = `ul#${CLASSIC_TAX}checklist`;
const SELECT_BOX = `select#${CLASSIC_TAX}checklist`;
const CLEAR_STUB = `#taxonomy-${CLASSIC_TAX}-clear`;
const METABOX_BOX = `#${CLASSIC_TAX}_input_element`;

test.describe('Classic editor metabox — Taxonomy_Single_Term render', () => {
	test.afterEach(() => {
		resetOption();
	});

	test('radio mode + FS on → renders radios for every term, clear stub absent', async ({ page }) => {
		await gotoNewClassicPost(page);

		await expect(page.locator(METABOX_BOX)).toBeVisible();

		const list = page.locator(RADIO_LIST);
		await expect(list).toBeVisible();
		await expect(list.locator('input[type="radio"]')).toHaveCount(2);
		await expect(list.locator('label').filter({ hasText: 'Foo' })).toBeVisible();
		await expect(list.locator('label').filter({ hasText: 'Bar' })).toBeVisible();

		// FS on suppresses the value="0" stub used by Clear; the absence is
		// what makes "force a selection" actually load-bearing for users
		// who interact via the classic UI rather than REST.
		await expect(page.locator(CLEAR_STUB)).toHaveCount(0);
	});

	test('radio mode + FS off → clear stub is rendered (hidden li, value="0" input)', async ({ page }) => {
		setOption({ force_selection: 0 });

		await gotoNewClassicPost(page);

		const stub = page.locator(CLEAR_STUB);
		await expect(stub).toHaveCount(1);
		await expect(stub).toHaveAttribute('value', '0');
	});

	test('select mode + FS on → "None" option absent', async ({ page }) => {
		setOption({ type: 'select' });

		await gotoNewClassicPost(page);

		const select = page.locator(SELECT_BOX);
		await expect(select).toBeVisible();
		// Term options exist for Foo + Bar; the value="0" placeholder must
		// not, otherwise FS-on is silently bypassable in select mode.
		await expect(select.locator('option[value="0"]')).toHaveCount(0);
		await expect(select.locator('option', { hasText: 'Foo' })).toHaveCount(1);
		await expect(select.locator('option', { hasText: 'Bar' })).toHaveCount(1);
	});

	test('select mode + FS off → "None" option rendered', async ({ page }) => {
		setOption({ type: 'select', force_selection: 0 });

		await gotoNewClassicPost(page);

		const select = page.locator(SELECT_BOX);
		await expect(select).toBeVisible();
		await expect(select.locator('option[value="0"]')).toHaveCount(1);
	});
});

test.describe('Classic editor metabox — save round-trip', () => {
	test.afterEach(() => {
		resetOption();
	});

	test('selecting a radio term + Publish → reloads with that term checked', async ({ page }) => {
		await gotoNewClassicPost(page);

		await page.locator('#title').fill('Classic save round-trip');

		const list = page.locator(RADIO_LIST);
		await list.locator('label').filter({ hasText: 'Foo' }).click();

		// Publish lands at /wp-admin/post.php?post=<id>&action=edit&message=6.
		// Wait for the redirect before inspecting the persisted state.
		await Promise.all([
			page.waitForURL(/post\.php\?post=\d+&action=edit/),
			page.locator('#publish').click(),
		]);

		const url = new URL(page.url());
		const postId = Number(url.searchParams.get('post'));
		expect(postId).toBeGreaterThan(0);

		try {
			const checked = page.locator(`${RADIO_LIST} input[type="radio"]:checked`);
			await expect(checked).toHaveCount(1);
			// Walker emits value="<term_id>"; resolve that back to the slug so
			// the assertion is term-id-independent.
			const termId = await checked.getAttribute('value');
			const slug = wpCli(['term', 'get', CLASSIC_TAX, termId!, '--field=slug']).trim();
			expect(slug).toBe('foo');
		} finally {
			wpCli(['post', 'delete', String(postId), '--force']);
		}
	});
});
