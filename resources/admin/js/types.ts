/**
 * The site's content types (`GET types`, D-233, D-234), loaded once and
 * shared by the navigation and the screens, and the type the current
 * screen is about, so the navigation can mark it.
 */

import { ref } from 'vue';
import { request, type ContentTypeSummary } from './api';
import type { IconName } from './icons';
import { iconMask, loadIcons } from './site-icons';

export const types = ref<ContentTypeSummary[]>([]);

/**
 * The type accounts' authors belong to, listed with people rather than
 * content, or `null` when the site has none.
 */
export const authorType = ref<string | null>(null);

/**
 * The type of the entries on screen, or `null` for screens that aren't
 * about one type.
 */
export const currentType = ref<string | null>(null);

// The masks of the site icons types name, once loaded.
const masks = ref<Record<string, string>>({});

let loading: Promise<ContentTypeSummary[]> | null = null;

/**
 * Loads the types, once per page load; a failed load is tried again next
 * time. Types that name an icon load the site's icons too.
 */
export function loadTypes(): Promise<ContentTypeSummary[]> {
	loading ??= request<{ types: ContentTypeSummary[]; authors: string | null }>('GET', '/types').then(
		(answer) => {
			types.value      = answer.types;
			authorType.value = answer.authors;

			if (answer.types.some((type) => type.icon !== null)) {
				loadIcons().then((icons) => {
					masks.value = Object.fromEntries(icons.filter((icon) => icon.svg !== '').map((icon) => [icon.name, iconMask(icon)]));
				}, () => undefined);
			}

			return answer.types;
		},
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
export function typeIcon(type: Pick<ContentTypeSummary, 'kind'>): IconName {
	return type.kind === 'taxonomy' ? 'tag' : (type.kind === 'pages' ? 'files' : 'file-text');
}

/**
 * The CSS mask of the site icon a type names, or `null` while the icons
 * load, or when it names none or one the site doesn't have.
 */
export function typeMask(type: Pick<ContentTypeSummary, 'icon'>): string | null {
	return type.icon === null ? null : (masks.value[type.icon] ?? null);
}
