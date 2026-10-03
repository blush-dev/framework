/**
 * A content type as a form (D-311): the options the admin edits for a
 * type in `user/data/types`, from a type's description and back into the
 * options to change (`PATCH types/{name}`, `POST types`), only the ones
 * that changed. The server leaves out what's at its default, so a field
 * that's blank or back at its default is simply sent.
 */

import type { ContentTypeDetail, FieldDescription, PeopleFieldInfo } from './api';

export type TypeKind = 'collection' | 'taxonomy' | 'tree';

export interface TypeForm {
	singular: string;
	plural: string;
	description: string;
	icon: string;
	// The URL prefix, without slashes; `''` for the folder's.
	prefix: string;
	public: boolean;
	sitemap: boolean;
	// Whether entries are listed in `llms.txt` (D-398): on by default for
	// collections and trees, off for taxonomies (D-401).
	llms: boolean;
	feed: boolean;
	// Whether entries credit authors, and whether those authors have
	// archives under the type, at a word (`''` for `authors`; D-329). The
	// new-type wizard's shortcut for the `authors` people field.
	authors: boolean;
	authorArchives: boolean;
	authorsWord: string;
	// Every people field, as the type editor edits them (D-353); `null`
	// in the wizard, which uses the shortcut above.
	people: PeopleForm[] | null;
	// A collection's: `none`, `year`, `month`, `day`, … (`DateArchives`).
	dateArchives: string;
	// A taxonomy's: whether a term may have a parent, and the types its
	// terms group (none for every type).
	hierarchical: boolean;
	types: string[];
	fields: FieldDescription[];
	// Route keys' paths, relative to the prefix (D-350); `''` for a key's
	// default.
	paths: Record<string, string>;
}

/**
 * One people field as a form (D-353).
 */
export interface PeopleForm {
	// The front matter key, fixed once saved.
	field: string;
	plural: string;
	singular: string;
	aliases: string[];
	// Whether it has archives, at a word (`''` for the field's name).
	archives: boolean;
	word: string;
	multiple: boolean;
	required: boolean;
	// Whether it's been added here and not saved yet.
	added: boolean;
}

/**
 * The word a people field's archives sit under, `false` for none.
 */
export function peopleWordOf(field: PeopleForm): string | false {
	return field.archives ? (field.word.trim().replace(/^\/+|\/+$/g, '') || field.field) : false;
}

/**
 * The `people` option a form's fields write: `false` for none, else each
 * field's settings by its key.
 */
export function peopleValueOf(people: PeopleForm[]): Record<string, unknown> | false {
	return people.length === 0 ? false : Object.fromEntries(people.map((item) => [item.field, {
		plural: item.plural.trim(),
		singular: item.singular.trim(),
		aliases: item.aliases,
		archive: peopleWordOf(item),
		multiple: item.multiple,
		required: item.required
	}]));
}

function peopleFormOf(item: PeopleFieldInfo): PeopleForm {
	return {
		field: item.field,
		plural: item.plural,
		singular: item.singular,
		aliases: [...item.aliases],
		archives: item.archive !== false,
		word: item.archive === false || item.archive === item.field ? '' : item.archive,
		multiple: item.multiple,
		required: item.required,
		added: false
	};
}

/**
 * The field a featured image is (D-281): a media field named `image`.
 */
export const FEATURED: FieldDescription = { name: 'image', type: 'media', label: 'Featured image', kind: 'image' };

/**
 * The word author archives sit under unless a type says otherwise.
 */
export const AUTHORS = 'authors';

/**
 * The word a form's author archives sit under, `false` for none.
 */
export function authorsWordOf(form: TypeForm): string | false {
	return form.authorArchives ? (form.authorsWord.trim().replace(/^\/+|\/+$/g, '') || AUTHORS) : false;
}

/**
 * A new type's form.
 */
export function emptyForm(): TypeForm {
	return { singular: '', plural: '', description: '', icon: '', prefix: '', public: true, sitemap: true, llms: true, feed: false, authors: true, authorArchives: true, authorsWord: '', people: null, dateArchives: 'none', hierarchical: false, types: [], fields: [], paths: {} };
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
		authorArchives: typeof type.authorsWord === 'string',
		authorsWord: typeof type.authorsWord === 'string' && type.authorsWord !== AUTHORS ? type.authorsWord : '',
		people: type.people.map(peopleFormOf),
		dateArchives: type.dateArchives,
		hierarchical: type.hierarchical === true,
		types: [...(type.types ?? [])],
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
		...(form.people === null ? { authors: form.authors } : { people: peopleValueOf(form.people) }),
		fields: form.fields,
		...(kind === 'collection' ? { dateArchives: form.dateArchives === 'none' ? null : form.dateArchives } : {}),
		...(kind === 'taxonomy' ? { hierarchical: form.hierarchical, types: form.types } : {})
	};

	// The author word is a URL setting, so it's sent only when it changes
	// (a site may not let data types set URLs), and the default as `null`.
	// The type editor's people fields carry their words themselves.
	const word = form.people === null ? authorsWordOf(form) : AUTHORS;

	if (initial === null) {
		return word === AUTHORS ? all : { ...all, authorsWord: word };
	}

	const before = changesOf(initial, null, kind);
	const was    = initial.people === null ? authorsWordOf(initial) : AUTHORS;
	const paths  = Object.fromEntries(Object.entries(form.paths)
		.filter(([key, path]) => pathOf(path) !== pathOf(initial.paths[key] ?? ''))
		.map(([key, path]) => [key, pathOf(path) || null]));

	return {
		...Object.fromEntries(Object.entries(all).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(before[key]))),
		...(word === was ? {} : { authorsWord: word === AUTHORS ? null : word }),
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
export const DATE_ARCHIVES = [
	{ value: 'none', label: 'None' },
	{ value: 'year', label: 'By year' },
	{ value: 'month', label: 'By month' },
	{ value: 'day', label: 'By day' },
	{ value: 'hour', label: 'By hour' },
	{ value: 'minute', label: 'By minute' },
	{ value: 'second', label: 'By second' }
];
