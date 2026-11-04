const boot = window.khseDashboard || {};
const STATUS_LABELS = boot.i18n?.statuses || {};

const CACHE_TYPE_TONES = ['blue', 'green', 'amber', 'violet', 'cyan', 'red'];

export function clone(value) {
	try {
		return JSON.parse(JSON.stringify(value ?? {}));
	} catch (error) {
		return {};
	}
}

export function percent(value) {
	const number = Number(value);

	return `${Math.max(0, Math.min(100, Number.isFinite(number) ? number : 0))}%`;
}

export function humanLabel(value) {
	const text = Array.isArray(value) ? value.join('') : String(value ?? '');
	const label = STATUS_LABELS[text] || text.replaceAll('_', ' ');

	return label ? label.charAt(0).toUpperCase() + label.slice(1) : '';
}

export function ttlLabel(seconds) {
	const value = Math.max(0, Number(seconds) || 0);

	if (value === 0) {
		return 'No expiry';
	}

	if (value < 3600) {
		return `${Math.round(value / 60)} min`;
	}

	if (value < 86400) {
		const hours = Math.round(value / 3600);

		return `${hours} hour${hours === 1 ? '' : 's'}`;
	}

	if (value < 604800) {
		const days = Math.round(value / 86400);

		return `${days} day${days === 1 ? '' : 's'}`;
	}

	const weeks = Math.round(value / 604800);

	return `${weeks} week${weeks === 1 ? '' : 's'}`;
}

export function lineCount(value) {
	return String(value ?? '')
		.split(/\r?\n/)
		.filter((line) => line.trim() !== '').length;
}

export function formatNumber(value) {
	const number = Number(value);

	return Number.isFinite(number) ? new Intl.NumberFormat().format(number) : '0';
}

export function cacheTypeTone(type) {
	const text = String(type ?? '');
	let hash = 0;

	for (let index = 0; index < text.length; index++) {
		hash = (hash * 31 + text.charCodeAt(index)) % 997;
	}

	return CACHE_TYPE_TONES[hash % CACHE_TYPE_TONES.length];
}
