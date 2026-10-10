/**
 * Builds core's site assets (D-573) from `resources/site` into
 * `public/site`, which is committed so sites never need Node, as the
 * admin's build is (D-221). Core serves them at `/blush/{path}`
 * (`Asset\AssetRoutes`) and registers them as assets by handle
 * (`Asset\AssetRegistrar`). File names carry no hashes: `js/player.ts`
 * builds to `js/player.js` and its styles to `css/player.css`; Blush
 * versions each URL with `?v={crc32}` (D-194). `licenses.txt` beside
 * them names the licenses of the third-party work in the build (D-694).
 *
 *     npm run site:build   # type-check, then build
 */

import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import { licenses } from '../licenses.ts';

const resources = import.meta.dirname;

export default defineConfig({
	root: resources,
	base: './',
	publicDir: false,
	plugins: [
		licenses([
			{ name: 'Lucide icons, in the players\' icons', file: resolve(resources, '../icons/blush/LICENSE') }
		])
	],
	build: {
		outDir: resolve(resources, '../../public/site'),
		emptyOutDir: true,
		rolldownOptions: {
			input: {
				player: resolve(resources, 'js/player.ts')
			},
			output: {
				entryFileNames: 'js/[name].js',
				chunkFileNames: 'js/[name].js',
				assetFileNames: 'css/[name][extname]'
			}
		}
	}
});
