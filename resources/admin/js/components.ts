/**
 * The components the editor's inserter offers (`GET components`, D-243),
 * loaded once and shared; how each is grouped and drawn; and the
 * directive text that inserts one.
 */

import { request, type FieldDescription } from './api';
import { attributeText } from './markdown';
import type { IconName } from './icons';

export interface ComponentProp extends FieldDescription {
	// A choice's labels, by value.
	choices?: Record<string, string>;
}

export interface ComponentDescription {
	// The full name, which is what's written (D-171): `blush/callout`.
	name: string;
	label: string;
	description: string;
	content: 'none' | 'text' | 'blocks';
	kind: 'container' | 'leaf' | 'inline';
	// A core component's group; `null` for the rest, which have a source.
	category: string | null;
	source: { kind: 'theme' | 'site' | 'extension'; label: string } | null;
	props: ComponentProp[];
	// Its variants under the active theme, Default not included (D-266).
	variants: ComponentVariant[];
}

export interface ComponentVariant {
	name: string;
	label: string;
	description: string;
	// Where it comes from, when not from the component's own namespace.
	source: { kind: 'theme' | 'site' | 'extension'; label: string } | null;
}

/**
 * A group in the inserter: a core category, or where the rest come from.
 */
export interface ComponentGroup {
	key: string;
	label: string;
	icon: IconName;
	// Where its components come from, for the section heading.
	source?: string;
}

const CATEGORIES: Record<string, { label: string; icon: IconName }> = {
	text: { label: 'Text', icon: 'pen-line' },
	media: { label: 'Media', icon: 'image' },
	layout: { label: 'Layout', icon: 'rows-3' },
	navigation: { label: 'Navigation', icon: 'compass' },
	data: { label: 'Data', icon: 'sliders-horizontal' }
};

const SOURCE_ICONS: Record<string, IconName> = { theme: 'paintbrush', site: 'house', extension: 'plug' };

const ICONS: Record<string, IconName> = {
	abbr: 'book-open',
	audio: 'headphones',
	button: 'arrow-up-right',
	callout: 'info',
	embed: 'globe',
	figure: 'panel-bottom',
	file: 'download',
	gallery: 'images',
	grid: 'layout-grid',
	group: 'folder',
	icon: 'star',
	kbd: 'terminal',
	menu: 'menu',
	meter: 'sliders-horizontal',
	progress: 'chevrons-right',
	row: 'rows-3',
	time: 'clock',
	toc: 'list-ordered',
	video: 'video'
};

let loading: Promise<ComponentDescription[]> | null = null;

/**
 * Loads the components, once per page load; a failed load is tried again
 * next time.
 */
export function loadComponents(): Promise<ComponentDescription[]> {
	loading ??= request<{ components: ComponentDescription[] }>('GET', '/components').then(
		(answer) => answer.components,
		(caught: unknown) => {
			loading = null;
			throw caught;
		}
	);

	return loading;
}

/**
 * The group a component is shown in.
 */
export function groupOf(component: ComponentDescription): ComponentGroup {
	if (component.category !== null) {
		const category = CATEGORIES[component.category];

		return { key: component.category, label: category?.label ?? component.category, icon: category?.icon ?? 'layers' };
	}

	const source = component.source ?? { kind: 'extension', label: component.name.split('/')[0] ?? '' };

	return {
		key: `${source.kind}:${source.label}`,
		label: source.label,
		icon: SOURCE_ICONS[source.kind] ?? 'plug',
		source: source.kind === 'theme' ? 'Theme' : (source.kind === 'site' ? undefined : 'Extension')
	};
}

/**
 * The groups the components fall in: the core categories in their order,
 * then the sources.
 */
export function groupsOf(components: ComponentDescription[]): ComponentGroup[] {
	const groups = new Map<string, ComponentGroup>();

	for (const key of Object.keys(CATEGORIES)) {
		const found = components.find((component) => component.category === key);

		if (found !== undefined) {
			groups.set(key, groupOf(found));
		}
	}

	for (const component of components) {
		const group = groupOf(component);

		if (!groups.has(group.key)) {
			groups.set(group.key, group);
		}
	}

	return [...groups.values()];
}

/**
 * The icon a component is drawn with.
 */
export function componentIcon(component: ComponentDescription): IconName {
	return (component.category !== null ? ICONS[component.name.replace(/^blush\//, '')] : undefined) ?? groupOf(component).icon;
}

/**
 * Whether a component matches a search: its label, name, description,
 * or group.
 */
export function matches(component: ComponentDescription, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${component.label} ${component.name} ${component.description} ${groupOf(component).label}`.toLowerCase();

	return words.every((word) => text.includes(word));
}

/**
 * Orders search results: a label or name that starts with the query
 * first, then one that contains it, then the rest.
 */
export function rank(components: ComponentDescription[], query: string): ComponentDescription[] {
	const q     = query.trim().toLowerCase();
	const score = (component: ComponentDescription): number => {
		const label = component.label.toLowerCase();
		const name  = component.name.toLowerCase();

		if (label.startsWith(q) || name.startsWith(q) || name.endsWith(`/${q}`)) {
			return 0;
		}

		return label.includes(q) || name.includes(q) ? 1 : 2;
	};

	return components.map((component, index) => ({ component, index, score: score(component) }))
		.sort((a, b) => a.score - b.score || a.index - b.index)
		.map((item) => item.component);
}

/**
 * The text that inserts a component, and where the caret goes in it:
 * `inline` for inside a sentence, else on lines of its own. `inner` (the
 * selected text) becomes its label or body, and `values` its first
 * attributes (a media file's `src`). Required props without a value are
 * written empty, so the author sees what the component needs; the rest
 * keep their defaults, unwritten. The caret goes in the first empty
 * value, else the empty label or body, else after the directive.
 */
export function directiveText(component: ComponentDescription, inline: boolean, inner = '', values: Record<string, string> = {}): { text: string; caret: number } {
	const empty      = component.props.filter((prop) => prop.required === true && prop.name !== 'label' && values[prop.name] === undefined);
	const written    = [...Object.entries(values).map(([name, value]) => attributeText(name, value)), ...empty.map((prop) => `${prop.name}=""`)];
	const attributes = written.length === 0 ? '' : `{${written.join(' ')}}`;
	const value      = empty.length === 0 ? -1 : attributes.indexOf(`${empty[0]?.name ?? ''}=""`) + (empty[0]?.name.length ?? 0) + 2;

	if (component.content === 'blocks') {
		const head = `:::${component.name}${attributes}`;
		const text = `${head}\n${inner}\n:::`;

		return { text, caret: value !== -1 ? head.length - attributes.length + value : (inner === '' ? head.length + 1 : text.length) };
	}

	const label = component.content === 'text' ? `[${inner}]` : '';
	const head  = `${inline ? ':' : '::'}${component.name}${label}`;
	const text  = `${head}${attributes}`;

	if (value !== -1 && (label === '' || inner !== '')) {
		return { text, caret: head.length + value };
	}

	return { text, caret: label !== '' && inner === '' ? head.length - 1 : text.length };
}
