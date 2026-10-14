/**
 * The elements a body is made of (admin.md §8, Every element is an
 * object): its container and leaf blocks, its images, and its Markdown blocks
 * of Markdown (`markdown.ts`), as one outline in source order, each with
 * the element it's inside. The outline drives the drawer's Outline, a
 * panel's Content group, and the footer's breadcrumb; `elementAt()` says
 * which element the caret is in.
 */

import { MARKDOWN_BLOCK_KINDS, LIST_STYLES } from './markdown-blocks';
import {
	attributesOf,
	blockHead,
	headingLevel,
	listItems,
	listStyle,
	swapped,
	unescaped,
	type Change,
	type MarkdownBlock,
	type MarkdownOutline
} from './markdown';
import { t } from './messages';

export interface ElementRef {
	kind: 'block' | 'image' | 'markdown';
	// Its index among the outline's blocks or images, or the Markdown blocks.
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
 * has a Content group: a container block, a list, a list item, or a
 * definition list.
 */
export function holdsContent(markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef): boolean {
	if (ref.kind === 'block') {
		return markdown.blocks[ref.index]?.kind === 'container';
	}

	const kind = ref.kind === 'markdown' ? found[ref.index]?.kind : undefined;

	return kind === 'list' || kind === 'item' || kind === 'definitions';
}

function candidates(markdown: MarkdownOutline, found: MarkdownBlock[]): Candidate[] {
	return [
		...markdown.blocks.map((item, index) => ({ kind: 'block' as const, index, start: item.start, end: item.end, rank: 0 })),
		...markdown.images.map((item, index) => ({ kind: 'image' as const, index, start: item.start, end: item.end, rank: 0 })),
		...found.map((item, index) => ({ kind: 'markdown' as const, index, start: item.start, end: item.end, rank: holdsContent(markdown, found, { kind: 'markdown', index }) ? 2 : 1 }))
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

	const closed = markdown.blocks.filter((item) => item.kind === 'container' && item.end < offset);

	for (const item of all) {
		const inline = item.kind === 'image' || (item.kind === 'block' && markdown.blocks[item.index]?.kind === 'inline');
		const buried = closed.some((container) => !(item.kind === 'block' && markdown.blocks[item.index] === container) && container.start <= item.start && item.end <= container.end);

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
 * blocks, images, and Markdown blocks (inline blocks are inside a
 * sentence, so they aren't listed). A line that's nothing but an image is
 * listed as the image, not a paragraph, and so is a paragraph of such
 * lines (a gallery's images, one to a line). Depth is containment: anything
 * starting before a container, list, list item, or definition list ends
 * is inside it.
 */
export function outlineItems(source: string, markdown: MarkdownOutline, found: MarkdownBlock[]): OutlineItem[] {
	const items = candidates(markdown, found).filter((item) => {
		if (item.kind === 'block') {
			return markdown.blocks[item.index]?.kind !== 'inline';
		}

		const markdownBlock = item.kind === 'markdown' ? found[item.index] : undefined;

		if (markdownBlock?.kind === 'paragraph') {
			return !markdown.lines.slice(markdownBlock.first, markdownBlock.last + 1).every((line) => imageLine(markdown, line.start, line.start + line.text.length));
		}

		return true;
	}).sort((a, b) => a.start - b.start || b.end - a.end || (b.rank - a.rank) || (a.kind === 'block' ? -1 : (b.kind === 'block' ? 1 : 0)));

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
 * Whether the text from `start` to `end` (a line) is nothing but one
 * image and the spaces around it.
 */
export function imageLine(markdown: MarkdownOutline, start: number, end: number): boolean {
	const line = markdown.lines.find((item) => item.start === start);

	return line !== undefined && markdown.images.some((image) => image.start >= start && image.end <= end
		&& line.text.slice(0, image.start - start).trim() === '' && line.text.slice(image.end - start).trim() === '');
}

/**
 * Where an element is: its outline item and every one it's inside,
 * outermost first. An element the outline leaves out (an inline
 * block) is inside the smallest listed element holding it.
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
 * An element's name: a block's label, "Image", or a Markdown block's kind (a
 * heading with its level).
 */
export function elementName(markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef, blockLabel: (name: string) => string): string {
	if (ref.kind === 'block') {
		return blockLabel(markdown.blocks[ref.index]?.name ?? '');
	}

	if (ref.kind === 'image') {
		return t('elements.image', 'Image');
	}

	const markdownBlock = found[ref.index];

	if (markdownBlock === undefined) {
		return '';
	}

	return markdownBlock.kind === 'heading' ? t('elements.heading', 'Heading {level}', { level: headingLevel(markdown, markdownBlock) }) : MARKDOWN_BLOCK_KINDS[markdownBlock.kind].label;
}

/**
 * One line of what's in an element, for the outline: the words of a
 * heading, paragraph, quote, or item; a code block's language and
 * length; a table's columns; a list's type and items; a block's
 * label or title; an image's caption, alt text, or file. A divider says
 * nothing.
 */
export function excerpt(source: string, markdown: MarkdownOutline, found: MarkdownBlock[], ref: ElementRef): string {
	if (ref.kind === 'block') {
		const item = markdown.blocks[ref.index];

		if (item === undefined) {
			return '';
		}

		const values = attributesOf(source, item);

		return blockHead(source, item).label?.text || values.title || values.caption || values.src || values.url || values.name || values.type || '';
	}

	if (ref.kind === 'image') {
		const item = markdown.images[ref.index];

		return item === undefined ? '' : (unescaped(item.title ?? '') || unescaped(item.alt) || item.src.split('/').pop() || '');
	}

	const markdownBlock = found[ref.index];

	if (markdownBlock === undefined) {
		return '';
	}

	const lines = markdown.lines.slice(markdownBlock.first, markdownBlock.last + 1).map((line) => line.text);

	switch (markdownBlock.kind) {
		case 'rule':
			return '';
		case 'code': {
			const language = (lines[0] ?? '').replace(/^\s*(?:`{3,}|~{3,})/, '').replace(/\{[^}]*\}\s*$/, '').trim();
			const count    = Math.max(0, lines.length - 2);

			return t('elements.code_excerpt', '{language} · {count, plural, one {# line} other {# lines}}', { language: language || t('elements.plain', 'plain'), count });
		}
		case 'table':
			return t('elements.columns', '{count, plural, one {# column} other {# columns}}', { count: (lines[0] ?? '').split('|').filter((cell) => cell.trim() !== '').length });
		case 'list':
			return t('elements.list_excerpt', '{style} · {count, plural, one {# item} other {# items}}', { style: LIST_STYLES[listStyle(markdown, found, markdownBlock)].label, count: listItems(found, markdownBlock).length });
		case 'definitions': {
			const inside = found.filter((item) => item.first >= markdownBlock.first && item.last <= markdownBlock.last);

			return t('elements.definitions_excerpt', '{terms, plural, one {# term} other {# terms}} · {definitions, plural, one {# definition} other {# definitions}}', { terms: inside.filter((item) => item.kind === 'term').length, definitions: inside.filter((item) => item.kind === 'definition').length });
		}
	}

	return (lines[0] ?? '')
		.replace(/[ \t]+\{[^}]*\}\s*$/, '')
		.replace(/^\s*(?:#{1,6}\s+|(?:>\s?)+|:(?!:)\s+|[-*+]\s+(?:\[[ xX]\]\s*)?|\d+[.)]\s+)/, '')
		.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
		.replace(/[*_`~]/g, '')
		.trim();
}

/**
 * The runs an element moves among (admin.md §8, Reordering; D-314): the
 * whole lines of each element sharing its parent (the top level, a
 * container's body, a list's items), in order. Elements sharing a line (a
 * paragraph and an image in it) are one run.
 */
export function siblingRuns(source: string, items: OutlineItem[], parent: number): { start: number; end: number }[] {
	const runs: { start: number; end: number }[] = [];

	for (const item of items) {
		if (item.parent !== parent) {
			continue;
		}

		const start = source.lastIndexOf('\n', item.start - 1) + 1;
		const close = source.indexOf('\n', item.end);
		const end   = close === -1 ? source.length : close;
		const last  = runs.at(-1);

		if (last !== undefined && start <= last.end) {
			last.end = Math.max(last.end, end);
		} else {
			runs.push({ start, end });
		}
	}

	return runs;
}

/**
 * Which run holds an element, or -1.
 */
export function runIndex(runs: { start: number; end: number }[], item: OutlineItem | undefined): number {
	return item === undefined ? -1 : runs.findIndex((run) => run.start <= item.start && item.start <= run.end);
}

/**
 * Moves the run at `index` past the one above or below it, carrying the
 * selection with it; `null` at the edge.
 */
export function movedElement(source: string, runs: { start: number; end: number }[], index: number, up: boolean, keep: { from: number; to: number }): Change | null {
	const other = runs[up ? index - 1 : index + 1];
	const run   = runs[index];

	if (run === undefined || other === undefined) {
		return null;
	}

	return up ? swapped(source, other, run, keep) : swapped(source, run, other, keep);
}
