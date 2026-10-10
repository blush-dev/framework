/**
 * Brand marks (D-695): Simple Icons' SVGs in `resources/admin/brands`
 * (CC0, as in its `LICENSE.md`), by the embed provider's `name`, as the
 * inner markup of a 24×24 mark filled with `currentColor`. Each file's
 * `<title>` is dropped: the name beside a mark says what it is.
 */

const files = import.meta.glob<string>('../brands/*.svg', { query: '?raw', import: 'default', eager: true });

const marks: Record<string, string> = {};

for (const [path, svg] of Object.entries(files)) {
	const name  = path.slice(path.lastIndexOf('/') + 1, -'.svg'.length);
	const inner = /<svg[^>]*>([\s\S]*)<\/svg>/.exec(svg)?.[1] ?? '';

	marks[name] = inner.replace(/<title>[\s\S]*?<\/title>/, '');
}

/**
 * A provider's mark, or `null` for one without.
 */
export function brandMark(name: string): string | null {
	return marks[name] ?? null;
}
