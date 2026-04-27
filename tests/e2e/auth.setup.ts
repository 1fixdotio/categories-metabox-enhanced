import { test as setup, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'password';
const AUTH_DIR = path.join(__dirname, '.auth');
const STATE_FILE = path.join(AUTH_DIR, 'admin.json');
const CREDS_FILE = path.join(AUTH_DIR, 'app-password.json');

setup('authenticate as admin and provision REST credentials', async ({ page }) => {
	mkdirSync(AUTH_DIR, { recursive: true });

	await page.goto('/wp-login.php');
	await page.locator('#user_login').fill(ADMIN_USER);
	await page.locator('#user_pass').fill(ADMIN_PASS);
	await page.locator('#wp-submit').click();
	await page.waitForURL(/wp-admin/);
	await expect(page.locator('#wpadminbar')).toBeVisible();

	await page.context().storageState({ path: STATE_FILE });

	// Provision an application password for REST specs. wp-cli prints the
	// unhashed password on --porcelain; wp-env wraps the command with spinner
	// messages on stderr so stdout stays clean. We re-create on every run to
	// avoid coupling to passwords left over from prior sessions.
	const stdout = execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'tests-cli',
			'wp',
			'user',
			'application-password',
			'create',
			ADMIN_USER,
			'playwright',
			'--porcelain',
		],
		{ encoding: 'utf8' }
	);
	const appPassword = stdout.trim().split('\n').pop()!.trim();
	expect(appPassword, 'wp-cli must return an unhashed app password').toMatch(/^[A-Za-z0-9 ]{20,}$/);

	const basicAuth = 'Basic ' + Buffer.from(`${ADMIN_USER}:${appPassword}`).toString('base64');
	writeFileSync(CREDS_FILE, JSON.stringify({ user: ADMIN_USER, appPassword, basicAuth }, null, 2));
});
