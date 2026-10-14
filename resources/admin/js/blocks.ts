/**
 * The blocks the editor's inserter offers (`GET blocks`, D-243),
 * loaded once and shared; how each is grouped and drawn; and the
 * block text that inserts one.
 */

import { request, type FieldDescription } from './api';
import { MARKDOWN_BLOCK_KINDS } from './markdown-blocks';
import { attributeText, registerBlockKinds, shortName } from './markdown';
import type { IconName } from './icons';
import { t } from './messages';

export interface BlockProp extends FieldDescription {
	// A choice's labels, by value.
	choices?: Record<string, string>;
}

export interface BlockDescription {
	// The full name, which is what's written (D-171): `core/callout`.
	name: string;
	label: string;
	description: string;
	content: 'none' | 'text' | 'blocks';
	kind: 'container' | 'leaf' | 'inline';
	// A core block's group; `null` for the rest, which have a source.
	category: string | null;
	source: { kind: 'theme' | 'icon-pack' | 'plugin'; label: string } | null;
	props: BlockProp[];
	// Its variants under the active theme, Default not included (D-266).
	variants: BlockVariant[];
	// What a container holds, when it's only some things (D-314): `image`,
	// or blocks' full names.
	only?: string[] | null;
	// A Markdown element's tile (D-313): its icon, the other names it's
	// found by, and what it writes, with the placeholder to select.
	icon?: IconName;
	aliases?: string;
	markdown?: { text: string; pick: string };
}

export interface BlockVariant {
	name: string;
	label: string;
	description: string;
	// Where it comes from, when not from the block's own namespace.
	source: { kind: 'theme' | 'icon-pack' | 'plugin'; label: string } | null;
}

/**
 * A group in the inserter: a core category, or where the rest come from.
 */
export interface BlockGroup {
	key: string;
	label: string;
	icon: IconName;
	// Where its blocks come from, for the section heading.
	source?: string;
}

const CATEGORIES: Record<string, { label: string; icon: IconName }> = {
	text: { label: t('blocks.categories.text', 'Text'), icon: 'pen-line' },
	media: { label: t('blocks.categories.media', 'Media'), icon: 'image' },
	layout: { label: t('blocks.categories.layout', 'Layout'), icon: 'rows-3' },
	navigation: { label: t('blocks.categories.navigation', 'Navigation'), icon: 'compass' },
	data: { label: t('blocks.categories.data', 'Data'), icon: 'sliders-horizontal' }
};

const SOURCE_ICONS: Record<string, IconName> = { theme: 'paintbrush', 'icon-pack': 'shapes', plugin: 'plug' };

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
	playlist: 'list-music',
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
 * What `GET blocks` answers: the blocks, and Markdown images'
 * variants, the classes the theme offers them (D-268).
 */
interface BlocksAnswer {
	blocks: BlockDescription[];
	image: { variants: BlockVariant[] };
	bleed: BleedClasses;
}

/**
 * The classes the active theme names for the bleed widths (D-313).
 */
export interface BleedClasses {
	wide: string;
	full: string;
}

let loading: Promise<BlocksAnswer> | null = null;

function load(): Promise<BlocksAnswer> {
	loading ??= request<BlocksAnswer>('GET', '/blocks').then((answer) => {
		registerBlockKinds(new Map(answer.blocks.map((block) => [block.name, block.kind])));

		return answer;
	}).catch((caught: unknown) => {
		loading = null;
		throw caught;
	});

	return loading;
}

/**
 * Loads the blocks, once per page load; a failed load is tried again
 * next time.
 */
export async function loadBlocks(): Promise<BlockDescription[]> {
	return (await load()).blocks;
}

/**
 * Loads the variants the theme offers Markdown images, with the
 * blocks.
 */
export async function imageVariants(): Promise<BlockVariant[]> {
	return (await load()).image.variants;
}

/**
 * Loads the classes the bleed control writes, with the blocks.
 */
export async function bleedClasses(): Promise<BleedClasses> {
	return (await load()).bleed;
}

/**
 * Markdown images in the block panel (D-268): not a block, but
 * listed under Media where people look for one. Choosing it opens the
 * media library, which writes plain Markdown.
 */
export const IMAGE_BLOCK: BlockDescription = {
	name: 'image',
	label: t('blocks.image', 'Image'),
	description: t('blocks.image_description', 'An ordinary Markdown image, from the media library. The quoted part after its address is its caption.'),
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
 * names it everywhere else, in a category group with the blocks, and
 * written with its placeholder selected.
 */
function markdownElement(kind: keyof typeof MARKDOWN_BLOCK_KINDS, category: string, aliases: string, description: string, text: string, pick: string): BlockDescription {
	return {
		name: `markdown/${kind}`,
		label: MARKDOWN_BLOCK_KINDS[kind].label,
		description,
		content: 'none',
		kind: 'leaf',
		category,
		source: null,
		props: [],
		variants: [],
		icon: MARKDOWN_BLOCK_KINDS[kind].icon,
		aliases,
		markdown: { text, pick }
	};
}

export const MARKDOWN_ELEMENTS: BlockDescription[] = [
	markdownElement('heading', 'text', 'h1 h2 h3 title', t('blocks.descriptions.heading', 'A heading, at level 2: the entry\'s title is the page\'s level 1.'), '## Heading', 'Heading'),
	markdownElement('quote', 'text', 'blockquote citation', t('blocks.descriptions.quote', 'A block quotation. Every line carries the marker.'), '> Quoted text.', 'Quoted text.'),
	markdownElement('list', 'text', 'bullet numbered ordered task checklist ul ol', t('blocks.descriptions.list', 'A list. Its type, bulleted, numbered, or task, is in the settings.'), '- First item\n- Second item', 'First item'),
	markdownElement('definitions', 'text', 'definition dl terms glossary', t('blocks.descriptions.definitions', 'Terms, each with one or more definitions under it.'), 'Term\n: Its definition.', 'Term'),
	markdownElement('code', 'text', 'fence fenced pre snippet', t('blocks.descriptions.code', 'A fenced code block. The word after the fence is its language.'), '```\ncode\n```', 'code'),
	markdownElement('table', 'data', 'grid rows columns pipe', t('blocks.descriptions.table', 'A table written with pipes. Enter on its last row adds another.'), '| Column | Column |\n| --- | --- |\n| Cell | Cell |', 'Column'),
	markdownElement('rule', 'layout', 'divider hr rule thematic break separator', t('blocks.descriptions.rule', 'A horizontal rule between sections.'), '---', '')
];

/**
 * The group a block is shown in.
 */
export function groupOf(block: BlockDescription): BlockGroup {
	if (block.category !== null) {
		const category = CATEGORIES[block.category];

		return { key: block.category, label: category?.label ?? block.category, icon: category?.icon ?? 'layers' };
	}

	const source = block.source ?? { kind: 'plugin', label: block.name.split('/')[0] ?? '' };

	return {
		key: `${source.kind}:${source.label}`,
		label: source.label,
		icon: SOURCE_ICONS[source.kind] ?? 'plug',
		source: source.kind === 'theme' ? t('blocks.source_theme', 'Theme') : t('blocks.source_plugin', 'Plugin')
	};
}

/**
 * The groups the blocks fall in: the core categories in their order,
 * then the sources.
 */
export function groupsOf(blocks: BlockDescription[]): BlockGroup[] {
	const groups = new Map<string, BlockGroup>();

	for (const key of Object.keys(CATEGORIES)) {
		const found = blocks.find((block) => block.category === key);

		if (found !== undefined) {
			groups.set(key, groupOf(found));
		}
	}

	for (const block of blocks) {
		const group = groupOf(block);

		if (!groups.has(group.key)) {
			groups.set(group.key, group);
		}
	}

	return [...groups.values()];
}

/**
 * The icon a block is drawn with.
 */
export function blockIcon(block: BlockDescription): IconName {
	return block.icon ?? (block.category !== null ? ICONS[shortName(block.name)] : undefined) ?? groupOf(block).icon;
}

/**
 * Whether a block matches a search: its label, name, description,
 * or group.
 */
export function matches(block: BlockDescription, query: string): boolean {
	const words = query.trim().toLowerCase().split(/\s+/).filter((word) => word !== '');
	const text  = `${block.label} ${block.name} ${block.aliases ?? ''} ${block.description} ${groupOf(block).label}`.toLowerCase();

	return words.every((word) => text.includes(word));
}

/**
 * Orders search results: a label or name that starts with the query
 * first, then one that contains it, then the rest.
 */
export function rank(blocks: BlockDescription[], query: string): BlockDescription[] {
	const q     = query.trim().toLowerCase();
	const score = (block: BlockDescription): number => {
		const label = block.label.toLowerCase();
		const name  = block.name.toLowerCase();

		if (label.startsWith(q) || name.startsWith(q) || name.endsWith(`/${q}`)) {
			return 0;
		}

		return label.includes(q) || name.includes(q) ? 1 : 2;
	};

	return blocks.map((block, index) => ({ block, index, score: score(block) }))
		.sort((a, b) => a.score - b.score || a.index - b.index)
		.map((item) => item.block);
}

/**
 * The text that inserts a block, and where the caret goes in it:
 * `inline` for inside a sentence, else on lines of its own. `inner` (the
 * selected text) becomes its label or body, and `values` its first
 * attributes (a media file's `src`). Required props without a value are
 * written empty, so the author sees what the block needs; the rest
 * keep their defaults, unwritten. The caret goes in the first empty
 * value, else the empty label or body, else after the block.
 */
export function blockText(block: BlockDescription, inline: boolean, inner = '', values: Record<string, string> = {}): { text: string; caret: number } {
	const empty      = block.props.filter((prop) => prop.required === true && prop.name !== 'label' && values[prop.name] === undefined);
	const written    = [...Object.entries(values).map(([name, value]) => attributeText(name, value)), ...empty.map((prop) => `${prop.name}=""`)];
	const attributes = written.length === 0 ? '' : `{${written.join(' ')}}`;
	const value      = empty.length === 0 ? -1 : attributes.indexOf(`${empty[0]?.name ?? ''}=""`) + (empty[0]?.name.length ?? 0) + 2;

	if (block.content === 'blocks') {
		const head = `:::${block.name}${attributes}`;
		const text = `${head}\n${inner}\n:::`;

		return { text, caret: value !== -1 ? head.length - attributes.length + value : (inner === '' ? head.length + 1 : text.length) };
	}

	const label = block.content === 'text' ? `[${inner}]` : '';
	const head  = `${inline ? ':' : '::'}${block.name}${label}`;
	const text  = `${head}${attributes}`;

	if (value !== -1 && (label === '' || inner !== '')) {
		return { text, caret: head.length + value };
	}

	return { text, caret: label !== '' && inner === '' ? head.length - 1 : text.length };
}
