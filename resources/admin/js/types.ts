/**
 * The site's content types (`GET types`, D-233, D-234), loaded once and
 * shared by the navigation and the screens, and the type the current
 * screen is about, so the navigation can mark it.
 */

import { ref } from 'vue';
import { request, type ContentTypeSummary } from './api';
import type { IconName } from './icons';

export const types = ref<ContentTypeSummary[]>([]);

/**
 * The type of the entries on screen, or `null` for screens that aren't
 * about one type.
 */
export const currentType = ref<string | null>(null);

let loading: Promise<ContentTypeSummary[]> | null = null;

/**
 * Loads the types, once per page load; a failed load is tried again next
 * time.
 */
export function loadTypes(): Promise<ContentTypeSummary[]> {
	loading ??= request<{ types: ContentTypeSummary[] }>('GET', '/types').then(
		(answer) => (types.value = answer.types),
		(caught: unknown) => {
			loading = null;
			throw caught;
		}
	);

	return loading;
}

/**
 * A type by name, once loaded.
 */
export function findType(name: string): ContentTypeSummary | undefined {
	return types.value.find((type) => type.name === name);
}

/**
 * The icon a type's kind is shown with.
 */
export function typeIcon(type: ContentTypeSummary): IconName {
	return type.kind === 'taxonomy' ? 'tag' : (type.kind === 'pages' ? 'files' : 'file-text');
}
