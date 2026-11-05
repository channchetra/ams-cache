import { test, expect } from '@playwright/test';

const user = process.env.WP_ADMIN_USER || '';
const password = process.env.WP_ADMIN_PASS || '';

test.describe('admin console', () => {
	test.skip(!user || !password, 'Set WP_ADMIN_USER and WP_ADMIN_PASS to run browser login checks.');

	test.beforeEach(async ({ page }) => {
		await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
		await page.fill('#user_login', user);
		await page.fill('#user_pass', password);
		await Promise.all([
			page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
			page.click('#wp-submit'),
		]);
	});

	test('KH Speed dashboard loads the React console', async ({ page }) => {
		const errors = [];

		page.on('pageerror', (error) => errors.push(error.message));

		const response = await page.goto('/wp-admin/admin.php?page=kh-speed-engine-dashboard', {
			waitUntil: 'domcontentloaded',
		});

		expect(response.status()).toBe(200);
		await expect(page.locator('#khse-admin-root')).toBeVisible();
		await expect(page.locator('body')).toContainText('KH Speed');
		expect(errors).toEqual([]);
	});

	test('workspace navigation renders each panel', async ({ page }) => {
		await page.goto('/wp-admin/admin.php?page=kh-speed-engine-dashboard', {
			waitUntil: 'domcontentloaded',
		});

		const root = page.locator('#khse-admin-root');

		await expect(root).toBeVisible();

		for (const label of ['Dashboard', 'Cache', 'Performance', 'Preload', 'Settings']) {
			const item = page.getByRole('button', { name: label, exact: false }).first();

			if (await item.count()) {
				await item.click();
				await expect(root).toBeVisible();
			}
		}
	});
});
