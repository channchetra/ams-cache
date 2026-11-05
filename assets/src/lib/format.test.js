import {describe, expect, it} from 'vitest';
import {cacheTypeTone, clone, formatNumber, humanLabel, lineCount, percent, ttlLabel} from './format.js';

describe('format helpers', () => {
	it('clamps percentages to the 0–100 range', () => {
		expect(percent(42)).toBe('42%');
		expect(percent(150)).toBe('100%');
		expect(percent(-5)).toBe('0%');
		expect(percent('invalid')).toBe('0%');
	});

	it('formats TTL values in human units', () => {
		expect(ttlLabel(0)).toBe('No expiry');
		expect(ttlLabel(60)).toBe('1 min');
		expect(ttlLabel(3600)).toBe('1 hour');
		expect(ttlLabel(7200)).toBe('2 hours');
		expect(ttlLabel(86400)).toBe('1 day');
		expect(ttlLabel(604800)).toBe('1 week');
	});

	it('counts non-empty lines in textarea values', () => {
		expect(lineCount('one\n\ntwo\r\n  \nthree')).toBe(3);
		expect(lineCount('')).toBe(0);
		expect(lineCount(null)).toBe(0);
	});

	it('humanizes identifiers and empty values', () => {
		expect(humanLabel('no_change')).toBe('No change');
		expect(humanLabel('')).toBe('');
	});

	it('clones values without sharing references', () => {
		const source = {nested: {value: 1}};
		const copy = clone(source);

		copy.nested.value = 2;

		expect(source.nested.value).toBe(1);
		expect(clone(null)).toEqual({});
	});

	it('returns a stable tone for a cache type', () => {
		const tones = ['blue', 'green', 'amber', 'violet', 'cyan', 'red'];

		expect(tones).toContain(cacheTypeTone('homepage'));
		expect(cacheTypeTone('homepage')).toBe(cacheTypeTone('homepage'));
	});

	it('formats finite numbers and falls back to zero', () => {
		expect(formatNumber('not-a-number')).toBe('0');
		expect(formatNumber(1000)).not.toBe('0');
	});
});
