/**
 * The directives the editor's inserter offers (`GET directives`, D-243),
 * loaded once and shared; how each is grouped and drawn; and the
 * directive text that inserts one.
 */

import { request, type FieldDescription } from './api';
import { BLOCK_KINDS } from './blocks';
import { attributeText, registerDirectiveKinds } from './markdown';
import type { IconName } from './icons';

export interface DirectiveProp extends FieldDescription {
	// A choice's labels, by value.
	choices?: Record<string, string>;
}

export interface DirectiveDescription {
	// The full name, which is what's written (D-171): `blush/callout`.
	name: string;
	label: string;
	description: string;
	content: 'none' | 'text' | 'blocks';
	kind: 'container' | 'leaf' | 'inline';
	// A core directive's group; `null` for the rest, which have a source.
	category: string | null;
	source: { kind: 'theme' | 'site' | 'icon-pack' | 'plugin'; label: string } | null;
	props: DirectiveProp[];
	// Its variants under the active theme, Default not included (D-266).
	variants: DirectiveVariant[];
	// What a container holds, when it's only some things (D-314): `image`,
	// or directives' full names.
	only?: string[] | null;
	// A Markdown element's tile (D-313): its icon, the other names it's
	// found by, and what it writes, with the placeholder to select.
	icon?: IconName;
	aliases?: string;
	markdown?: { text: string; pick: string };
}

export interface DirectiveVariant {
	name: string;
	label: string;
	description: string;
	// Where it comes from, when not from the directive's own namespace.
	source: { kind: 'theme' | 'site' | 'icon-pack' | 'plugin'; label: string } | null;
}

/**
 * A group in the inserter: a core category, or where the rest come from.
 */
export interface DirectiveGroup {
	key: string;
	label: string;
	icon: IconName;
	// Where its directives come from, for the section heading.
	source?: string;
}

const CATEGORIES: Record<string, { label: string; icon: IconName }> = {
	text: { label: 'Text', icon: 'pen-line' },
	media: { label: 'Media', icon: 'image' },
	layout: { label: 'Layout', icon: 'rows-3' },
	navigation: { label: 'Navigation', icon: 'compass' },
	data: { label: 'Data', icon: 'sliders-horizontal' }
};

const SOURCE_ICONS: Record<string, IconName> = { theme: 'paintbrush', site: 'house', 'icon-pack': 'shapes', plugin: 'plug' };

const ICONS: Record<string, IconName> = {
	abbr: 'book-open',
	audio: 'headphones',
	badge: 'tag',
	button: 'arrow-up-right',
	callout: 'info',
	cite: 'quote',
	dfn: 'lightbulb',
	embed: 'globe',
	figure: 'panel-bottom',
	file: 'download',
	gallery: 'images',
	grid: 'layout-grid',
	group: 'group',
	icon: 'star',
	image: 'image',
	ins: 'plus',
	kbd: 'terminal',
	menu: 'menu',
	meter: 'sliders-horizontal',
	progress: 'chevrons-right',
	row: 'columns-3',
	samp: 'monitor',
	small: 'baseline',
	stack: 'rows-3',
	time: 'clock',
	toc: 'list-ordered',
	var: 'code',
	video: 'video'
};

/**
 * What `GET directives` answers: the directives, and Markdown images'
 * variants, the classes the theme offers them (D-268).
 */
interface DirectivesAnswer {
	directives: DirectiveDescription[];
	image: { variants: DirectiveVariant[] };
	bleed: BleedClasses;
}

/**
 * The classes the active theme names for the bleed widths (D-313).
 */
export interface BleedClasses {
	wide: string;
	full: string;
}

let loading: Promise<DirectivesAnswer> | null = null;

function load(): Promise<DirectivesAnswer> {
	loading ??= request<DirectivesAnswer>('GET', '/directives').then((answer) => {
		registerDirectiveKinds(new Map(answer.directives.map((directive) => [directive.name, directive.kind])));

		return answer;
	}).catch((caught: unknown) => {
		loading = null;
		throw caught;
	});

	return loading;
}

/**
 * Loads the directives, once per page load; a failed load is tried again
 * next time.
 */
export async function loadDirectives(): Promise<DirectiveDescription[]> {
	return (await load()).directives;
}

/**
 * Loads the variants the theme offers Markdown images, with the
 * directives.
 */
export async function imageVariants(): Promise<DirectiveVariant[]> {
	return (await load()).image.variants;
}

/**
 * Loads the classes the bleed control writes, with the directives.
 */
export async function bleedClasses(): Promise<BleedClasses> {
	return (await load()).bleed;
}

/**
 * Markdown images in the directive panel (D-268): not a directive, but
 * listed under Media where people look for one. Choosing it opens the
 * media library, which writes plain Markdown.
 */
export const IMAGE_DIRECTIVE: DirectiveDescription = {
	name: 'image',
	label: 'Image',
	description: 'An ordinary Markdown image, from the media library. The quoted part after its address is its caption.',
	content: 'none',
	kind: 'leaf',
	category: 'media',
	source: null,
	props: [],
	variants: []
};

/**
 * A Markdown element as a tile in the inserter (admin.md §8, The Markdown
 * elements are in the same panel; D-313): named and drawn as the editor
 * names it everywhere else, in a category group with the directives, and
 * written with its placeholder selected.
 */
function markdownElement(kind: keyof typeof BLOCK_KINDS, category: string, aliases: string, description: string, text: string, pick: string): DirectiveDescription {
	return {
		name: `markdown/${kind}`,
		label: BLOCK_KINDS[kind].label,
		description,
		content: 'none',
		kind: 'leaf',
		category,
		source: null,
		props: [],
		variants: [],
		icon: BLOCK_KINDS[kind].icon,
		aliases,
		markdown: { text, pick }
	};
}

export const MARKDOWN_ELEMENTS: DirectiveDescription[] = [
	markdownElement('heading', 'text', 'h1 h2 h3 title', 'A heading, at level 2: the entry\'s title is the page\'s level 1.', '## Heading', 'Heading'),
	markdownElement('quote', 'text', 'blockquote citation', 'A block quotation. Every line carries the marker.', '> Quoted text.', 'Quoted text.'),
	markdownElement('list', 'text', 'bullet numbered ordered task checklist ul ol', 'A list. Its type, bulleted, numbered, or task, is in the settings.', '- First item\n- Second item', 'First item'),
	markdownElement('definitions', 'text', 'definition dl terms glossary', 'Terms, each with one or more definitions under it.', 'Term\n: Its definition.', 'Term'),
	markdownElement('code', 'text', 'fence fenced pre snippet', 'A fenced code block. The word after the fence is its language.', '```\ncode\n```', 'code'),
	markdownElement('table', 'data', 'grid rows columns pipe', 'A table written with pipes. Enter on its last row adds another.', '| Column | Column |\n| --- | --- |\n| Cell | Cell |', 'Column'),
	markdownElement('rule', 'layout', 'divider hr rule thematic break separator', 'A horizontal rule between sections.', '---', '')
];

/**
 * The group a directive is shown in.
 */
export function groupOf(directive: DirectiveDescription): DirectiveGroup {
	if (directive.category !== null) {
		const category = CATEGORIES[directive.category];

		return { key: directive.category, label: category?.label ?? directive.category, icon: category?.icon ?? 'layers' };
	}

	const source = directive.source ?? { kind: 'plugin', label: directive.name.split('/')[0] ?? '' };

	return {
		key: `${source.kind}:${source.label}`,
		label: source.label,
		icon: SOURCE_ICONS[source.kind] ?? 'plug',
		source: source.kind === 'theme' ? 'Theme' : (source.kind === 'site' ? undefined : 'Plugin')
	};
}

/**
 * The groups the directives fall in: the core categories in their order,
 * then the sources.
 */
export function groupsOf(directives: DirectiveDescription[]): DirectiveGroup[] {
	const groups = new Map<string, DirectiveGroup>();

	for (const key of Object.keys(CATEGORIES)) {
		const found = directives.find((directive) => directive.category === key);

		if (found !== undefined) {
			groups.set(key, groupOf(found));
		}
	}

	for (const directive of directives) {
		const group = groupOf(directive);

		if (!groups.has(group.key)) {
			groups.set(group.key, group);
		}
	}

	return [...groups.values()];
}

/**
 * The icon a directive is drawn with.
 */
export function directiveIcon(directive: DirectiveDescription): IconName {
	return directive.icon ?? (directive.category !== null ? ICONS[directive.name.replace(/^blush\//, '')] : undefined) ?? groupOf(directive).icon;
}

/**
 * Whether a directive matches a search: its label, name, description,
 * or group.
 */
export function matches(directive: DirectiveDescription, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${directive.label} ${directive.name} ${directive.aliases ?? ''} ${directive.description} ${groupOf(directive).label}`.toLowerCase();

	return words.every((word) => text.includes(word));
}

/**
 * Orders search results: a label or name that starts with the query
 * first, then one that contains it, then the rest.
 */
export function rank(directives: DirectiveDescription[], query: string): DirectiveDescription[] {
	const q     = query.trim().toLowerCase();
	const score = (directive: DirectiveDescription): number => {
		const label = directive.label.toLowerCase();
		const name  = directive.name.toLowerCase();

		if (label.startsWith(q) || name.startsWith(q) || name.endsWith(`/${q}`)) {
			return 0;
		}

		return label.includes(q) || name.includes(q) ? 1 : 2;
	};

	return directives.map((directive, index) => ({ directive, index, score: score(directive) }))
		.sort((a, b) => a.score - b.score || a.index - b.index)
		.map((item) => item.directive);
}

/**
 * The text that inserts a directive, and where the caret goes in it:
 * `inline` for inside a sentence, else on lines of its own. `inner` (the
 * selected text) becomes its label or body, and `values` its first
 * attributes (a media file's `src`). Required props without a value are
 * written empty, so the author sees what the directive needs; the rest
 * keep their defaults, unwritten. The caret goes in the first empty
 * value, else the empty label or body, else after the directive.
 */
export function directiveText(directive: DirectiveDescription, inline: boolean, inner = '', values: Record<string, string> = {}): { text: string; caret: number } {
	const empty      = directive.props.filter((prop) => prop.required === true && prop.name !== 'label' && values[prop.name] === undefined);
	const written    = [...Object.entries(values).map(([name, value]) => attributeText(name, value)), ...empty.map((prop) => `${prop.name}=""`)];
	const attributes = written.length === 0 ? '' : `{${written.join(' ')}}`;
	const value      = empty.length === 0 ? -1 : attributes.indexOf(`${empty[0]?.name ?? ''}=""`) + (empty[0]?.name.length ?? 0) + 2;

	if (directive.content === 'blocks') {
		const head = `:::${directive.name}${attributes}`;
		const text = `${head}\n${inner}\n:::`;

		return { text, caret: value !== -1 ? head.length - attributes.length + value : (inner === '' ? head.length + 1 : text.length) };
	}

	const label = directive.content === 'text' ? `[${inner}]` : '';
	const head  = `${inline ? ':' : '::'}${directive.name}${label}`;
	const text  = `${head}${attributes}`;

	if (value !== -1 && (label === '' || inner !== '')) {
		return { text, caret: head.length + value };
	}

	return { text, caret: label !== '' && inner === '' ? head.length - 1 : text.length };
}
