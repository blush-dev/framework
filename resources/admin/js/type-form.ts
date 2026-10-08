/**
 * A content type as a form (D-311): the options the admin edits for a
 * type in `user/data/types`, from a type's description and back into the
 * options to change (`PATCH types/{name}`, `POST types`), only the ones
 * that changed. The server leaves out what's at its default, so a field
 * that's blank or back at its default is simply sent.
 */

import type { ContentTypeDetail, FieldDescription } from './api';

export type TypeKind = 'collection' | 'tree';

export interface TypeForm {
	singular: string;
	plural: string;
	description: string;
	icon: string;
	// The URL prefix, without slashes; `''` for the folder's.
	prefix: string;
	public: boolean;
	sitemap: boolean;
	// Whether entries are listed in `llms.txt` (D-398): on by default,
	// off for a new type of terms (D-401).
	llms: boolean;
	feed: boolean;
	// The new-type wizard's: whether the type joins the `authors` credit
	// relation (D-602), sent beside the options, not among them.
	authors: boolean;
	// The credit relation that's its byline, `''` for its only one
	// (D-602).
	byline: string;
	// A collection's: `none`, `year`, `month`, `day`, … (`DateArchives`).
	dateArchives: string;
	// Its file name pattern (D-511, any kind, D-514), `''` for the
	// default, the slug alone (D-515).
	filename: string;
	// A collection's: whether an entry may name a parent (D-593), and
	// whether entries are newest published first or by `position`.
	hierarchical: boolean;
	order: 'published' | 'position';
	fields: FieldDescription[];
	// Route keys' paths, relative to the prefix (D-350); `''` for a key's
	// default.
	paths: Record<string, string>;
}

/**
 * The field a featured image is (D-281): a media field named `image`.
 */
export const FEATURED: FieldDescription = { name: 'image', type: 'media', label: 'Featured image', kind: 'image' };

/**
 * A new type's form.
 */
export function emptyForm(): TypeForm {
	return { singular: '', plural: '', description: '', icon: '', prefix: '', public: true, sitemap: true, llms: true, feed: false, authors: true, byline: '', dateArchives: 'none', filename: '', hierarchical: false, order: 'published', fields: [], paths: {} };
}

/**
 * A type's form, from how the server describes it.
 */
export function formOf(type: ContentTypeDetail): TypeForm {
	const prefix = type.prefix === null ? '' : type.prefix.replace(/^\/+|\/+$/g, '');

	return {
		singular: type.labels.singular,
		plural: type.labels.plural,
		description: type.description,
		icon: type.icon ?? '',
		prefix: prefix === type.folderPrefix ? '' : prefix,
		public: type.public,
		sitemap: type.sitemap,
		llms: type.llms,
		feed: type.feed,
		authors: type.authors,
		byline: type.byline ?? '',
		dateArchives: type.dateArchives,
		filename: type.filename ?? '',
		hierarchical: type.hierarchical,
		order: type.order ?? 'published',
		fields: copy(type.fields),
		paths: Object.fromEntries(type.routes.map((route) => [route.key, route.path === route.default ? '' : route.path]))
	};
}

/**
 * A deep copy of plain data, reactive or not (`structuredClone()` refuses
 * Vue's proxies).
 */
export function copy<T>(value: T): T {
	return JSON.parse(JSON.stringify(value)) as T;
}

/**
 * Whether the form's fields include a featured image.
 */
export function hasFeatured(form: TypeForm): boolean {
	return form.fields.some((field) => field.name === FEATURED.name && field.type === FEATURED.type);
}

/**
 * A path as it's saved: no slashes around it, `''` for the default.
 */
export function pathOf(path: string): string {
	return path.trim().replace(/^\/+|\/+$/g, '');
}

/**
 * The options to change: every one for a new type (`initial` is
 * `null`), else the ones that differ from `initial`. Route paths are
 * sent only for the keys that changed, `null` for a key's default.
 */
export function changesOf(form: TypeForm, initial: TypeForm | null, kind: TypeKind): Record<string, unknown> {
	const all: Record<string, unknown> = {
		labels: { singular: form.singular.trim(), plural: form.plural.trim() },
		description: form.description.trim() || null,
		icon: form.icon.trim() || null,
		// A tree has no URLs or feed of its own (D-386).
		...(kind === 'tree' ? {} : { prefix: form.prefix.trim().replace(/^\/+|\/+$/g, '') || null }),
		public: form.public,
		sitemap: form.sitemap,
		llms: form.llms,
		...(kind === 'tree' ? {} : { feed: form.feed }),
		byline: form.byline || null,
		fields: form.fields,
		filename: form.filename || null,
		...(kind === 'collection' ? { dateArchives: form.dateArchives === 'none' ? null : form.dateArchives } : {}),
		...(kind === 'collection' ? { hierarchical: form.hierarchical, order: form.order === 'published' ? null : form.order } : {})
	};

	if (initial === null) {
		return all;
	}

	const before = changesOf(initial, null, kind);
	const paths  = Object.fromEntries(Object.entries(form.paths)
		.filter(([key, path]) => pathOf(path) !== pathOf(initial.paths[key] ?? ''))
		.map(([key, path]) => [key, pathOf(path) || null]));

	return {
		...Object.fromEntries(Object.entries(all).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(before[key]))),
		...(Object.keys(paths).length > 0 ? { paths } : {})
	};
}

/**
 * A field key made from a label ("Cook time" → `cook_time`), and a type
 * key made from a name ("Recipes" → `recipe`).
 */
export function keyOf(label: string): string {
	return label.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1');
}

export function typeKeyOf(name: string): string {
	const key = keyOf(singularOf(name));

	return /^[a-z]/.test(key) ? key : '';
}

/**
 * A plural name made singular, by the common English endings
 * ("Recipes" → "Recipe", "Categories" → "Category").
 */
export function singularOf(plural: string): string {
	const name = plural.trim();

	if (/ies$/i.test(name)) {
		return `${name.slice(0, -3)}y`;
	}

	if (/(ses|xes|zes|ches|shes)$/i.test(name)) {
		return name.slice(0, -2);
	}

	return /s$/i.test(name) && !/ss$/i.test(name) ? name.slice(0, -1) : name;
}

/**
 * A folder made from a name ("Field notes" → `field-notes`).
 */
export function folderOf(name: string): string {
	return name.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

/**
 * The date archive choices (`DateArchives`).
 */
/**
 * File name patterns (D-511, D-514), with an example of each.
 * A pattern from config that isn't one of these is offered as itself.
 */
export const FILENAMES = [
	{ value: '', label: 'Default', hint: 'hello-world.md' },
	{ value: '{slug}', label: 'Slug', hint: 'hello-world.md' },
	{ value: '{date}.{slug}', label: 'Date and slug', hint: '2026-10-05.hello-world.md' },
	{ value: '{date}-{time}.{slug}', label: 'Date, time, and slug', hint: '2026-10-05-093000.hello-world.md' }
];

/**
 * A collection's orders (D-593, `TypeOrder`).
 */
export const ORDERS = [
	{ value: 'published', label: 'Newest published first' },
	{ value: 'position', label: 'By position, then title' }
];

export const DATE_ARCHIVES = [
	{ value: 'none', label: 'None' },
	{ value: 'year', label: 'By year' },
	{ value: 'month', label: 'By month' },
	{ value: 'day', label: 'By day' },
	{ value: 'hour', label: 'By hour' },
	{ value: 'minute', label: 'By minute' },
	{ value: 'second', label: 'By second' }
];
