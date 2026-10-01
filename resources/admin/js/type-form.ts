/**
 * A content type as a form (D-311): the options the admin edits for a
 * type in `user/data/types`, from a type's description and back into the
 * options to change (`PATCH types/{name}`, `POST types`), only the ones
 * that changed. The server leaves out what's at its default, so a field
 * that's blank or back at its default is simply sent.
 */

import type { ContentTypeDetail, FieldDescription } from './api';

export type TypeKind = 'collection' | 'taxonomy';

export interface TypeForm {
	singular: string;
	plural: string;
	description: string;
	icon: string;
	// The URL prefix, without slashes; `''` for the folder's.
	prefix: string;
	public: boolean;
	sitemap: boolean;
	feed: boolean;
	// A collection's: `none`, `year`, `month`, `day`, … (`DateArchives`).
	dateArchives: string;
	// A taxonomy's: whether a term may have a parent, and the types its
	// terms group (none for every type).
	hierarchical: boolean;
	types: string[];
	fields: FieldDescription[];
}

/**
 * The field a featured image is (D-281): a media field named `image`.
 */
export const FEATURED: FieldDescription = { name: 'image', type: 'media', label: 'Featured image', kind: 'image' };

/**
 * A new type's form.
 */
export function emptyForm(): TypeForm {
	return { singular: '', plural: '', description: '', icon: '', prefix: '', public: true, sitemap: true, feed: false, dateArchives: 'none', hierarchical: false, types: [], fields: [] };
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
		feed: type.feed,
		dateArchives: type.dateArchives,
		hierarchical: type.hierarchical === true,
		types: [...(type.types ?? [])],
		fields: copy(type.fields)
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
 * The options to change: every one for a new type (`initial` is
 * `null`), else the ones that differ from `initial`.
 */
export function changesOf(form: TypeForm, initial: TypeForm | null, kind: TypeKind): Record<string, unknown> {
	const all: Record<string, unknown> = {
		labels: { singular: form.singular.trim(), plural: form.plural.trim() },
		description: form.description.trim() || null,
		icon: form.icon.trim() || null,
		prefix: form.prefix.trim().replace(/^\/+|\/+$/g, '') || null,
		public: form.public,
		sitemap: form.sitemap,
		feed: form.feed,
		fields: form.fields,
		...(kind === 'collection' ? { dateArchives: form.dateArchives === 'none' ? null : form.dateArchives } : {}),
		...(kind === 'taxonomy' ? { hierarchical: form.hierarchical, types: form.types } : {})
	};

	if (initial === null) {
		return all;
	}

	const before = changesOf(initial, null, kind);

	return Object.fromEntries(Object.entries(all).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(before[key])));
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
export const DATE_ARCHIVES = [
	{ value: 'none', label: 'None' },
	{ value: 'year', label: 'By year' },
	{ value: 'month', label: 'By month' },
	{ value: 'day', label: 'By day' },
	{ value: 'hour', label: 'By hour' },
	{ value: 'minute', label: 'By minute' },
	{ value: 'second', label: 'By second' }
];
