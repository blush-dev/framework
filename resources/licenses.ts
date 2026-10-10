/**
 * Writes `licenses.txt` beside a build (D-694): the license of every
 * package the build bundles from `node_modules`, found from its modules,
 * so the list never falls behind, then the licenses of what's copied in
 * by hand (Lucide's icons, fonts), which the build is given.
 */

import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join, sep } from 'node:path';
import type { Plugin } from 'vite';

export interface CopiedWork {
	// What it is and where it's used: "Lucide icons, in the admin's icons".
	name: string;
	// The license file to copy in.
	file: string;
}

interface Package {
	name: string;
	version: string;
	license: string;
	text: string;
}

/**
 * The package a module is from: its folder under the last `node_modules`
 * in its path, scoped or not.
 */
function packageRoot(id: string): string | null {
	const path   = id.split('?')[0] ?? id;
	const marker = `${sep}node_modules${sep}`;
	const index  = path.lastIndexOf(marker);

	if (index === -1) {
		return null;
	}

	const parts = path.slice(index + marker.length).split(sep);
	const depth = parts[0]?.startsWith('@') ? 2 : 1;

	return join(path.slice(0, index + marker.length), ...parts.slice(0, depth));
}

/**
 * A package's name, version, license, and license text.
 */
function readPackage(root: string): Package {
	const manifest = JSON.parse(readFileSync(join(root, 'package.json'), 'utf8')) as { name?: string; version?: string; license?: string };
	const file     = readdirSync(root).find((name) => /^(licen[cs]e|copying)(\.(md|txt))?$/i.test(name));

	if (file === undefined) {
		throw new Error(`${manifest.name ?? root} has no license file to copy into licenses.txt (D-694).`);
	}

	return {
		name: manifest.name ?? root,
		version: manifest.version ?? '',
		license: manifest.license ?? 'see below',
		text: readFileSync(join(root, file), 'utf8').trim()
	};
}

export function licenses(copied: CopiedWork[] = []): Plugin {
	return {
		name: 'blush-licenses',
		apply: 'build',

		generateBundle(options, bundle) {
			const roots = new Set<string>();

			for (const file of Object.values(bundle)) {
				for (const id of file.type === 'chunk' ? file.moduleIds : []) {
					const root = packageRoot(id);

					if (root !== null && existsSync(join(root, 'package.json'))) {
						roots.add(root);
					}
				}
			}

			const packages = [...roots].map(readPackage).sort((a, b) => a.name.localeCompare(b.name));
			const rule     = '-'.repeat(72);
			const sections = [
				...packages.map((item) => `${item.name} ${item.version} (${item.license})\n\n${item.text}`),
				...copied.map((item) => `${item.name}\n\n${readFileSync(item.file, 'utf8').trim()}`)
			];

			this.emitFile({
				type: 'asset',
				fileName: 'licenses.txt',
				source: `This build includes the following third-party work, under these licenses.\n\n${sections.map((section) => `${rule}\n${section}\n`).join('\n')}`
			});
		}
	};
}
