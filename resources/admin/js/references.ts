/**
 * What a reference field can point at (`GET references/{type}`, D-281),
 * for the editor's reference picker, and the slugs references store.
 */

import { request } from './api';

export interface ReferenceItem {
	slug: string;
	title: string;
	// A held slug can name a trashed entry (D-607).
	status: 'draft' | 'scheduled' | 'published' | 'trash' | null;
	parent: string | null;
	// How many published entries use a term; `null` for other types.
	uses: number | null;
	// Its depth in a hierarchical collection's tree.
	depth: number | null;
	// A slug the field holds that nothing answers to.
	missing: boolean;
	// Its image and publish date (`Y-m-d`), for cards (D-599).
	image?: string | null;
	date?: string | null;
	// Its ancestors' titles (`Mains › Pasta`), for a nesting type (D-607).
	path?: string | null;
	// For a missing slug, the candidate it most likely meant.
	closest?: { slug: string; title: string } | null;
	// Asked `from` a relation whose inverse has a `max`: how many entries
	// name it through the relation (D-608).
	taken?: number | null;
}

export interface ReferenceList {
	type: string;
	// Whether a new item may be written as it's typed (a classify relation's
	// term, whose file the picker writes, D-584).
	create: boolean;
	// Whether it's a hierarchical collection, answered whole, in tree order.
	tree: boolean;
	search: string;
	// With no search, how many candidates there are; else how many match.
	total: number;
	items: ReferenceItem[];
	// Asked with `upto`: whether `items` is every candidate (D-607).
	whole?: boolean;
	// How many `except` with `branch` left out besides itself.
	excluded?: number;
	// What's offered before anything's typed (`suggest`).
	suggested?: ReferenceItem[];
	// Asked `from` a relation: its inverse's `max`, which `taken` counts
	// against, or `null` (D-608).
	inverseMax?: number | null;
}

/**
 * The most candidates a picker holds whole and filters itself; past it,
 * it only searches (D-607).
 */
export const WHOLE = 50;

/**
 * How many results a list shows: rows, and cards, which are taller.
 */
export const CAP      = 8;
export const CARD_CAP = 6;

/**
 * Past this many chips, the rest fold into one.
 */
export const FOLD = 12;

/**
 * How well a title matches a search, as the server ranks them: 0 when
 * it starts with it, 1 when a word in it does, 2 when it's elsewhere,
 * and `null` when it isn't there (nor in the slug).
 */
export function matchRank(item: Pick<ReferenceItem, 'title' | 'slug'>, query: string): number | null {
	const text  = query.trim().toLowerCase();
	const title = item.title.toLowerCase();

	if (text === '' || title.startsWith(text)) {
		return 0;
	}

	const at = title.indexOf(text);

	if (at < 0) {
		return item.slug.includes(text) ? 2 : null;
	}

	const escaped = text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

	return new RegExp(`(?<![\\p{L}\\p{N}])${escaped}`, 'u').test(title) ? 1 : 2;
}

/**
 * The items a search finds among a whole list, ranked as the server
 * ranks them, by title within each rank.
 */
export function ranked<T extends Pick<ReferenceItem, 'title' | 'slug'>>(items: readonly T[], query: string): T[] {
	return items
		.map((item) => ({ item, rank: matchRank(item, query) }))
		.filter((found): found is { item: T; rank: number } => found.rank !== null)
		.sort((a, b) => a.rank - b.rank || a.item.title.localeCompare(b.item.title, undefined, { numeric: true, sensitivity: 'base' }))
		.map((found) => found.item);
}

/**
 * A title split around what a search matched, for bolding it: the
 * match at a word's start when there is one, else the first.
 */
export function marked(title: string, query: string): [string, string, string] {
	const text  = query.trim().toLowerCase();
	const lower = title.toLowerCase();

	if (text === '') {
		return [title, '', ''];
	}

	let at = lower.indexOf(text);

	for (let from = at; from >= 0; from = lower.indexOf(text, from + 1)) {
		if (from === 0 || !/[\p{L}\p{N}]/u.test(lower[from - 1] ?? '')) {
			at = from;
			break;
		}
	}

	return at < 0 ? [title, '', ''] : [title.slice(0, at), title.slice(at, at + text.length), title.slice(at + text.length)];
}

/**
 * The last line of a capped list (D-607): how many there are past what's
 * shown, never a scrollbar.
 */
export function moreLine(shown: number, total: number): string {
	return total > shown ? `${shown.toLocaleString()} of ${total.toLocaleString()} matches. Keep typing to narrow.` : `${total.toLocaleString()} ${total === 1 ? 'match' : 'matches'}.`;
}

/**
 * The slug a reference stores for some words, as the server makes it
 * (`Slug::from`): `Book Reviews` is `book-reviews`.
 */
export function slugOf(value: string): string {
	return value
		.replace(/_+/gu, '-')
		.replace(/[^-\p{L}\p{N}\s]+/gu, '-')
		.replace(/[-\s]+/gu, '-')
		.toLowerCase()
		.replace(/^-+|-+$/g, '');
}

/**
 * The slugs in a reference field's form value (`a, b`), as written.
 */
export function referenceValues(value: string): string[] {
	return value.split(',').map((item) => item.trim()).filter((item) => item !== '');
}

/**
 * Loads what a reference to `type` can point at. With `for`, a term type
 * answers only the terms that type's entries use (D-303); with `tree`, a
 * tree answers all its pages in tree order (D-408).
 */
export interface ReferenceOptions {
	search?: string;
	slugs?: string[];
	limit?: number;
	for?: string;
	tree?: boolean;
	// Every candidate when there are this many or fewer (D-607).
	upto?: number;
	// What to offer before anything's typed, how many, and the relation
	// (`recipe.cooks`) `recent` asks by.
	suggest?: 'uses' | 'edited' | 'recent';
	suggestions?: number;
	from?: string;
	// A slug to leave out, with the entries under it with `branch`.
	except?: string;
	branch?: boolean;
}

export function loadReferences(type: string, options: ReferenceOptions = {}): Promise<ReferenceList> {
	const query = new URLSearchParams();

	if (options.search) {
		query.set('search', options.search);
	}

	if (options.slugs?.length) {
		query.set('slugs', options.slugs.map(slugOf).join(','));
	}

	if (options.limit !== undefined) {
		query.set('limit', String(options.limit));
	}

	if (options.for !== undefined) {
		query.set('for', options.for);
	}

	if (options.tree === true) {
		query.set('tree', '1');
	}

	for (const name of ['upto', 'suggest', 'suggestions', 'from', 'except'] as const) {
		const value = options[name];

		if (value !== undefined && value !== '') {
			query.set(name, String(value));
		}
	}

	if (options.branch === true) {
		query.set('branch', '1');
	}

	const string = query.toString();

	return request<ReferenceList>('GET', `/references/${encodeURIComponent(type)}${string === '' ? '' : `?${string}`}`);
}
