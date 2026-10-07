/**
 * What a reference field can point at (`GET references/{type}`, D-281),
 * for the editor's reference picker, and the slugs references store.
 */

import { request } from './api';

export interface ReferenceItem {
	slug: string;
	title: string;
	status: 'draft' | 'scheduled' | 'published' | null;
	parent: string | null;
	// How many published entries use a term; `null` for other types.
	uses: number | null;
	// Its depth in a hierarchical collection's tree.
	depth: number | null;
	// A slug the field holds that nothing answers to.
	missing: boolean;
}

export interface ReferenceList {
	type: string;
	// Whether a new item may be written as it's typed (a classify relation's
	// term, whose file the picker writes, D-584).
	create: boolean;
	// Whether it's a hierarchical collection, answered whole, in tree order.
	tree: boolean;
	search: string;
	total: number;
	items: ReferenceItem[];
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
export function loadReferences(type: string, options: { search?: string; slugs?: string[]; limit?: number; for?: string; tree?: boolean } = {}): Promise<ReferenceList> {
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

	const string = query.toString();

	return request<ReferenceList>('GET', `/references/${encodeURIComponent(type)}${string === '' ? '' : `?${string}`}`);
}
