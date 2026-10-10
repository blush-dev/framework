/**
 * The site's icons (`GET icons`, D-246), for the editor's icon picker:
 * loaded once and shared, and grouped as the picker shows them (D-265).
 * Each is drawn from its SVG as a CSS mask filled with the text color, so
 * nothing in the file runs and every icon takes the picker's colors.
 */

import { ref } from 'vue';
import { request } from './api';
import type { IconName } from './icons';

export interface SiteIcon {
	// As the icon directive's `name` takes it: `house`, `jtcom/github`.
	name: string;
	label: string;
	keywords: string[];
	// A core icon's group; `null` for the rest, which have a source.
	category: string | null;
	source: { kind: 'theme' | 'icon-pack' | 'plugin'; label: string } | null;
	svg: string;
}

/**
 * A group in the picker: a core category, or where the rest come from.
 */
export interface IconGroup {
	key: string;
	label: string;
	// The admin's icon for it, in the picker's list of groups.
	icon: IconName;
}

// The core categories, in the order the picker lists them.
const CATEGORIES: Record<string, { label: string; icon: IconName }> = {
	status: { label: 'Status', icon: 'info' },
	interface: { label: 'Interface', icon: 'layout-grid' },
	arrows: { label: 'Arrows', icon: 'arrow-up-right' },
	writing: { label: 'Writing', icon: 'file-text' },
	media: { label: 'Media', icon: 'image' },
	communication: { label: 'Communication', icon: 'message-square' },
	people: { label: 'People', icon: 'users' },
	security: { label: 'Security', icon: 'shield' },
	time: { label: 'Time', icon: 'clock' },
	places: { label: 'Places', icon: 'globe' },
	development: { label: 'Development', icon: 'code' },
	design: { label: 'Design', icon: 'paintbrush' },
	nature: { label: 'Nature', icon: 'leaf' },
	things: { label: 'Things', icon: 'lightbulb' }
};

const SOURCE_ICONS: Record<string, IconName> = { theme: 'paintbrush', 'icon-pack': 'shapes', plugin: 'plug' };

/**
 * The group an icon is shown in.
 */
export function iconGroupOf(icon: SiteIcon): IconGroup {
	if (icon.category !== null) {
		const category = CATEGORIES[icon.category];

		return { key: icon.category, label: category?.label ?? icon.category, icon: category?.icon ?? 'layers' };
	}

	const source = icon.source ?? { kind: 'plugin', label: icon.name.split('/')[0] ?? '' };

	return { key: `${source.kind}:${source.label}`, label: source.label, icon: SOURCE_ICONS[source.kind] ?? 'plug' };
}

/**
 * The groups the icons fall in, each with its icons: the core categories
 * in their order, then the sources.
 */
export function iconGroups(icons: SiteIcon[]): (IconGroup & { icons: SiteIcon[] })[] {
	const groups = new Map<string, IconGroup & { icons: SiteIcon[] }>();

	for (const key of Object.keys(CATEGORIES)) {
		groups.set(key, { key, label: CATEGORIES[key]?.label ?? key, icon: CATEGORIES[key]?.icon ?? 'layers', icons: [] });
	}

	for (const icon of icons) {
		const group = iconGroupOf(icon);

		if (!groups.has(group.key)) {
			groups.set(group.key, { ...group, icons: [] });
		}

		groups.get(group.key)?.icons.push(icon);
	}

	return [...groups.values()].filter((group) => group.icons.length > 0);
}

let loading: Promise<SiteIcon[]> | null = null;

/**
 * Loads the icons, once per page load; a failed load is tried again next
 * time.
 */
export function loadIcons(): Promise<SiteIcon[]> {
	loading ??= request<{ icons: SiteIcon[] }>('GET', '/icons').then(
		(answer) => answer.icons,
		(caught: unknown) => {
			loading = null;
			throw caught;
		}
	);

	return loading;
}

/**
 * The CSS mask that draws an icon, or `none` when it has no SVG.
 */
export function iconMask(icon: SiteIcon): string {
	return icon.svg === '' ? 'none' : `url("data:image/svg+xml,${encodeURIComponent(icon.svg)}")`;
}

// The masks of the site's icons, by name, once `loadIconMasks()` has
// loaded them: for drawing an icon a type or a menu item names.
export const iconMasks = ref<Record<string, string>>({});

let masking = false;

/**
 * Loads the site's icons' masks into `iconMasks`, once.
 */
export function loadIconMasks(): void {
	if (masking) {
		return;
	}

	masking = true;
	loadIcons().then((icons) => {
		iconMasks.value = Object.fromEntries(icons.filter((icon) => icon.svg !== '').map((icon) => [icon.name, iconMask(icon)]));
	}, () => {
		masking = false;
	});
}

/**
 * Whether an icon matches a search: its name, label, keywords, or group.
 */
export function iconMatches(icon: SiteIcon, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${icon.name} ${icon.label} ${icon.keywords.join(' ')} ${iconGroupOf(icon).label}`.toLowerCase();

	return words.every((word) => text.includes(word));
}
