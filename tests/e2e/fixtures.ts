import { test as base, request, type APIRequestContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const CREDS_FILE = path.join(__dirname, '.auth', 'app-password.json');

type Credentials = { user: string; appPassword: string; basicAuth: string };

function loadCredentials(): Credentials {
	return JSON.parse(readFileSync(CREDS_FILE, 'utf8')) as Credentials;
}

/**
 * Run a wp-cli command inside the wp-env tests container. Used by specs
 * that need to drive paths the REST API doesn't expose directly — most
 * notably wp_set_object_terms with a slug array (regression for the
 * `(int) 'slug' === 0` bug).
 */
export function wpCli(args: string[]): string {
	return execFileSync('npx', ['wp-env', 'run', 'tests-cli', 'wp', ...args], { encoding: 'utf8' });
}

type Fixtures = {
	api: APIRequestContext;
	createdPostIds: number[];
};

export const test = base.extend<Fixtures>({
	api: async ({ baseURL }, use) => {
		// `storageState: undefined` is load-bearing: the chromium project
		// configures storageState for browser auth, and request.newContext()
		// inherits it inside fixtures. WP then sees both admin cookies AND our
		// Basic Auth header, prefers cookie auth, and rejects state-changing
		// requests for missing X-WP-Nonce. Wiping storage state isolates this
		// context to the application-password path.
		const { basicAuth } = loadCredentials();
		const ctx = await request.newContext({
			baseURL,
			storageState: undefined,
			extraHTTPHeaders: { Authorization: basicAuth },
		});
		await use(ctx);
		await ctx.dispose();
	},

	// Specs push every post id they create here so afterEach can force-delete
	// them. Keeps the suite reentrant on the long-lived tests env.
	createdPostIds: async ({ api }, use) => {
		const ids: number[] = [];
		await use(ids);
		for (const id of ids) {
			await api.delete(`/wp-json/wp/v2/posts/${id}?force=true`);
		}
	},
});

export const expect = test.expect;
