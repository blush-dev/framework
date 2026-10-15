/**
 * A content type as a form (D-311): the options the admin edits for a
 * type in `user/data/types`, from a type's description and back into the
 * options to change (`PATCH types/{name}`, `POST types`), only the ones
 * that changed. The server leaves out what's at its default, so a field
 * that's blank or back at its default is simply sent.
 */

import type { ContentTypeDetail, TypeFieldItem } from './api';
import { t } from './messages';

export type TypeKind = 'collection' | 'tree';

/**
 * The labels made from a type's names (`TypeLabels::KEYS`, D-866),
 * beyond the singular and plural.
 */
export const LABEL_KEYS = ['menu', 'item', 'items', 'newItem', 'editItem', 'searchItems'] as const;

export type LabelKey = typeof LABEL_KEYS[number];

/**
 * A listing's settings (`Listing`): what it lists, by what, in which
 * direction, and how many to a page; `''` for each one's default.
 */
export interface ListingForm {
	type: string;
	orderBy: string;
	order: string;
	perPage: string;
}

export interface TypeForm {
	singular: string;
	plural: string;
	// The other labels it sets, `''` for each one's default (D-866).
	labels: Record<LabelKey, string>;
	description: string;
	icon: string;
	// The URL prefix, without slashes (a tree's too, D-683); `''` for
	// its key's.
	prefix: string;
	public: boolean;
	sitemap: boolean;
	// Whether entries are listed in `llms.txt` (D-398): on by default,
	// off for a new type of terms (D-401).
	llms: boolean;
	feed: boolean;
	// The taxonomy whose entries become a feed item's categories, `''`
	// for every one but authors, and how the feed lists (D-866).
	feedCategories: string;
	feedListing: ListingForm;
	// Whether it has routes at all (`urls: false` when not, D-866).
	routes: boolean;
	// How its listing page lists entries (D-866).
	listing: ListingForm;
	// Whether the type joins the `authors` credit relation (D-602), sent
	// beside the options, not among them.
	authors: boolean;
	// The credit relation that's its byline, `''` for its only one
	// (D-602).
	byline: string;
	// A collection's: `none`, `year`, `month`, `day`, … (`DateArchives`).
	dateArchives: string;
	// Its file name pattern (D-511, any kind, D-514), `''` for the
	// default, the slug alone (D-515).
	filename: string;
	// A collection's or the profiles' folder pattern (D-629), below its
	// folder, `_` and its key (D-683); `''` for none.
	folders: string;
	// A collection's: whether an entry may name a parent (D-593), and
	// whether entries are newest published first or by `position`.
	hierarchical: boolean;
	order: 'date' | 'position';
	// The library fields it carries, in sections (D-858, D-863).
	fields: TypeFieldItem[];
	// Its place in the content menu (D-726): its position, `''` for none,
	// and the type it sits under, `''` for the top level.
	adminPosition: string;
	adminUnder: string;
	// Its workflow's name (D-851), `''` for core's `standard`.
	workflow: string;
	// Whether its entries may have an Expires date (D-852).
	expires: boolean;
	// Whether its entries choose a visibility (D-855).
	visibility: boolean;
	// The optional core fields it has (D-866).
	image: boolean;
	summary: boolean;
	subtitle: boolean;
	position: boolean;
	// Route keys' paths, relative to the prefix (D-350); `''` for a key's
	// default.
	paths: Record<string, string>;
}

/**
 * A new type's form.
 */
export function emptyForm(): TypeForm {
	return {
		singular: '', plural: '', labels: emptyLabels(), description: '', icon: '', prefix: '', public: true, sitemap: true, llms: true, feed: false,
		feedCategories: '', feedListing: emptyListing(), routes: true, listing: emptyListing(), authors: true, byline: '', dateArchives: 'none', filename: '', folders: '',
		hierarchical: false, order: 'date', fields: [], adminPosition: '', adminUnder: '', workflow: '', expires: false,
		// A new type starts with a featured image, a summary, and a
		// visibility (D-866); `position` follows its kind and nesting.
		visibility: true, image: true, summary: true, subtitle: false, position: false, paths: {}
	};
}

function emptyLabels(): Record<LabelKey, string> {
	return { menu: '', item: '', items: '', newItem: '', editItem: '', searchItems: '' };
}

function emptyListing(): ListingForm {
	return { type: '', orderBy: '', order: '', perPage: '' };
}

function listingFormOf(listing: Record<string, unknown> | null | undefined): ListingForm {
	const text = (value: unknown): string => typeof value === 'string' || typeof value === 'number' ? String(value) : '';

	return { type: text(listing?.type), orderBy: text(listing?.orderBy), order: text(listing?.order), perPage: text(listing?.perPage) };
}

/**
 * A listing as the server takes it: only what's set, or `null` for its
 * defaults.
 */
function listingOf(listing: ListingForm): Record<string, unknown> | null {
	const out: Record<string, unknown> = {};

	if (listing.type) {
		out.type = listing.type;
	}

	if (listing.orderBy) {
		out.orderBy = listing.orderBy;
	}

	if (listing.order) {
		out.order = listing.order;
	}

	if (listing.perPage.trim() !== '' && /^\d+$/.test(listing.perPage.trim())) {
		out.perPage = Number(listing.perPage.trim());
	}

	return Object.keys(out).length ? out : null;
}

/**
 * A type's form, from how the server describes it.
 */
export function formOf(type: ContentTypeDetail): TypeForm {
	const prefix = type.prefix === null ? '' : type.prefix.replace(/^\/+|\/+$/g, '');

	return {
		singular: type.labels.singular,
		plural: type.labels.plural,
		labels: Object.fromEntries(LABEL_KEYS.map((key) => [key, typeof type.labelsSet[key] === 'string' ? type.labelsSet[key] : ''])) as Record<LabelKey, string>,
		description: type.description,
		icon: type.icon ?? '',
		prefix: prefix === type.defaultPrefix ? '' : prefix,
		public: type.public,
		sitemap: type.sitemap,
		llms: type.llms,
		feed: type.feed,
		feedCategories: typeof type.feedSettings?.categories === 'string' ? type.feedSettings.categories : '',
		feedListing: listingFormOf(type.feedSettings?.listing as Record<string, unknown> | undefined),
		routes: type.hasRoutes,
		listing: listingFormOf(type.listing),
		authors: type.credits.includes('authors'),
		byline: type.byline ?? '',
		dateArchives: type.dateArchives,
		filename: type.filename ?? '',
		folders: type.folders ?? '',
		hierarchical: type.hierarchical,
		order: type.order ?? 'date',
		fields: copy(type.fields),
		adminPosition: type.adminPosition === null ? '' : String(type.adminPosition),
		adminUnder: type.adminUnder ?? '',
		workflow: type.workflow === 'standard' ? '' : type.workflow,
		expires: type.supports.expires,
		visibility: type.supports.visibility,
		image: type.supports.image,
		summary: type.supports.summary,
		subtitle: type.supports.subtitle,
		position: type.supports.position,
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
 * A path as it's saved: no slashes around it, `''` for the default.
 */
export function pathOf(path: string): string {
	return path.trim().replace(/^\/+|\/+$/g, '');
}

/**
 * A type's `feed`: off, on, or on with its settings (D-866).
 */
function feedOf(form: TypeForm): boolean | Record<string, unknown> {
	if (!form.feed) {
		return false;
	}

	const listing = listingOf({ ...form.feedListing, type: '' });

	return form.feedCategories || listing ? { ...(form.feedCategories ? { categories: form.feedCategories } : {}), ...(listing ? { listing } : {}) } : true;
}

/**
 * The options to change: every one for a new type (`initial` is
 * `null`), else the ones that differ from `initial`. Route paths are
 * sent only for the keys that changed, `null` for a key's default.
 */
export function changesOf(form: TypeForm, initial: TypeForm | null, kind: TypeKind): Record<string, unknown> {
	const all: Record<string, unknown> = {
		labels: { singular: form.singular.trim(), plural: form.plural.trim(), ...Object.fromEntries(LABEL_KEYS.map((key) => [key, form.labels[key].trim()])) },
		description: form.description.trim() || null,
		icon: form.icon.trim() || null,
		prefix: form.prefix.trim().replace(/^\/+|\/+$/g, '') || null,
		public: form.public,
		sitemap: form.sitemap,
		llms: form.llms,
		// A tree has no feed, routes of its own, or listing (D-386).
		...(kind === 'tree' ? {} : { feed: feedOf(form), routes: form.routes, listing: listingOf(form.listing) }),
		byline: form.byline || null,
		fields: form.fields,
		adminPosition: String(form.adminPosition).trim() === '' ? null : Number(form.adminPosition),
		adminUnder: form.adminUnder || null,
		workflow: form.workflow || null,
		expires: form.expires,
		visibility: form.visibility,
		image: form.image,
		summary: form.summary,
		subtitle: form.subtitle,
		position: form.position,
		filename: form.filename || null,
		// A tree's folders are its pages' (D-629).
		...(kind === 'tree' ? {} : { folders: form.folders || null }),
		...(kind === 'collection' ? { dateArchives: form.dateArchives === 'none' ? null : form.dateArchives } : {}),
		...(kind === 'collection' ? { hierarchical: form.hierarchical, order: form.order === 'date' ? null : form.order } : {})
	};

	// A new type sends the route paths typed for it (D-870).
	if (initial === null) {
		const typed = Object.fromEntries(Object.entries(form.paths).map(([key, path]) => [key, pathOf(path)]).filter(([, path]) => path !== ''));

		return Object.keys(typed).length ? { ...all, paths: typed } : all;
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
	{ value: '', label: t('type_form.filename_default', 'Default'), hint: 'hello-world.md' },
	{ value: '{slug}', label: t('type_form.filename_slug', 'Slug'), hint: 'hello-world.md' },
	{ value: '{date}.{slug}', label: t('type_form.filename_date', 'Date and slug'), hint: '2026-10-05.hello-world.md' },
	{ value: '{date}-{time}.{slug}', label: t('type_form.filename_date_time', 'Date, time, and slug'), hint: '2026-10-05-093000.hello-world.md' }
];

/**
 * The folder patterns offered (D-629), each below the type's folder.
 */
export const FOLDERS = [
	{ value: '', label: t('type_form.folders_none', 'None'), hint: t('type_form.folders_none_hint', 'all in one folder') },
	{ value: '{year}', label: t('type_form.folders_year', 'By year'), hint: '2026/hello-world.md' },
	{ value: '{year}/{month}', label: t('type_form.folders_year_month', 'By year and month'), hint: '2026/10/hello-world.md' },
	{ value: '{initial}', label: t('type_form.folders_initial', 'By first letter'), hint: 'h/hello-world.md' }
];

/**
 * A collection's orders (D-593, `TypeOrder`).
 */
export const ORDERS = [
	{ value: 'date', label: t('type_form.order_date', 'Newest first, by date') },
	{ value: 'position', label: t('type_form.order_position', 'By position, then title') }
];

export const DATE_ARCHIVES = [
	{ value: 'none', label: t('type_form.date_archives_none', 'None') },
	{ value: 'year', label: t('type_form.date_archives_year', 'By year') },
	{ value: 'month', label: t('type_form.date_archives_month', 'By month') },
	{ value: 'day', label: t('type_form.date_archives_day', 'By day') },
	{ value: 'hour', label: t('type_form.date_archives_hour', 'By hour') },
	{ value: 'minute', label: t('type_form.date_archives_minute', 'By minute') },
	{ value: 'second', label: t('type_form.date_archives_second', 'By second') }
];
