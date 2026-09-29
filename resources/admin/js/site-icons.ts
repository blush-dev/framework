/**
 * The site's icons (`GET icons`, D-246), for the editor's icon picker:
 * loaded once and shared. Each is drawn from its SVG as a CSS mask filled
 * with the text color, so nothing in the file runs and every icon takes
 * the picker's colors.
 */

import { request } from './api';

export interface SiteIcon {
	// As the icon component's `name` takes it: `house`, `jtcom/github`.
	name: string;
	label: string;
	keywords: string[];
	svg: string;
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

/**
 * Whether an icon matches a search: its name, label, or keywords.
 */
export function iconMatches(icon: SiteIcon, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${icon.name} ${icon.label} ${icon.keywords.join(' ')}`.toLowerCase();

	return words.every((word) => text.includes(word));
}
