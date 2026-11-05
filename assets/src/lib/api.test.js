import {afterEach, describe, expect, it, vi} from 'vitest';
import {request} from './api.js';

function response(json, ok = true) {
	return Promise.resolve({
		ok,
		json: () => Promise.resolve(json)
	});
}

describe('request', () => {
	afterEach(() => {
		vi.restoreAllMocks();
	});

	it('posts the action, nonce, and payload as form data', async () => {
		const fetch = vi.fn(() => response({success: true, data: {message: 'Done'}}));
		vi.stubGlobal('fetch', fetch);

		const result = await request('khse_action_test', {foo: 'bar', empty: null});

		expect(result).toEqual({message: 'Done'});

		const [url, options] = fetch.mock.calls[0];
		expect(url).toBe('https://example.test/wp-admin/admin-ajax.php');
		expect(options.method).toBe('POST');
		expect(options.credentials).toBe('same-origin');
		expect(options.body.get('action')).toBe('khse_action_test');
		expect(options.body.get('_wpnonce')).toBe('test-nonce');
		expect(options.body.get('foo')).toBe('bar');
		expect(options.body.get('empty')).toBe('');
	});

	it('rejects unsuccessful JSON envelopes with the server message', async () => {
		vi.stubGlobal('fetch', vi.fn(() => response({success: false, data: {message: 'Nope'}})));

		await expect(request('khse_action_test')).rejects.toThrow('Nope');
	});

	it('rejects non-OK HTTP responses', async () => {
		vi.stubGlobal('fetch', vi.fn(() => response({data: {message: 'Server error'}}, false)));

		await expect(request('khse_action_test')).rejects.toThrow('Server error');
	});

	it('falls back to the localized error when the response is invalid', async () => {
		vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ok: true, json: () => Promise.reject(new Error('bad')), })));

		await expect(request('khse_action_test')).rejects.toThrow('Request failed.');
	});
});
