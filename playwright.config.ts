import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8889';

export default defineConfig({
	testDir: './tests/e2e',
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	timeout: 30_000,
	expect: { timeout: 10_000 },
	reporter: process.env.CI
		? [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]]
		: [['list']],
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /auth\.setup\.ts/,
		},
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				storageState: 'tests/e2e/.auth/admin.json',
			},
			dependencies: ['setup'],
			testIgnore: /auth\.setup\.ts/,
		},
	],
});
