import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
	base: '',
	define: {
		'process.env.NODE_ENV': JSON.stringify('production')
	},
	plugins: [
		react(),
		tailwindcss()
	],
	build: {
		outDir: 'inc/assets/build',
		emptyOutDir: true,
		manifest: true,
		cssCodeSplit: false,
		lib: {
			entry: resolve(__dirname, 'assets/src/admin.jsx'),
			name: 'AmsCacheAdmin',
			formats: ['iife'],
			fileName: () => 'admin.js'
		},
		rollupOptions: {
			output: {
				assetFileNames: 'admin.[ext]'
			}
		}
	}
});
