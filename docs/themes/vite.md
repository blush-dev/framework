# Building assets with Vite

A theme that uses Sass or bundles JavaScript can build its assets with
[Vite](https://vite.dev/). Keep sources in the theme's `resources/`
folder (Blush never serves it) and build into its `public/` folder.

## The theme's folders

```
extensions/acme/notebook/
  theme.json      "styles": ["resources/scss/style.scss"], "scripts": ["resources/js/app.js"]
  package.json    vite, plus sass-embedded for Sass
  vite.config.js
  resources/
    scss/  js/            sources Vite builds
    fonts/  img/  svg/    copied to public/ as they are
  public/                 the build: css/, js/, the copies, and .vite/manifest.json
```

## Linking the build

List your source files in `theme.json`. Blush reads Vite's manifest and
links the built files, and `$theme->asset('resources/fonts/body.woff2')`
finds a font your CSS uses the same way. Copied files are reached by
their path: `$theme->asset('public/img/icon.png')`.

## Versioned URLs

Every theme asset URL ends in `?v=` and a hash of the file's contents,
so browsers can cache files for as long as they like and still get a
changed file right away. Built files keep their plain names
(`public/css/style.css`), and the config below versions the fonts and
images your CSS points to the same way.

## `vite.config.js`

The build lives in the theme, so it travels with the theme's repository.
Blush never serves `package.json`, `node_modules/`, or `*.config.js`
files. Its `vite.config.js`:

```js
import { cpSync, readdirSync } from 'node:fs';
import { posix, relative, resolve } from 'node:path';
import { crc32 } from 'node:zlib';
import { defineConfig } from 'vite';

const theme = import.meta.dirname;
const resources = resolve(theme, 'resources');
const outDir = resolve(theme, 'public');

// Folders Vite builds from; everything else in resources/ is copied.
const sources = ['scss', 'js'];

// The same hash Blush adds to URLs.
const version = (source) => crc32(source).toString(16).padStart(8, '0');

const resourceFiles = () => ({
	name: 'resource-files',

	// Adds ?v= to the fonts and images the CSS points to.
	generateBundle(options, bundle) {
		for (const file of Object.values(bundle)) {
			if (file.type !== 'asset' || !file.fileName.endsWith('.css')) {
				continue;
			}

			file.source = String(file.source).replace(/url\(\s*(['"]?)([^'")?#]+)\1\s*\)/g, (match, quote, url) => {
				const target = bundle[posix.join(posix.dirname(file.fileName), url)];

				return target ? `url(${quote}${url}?v=${version(target.source ?? target.code)}${quote})` : match;
			});
		}
	},

	writeBundle() {
		for (const name of readdirSync(resources)) {
			if (!sources.includes(name)) {
				cpSync(resolve(resources, name), resolve(outDir, name), { recursive: true });
			}
		}
	}
});

export default defineConfig({
	root: theme,
	base: './',
	publicDir: false,
	plugins: [resourceFiles()],
	build: {
		outDir,
		emptyOutDir: true,
		manifest: true,
		rolldownOptions: {
			input: ['resources/scss/style.scss', 'resources/js/app.js'].map((file) => resolve(theme, file)),
			output: {
				entryFileNames: 'js/[name].js',
				chunkFileNames: 'js/[name].js',
				assetFileNames: ({ names, originalFileNames }) => {
					if (names[0]?.endsWith('.css')) {
						return 'css/[name][extname]';
					}

					const original = originalFileNames[0];

					return original ? relative(resources, resolve(theme, original)) : 'assets/[name][extname]';
				}
			}
		}
	}
});
```

## Building

It needs Node 22.2 or later. Run `npx vite build` in the theme's folder
(or `vite build --watch` while you work) and commit the built `public/`
folder, so your server never needs Node. A build that hashes file names
(Vite's default) works too; the URLs just carry both hashes.

