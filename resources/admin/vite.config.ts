/**
 * Builds the admin app (D-221) from `resources/admin` into `public/admin`,
 * which is committed so sites never need Node. Like theme builds (D-194),
 * file names carry no hashes: `js/admin.ts` builds to `js/admin.js` and
 * its styles to `css/admin.css`, and anything else in `resources/admin`
 * (fonts, images) is copied as it is. Blush reads the manifest and
 * versions each URL with `?v={hash}` (`AdminApp`); this build does the
 * same for the files the CSS points to, and for the screens loaded on
 * demand (D-687): their files and the files they import, `admin.js`
 * among them, with the version Blush gives it, or the browser would run
 * the admin twice. `licenses.txt` beside them names the licenses of the
 * third-party work in the build (D-694).
 *
 *     npm run admin:build   # type-check, then build
 *     npm run admin:watch   # rebuild on change
 */

import { cpSync, existsSync, readdirSync } from 'node:fs';
import { posix, relative, resolve } from 'node:path';
import { crc32 } from 'node:zlib';
import { defineConfig, type Plugin } from 'vite';
import vue from '@vitejs/plugin-vue';
import { licenses } from '../licenses.ts';

const resources = import.meta.dirname;
const outDir = resolve(resources, '../../public/admin');

// Folders Vite builds from, and files that aren't assets; everything
// else in `resources/admin` is copied.
const sources = ['js', 'css', 'tsconfig.json', 'tsconfig.app.json', 'tsconfig.node.json', 'vite.config.ts'];

// The CRC32 Blush versions URLs with (`hash_file('crc32b')`).
const version = (source: string | Uint8Array): string => crc32(source).toString(16).padStart(8, '0');

type Bundle = Parameters<Extract<NonNullable<Plugin['generateBundle']>, { handler: unknown }>['handler']>[1];

/**
 * Versions the paths between script files: the entry's, which Blush
 * versions with a hash of its contents (`AdminApp`), and every other
 * script and stylesheet a script loads, with one hash of the whole
 * build. The two can't each hash the other, since the entry names the
 * screens and they import it; the build's hash covers everything both
 * are made from, so it changes whenever either does.
 */
function versionChunks(bundle: Bundle): void {
	const chunks = Object.values(bundle).filter((file) => file.type === 'chunk');
	const entry = chunks.find((chunk) => chunk.isEntry);

	if (entry === undefined || chunks.length === 1) {
		return;
	}

	// Vite's preloader tells a stylesheet by its path ending in `.css`,
	// which a version hides, so it's told to look before the `?`.
	const cssCheck = /(\b[\w$]+)\.endsWith\(([`"'])\.css\2\)/g;
	const helper = chunks.find((chunk) => chunk.code.includes('__vite__mapDeps') && chunk.code.search(cssCheck) !== -1);

	if (helper === undefined && chunks.some((chunk) => chunk.code.includes('__vite__mapDeps'))) {
		throw new Error('Vite\'s preloader has changed: no stylesheet check to version (D-687).');
	}

	if (helper !== undefined) {
		helper.code = helper.code.replace(cssCheck, (match, name: string, quote: string) => `${name}.split(${quote}?${quote})[0].endsWith(${quote}.css${quote})`);
	}

	const paths = /(["'`])(\.\.?\/[^"'`\s]+\.(?:js|css))\1/g;
	const build = version(Object.values(bundle)
		.filter((file) => file.fileName.endsWith('.js') || file.fileName.endsWith('.css'))
		.sort((a, b) => a.fileName.localeCompare(b.fileName))
		.map((file) => (file.type === 'chunk' ? file.code : String(file.source)))
		.join('\n'));

	const versioned = (chunk: typeof entry, entryVersion: string): string => chunk.code.replace(paths, (match, quote: string, path: string) => {
		const target = bundle[posix.join(posix.dirname(chunk.fileName), path)];

		if (target === undefined) {
			return match;
		}

		return `${quote}${path}?v=${target === entry ? entryVersion : build}${quote}`;
	});

	entry.code = versioned(entry, '');

	const entryVersion = version(entry.code);

	for (const chunk of chunks) {
		if (chunk !== entry) {
			chunk.code = versioned(chunk, entryVersion);
		}
	}
}

const resourceFiles = (): Plugin => ({
	name: 'blush-admin-resources',

	// Adds `?v={hash}` to the built files CSS points to, then to the
	// paths between script files (D-687).
	generateBundle: {
		order: 'post',
		handler(options, bundle) {
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

			versionChunks(bundle);
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
	// Elements named `blush-…` are custom elements, not Vue components:
	// the audio player (`resources/player`, D-553).
	plugins: [
		vue({ template: { compilerOptions: { isCustomElement: (tag) => tag.startsWith('blush-') } } }),
		resourceFiles(),
		licenses([
			{ name: 'Lucide icons, in the admin\'s and the players\' icons', file: resolve(resources, '../icons/blush/LICENSE') },
			{ name: 'Fira Code, Karla, and Newsreader fonts, in fonts/', file: resolve(resources, 'fonts/LICENSE') }
		])
	],
	build: {
		outDir,
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: resolve(resources, 'js/admin.ts'),
			output: {
				// Everything the first screen needs stays in `admin.js`;
				// otherwise Rolldown moves what the screens loaded on demand
				// share with it (Vue, the router, the API) to a file of its
				// own that `admin.js` waits on (D-687).
				codeSplitting: { groups: [{ name: 'admin', tags: ['$initial'] }] },
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
