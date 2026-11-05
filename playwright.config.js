import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'https://local-dev.local';
const appUser = process.env.WP_APP_USER || 'Agent';
const appPassword = process.env.WP_APP_PASSWORD || '';
const basicAuth = appPassword
	? 'Basic ' + Buffer.from(`${appUser}:${appPassword}`).toString('base64')
	: undefined;

export default defineConfig({
	testDir: './tests/e2e',
	timeout: 30_000,
	expect: { timeout: 5_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [['list']],
	use: {
		baseURL,
		ignoreHTTPSErrors: true,
		channel: 'chrome',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		extraHTTPHeaders: basicAuth ? { Authorization: basicAuth } : {},
	},
});
