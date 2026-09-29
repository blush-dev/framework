/**
 * Builds the admin app (D-221) from `resources/admin` into `public/admin`,
 * which is committed so sites never need Node. Like theme builds (D-194),
 * file names carry no hashes: `js/admin.ts` builds to `js/admin.js` and
 * its styles to `css/admin.css`, and anything else in `resources/admin`
 * (fonts, images) is copied as it is. Blush reads the manifest and
 * versions each URL with `?v={hash}` (`AdminApp`); this build does the
 * same for the files the CSS points to.
 *
 *     npm run admin:build   # type-check, then build
 *     npm run admin:watch   # rebuild on change
 */

import { cpSync, existsSync, readdirSync } from 'node:fs';
import { posix, relative, resolve } from 'node:path';
import { crc32 } from 'node:zlib';
import { defineConfig, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';

const resources = import.meta.dirname;
const outDir = resolve(resources, '../../public/admin');

// Folders Vite builds from, and files that aren't assets; everything
// else in `resources/admin` is copied.
const sources = ['js', 'css', 'tsconfig.json', 'tsconfig.app.json', 'tsconfig.node.json', 'vite.config.ts'];

// The CRC32 Blush versions URLs with (`hash_file('crc32b')`).
const version = (source: string | Uint8Array): string => crc32(source).toString(16).padStart(8, '0');

const resourceFiles = (): Plugin => ({
	name: 'blush-admin-resources',

	// Adds `?v={hash}` to the built files CSS points to.
	generateBundle(options, bundle) {
		for (const file of Object.values(bundle)) {
			if (file.type !== 'asset' || !file.fileName.endsWith('.css')) {
				continue;
			}

			file.source = String(file.source).replace(/url\(\s*(['"]?)([^'")?#]+)\1\s*\)/g, (match, quote: string, url: string) => {
				const target = bundle[posix.join(posix.dirname(file.fileName), url)];
				const source = target === undefined ? null : (target.type === 'asset' ? target.source : target.code);

				return source === null ? match : `url(${quote}${url}?v=${version(source)}${quote})`;
			});
		}
	},

	writeBundle() {
		for (const name of readdirSync(resources)) {
			if (!sources.includes(name) && existsSync(resolve(resources, name))) {
				cpSync(resolve(resources, name), resolve(outDir, name), { recursive: true });
			}
		}
	}
});

export default defineConfig({
	root: resources,
	base: './',
	publicDir: false,
	plugins: [vue(), resourceFiles()],
	build: {
		outDir,
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: resolve(resources, 'js/admin.ts'),
			output: {
				entryFileNames: 'js/[name].js',
				chunkFileNames: 'js/[name].js',
				assetFileNames: ({ names, originalFileNames }) => {
					if (names[0]?.endsWith('.css')) {
						return 'css/[name][extname]';
					}

					// Fonts and images keep their place in `resources/admin`.
					const original = originalFileNames[0];

					return original ? relative(resources, resolve(resources, original)) : 'assets/[name][extname]';
				}
			}
		}
	}
});
