import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const ADMIN_FONTS = [
	{ token: '__KHSE_FONT_LATIN__', file: 'google-sans-latin.woff2' },
	{ token: '__KHSE_FONT_LATIN_EXT__', file: 'google-sans-latin-ext.woff2' }
];

/**
 * Library mode always inlines CSS assets as data URLs. Emit the self-hosted
 * Google Sans files next to admin.css and keep the CSS references relative.
 */
function khseAdminFonts() {
	return {
		name: 'khse-admin-fonts',
		apply: 'build',
		enforce: 'pre',
		transform( code, id ) {
			if ( ! id.endsWith( '.css' ) ) {
				return null;
			}

			let next = code;

			ADMIN_FONTS.forEach( ( { token, file } ) => {
				next = next.replaceAll( `url('./fonts/${ file }')`, `url(#${ token })` );
			} );

			return next === code ? null : { code: next, map: null };
		},
		generateBundle() {
			ADMIN_FONTS.forEach( ( { file } ) => {
				this.emitFile( {
					type: 'asset',
					fileName: `fonts/${ file }`,
					source: readFileSync( resolve( __dirname, 'assets/src/fonts', file ) )
				} );
			} );

			this.emitFile( {
				type: 'asset',
				fileName: 'fonts/OFL.txt',
				source: readFileSync( resolve( __dirname, 'assets/src/fonts/OFL.txt' ) )
			} );
		},
		writeBundle() {
			const cssPath = resolve( __dirname, 'inc/assets/build/admin.css' );
			let source = readFileSync( cssPath, 'utf8' );

			ADMIN_FONTS.forEach( ( { token, file } ) => {
				source = source.replaceAll( `#${ token }`, `./fonts/${ file }` );
			} );

			writeFileSync( cssPath, source );
		}
	};
}

export default defineConfig({
	base: '',
	define: {
		'process.env.NODE_ENV': JSON.stringify('production')
	},
	plugins: [
		khseAdminFonts(),
		react()
	],
	build: {
		outDir: 'inc/assets/build',
		emptyOutDir: true,
		manifest: true,
		cssCodeSplit: false,
		lib: {
			entry: resolve(__dirname, 'assets/src/admin.jsx'),
			name: 'KhseAdmin',
			formats: ['iife'],
			fileName: () => 'admin.js'
		},
		rollupOptions: {
			output: {
				assetFileNames: ( assetInfo ) => {
					const name = assetInfo.name || '';
					return name.endsWith( '.css' ) ? 'admin.css' : 'assets/[name]-[hash][extname]';
				}
			}
		}
	}
});
