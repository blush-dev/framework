/**
 * The site's content types (`GET types`, D-233, D-234), loaded once and
 * shared by the navigation and the screens, and the type the current
 * screen is about, so the navigation can mark it.
 */

import { ref } from 'vue';
import { entryRoute, request, type ContentTypeSummary, type TypeLabels } from './api';
import { humanize } from './fields';
import type { IconName } from './icons';
import { iconMask, loadIcons } from './site-icons';

export const types = ref<ContentTypeSummary[]>([]);

/**
 * The type accounts' authors belong to, listed with people rather than
 * content, or `null` when the site has none.
 */
export const profileType = ref<string | null>(null);

/**
 * Whether types can be created here (types in `user/data/types` are
 * read, D-311), and whether they may set their own URLs.
 */
export const canCreateTypes = ref(false);
export const typeUrls       = ref(true);

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
	loading ??= request<{ types: ContentTypeSummary[]; authors: string | null; create: boolean; urls: boolean }>('GET', '/types').then(
		(answer) => {
			types.value          = answer.types;
			profileType.value     = answer.authors;
			canCreateTypes.value = answer.create;
			typeUrls.value       = answer.urls;

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
 * Loads the types again, after one changed (D-311), so the navigation and
 * the screens show it.
 */
export function reloadTypes(): Promise<ContentTypeSummary[]> {
	loading = null;

	return loadTypes();
}

/**
 * A type by name, once loaded.
 */
export function findType(name: string): ContentTypeSummary | undefined {
	return types.value.find((type) => type.name === name);
}

/**
 * A type's labels (D-278), or, while the types load or for a type the
 * site doesn't have, plain ones made from its name.
 */
export function labelsOf(name: string): TypeLabels {
	const found = findType(name);

	if (found) {
		return found.labels;
	}

	const singular = humanize(name);
	const item     = name.replace(/[_-]+/g, ' ').trim();

	return { singular, plural: singular, menu: singular, item, items: item, newItem: `New ${item}`, editItem: `Edit ${item}`, searchItems: `Search ${item}` };
}

/**
 * The icon a type's kind is shown with.
 */
export function typeIcon(type: Pick<ContentTypeSummary, 'kind'>): IconName {
	return ({ taxonomy: 'tag', tree: 'files', profiles: 'user-round', collection: 'file-text' } as const)[type.kind];
}

/**
 * The CSS mask of the site icon a type names, or `null` while the icons
 * load, or when it names none or one the site doesn't have.
 */
export function typeMask(type: Pick<ContentTypeSummary, 'icon'>): string | null {
	return type.icon === null ? null : (masks.value[type.icon] ?? null);
}

/**
 * Where an entry in a list opens: a profile's own screen (D-353), which
 * says where it appears and links to its editor, or else the editor.
 */
export function listRoute(entry: { id: string; type: string; handle: string | null }): { name: string; params: Record<string, string | string[]> } {
	if (entry.type === profileType.value && entry.handle !== null) {
		return { name: 'profile-detail', params: { slug: entry.handle.slice(entry.handle.indexOf('/') + 1) } };
	}

	return entryRoute(entry);
}
