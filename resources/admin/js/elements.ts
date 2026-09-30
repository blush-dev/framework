/**
 * The elements a body is made of (admin.md §8, Every element is an
 * object): its container and leaf directives, its images, and its blocks
 * of Markdown (`markdown.ts`), as one outline in source order, each with
 * the element it's inside. The outline drives the drawer's Outline, a
 * panel's Content group, and the footer's breadcrumb; `elementAt()` says
 * which element the caret is in.
 */

import { BLOCK_KINDS, LIST_STYLES } from './blocks';
import {
	attributesOf,
	directiveHead,
	headingLevel,
	listItems,
	listStyle,
	unescaped,
	type MarkdownBlock,
	type MarkdownOutline
} from './markdown';
import { plural } from './format';

export interface ElementRef {
	kind: 'directive' | 'image' | 'block';
	// Its index among the outline's directives or images, or the blocks.
	index: number;
}

export interface OutlineItem extends ElementRef {
	start: number;
	end: number;
	depth: number;
	// The item it's inside, by index in the outline, or -1.
	parent: number;
}

interface Candidate extends ElementRef {
	start: number;
	end: number;
	// Which of two with the same span is more specific: lower is.
	rank: number;
}

export function sameElement(a: ElementRef | null | undefined, b: ElementRef | null | undefined): boolean {
	return a !== null && a !== undefined && b !== null && b !== undefined && a.kind === b.kind && a.index === b.index;
}

/**
 * Whether an element holds others, so it opens a level in the outline and
 * has a Content group: a container directive, a list, a list item, or a
 * definition list.
 */
export function holdsContent(markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef): boolean {
	if (ref.kind === 'directive') {
		return markdown.directives[ref.index]?.kind === 'container';
	}

	const kind = ref.kind === 'block' ? found[ref.index]?.kind : undefined;

	return kind === 'list' || kind === 'item' || kind === 'definitions';
}

function candidates(markdown: MarkdownOutline, found: MarkdownBlock[]): Candidate[] {
	return [
		...markdown.directives.map((item, index) => ({ kind: 'directive' as const, index, start: item.start, end: item.end, rank: 0 })),
		...markdown.images.map((item, index) => ({ kind: 'image' as const, index, start: item.start, end: item.end, rank: 0 })),
		...found.map((item, index) => ({ kind: 'block' as const, index, start: item.start, end: item.end, rank: holdsContent(markdown, found, { kind: 'block', index }) ? 2 : 1 }))
	];
}

/**
 * The element at an offset: the smallest one containing it (a caret just
 * after one counts as in it), so inside a callout the caret is in a
 * paragraph, and the callout only wins on its own opening and closing
 * lines. On a blank line, it's the element above, at the caret's own
 * level: one inside a container that closed above the caret is passed
 * over for the container. `null` above the first.
 */
export function elementAt(markdown: MarkdownOutline, found: MarkdownBlock[], offset: number): ElementRef | null {
	const all = candidates(markdown, found);
	let best: Candidate | null = null;

	const better = (item: Candidate, than: Candidate | null): boolean => than === null
		|| item.end - item.start < than.end - than.start
		|| (item.end - item.start === than.end - than.start && item.rank < than.rank);

	for (const item of all) {
		if (item.start <= offset && offset <= item.end && better(item, best)) {
			best = item;
		}
	}

	if (best !== null) {
		return { kind: best.kind, index: best.index };
	}

	const closed = markdown.directives.filter((item) => item.kind === 'container' && item.end < offset);

	for (const item of all) {
		const inline = item.kind === 'image' || (item.kind === 'directive' && markdown.directives[item.index]?.kind === 'inline');
		const buried = closed.some((container) => !(item.kind === 'directive' && markdown.directives[item.index] === container) && container.start <= item.start && item.end <= container.end);

		if (item.end >= offset || inline || buried) {
			continue;
		}

		if (best === null || item.end > best.end || (item.end === best.end && better(item, best))) {
			best = item;
		}
	}

	return best === null ? null : { kind: best.kind, index: best.index };
}

/**
 * Everything the body is made of, in source order: container and leaf
 * directives, images, and blocks (inline directives are inside a
 * sentence, so they aren't listed). A line that's nothing but an image is
 * listed as the image, not a paragraph. Depth is containment: anything
 * starting before a container, list, list item, or definition list ends
 * is inside it.
 */
export function outlineItems(source: string, markdown: MarkdownOutline, found: MarkdownBlock[]): OutlineItem[] {
	const items = candidates(markdown, found).filter((item) => {
		if (item.kind === 'directive') {
			return markdown.directives[item.index]?.kind !== 'inline';
		}

		if (item.kind === 'block' && found[item.index]?.kind === 'paragraph') {
			return !markdown.images.some((image) => image.start === item.start && source.slice(image.end, item.end).trim() === '');
		}

		return true;
	}).sort((a, b) => a.start - b.start || b.end - a.end || (b.rank - a.rank) || (a.kind === 'directive' ? -1 : (b.kind === 'directive' ? 1 : 0)));

	const open: { end: number; index: number }[] = [];

	return items.map((item, index) => {
		while (open.length > 0 && item.start >= (open.at(-1)?.end ?? 0)) {
			open.pop();
		}

		const entry: OutlineItem = { kind: item.kind, index: item.index, start: item.start, end: item.end, depth: open.length, parent: open.at(-1)?.index ?? -1 };

		if (holdsContent(markdown, found, item)) {
			open.push({ end: item.end, index });
		}

		return entry;
	});
}

/**
 * Where an element is: its outline item and every one it's inside,
 * outermost first. An element the outline leaves out (an inline
 * directive) is inside the smallest listed element holding it.
 */
export function pathTo(items: OutlineItem[], ref: ElementRef | null, span?: { start: number; end: number }): OutlineItem[] {
	if (ref === null) {
		return [];
	}

	let at = items.findIndex((item) => sameElement(item, ref));
	const path: OutlineItem[] = [];

	if (at === -1 && span !== undefined) {
		let size = Infinity;

		items.forEach((item, index) => {
			if (item.start <= span.start && span.end <= item.end && item.end - item.start <= size) {
				at   = index;
				size = item.end - item.start;
			}
		});
	}

	while (at !== -1) {
		const item = items[at] as OutlineItem;

		path.unshift(item);
		at = item.parent;
	}

	return path;
}

/**
 * The elements directly inside one: one level, never the subtree.
 */
export function childrenOf(items: OutlineItem[], ref: ElementRef): OutlineItem[] {
	const at = items.findIndex((item) => sameElement(item, ref));

	return at === -1 ? [] : items.filter((item) => item.parent === at);
}

/**
 * An element's name: a component's label, "Image", or a block's kind (a
 * heading with its level).
 */
export function elementName(markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef, componentLabel: (name: string) => string): string {
	if (ref.kind === 'directive') {
		return componentLabel(markdown.directives[ref.index]?.name ?? '');
	}

	if (ref.kind === 'image') {
		return 'Image';
	}

	const block = found[ref.index];

	if (block === undefined) {
		return '';
	}

	return block.kind === 'heading' ? `Heading ${headingLevel(markdown, block)}` : BLOCK_KINDS[block.kind].label;
}

/**
 * One line of what's in an element, for the outline: the words of a
 * heading, paragraph, quote, or item; a code block's language and
 * length; a table's columns; a list's type and items; a component's
 * label or title; an image's caption, alt text, or file. A divider says
 * nothing.
 */
export function excerpt(source: string, markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef): string {
	if (ref.kind === 'directive') {
		const item = markdown.directives[ref.index];

		if (item === undefined) {
			return '';
		}

		const values = attributesOf(source, item);

		return directiveHead(source, item).label?.text || values.title || values.caption || values.src || values.url || values.name || values.type || '';
	}

	if (ref.kind === 'image') {
		const item = markdown.images[ref.index];

		return item === undefined ? '' : (unescaped(item.title ?? '') || unescaped(item.alt) || item.src.split('/').pop() || '');
	}

	const block = found[ref.index];

	if (block === undefined) {
		return '';
	}

	const lines = markdown.lines.slice(block.first, block.last + 1).map((line) => line.text);

	switch (block.kind) {
		case 'rule':
			return '';
		case 'code': {
			const language = (lines[0] ?? '').replace(/^\s*(?:`{3,}|~{3,})/, '').replace(/\{[^}]*\}\s*$/, '').trim();
			const count    = Math.max(0, lines.length - 2);

			return `${language || 'plain'} · ${plural(count, 'line')}`;
		}
		case 'table':
			return plural((lines[0] ?? '').split('|').filter((cell) => cell.trim() !== '').length, 'column');
		case 'list':
			return `${LIST_STYLES[listStyle(markdown, found, block)].label} · ${plural(listItems(found, block).length, 'item')}`;
		case 'definitions': {
			const inside = found.filter((item) => item.first >= block.first && item.last <= block.last);

			return `${plural(inside.filter((item) => item.kind === 'term').length, 'term')} · ${plural(inside.filter((item) => item.kind === 'definition').length, 'definition')}`;
		}
	}

	return (lines[0] ?? '')
		.replace(/[ \t]+\{[^}]*\}\s*$/, '')
		.replace(/^\s*(?:#{1,6}\s+|(?:>\s?)+|:(?!:)\s+|[-*+]\s+(?:\[[ xX]\]\s*)?|\d+[.)]\s+)/, '')
		.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
		.replace(/[*_`~]/g, '')
		.trim();
}
