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
	// Its depth in a hierarchical taxonomy's tree.
	depth: number | null;
	// A term used by entries without a file of its own.
	virtual: boolean;
	// A slug the field holds that nothing answers to.
	missing: boolean;
}

export interface ReferenceList {
	type: string;
	// Whether a slug with nothing behind it may be written (a taxonomy's
	// virtual term).
	create: boolean;
	// Whether it's a hierarchical taxonomy, answered whole, in tree order.
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

export function loadReferences(type: string, options: { search?: string; slugs?: string[]; limit?: number } = {}): Promise<ReferenceList> {
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

	const string = query.toString();

	return request<ReferenceList>('GET', `/references/${encodeURIComponent(type)}${string === '' ? '' : `?${string}`}`);
}
