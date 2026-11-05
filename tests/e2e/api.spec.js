import { test, expect } from '@playwright/test';

test.describe('WordPress REST and application password', () => {
	test.skip(!process.env.WP_APP_PASSWORD, 'Set WP_APP_PASSWORD to run authenticated REST checks.');

	test('application password authenticates the current user', async ({ request }) => {
		const response = await request.get('/wp-json/wp/v2/users/me');

		expect(response.status()).toBe(200);

		const user = await response.json();

		expect(user.id).toBeGreaterThan(0);
		expect((user.slug || user.name || '').length).toBeGreaterThan(0);
	});

	test('REST index is reachable', async ({ request }) => {
		const response = await request.get('/wp-json/');

		expect(response.status()).toBe(200);

		const index = await response.json();

		expect(index.namespace).toBe('wp/v2');
	});
});
