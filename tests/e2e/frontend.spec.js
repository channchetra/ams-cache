import { test, expect } from '@playwright/test';

test.describe('front-end delivery', () => {
	test('homepage renders without JavaScript errors', async ({ page }) => {
		const errors = [];

		page.on('console', (message) => {
			if (message.type() === 'error') {
				errors.push(message.text());
			}
		});

		const response = await page.goto('/', { waitUntil: 'domcontentloaded' });

		expect(response.status()).toBe(200);
		expect((await page.title()).length).toBeGreaterThan(0);

		const relevant = errors.filter((message) => !/favicon|ERR_BLOCKED|CORS policy|ERR_FAILED|Access to script at 'http:\/\/local-dev\.local/i.test(message));
		expect(relevant).toEqual([]);
	});

	test('login page renders the login form', async ({ page }) => {
		const response = await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });

		expect(response.status()).toBe(200);
		await expect(page.locator('#loginform')).toBeVisible();
		await expect(page.locator('#user_login')).toBeVisible();
	});

	test('unknown URLs return a 404 document', async ({ page }) => {
		const response = await page.goto(`/khse-e2e-missing-${Date.now()}/`, { waitUntil: 'domcontentloaded' });

		expect(response.status()).toBe(404);
		await expect(page.locator('body')).toBeVisible();
	});
});
