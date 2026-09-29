/**
 * Builds the admin app (D-221) into `public/admin`, which is committed so
 * sites never need Node. `ShellController` reads the manifest to find the
 * entry, and `AssetController` serves `assets/`. The base is relative, so
 * the app works wherever `AdminConfig::$path` puts it.
 */

import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
	root,
	base: './',
	publicDir: false,
	plugins: [vue()],
	build: {
		outDir: fileURLToPath(new URL('../../public/admin', import.meta.url)),
		emptyOutDir: true,
		manifest: true,
		assetsDir: 'assets',
		rollupOptions: {
			input: fileURLToPath(new URL('src/main.ts', import.meta.url))
		}
	}
});
