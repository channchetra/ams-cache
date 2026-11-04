const boot = window.khseDashboard || {};

export function request(action, payload = {}) {
	const body = new FormData();

	body.append('action', action);
	body.append('_wpnonce', boot.nonce || '');

	Object.entries(payload).forEach(([key, value]) => body.append(key, value ?? ''));

	return fetch(boot.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body
	})
		.then((response) => response.json().catch(() => null).then((json) => {
			if (!response.ok) {
				throw new Error(json?.data?.message || boot.i18n?.failed || 'Request failed.');
			}

			return json;
		}))
		.then((json) => {
			if (!json || !json.success) {
				throw new Error(json?.data?.message || boot.i18n?.failed || 'Request failed.');
			}

			return json.data || {};
		});
}
