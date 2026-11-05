import { defineConfig } from 'vitest/config';

export default defineConfig({
	test: {
		environment: 'node',
		setupFiles: ['./assets/src/test/setup.js'],
		include: ['assets/src/**/*.test.js']
	}
});
