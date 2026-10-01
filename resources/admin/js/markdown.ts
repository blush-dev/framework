/**
 * Reads a Markdown body the way the editor shows it (D-241): which lines
 * are headings, code, or component directives, where each directive
 * starts and ends, and the body as highlighted HTML for the editor's
 * source view (D-253, D-265). The words are the point: every syntax
 * character is muted and what it wraps keeps full ink. Headings, strong
 * and emphasized text, quotes, lists and tasks, rules, tables, code,
 * links and images, footnotes, and attribute blocks are each marked as
 * they read; only valid syntax lights up, so the highlighting doubles as
 * a check. Directives follow the server's parser (D-026,
 * `Markdown\CommonMark\Directive`):
 *
 * - a container opens with a line of `:::name[label]{attributes}` (three
 *   or more colons) and closes with a line of at least as many colons;
 *   a closing line closes the innermost open container it's long enough
 *   for (D-320), and anything inside that, so nested containers can all
 *   use `:::`;
 * - a leaf is a line of `::name[label]{attributes}`;
 * - an inline directive is `:name[label]{attributes}`, not straight after
 *   a letter, digit, underscore, or colon.
 *
 * Nothing in fenced code is a directive. This isn't a Markdown parser:
 * it's close enough to show structure while typing, and the site's own
 * rendering has the last word.
 *
 * Images and blocks are objects the editor's settings edit too (D-268):
 * an image's alternative text, address, caption (its quoted title), and
 * the attributes right after it (`{.stretch-wide}`); and every block
 * (heading, paragraph, list item, quote, code block, table, rule), with
 * the attributes the site reads for it: at the end of a heading's,
 * paragraph's, or list item's last line, or on a line of their own just
 * above any block.
 */

export type DirectiveKind = 'container' | 'leaf' | 'inline';

export interface Directive {
	kind: DirectiveKind;
	name: string;
	// Offsets in the body: from the opening colons to the end of the
	// closing line (a container), the line (a leaf), or the directive.
	start: number;
	end: number;
	// Whether a container has its closing line; one without runs to the
	// end of the body.
	closed?: boolean;
}

type LineKind = 'text' | 'heading' | 'fence' | 'code' | 'open' | 'close' | 'leaf';

type TokenKind = 'escape' | 'code' | 'directive' | 'link' | 'footnote' | 'autolink' | 'strong' | 'em' | 'strike' | 'attributes';

// The kinds in the order `INLINE` names their groups.
const KINDS = ['escape', 'code', 'directive', 'link', 'footnote', 'autolink', 'strong', 'em', 'strike', 'attributes'] as const;

interface Token {
	kind: TokenKind;
	// Offsets in the line.
	start: number;
	end: number;
	// Where the text between the marks is (strong, emphasis, struck
	// text, and a link's label), and what's in it.
	inner?: { start: number; end: number; tokens: Token[] };
	directive?: number;
}

type MarkKind = 'heading' | 'quote' | 'list' | 'rule';

interface Line {
	kind: LineKind;
	start: number;
	text: string;
	// The directive an `open`, `close`, or `leaf` line belongs to.
	directive?: number;
	// The marks a line of text starts with (`> `, `## `, `- `), in order,
	// each to where it ends in the line.
	marks: { kind: MarkKind; end: number }[];
	tokens: Token[];
}

export interface MarkdownOutline {
	lines: Line[];
	directives: Directive[];
	images: MarkdownImage[];
}

/**
 * An image, `![alt](src "title"){attributes}`, as written: its text's
 * escapes are kept, and the address is without the `<…>` that wraps one
 * with spaces.
 */
export interface MarkdownImage {
	// From the `!` to the `)`, or to the `}` of attributes right after it.
	start: number;
	end: number;
	alt: string;
	src: string;
	// The quoted title, which the site shows as the caption, or `null`.
	title: string | null;
	// The attributes' body, without the braces, and where it is.
	attributes: { start: number; end: number; text: string } | null;
}

const NAME = '[A-Za-z][A-Za-z0-9_-]*(?:\\/[A-Za-z][A-Za-z0-9_-]*)?';
const REST = '(?:\\[[^\\]\\n]*\\])?(?:\\{[^}\\n]*\\})?';

const FENCE     = /^ {0,3}(`{3,}|~{3,})/;
const OPEN      = new RegExp(`^ {0,3}(:{3,})\\s*(${NAME})${REST}\\s*$`);
const CLOSE     = /^ {0,3}(:{3,})\s*$/;
const LEAF      = new RegExp(`^ {0,3}::(${NAME})${REST}\\s*$`);
const HEADING   = /^ {0,3}#{1,6}(?:\s|$)/;
const QUOTE     = /^ {0,3}> ?/;
const RULE      = /^ {0,3}([-*_])(?: *\1){2,} *$/;
const HEADS     = /^ {0,3}#{1,6}(?: +|$)/;
const ITEM      = /^ *(?:[-*+]|\d{1,9}[.)])(?: +\[[ xX]\])?(?: +|$)/;
const TABLE     = /^ {0,3}\|/;
const DELIMITER = /^ {0,3}\|?(?: *:?-+:? *\|)+ *(?::?-+:? *)?$/;

// An attribute block in prose, `{.class #id key=value}`: only classes,
// ids, and keys with values count, so a brace in a sentence stays text.
const PART       = '(?:[.#][A-Za-z0-9_-]+|[A-Za-z_:][A-Za-z0-9_:.-]*[ \\t]*=[ \\t]*(?:"[^"\\n]*"|\'[^\'\\n]*\'|[^\\s{}"\'=]+))';
const ATTRIBUTES = `\\{:?[ \\t]*${PART}(?:[ \\t]+${PART})*[ \\t]*\\}`;

// Inline marks, earliest first and, at one place, in this order. Strong,
// emphasis, and struck text need something that isn't a space just
// inside their marks, and underscores don't count inside a word, much as
// CommonMark reads them.
const WORD   = '[\\p{L}\\p{N}_]';
const INLINE = new RegExp([
	'(?<escape>\\\\[!-/:-@[-`{-~])',
	'(?<code>(?<ticks>`+).+?\\k<ticks>(?!`))',
	`(?<directive>(?<![\\p{L}\\p{N}_:]):(?<name>${NAME})\\[[^\\]\\n]*\\](?:\\{[^}\\n]*\\})?)`,
	'(?<link>!?\\[(?<label>(?:\\\\[^\\n]|[^\\]\\\\\\n])*)\\]\\([^)\\n]*\\))',
	'(?<footnote>\\[\\^[^\\]\\s]+\\])',
	'(?<autolink><(?:https?:\\/\\/|mailto:)[^>\\s]+>)',
	`(?<strong>\\*\\*(?!\\s)(?:.*?\\S)?\\*\\*(?!\\*)|(?<!${WORD})__(?!\\s)(?:.*?\\S)?__(?!${WORD}))`,
	`(?<em>\\*(?![\\s*])(?:.*?[^\\s*])?\\*(?!\\*)|(?<!${WORD})_(?![\\s_])(?:.*?[^\\s_])?_(?!${WORD}))`,
	'(?<strike>~~(?!\\s)(?:.*?\\S)?~~)',
	`(?<attributes>${ATTRIBUTES})`
].join('|'), 'gu');

/**
 * Finds the body's lines and directives.
 */
export function outline(source: string): MarkdownOutline {
	const lines: Line[] = [];
	const directives: Directive[] = [];
	const images: MarkdownImage[] = [];
	const open: { index: number; fence: number }[] = [];

	let fence: string | null = null;
	let start = 0;

	for (const text of source.split('\n')) {
		const end  = start + text.length;
		const line: Line = { kind: 'text', start, text, marks: [], tokens: [] };
		let match: RegExpMatchArray | null;

		if (fence !== null) {
			const closing = FENCE.exec(text);

			if (closing?.[1] !== undefined && closing[1][0] === fence[0] && closing[1].length >= fence.length && text.trim() === closing[1]) {
				fence = null;
				line.kind = 'fence';
			} else {
				line.kind = 'code';
			}
		} else if ((match = FENCE.exec(text)) !== null) {
			fence = match[1] ?? '```';
			line.kind = 'fence';
		} else if ((match = OPEN.exec(text)) !== null) {
			line.kind      = 'open';
			line.directive = directives.push({ kind: 'container', name: match[2] ?? '', start: start + text.indexOf(':'), end: source.length }) - 1;
			open.push({ index: line.directive, fence: match[1]?.length ?? 3 });
		} else if ((match = CLOSE.exec(text)) !== null && closes(open, match[1]?.length ?? 0) !== -1) {
			const closed = open.splice(closes(open, match[1]?.length ?? 0));

			for (const item of closed) {
				const directive = directives[item.index];

				if (directive !== undefined) {
					directive.end    = end;
					directive.closed = true;
				}
			}

			line.kind      = 'close';
			line.directive = closed[0]?.index;
		} else if ((match = LEAF.exec(text)) !== null) {
			line.kind      = 'leaf';
			line.directive = directives.push({ kind: 'leaf', name: match[1] ?? '', start: start + text.indexOf(':'), end }) - 1;
		} else {
			const from = (line.marks = marks(text)).at(-1)?.end ?? 0;

			line.kind   = HEADING.test(text) ? 'heading' : 'text';
			line.tokens = line.marks.at(-1)?.kind === 'rule' ? [] : inline(text.slice(from), start + from, directives, from);

			collectImages(line, line.tokens, images);
		}

		lines.push(line);
		start = end + 1;
	}

	return { lines, directives, images };
}

// An image's target: the address (bare, or in `<…>`), then any quoted
// title.
const IMAGE_TARGET = /^\s*(<[^>\n]*>|\S*)(?:\s+(["'])(.*)\2)?\s*$/;

/**
 * Finds the images among a line's tokens (and inside links' labels, for
 * a linked image), each with the attributes that follow it directly.
 */
function collectImages(line: Line, tokens: Token[], images: MarkdownImage[]): void {
	tokens.forEach((token, index) => {
		if (token.kind !== 'link' || token.inner === undefined) {
			return;
		}

		if (line.text[token.start] !== '!') {
			collectImages(line, token.inner.tokens, images);

			return;
		}

		const target = IMAGE_TARGET.exec(line.text.slice(token.inner.end + 2, token.end - 1));
		const next   = tokens[index + 1];
		const after  = next?.kind === 'attributes' && next.start === token.end ? next : undefined;
		const src    = target?.[1] ?? '';

		images.push({
			start: line.start + token.start,
			end: line.start + (after?.end ?? token.end),
			alt: line.text.slice(token.inner.start, token.inner.end),
			src: src.startsWith('<') ? src.slice(1, -1) : src,
			title: target?.[3] ?? null,
			attributes: after === undefined ? null : { start: line.start + after.start + 1, end: line.start + after.end - 1, text: line.text.slice(after.start + 1, after.end - 1) }
		});
	});
}

/**
 * Which open container a closing line of `length` colons closes (the
 * innermost it's long enough for; D-320), or -1.
 */
function closes(open: { fence: number }[], length: number): number {
	for (let index = open.length - 1; index >= 0; index--) {
		if ((open[index]?.fence ?? Infinity) <= length) {
			return index;
		}
	}

	return -1;
}

/**
 * Finds the marks a line of text starts with: quotes, then a heading's
 * hashes, a list item's marker, or a whole line that's a rule.
 */
function marks(text: string): Line['marks'] {
	const found: Line['marks'] = [];
	let at = 0;
	let match: RegExpExecArray | null;

	while ((match = QUOTE.exec(text.slice(at))) !== null) {
		at += match[0].length;
		found.push({ kind: 'quote', end: at });
	}

	const rest = text.slice(at);

	if (RULE.test(rest)) {
		found.push({ kind: 'rule', end: text.length });
	} else if ((match = HEADS.exec(rest) ?? ITEM.exec(rest)) !== null) {
		found.push({ kind: match[0].trimStart().startsWith('#') ? 'heading' : 'list', end: at + match[0].length });
	}

	return found;
}

/**
 * Finds the inline code, directives, links, strong, emphasized, and
 * struck text in part of a line: `text`, which starts at `start` in the
 * body and `offset` in its line. What's inside marks is read the same
 * way, so marks nest.
 */
function inline(text: string, start: number, directives: Directive[], offset = 0): Token[] {
	const tokens: Token[] = [];

	for (const match of text.matchAll(INLINE)) {
		const groups = match.groups ?? {};
		const kind   = KINDS.find((name) => groups[name] !== undefined) ?? 'code';
		const source = match[0];
		const token: Token = { kind, start: offset + match.index, end: offset + match.index + source.length };

		// Where the text inside the marks is, from and to, in the match.
		const inside = (from: number, to: number): Token['inner'] => ({
			start: token.start + from,
			end: token.start + to,
			tokens: inline(source.slice(from, to), start + match.index + from, directives, token.start + from)
		});

		if (kind === 'directive') {
			token.directive = directives.push({ kind: 'inline', name: groups.name ?? '', start: start + match.index, end: start + match.index + source.length }) - 1;
		} else if (kind === 'link') {
			const from = source.startsWith('!') ? 2 : 1;

			token.inner = inside(from, from + (groups.label?.length ?? 0));
		} else if (kind === 'strong' || kind === 'em' || kind === 'strike') {
			const size = kind === 'em' ? 1 : 2;

			token.inner = inside(size, source.length - size);
		}

		tokens.push(token);
	}

	return tokens;
}

/**
 * The innermost directive at an offset in the body (a caret just after
 * one counts as in it), or -1.
 */
export function directiveAt(directives: Directive[], offset: number): number {
	let found  = -1;
	let length = Infinity;

	directives.forEach((directive, index) => {
		if (directive.start <= offset && offset <= directive.end && directive.end - directive.start < length) {
			found  = index;
			length = directive.end - directive.start;
		}
	});

	return found;
}

/**
 * Counts the words a reader would see: headings and paragraphs, without
 * code, directive lines, link addresses, or attributes.
 */
export function wordCount(markdown: MarkdownOutline): number {
	let count = 0;

	for (const line of markdown.lines) {
		if (line.kind !== 'text' && line.kind !== 'heading') {
			continue;
		}

		const prose = line.text
			.replace(/`+[^`]*`+/g, ' ')
			.replace(/\]\([^)]*\)/g, ' ')
			.replace(/\{[^}]*\}/g, ' ')
			.replace(new RegExp(`:${NAME}\\[`, 'g'), ' ');

		count += prose.match(/[\p{L}\p{N}][\p{L}\p{N}'’_-]*/gu)?.length ?? 0;
	}

	return count;
}

// Each line's highlighted HTML, by what it depends on (`highlight()`).
let lineCache = new Map<string, string>();

const ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };

function escape(text: string): string {
	return text.replace(/[&<>"]/g, (character) => ESCAPES[character] ?? character);
}

/**
 * Marks, muted: every syntax character is.
 */
function markHtml(text: string, kind = ''): string {
	return text === '' ? '' : `<span class="md-mark${kind === '' ? '' : ` md-mark--${kind}`}">${escape(text)}</span>`;
}

// A class or id, or a key with its separator and value, in a block of
// attributes.
const ATTRIBUTE_PARTS = /([.#][A-Za-z0-9_:-]+)|([A-Za-z_:][A-Za-z0-9_:.-]*)(?:(\s*=\s*)("[^"]*"|'[^']*'|[^\s"'=}]+))?/g;

/**
 * A block of attributes, braces included, as the gray chip: braces and
 * punctuation muted, keys and values quieter, and the class and id names,
 * which are what a document is scanned for, in full ink.
 */
function attributesHtml(text: string): string {
	const inner = text.slice(1, -1);
	let html = '';
	let at   = 0;

	for (const match of inner.matchAll(ATTRIBUTE_PARTS)) {
		html += escape(inner.slice(at, match.index));

		if (match[1] !== undefined) {
			html += `<span class="md-attr__name">${escape(match[1])}</span>`;
		} else {
			html += `<span class="md-attr__key">${escape(match[2] ?? '')}</span>`;

			if (match[3] !== undefined && match[4] !== undefined) {
				html += `${escape(match[3])}<span class="md-attr__value">${escape(match[4])}</span>`;
			}
		}

		at = match.index + match[0].length;
	}

	return `<span class="md-attr">${markHtml('{')}${html}${escape(inner.slice(at))}${markHtml('}')}</span>`;
}

const DIRECTIVE_HEAD = new RegExp(`^(:+)(${NAME})(?:(\\[)([^\\]\\n]*)(\\]))?`);

/**
 * A directive: its colons muted, its name in the accent, its label in
 * full ink, and its attributes as the gray chip. It isn't boxed: the box
 * is kept for the one the caret is in (`current`), so a box always means
 * "you are here".
 */
function directiveHtml(text: string, current: boolean): string {
	const match = DIRECTIVE_HEAD.exec(text);
	// A container's closing line is all syntax.
	let html    = /^:+\s*$/.test(text) ? markHtml(text) : escape(text);

	if (match !== null) {
		const rest = text.slice(match[0].length);

		html = markHtml(match[1] ?? '') + `<span class="md-directive__name">${escape(match[2] ?? '')}</span>`;

		if (match[3] !== undefined) {
			html += markHtml('[') + `<span class="md-directive__label">${escape(match[4] ?? '')}</span>` + markHtml(']');
		}

		html += rest.startsWith('{') && rest.trimEnd().endsWith('}')
			? attributesHtml(rest.trimEnd()) + escape(rest.slice(rest.trimEnd().length))
			: escape(rest);
	}

	return `<span class="md-directive${current ? ' is-current' : ''}">${html}</span>`;
}

/**
 * Text between tokens: escaped, with a table row's pipes muted.
 */
function plainHtml(text: string, pipes: boolean): string {
	return pipes ? text.split('|').map(escape).join(markHtml('|')) : escape(text);
}

/**
 * What's selected, for the highlight: a directive's index and an image's
 * start in the body (-1 for none), and the start of the line being drawn.
 */
interface Current {
	directive: number;
	image: number;
	line: number;
}

/**
 * Part of a line, `from` to `to`, with its tokens. The image selected is
 * boxed with the attributes right after it.
 */
function inlineHtml(text: string, tokens: Token[], from: number, to: number, current: Current, pipes = false): string {
	let html = '';
	let at   = from;
	let box  = -1;

	tokens.forEach((token, index) => {
		html += plainHtml(text.slice(at, token.start), pipes);

		if (token.kind === 'link' && text[token.start] === '!' && current.line + token.start === current.image) {
			const next = tokens[index + 1];

			box   = next?.kind === 'attributes' && next.start === token.end ? next.end : token.end;
			html += '<span class="md-selected">';
		}

		html += tokenHtml(text, token, current);
		at    = token.end;

		if (token.end === box) {
			html += '</span>';
			box   = -1;
		}
	});

	return html + plainHtml(text.slice(at, to), pipes);
}

/**
 * A token: its marks muted around what's inside them. A link's label is
 * read in the sentence and takes the accent; its address steps back. An
 * image is marked as a link, since it is one (D-268): its alternative
 * text takes the accent too.
 */
function tokenHtml(text: string, token: Token, current: Current): string {
	const source = text.slice(token.start, token.end);

	switch (token.kind) {
		case 'directive':
			return directiveHtml(source, token.directive === current.directive);
		case 'code': {
			const ticks = /^`+/.exec(source)?.[0].length ?? 1;

			return `<span class="md-code">${markHtml(source.slice(0, ticks))}${escape(source.slice(ticks, -ticks))}${markHtml(source.slice(-ticks))}</span>`;
		}
		case 'attributes':
			return attributesHtml(source);
		case 'footnote':
			return `<span class="md-footnote">${escape(source)}</span>`;
		case 'autolink':
			return markHtml('<') + `<span class="md-link__text">${escape(source.slice(1, -1))}</span>` + markHtml('>');
		case 'escape':
			return markHtml(source.slice(0, 1)) + escape(source.slice(1));
	}

	const { inner } = token;

	if (inner === undefined) {
		return escape(source);
	}

	if (token.kind === 'link') {
		const image = source.startsWith('!');

		return `<span class="md-${image ? 'image' : 'link'}">${markHtml(text.slice(token.start, inner.start))}`
			+ `<span class="md-${image ? 'image' : 'link'}__text">${inlineHtml(text, inner.tokens, inner.start, inner.end, current)}</span>`
			+ markHtml('](') + targetHtml(text.slice(inner.end + 2, token.end - 1), image) + markHtml(')')
			+ '</span>';
	}

	const before = markHtml(text.slice(token.start, inner.start));
	const after  = markHtml(text.slice(inner.end, token.end));

	return `<span class="md-${token.kind}">${before}<span class="md-${token.kind}__text">${inlineHtml(text, inner.tokens, inner.start, inner.end, current)}</span>${after}</span>`;
}

/**
 * A link's or image's target: the address muted, and an image's quoted
 * title, which the site shows as its caption (D-267), read as words.
 */
function targetHtml(target: string, image: boolean): string {
	const parts = image ? /^(\S+|<[^>\n]*>)(\s+)(["'])(.*)\3(\s*)$/.exec(target) : null;

	if (parts === null) {
		return `<span class="md-link__url">${escape(target)}</span>`;
	}

	return `<span class="md-link__url">${escape(parts[1] ?? '')}</span>${escape(parts[2] ?? '')}`
		+ markHtml(parts[3] ?? '') + `<span class="md-image__caption">${escape(parts[4] ?? '')}</span>` + markHtml(parts[3] ?? '') + escape(parts[5] ?? '');
}

const ITEM_PARTS = /^( *)([-*+]|\d{1,9}[.)])( +)?(\[[ xX]\])?( *)$/;

/**
 * A line's leading mark: a heading's hashes, a list marker (and a task's
 * box), a rule, or a quote's `>`.
 */
function leadHtml(text: string, kind: MarkKind): string {
	if (kind === 'rule') {
		return `<span class="md-rule">${escape(text)}</span>`;
	}

	if (kind === 'list') {
		const parts = ITEM_PARTS.exec(text);

		if (parts !== null) {
			const box = parts[4];

			return escape(parts[1] ?? '') + `<span class="md-bullet">${escape(parts[2] ?? '')}</span>` + escape(parts[3] ?? '')
				+ (box === undefined ? '' : `<span class="md-task${box === '[ ]' ? '' : ' md-task--done'}">${escape(box)}</span>`)
				+ escape(parts[5] ?? '');
		}
	}

	return markHtml(text);
}

// What the block scan knows about a line that the line can't say for
// itself: a table's header row is a row, and a term is a line of prose.
type LineRole = 'head' | 'term' | 'definition';

function lineRoles(found: MarkdownBlock[]): Map<number, LineRole> {
	const roles = new Map<number, LineRole>();

	for (const block of found) {
		if (block.kind === 'table') {
			roles.set(block.first, 'head');
		} else if (block.kind === 'term' || block.kind === 'definition') {
			roles.set(block.first, block.kind);
		}
	}

	return roles;
}

/**
 * One line of text (not code or a directive's own line), with its marks
 * and tokens.
 */
function lineHtml(line: Line, current: Current, role: LineRole | undefined): string {
	// A table's delimiter row is all syntax but its alignment colons, the
	// one part of it that says something.
	if (TABLE.test(line.text) && DELIMITER.test(line.text) && line.text.includes('-')) {
		return line.text.split(/(:)/).map((part) => part === ':' ? '<span class="md-talign">:</span>' : markHtml(part)).join('');
	}

	// A table's header row: its cells full ink and heavier, its pipes muted.
	if (role === 'head') {
		return `<span class="md-th">${inlineHtml(line.text, line.tokens, 0, line.text.length, current, true)}</span>`;
	}

	// A definition list's term, and a definition, whose colon is syntax.
	if (role === 'term') {
		return `<span class="md-dt">${inlineHtml(line.text, line.tokens, 0, line.text.length, current)}</span>`;
	}

	if (role === 'definition') {
		const marker = DEFINITION.exec(line.text)?.[0] ?? '';
		const colon  = marker.indexOf(':');

		return escape(marker.slice(0, colon)) + markHtml(':') + escape(marker.slice(colon + 1))
			+ `<span class="md-dd">${inlineHtml(line.text, line.tokens, marker.length, line.text.length, current)}</span>`;
	}

	// The line's marks, then its text: a heading or a quote wraps what
	// follows its marks.
	let html = '';
	let at   = 0;
	const wraps: string[] = [];

	for (const mark of line.marks) {
		const text = line.text.slice(at, mark.end);

		html += leadHtml(text, mark.kind);
		at    = mark.end;

		if (mark.kind === 'quote' && wraps.length === 0) {
			html += '<span class="md-quote">';
			wraps.push('</span>');
		} else if (mark.kind === 'heading') {
			// Told apart by weight, not size: the grid is fixed.
			html += `<span class="md-heading md-heading--${Math.min(3, text.trim().length)}">`;
			wraps.push('</span>');
		}
	}

	return html + inlineHtml(line.text, line.tokens, at, line.text.length, current, TABLE.test(line.text)) + wraps.join('');
}

/**
 * The body as HTML for the editor's highlighted copy, with the directive
 * at `directive` or the image at `image` (indexes, -1 for none) boxed:
 * the box means "you are here". A container is boxed on its opening and
 * closing lines only; its body is the writing, and stays untinted
 * (D-268). Every character of the source is in it, escaped, so it lines
 * up with the text area over it.
 *
 * A fenced code block is one box, fences included (admin.md §8, The
 * fenced block is a box): one block element holding its lines and the
 * line breaks between them. A block ends its own line, so the break
 * after a closing fence isn't written as well; an unclosed one runs to
 * the end. A space at the very end gives a final empty line its height,
 * as the text area gives it, except after a closed block, which already
 * ends its line.
 *
 * Each line's HTML is kept between calls, keyed on everything it depends
 * on (its kind and text, what the block scan says it is, and where in it
 * the selected directive or image is, if anywhere), so a keystroke
 * rebuilds the line it changed rather than the whole body (D-316). The
 * lines a call uses are what the next one keeps.
 */
export function highlight(markdown: MarkdownOutline, directive: number, image = -1, found: MarkdownBlock[] = blocks(markdown)): string {
	const at      = markdown.images[image]?.start ?? -1;
	const current = markdown.directives[directive];
	const roles   = lineRoles(found);
	const used    = new Map<string, string>();
	let html      = '';
	let inCode    = false;
	let joined    = true;

	// A line's HTML from the cache, or built and kept.
	const cached = (key: string, build: () => string): string => {
		let value = used.get(key) ?? lineCache.get(key);

		if (value === undefined) {
			value = build();
		}

		used.set(key, value);

		return value;
	};

	// Where in a line something selected starts, or -1.
	const within = (line: Line, offset: number): number => offset >= line.start && offset <= line.start + line.text.length ? offset - line.start : -1;

	markdown.lines.forEach((line, index) => {
		const before = index === 0 || !joined ? '' : '\n';

		joined = true;

		switch (line.kind) {
			case 'fence': {
				const fence = cached(`f\u0000${line.text}`, () => {
					const parts = /^(\s*)(`{3,}|~{3,})(.*)$/.exec(line.text);

					return parts === null ? escape(line.text) : escape(parts[1] ?? '') + markHtml(parts[2] ?? '') + (parts[3] ? `<span class="md-fence__lang">${escape(parts[3])}</span>` : '');
				});

				if (inCode) {
					html  += `${before}${fence}</span>`;
					inCode = false;
					joined = false;
				} else {
					html  += `${before}<span class="md-codeblock">${fence}`;
					inCode = true;
				}

				return;
			}
			case 'code':
				html += before + escape(line.text);

				return;
			case 'open':
			case 'leaf':
			case 'close': {
				const selected = line.directive === directive;

				html += before + cached(`d\u0000${selected ? 1 : 0}\u0000${line.text}`, () => {
					const indent = line.text.length - line.text.trimStart().length;

					return escape(line.text.slice(0, indent)) + directiveHtml(line.text.slice(indent), selected);
				});

				return;
			}
			default: {
				// The selected inline directive and image matter only on their
				// own line, by where they are in it.
				const inline = current?.kind === 'inline' ? within(line, current.start) : -1;
				const picked = within(line, at);
				const role   = roles.get(index) ?? '';

				html += before + cached(`${line.kind}\u0000${role}\u0000${inline}\u0000${picked}\u0000${line.text}`, () => lineHtml(line, { directive, image: at, line: line.start }, roles.get(index)));
			}
		}
	});

	lineCache = used;

	if (inCode) {
		return `${html} </span>`;
	}

	return joined ? `${html} ` : html;
}

/**
 * A change to the body: replace `from`…`to` with `text`.
 */
export interface Edit {
	from: number;
	to: number;
	text: string;
}

/**
 * Where a directive's head is: from its colons to the end of its
 * `{attributes}`, with its `[label]` and attributes' bodies (without the
 * brackets), if it has them.
 */
export interface DirectiveHead {
	start: number;
	end: number;
	nameEnd: number;
	label: { start: number; end: number; text: string } | null;
	attributes: { start: number; end: number; text: string } | null;
}

const HEAD = new RegExp(`^:+(${NAME})(?:\\[([^\\]\\n]*)\\])?(?:\\{([^}\\n]*)\\})?`);

/**
 * Reads a directive's head.
 */
export function directiveHead(source: string, directive: Directive): DirectiveHead {
	const text  = source.slice(directive.start, directive.kind === 'inline' ? directive.end : lineEnd(source, directive.start));
	const match = HEAD.exec(text);
	const start = directive.start;

	if (match === null) {
		return { start, end: start + text.length, nameEnd: start + text.length, label: null, attributes: null };
	}

	const colons  = match[0].length - match[0].replace(/^:+/, '').length;
	const nameEnd = start + colons + (match[1]?.length ?? 0);
	const label   = match[2] === undefined ? null : { start: nameEnd + 1, end: nameEnd + 1 + match[2].length, text: match[2] };
	const after   = label === null ? nameEnd : label.end + 1;
	const attributes = match[3] === undefined ? null : { start: after + 1, end: after + 1 + match[3].length, text: match[3] };

	return { start, end: start + match[0].length, nameEnd, label, attributes };
}

function lineEnd(source: string, from: number): number {
	const end = source.indexOf('\n', from);

	return end === -1 ? source.length : end;
}

// A directive's attributes, as the server reads them (D-026,
// `DirectiveAttributes`): `key=value`, `key="a value"`, `key='a value'`,
// `.class`, `#id`, and a bare `key` (read as `"true"`).
const ATTRIBUTE = /([.#])([A-Za-z0-9_:-]+)|([A-Za-z_:][A-Za-z0-9_:.-]*)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=]+)))?/g;

interface AttributeToken {
	name: string;
	value: string;
	start: number;
	end: number;
}

function tokens(body: string): AttributeToken[] {
	const found: AttributeToken[] = [];

	for (const match of body.matchAll(ATTRIBUTE)) {
		const start = match.index;
		const end   = start + match[0].length;

		if (match[1] === '.') {
			found.push({ name: 'class', value: match[2] ?? '', start, end });
		} else if (match[1] === '#') {
			found.push({ name: 'id', value: match[2] ?? '', start, end });
		} else if (match[3] !== undefined) {
			found.push({ name: match[3], value: match[4] ?? match[5] ?? match[6] ?? 'true', start, end });
		}
	}

	return found;
}

/**
 * A directive's attributes, by name (classes joined, as the server does).
 */
export function attributesOf(source: string, directive: Directive): Record<string, string> {
	const body   = directiveHead(source, directive).attributes?.text ?? '';
	const values: Record<string, string> = {};

	for (const token of tokens(body)) {
		values[token.name] = token.name === 'class' && values.class !== undefined ? `${values.class} ${token.value}` : token.value;
	}

	return values;
}

/**
 * An attribute as written: bare when it needs no quotes, else in double
 * quotes (single when the value has double quotes; a value with both
 * loses its double quotes, which the syntax can't hold).
 */
export function attributeText(name: string, value: string): string {
	if (/^[^\s"'=}{]+$/.test(value)) {
		return `${name}=${value}`;
	}

	if (!value.includes('"')) {
		return `${name}="${value}"`;
	}

	return value.includes("'") ? `${name}="${value.replaceAll('"', '')}"` : `${name}='${value}'`;
}

/**
 * The edit that sets one attribute (or removes it, for `null`), leaving
 * the rest of the head as the author wrote it: an attribute that's there
 * is rewritten in place, a new one is added at the end, and braces left
 * empty are removed.
 */
export function withAttribute(source: string, directive: Directive, name: string, value: string | null): Edit | null {
	const head    = directiveHead(source, directive);
	const written = value === null ? '' : attributeText(name, value);

	if (head.attributes === null) {
		if (value === null) {
			return null;
		}

		const at = head.label === null ? head.nameEnd : head.label.end + 1;

		return { from: at, to: at, text: `{${written}}` };
	}

	const body  = head.attributes.text;
	const found = tokens(body).filter((token) => token.name === name);
	let next: string;

	if (found.length === 0) {
		if (value === null) {
			return null;
		}

		next = body.trimEnd() === '' ? written : `${body.trimEnd()} ${written}`;
	} else {
		// The first is rewritten; any repeats (the last would win) go.
		next = body;

		for (const token of [...found].reverse()) {
			const first   = token === found[0];
			const replace = first && value !== null ? written : '';
			let from = token.start;
			let to   = token.end;

			if (replace === '') {
				// Take one side's spaces with it.
				while (to < next.length && next[to] === ' ') {
					to++;
				}

				if (to === next.length) {
					while (from > 0 && next[from - 1] === ' ') {
						from--;
					}
				}
			}

			next = next.slice(0, from) + replace + next.slice(to);
		}
	}

	if (next.trim() === '') {
		return { from: head.attributes.start - 1, to: head.attributes.end + 1, text: '' };
	}

	return { from: head.attributes.start, to: head.attributes.end, text: next };
}

/**
 * The edit that sets a directive's `[label]`. A label can't hold `]` or
 * a line break, so they're left out. An empty label stays as `[]` on a
 * directive that had one.
 */
export function withLabel(source: string, directive: Directive, label: string): Edit {
	const head = directiveHead(source, directive);
	const text = label.replace(/[\]\n]/g, '');

	return head.label === null
		? { from: head.nameEnd, to: head.nameEnd, text: `[${text}]` }
		: { from: head.label.start, to: head.label.end, text };
}

/**
 * The edit that removes a directive. An inline directive's label stays,
 * as text; a leaf goes with its line, and a container with everything in
 * it (D-272), each with a blank line after it, so the text around closes
 * up.
 */
export function withoutDirective(source: string, directive: Directive): Edit {
	if (directive.kind === 'inline') {
		return { from: directive.start, to: directive.end, text: directiveHead(source, directive).label?.text ?? '' };
	}

	const lineStart = source.lastIndexOf('\n', directive.start - 1) + 1;
	const end       = directive.kind === 'leaf' ? lineEnd(source, directive.start) : directive.end;
	let to          = end < source.length ? end + 1 : end;

	if (to < source.length && source[to] === '\n') {
		to++;
	}

	return { from: lineStart, to, text: '' };
}

/**
 * A Markdown image, `![alt](src)`, and where the caret goes in it: in
 * the alternative text when there's none yet, else after the image. A
 * `title` (which the site shows as the figure's caption) is written in
 * quotes. The address is wrapped in `<…>` when it has spaces or
 * parentheses.
 */
export function imageText(src: string, alt = '', title = '', attributes = ''): { text: string; caret: number } {
	const address = /[\s()<>]/.test(src) ? `<${src.replace(/[<>]/g, (character) => encodeURIComponent(character))}>` : src;
	const label   = alt.replace(/[\\[\]]/g, '\\$&').replace(/\s*\n\s*/g, ' ');
	const caption = title === '' ? '' : ` "${title.replace(/["\\]/g, '\\$&').replace(/\s*\n\s*/g, ' ')}"`;
	const text    = `![${label}](${address}${caption})` + (attributes.trim() === '' ? '' : `{${attributes.trim()}}`);

	return { text, caret: label === '' ? 2 : text.length };
}

/**
 * Text as written, without its backslash escapes: an image's alternative
 * text or title, to show in a field.
 */
export function unescaped(text: string): string {
	return text.replace(/\\([!-/:-@[-`{-~])/g, '$1');
}

/**
 * The edit that rewrites an image with some of its parts changed: `alt`
 * and `title` as they read (an empty title writes none), `src`, and the
 * attributes' body. The rest stay as they were.
 */
export function withImage(image: MarkdownImage, changes: { alt?: string; src?: string; title?: string; attributes?: string }): Edit {
	const { text } = imageText(
		changes.src ?? image.src,
		changes.alt ?? unescaped(image.alt),
		changes.title ?? unescaped(image.title ?? ''),
		changes.attributes ?? image.attributes?.text ?? ''
	);

	return { from: image.start, to: image.end, text };
}

/**
 * The edit that removes an image: alone on its line, the line goes, and
 * a blank line after it, so the text around it closes up; in a sentence,
 * just the image.
 */
export function withoutImage(source: string, image: MarkdownImage): Edit {
	const lineStart = source.lastIndexOf('\n', image.start - 1) + 1;
	const end       = lineEnd(source, image.end);

	if (source.slice(lineStart, image.start).trim() !== '' || source.slice(image.end, end).trim() !== '') {
		// One of the spaces around it goes too.
		const space = source[image.end] === ' ' && (image.start === lineStart || source[image.start - 1] === ' ') ? 1 : 0;

		return { from: image.start, to: image.end + space, text: '' };
	}

	let to = end < source.length ? end + 1 : end;

	if (to < source.length && source[to] === '\n') {
		to++;
	}

	return { from: lineStart, to, text: '' };
}

/**
 * The classes and id in the body of a block of attributes.
 */
export function attributeParts(body: string): { classes: string[]; id: string } {
	const found = tokens(body);

	return {
		classes: found.filter((token) => token.name === 'class').map((token) => token.value),
		id: found.filter((token) => token.name === 'id').at(-1)?.value ?? ''
	};
}

/**
 * Class names as typed in a field (space separated, dots optional), each
 * kept to the characters a name may have.
 */
export function classNames(value: string): string[] {
	return value.split(/\s+/).map((name) => name.replace(/^[.]+/, '').replace(/[^A-Za-z0-9_-]/g, '')).filter((name) => name !== '');
}

/**
 * A body of attributes with its classes and id replaced: classes first,
 * then the id, then everything else as it was written.
 */
export function withParts(body: string, classes: string[], id: string): string {
	const others = tokens(body).filter((token) => token.name !== 'class' && token.name !== 'id').map((token) => body.slice(token.start, token.end));
	const name   = id.replace(/^#+/, '').replace(/[^A-Za-z0-9_-]/g, '');

	return [...classes.map((item) => `.${item}`), ...(name === '' ? [] : [`#${name}`]), ...others].join(' ');
}

/**
 * The edit that sets a directive's classes and id, leaving its other
 * attributes as they were; braces left empty go.
 */
export function withDirectiveParts(source: string, directive: Directive, classes: string[], id: string): Edit | null {
	const head = directiveHead(source, directive);
	const next = withParts(head.attributes?.text ?? '', classes, id);

	if (head.attributes === null) {
		if (next === '') {
			return null;
		}

		const at = head.label === null ? head.nameEnd : head.label.end + 1;

		return { from: at, to: at, text: `{${next}}` };
	}

	return next === ''
		? { from: head.attributes.start - 1, to: head.attributes.end + 1, text: '' }
		: { from: head.attributes.start, to: head.attributes.end, text: next };
}

export type BlockKind = 'heading' | 'paragraph' | 'list' | 'item' | 'quote' | 'code' | 'table' | 'rule' | 'definitions' | 'term' | 'definition';

/**
 * A block of Markdown outside directives' own lines: a heading, a
 * paragraph, a list and each of its items (with their continuation
 * lines), a quote, a fenced code block, a table, a rule, or a definition
 * list with each of its terms and definitions.
 */
export interface MarkdownBlock {
	kind: BlockKind;
	// Its first and last lines, by index: for a list item, its own text,
	// without the lists nested under it.
	first: number;
	last: number;
	// From its attributes' own line, if it has one, to the end of its
	// last line; a list item's reaches over what's nested under it, so a
	// nested list is inside the item it was written under.
	start: number;
	end: number;
	// The attributes' body, without the braces, and where it is: on a line
	// of its own above (`own`), or at the end of the last line.
	attributes: { start: number; end: number; text: string; own: boolean } | null;
	// A list's and a list item's indent, in spaces.
	indent?: number;
}

// A line that's nothing but a block of attributes, and attributes at the
// end of a line, after a space (right after an image, they're the
// image's).
const ATTRIBUTE_LINE = new RegExp(`^ {0,3}(${ATTRIBUTES})[ \\t]*$`);
const TRAILING       = new RegExp(`[ \\t]+(${ATTRIBUTES})[ \\t]*$`);
const SETEXT         = /^ {0,3}(=+|-+)[ \t]*$/;

// A definition: one colon (two would be a leaf directive), then a space.
const DEFINITION = /^ {0,3}:(?!:)[ \t]+/;

function blank(line: Line | undefined): boolean {
	return line === undefined || line.text.trim() === '';
}

function isAttributeLine(line: Line): boolean {
	return line.kind === 'text' && line.marks.length === 0 && ATTRIBUTE_LINE.test(line.text);
}

function isDefinition(line: Line | undefined): boolean {
	return line !== undefined && line.kind === 'text' && line.marks.length === 0 && DEFINITION.test(line.text);
}

function indentOf(line: Line | undefined): number {
	return /^ */.exec(line?.text ?? '')?.[0].length ?? 0;
}

/**
 * Whether a line starts a block, so it ends a paragraph or list item.
 */
function startsBlock(line: Line, next: Line | undefined): boolean {
	return ['fence', 'open', 'close', 'leaf', 'heading'].includes(line.kind)
		|| ['quote', 'list', 'rule'].includes(line.marks[0]?.kind ?? '')
		|| isAttributeLine(line)
		|| (TABLE.test(line.text) && next !== undefined && DELIMITER.test(next.text));
}

/**
 * Where one group of terms and their definitions starting at `index`
 * ends (its last definition's line), or -1 if the lines there aren't one.
 */
function termsEnd(lines: Line[], index: number): number {
	let at = index;

	while (!blank(lines[at]) && !isDefinition(lines[at]) && !startsBlock(lines[at] as Line, lines[at + 1]) && lines[at]?.kind === 'text') {
		at++;
	}

	if (at === index || !isDefinition(lines[at])) {
		return -1;
	}

	while (isDefinition(lines[at + 1])) {
		at++;
	}

	return at;
}

/**
 * Where a definition list starting at `index` ends, or -1. Groups
 * separated by one blank line are one list, as they render.
 */
function definitionsEnd(lines: Line[], index: number): number {
	let end = termsEnd(lines, index);

	while (end !== -1 && blank(lines[end + 1]) && !blank(lines[end + 2])) {
		const next = termsEnd(lines, end + 2);

		if (next === -1) {
			break;
		}

		end = next;
	}

	return end;
}

/**
 * The body's blocks, in order: a list before its first item, a definition
 * list before its first term.
 */
export function blocks(markdown: MarkdownOutline): MarkdownBlock[] {
	const { lines } = markdown;
	const found: MarkdownBlock[] = [];
	const lineEndOf = (index: number): number => (lines[index] as Line).start + (lines[index] as Line).text.length;
	let above: Line | null = null;
	let index = 0;

	while (index < lines.length) {
		const line = lines[index] as Line;

		if (blank(line) || ['open', 'close', 'leaf', 'code'].includes(line.kind)) {
			above = null;
			index++;
			continue;
		}

		if (isAttributeLine(line) && !blank(lines[index + 1])) {
			// Above a list, the line is the list's, not its first item's.
			above = lines[index + 1]?.marks[0]?.kind === 'list' ? null : line;
			index++;
			continue;
		}

		const lead = line.marks[0]?.kind;
		let kind: BlockKind = 'paragraph';
		let last = index;
		const more = (test: (next: Line) => boolean): void => {
			while (!blank(lines[last + 1]) && test(lines[last + 1] as Line)) {
				last++;
			}
		};

		if (line.kind === 'fence') {
			kind = 'code';

			while (lines[last + 1]?.kind === 'code') {
				last++;
			}

			if (lines[last + 1]?.kind === 'fence') {
				last++;
			}
		} else if (lead === 'quote') {
			kind = 'quote';
			more((next) => next.marks[0]?.kind === 'quote' || !startsBlock(next, lines[last + 2]));
		} else if (lead === 'rule') {
			kind = 'rule';
		} else if (line.kind === 'heading') {
			kind = 'heading';
		} else if (lead === 'list') {
			kind = 'item';
			more((next) => /^\s/.test(next.text) && !startsBlock(next, lines[last + 2]));
		} else if (TABLE.test(line.text) && DELIMITER.test(lines[index + 1]?.text ?? '')) {
			kind = 'table';
			more((next) => next.text.includes('|'));
		} else if (definitionsEnd(lines, index) !== -1) {
			const end = definitionsEnd(lines, index);

			// The list's attributes are on a line above it, as a list's are
			// (D-282); a term's and a definition's at the end of its line.
			found.push({ kind: 'definitions', first: index, last: end, start: above?.start ?? line.start, end: lineEndOf(end), attributes: above === null ? null : ownAttributes(above) });

			for (let at = index; at <= end; at++) {
				if (!blank(lines[at])) {
					found.push({ kind: isDefinition(lines[at]) ? 'definition' : 'term', first: at, last: at, start: (lines[at] as Line).start, end: lineEndOf(at), attributes: trailingAttributes(lines[at] as Line) });
				}
			}

			above = null;
			index = end + 1;
			continue;
		} else {
			more((next) => SETEXT.test(next.text) || !startsBlock(next, lines[last + 2]));

			if (last > index && SETEXT.test(lines[last]?.text ?? '')) {
				kind = 'heading';
			}
		}

		const end = lines[last] as Line;
		let attributes: MarkdownBlock['attributes'] = null;

		if (above !== null) {
			attributes = ownAttributes(above);
		} else if (kind === 'heading' || kind === 'paragraph' || kind === 'item') {
			// A setext heading's attributes are on its text, not its underline.
			attributes = trailingAttributes(kind === 'heading' && last > index ? line : end);
		}

		const block: MarkdownBlock = { kind, first: index, last, start: above?.start ?? line.start, end: end.start + end.text.length, attributes };

		if (kind === 'item') {
			// The item reaches over the lines indented under it.
			let span = last;

			block.indent = indentOf(line);

			while (!blank(lines[span + 1]) && indentOf(lines[span + 1]) > block.indent && lines[span + 1]?.kind === 'text') {
				span++;
			}

			block.end = lineEndOf(span);
		}

		found.push(block);
		above = null;
		index = last + 1;
	}

	return addLists(found, lines);
}

/**
 * The attributes at the end of a line, after a space, if it has them.
 */
function trailingAttributes(holder: Line): MarkdownBlock['attributes'] {
	const match = TRAILING.exec(holder.text);

	if (match === null) {
		return null;
	}

	const brace = holder.text.length - match[0].length + match[0].indexOf('{');

	return { start: holder.start + brace + 1, end: holder.start + holder.text.lastIndexOf('}'), text: (match[1] ?? '').slice(1, -1), own: false };
}

/**
 * An attribute line's attributes.
 */
function ownAttributes(line: Line): NonNullable<MarkdownBlock['attributes']> {
	const brace = line.text.indexOf('{');
	const close = line.text.lastIndexOf('}');

	return { start: line.start + brace + 1, end: line.start + close, text: line.text.slice(brace + 1, close), own: true };
}

/**
 * Adds the lists the items make, rebuilt from their indents: a run of
 * items, with nothing but blank lines between them, is a list, and an
 * item indented further than the one before starts a list nested in it.
 * A list's attributes are on a line of their own just above it.
 */
function addLists(found: MarkdownBlock[], lines: Line[]): MarkdownBlock[] {
	const items = found.filter((block) => block.kind === 'item');
	const lists: MarkdownBlock[] = [];
	const open: { indent: number; first: number; last: number }[] = [];

	const close = (list: { indent: number; first: number; last: number }): void => {
		const above = lines[list.first - 1];
		const own   = above !== undefined && isAttributeLine(above) ? above : null;
		const last  = lines[list.last] as Line;

		lists.push({
			kind: 'list',
			first: list.first,
			last: list.last,
			start: own?.start ?? (lines[list.first] as Line).start,
			end: last.start + last.text.length,
			attributes: own === null ? null : ownAttributes(own),
			indent: list.indent
		});
	};

	items.forEach((item, at) => {
		const before = items[at - 1];
		const indent = item.indent ?? 0;

		// Anything but blank lines between two items ends every open list.
		if (before !== undefined && !lines.slice(before.last + 1, item.first).every((line) => blank(line) || indentOf(line) > (before.indent ?? 0))) {
			while (open.length > 0) {
				close(open.pop() as { indent: number; first: number; last: number });
			}
		}

		while (open.length > 0 && indent < (open.at(-1)?.indent ?? 0)) {
			close(open.pop() as { indent: number; first: number; last: number });
		}

		if (open.length === 0 || indent > (open.at(-1)?.indent ?? 0)) {
			open.push({ indent, first: item.first, last: item.last });
		}

		for (const list of open) {
			list.last = Math.max(list.last, item.last);
		}
	});

	while (open.length > 0) {
		close(open.pop() as { indent: number; first: number; last: number });
	}

	return [...found, ...lists].sort((a, b) => a.start - b.start || b.end - a.end || rank(a) - rank(b));
}

// On the same span, what holds the rest comes first: a list before its
// only item.
function rank(block: MarkdownBlock): number {
	return block.kind === 'list' || block.kind === 'definitions' ? 0 : 1;
}

/**
 * The block an offset is in, or else the nearest one above it, since a
 * blank line belongs to the block before it (D-268). `null` above the
 * first.
 */
export function blockAt(list: MarkdownBlock[], offset: number): number {
	let found = -1;

	list.forEach((block, index) => {
		if (block.start <= offset) {
			found = index;
		}
	});

	return found;
}

/**
 * The edit that sets a block's classes and id: where its attributes are,
 * else at the end of a heading's, paragraph's, or list item's line, or on
 * a line of their own above the rest. Attributes left empty go, with
 * their line when they had one.
 */
export function withBlockParts(source: string, markdown: MarkdownOutline, block: MarkdownBlock, classes: string[], id: string): Edit | null {
	const current = block.attributes;
	const next    = withParts(current?.text ?? '', classes, id);

	if (current !== null) {
		if (next !== '') {
			return { from: current.start, to: current.end, text: next };
		}

		if (current.own) {
			const from = source.lastIndexOf('\n', current.start - 1) + 1;
			const to   = lineEnd(source, current.end);

			return { from, to: to < source.length ? to + 1 : to, text: '' };
		}

		let from = current.start - 1;

		while (from > 0 && /[ \t]/.test(source[from - 1] ?? '')) {
			from--;
		}

		return { from, to: current.end + 1, text: '' };
	}

	if (next === '') {
		return null;
	}

	const first = markdown.lines[block.first] as Line;

	if (block.kind === 'heading' || block.kind === 'paragraph' || block.kind === 'item' || block.kind === 'term' || block.kind === 'definition') {
		const holder = block.kind === 'heading' && block.last > block.first ? first : markdown.lines[block.last] as Line;
		const at     = holder.start + holder.text.trimEnd().length;

		return { from: at, to: holder.start + holder.text.length, text: ` {${next}}` };
	}

	const indent = /^ */.exec(first.text)?.[0] ?? '';

	return { from: first.start, to: first.start, text: `${indent}{${next}}\n` };
}

/**
 * A heading's level: its hashes, or 1 or 2 for an underlined one.
 */
export function headingLevel(markdown: MarkdownOutline, block: MarkdownBlock): number {
	const first = markdown.lines[block.first];

	if (block.last > block.first) {
		return markdown.lines[block.last]?.text.trim().startsWith('=') ? 1 : 2;
	}

	return /^ {0,3}(#{1,6})/.exec(first?.text ?? '')?.[1]?.length ?? 1;
}

/**
 * The edit that sets a heading's level. An underlined heading becomes one
 * with hashes.
 */
export function withHeadingLevel(markdown: MarkdownOutline, block: MarkdownBlock, level: number): Edit | null {
	const first  = markdown.lines[block.first];
	const hashes = '#'.repeat(Math.min(6, Math.max(1, level)));

	if (first === undefined) {
		return null;
	}

	if (block.last > block.first) {
		const last = markdown.lines[block.last] as Line;

		return { from: first.start, to: last.start + last.text.length, text: `${hashes} ${first.text.trim()}` };
	}

	const match = /^( {0,3})(#{1,6})/.exec(first.text);

	return match === null ? null : { from: first.start + (match[1]?.length ?? 0), to: first.start + match[0].length, text: hashes };
}

const FENCE_PARTS = /^(\s*(?:`{3,}|~{3,})[ \t]*)([^\s`{]*)/;

/**
 * A code block's language: the first word of its fence's info string.
 */
export function codeLanguage(markdown: MarkdownOutline, block: MarkdownBlock): string {
	return FENCE_PARTS.exec(markdown.lines[block.first]?.text ?? '')?.[2] ?? '';
}

/**
 * The edit that sets a code block's language.
 */
export function withCodeLanguage(markdown: MarkdownOutline, block: MarkdownBlock, language: string): Edit | null {
	const first = markdown.lines[block.first];
	const match = FENCE_PARTS.exec(first?.text ?? '');

	if (first === undefined || match === null) {
		return null;
	}

	const from = first.start + (match[1]?.length ?? 0);

	return { from, to: from + (match[2]?.length ?? 0), text: language.trim().replace(/[\s`{]/g, '') };
}

const ITEM_BOX = /^( *(?:[-*+]|\d{1,9}[.)]))( +)(?:\[([ xX])\] +)?/;

/**
 * Whether a list item is a task, and whether it's done.
 */
export function taskState(markdown: MarkdownOutline, block: MarkdownBlock): { task: boolean; done: boolean } {
	const box = ITEM_BOX.exec(markdown.lines[block.first]?.text ?? '')?.[3];

	return { task: box !== undefined, done: box !== undefined && box !== ' ' };
}

/**
 * The edit that makes a list item a task (open or done), or not one.
 */
export function withTask(markdown: MarkdownOutline, block: MarkdownBlock, task: boolean, done = false): Edit | null {
	const first = markdown.lines[block.first];
	const match = ITEM_BOX.exec(first?.text ?? '');

	if (first === undefined || match === null) {
		return null;
	}

	const from = first.start + (match[1]?.length ?? 0);

	return { from, to: first.start + match[0].length, text: task ? ` [${done ? 'x' : ' '}] ` : ' ' };
}

export type ListStyle = 'bullet' | 'number' | 'task';

// A list item's line: indent, marker, spaces, a task's box, and its text.
const ITEM_LINE = /^( *)([-*+]|\d{1,9}[.)])( +|$)(\[[ xX]\] +)?(.*)$/;

/**
 * A list's items at its own indent, not the ones nested in them.
 */
export function listItems(found: MarkdownBlock[], list: MarkdownBlock): MarkdownBlock[] {
	return found.filter((block) => block.kind === 'item' && block.first >= list.first && block.last <= list.last && block.indent === list.indent);
}

/**
 * What kind of list it is, by its first item: numbered, a task list, or
 * bulleted.
 */
export function listStyle(markdown: MarkdownOutline, found: MarkdownBlock[], list: MarkdownBlock): ListStyle {
	const first = listItems(found, list)[0];
	const match = ITEM_LINE.exec(markdown.lines[first?.first ?? -1]?.text ?? '');

	if (match === null) {
		return 'bullet';
	}

	return match[4] !== undefined ? 'task' : (/\d/.test(match[2] ?? '') ? 'number' : 'bullet');
}

/**
 * The smallest edit that turns `before` into `after`: what's the same at
 * both ends is left out.
 */
export function editBetween(before: string, after: string): Edit {
	let from = 0;

	while (from < before.length && from < after.length && before[from] === after[from]) {
		from++;
	}

	let tail = 0;

	while (tail < before.length - from && tail < after.length - from && before[before.length - 1 - tail] === after[after.length - 1 - tail]) {
		tail++;
	}

	return { from, to: before.length - tail, text: after.slice(from, after.length - tail) };
}

/**
 * The edit that makes a list bulleted, numbered, or a task list: every
 * marker at the list's own indent is rewritten, numbered in order, and
 * nested lists and the items' text are left alone. A task list's items
 * keep their boxes; a new one is open.
 */
export function withListStyle(source: string, markdown: MarkdownOutline, found: MarkdownBlock[], list: MarkdownBlock, style: ListStyle): Edit | null {
	const text = source.split('\n');
	let number = 0;

	for (const item of listItems(found, list)) {
		const match = ITEM_LINE.exec(text[item.first] ?? '');

		if (match === null) {
			continue;
		}

		number++;

		const [, indent = '', marker = '-', , box] = match;
		const bullet = /\d/.test(marker) ? '-' : marker;
		const start  = number === 1 && /\d/.test(marker) ? Number.parseInt(marker, 10) : 1;

		if (number === 1) {
			number = start;
		}

		const lead = style === 'number' ? `${number}.` : bullet;

		text[item.first] = `${indent}${lead} ${style === 'task' ? (box ?? '[ ] ') : ''}${match[5] ?? ''}`;
	}

	const after = text.join('\n');

	return after === source ? null : editBetween(source, after);
}

/**
 * Renumbers the numbered list with an item on line `index`: the whole
 * run at that item's indent, from the number it starts at, since an item
 * pushed into the middle makes it wrong from there down (or from
 * `start`, the number it started at before its items moved). Nested
 * lines and single blank lines between items stay in the run.
 */
function renumber(text: string[], index: number, start?: number): void {
	const match  = ITEM_LINE.exec(text[index] ?? '');
	const indent = match?.[1]?.length ?? 0;

	if (match === null || !/\d/.test(match[2] ?? '')) {
		return;
	}

	const inRun = (at: number): boolean => {
		const line = text[at];

		if (line === undefined) {
			return false;
		}

		if (line.trim() === '') {
			return text[at + 1] !== undefined && text[at + 1]?.trim() !== '' && inRun(at + 1) && at > 0 && text[at - 1]?.trim() !== '';
		}

		const item = ITEM_LINE.exec(line);

		return (/^ */.exec(line)?.[0].length ?? 0) > indent || (item !== null && (item[1]?.length ?? 0) === indent && /\d/.test(item[2] ?? ''));
	};

	let first = index;

	while (first > 0 && inRun(first - 1)) {
		first--;
	}

	let number: number | null = null;

	for (let at = first; at < text.length && inRun(at); at++) {
		const item = ITEM_LINE.exec(text[at] ?? '');

		if (item === null || (item[1]?.length ?? 0) !== indent) {
			continue;
		}

		const marker = item[2] ?? '1.';

		number = number === null ? start ?? Number.parseInt(marker, 10) : number + 1;
		text[at] = `${item[1] ?? ''}${number}${marker.slice(-1)}${item[3] || ' '}${item[4] ?? ''}${item[5] ?? ''}`;
	}
}

// A blockquote's markers, and a table's delimiter cells.
const QUOTE_LINE = /^( {0,3})((?:> ?)+)(.*)$/;

/**
 * What Enter does at `position` in a list, quote, or table (admin.md §8,
 * Enter carries the marker): the new text and where the caret goes, or
 * `null` for an ordinary line break.
 *
 * - In a list item, the next line starts with the same marker (the next
 *   number, renumbering the run; an open box for a task). On an item
 *   with nothing in it, the marker goes and a blank line is left above
 *   the caret, so what's written next is a paragraph, not part of the
 *   item.
 * - In a quote, the `>` carries down; on a bare `>`, it goes.
 * - In a table row, a new row with the same columns, after the
 *   delimiter row the syntax needs if there isn't one yet; on a row of
 *   empty cells, the table ends. A delimiter row has dashes in it: a row
 *   of empty cells is only pipes and spaces.
 */
export function continuation(source: string, position: number): { text: string; caret: number } | null {
	const markdown = outline(source);
	const index    = markdown.lines.findIndex((line) => line.start <= position && position <= line.start + line.text.length);
	const line     = markdown.lines[index];

	if (line === undefined || line.kind !== 'text' && line.kind !== 'heading') {
		return null;
	}

	const at    = position - line.start;
	const text  = source.split('\n');
	const end   = line.start + line.text.length;
	const empty = (): { text: string; caret: number } => ({ text: `${source.slice(0, line.start)}\n${source.slice(end)}`, caret: line.start + 1 });

	if (TABLE.test(line.text) && !(DELIMITER.test(line.text) && line.text.includes('-'))) {
		const cells = line.text.trim().replace(/^\|/, '').replace(/\|$/, '').split('|');

		if (cells.every((cell) => cell.trim() === '')) {
			return empty();
		}

		let first = index;
		let last  = index;

		while (first > 0 && TABLE.test(text[first - 1] ?? '')) {
			first--;
		}

		while (TABLE.test(text[last + 1] ?? '')) {
			last++;
		}

		const hasDelimiter = text.slice(first, last + 1).some((row) => DELIMITER.test(row) && row.includes('-'));
		const delimiter    = hasDelimiter ? '' : `\n|${' --- |'.repeat(cells.length)}`;
		const row          = `\n|${'   |'.repeat(cells.length)}`;

		return { text: source.slice(0, end) + delimiter + row + source.slice(end), caret: end + delimiter.length + 3 };
	}

	const item = ITEM_LINE.exec(line.text);

	if (item !== null && line.marks.at(-1)?.kind === 'list') {
		const lead = (item[1] ?? '') + (item[2] ?? '') + (item[3] ?? '') + (item[4] ?? '');

		if (at < lead.length) {
			return null;
		}

		if ((item[5] ?? '').trim() === '') {
			return empty();
		}

		const marker = item[2] ?? '-';
		const next   = /\d/.test(marker) ? `${Number.parseInt(marker, 10) + 1}${marker.slice(-1)}` : marker;
		const added  = `${item[1] ?? ''}${next}${item[3] || ' '}${item[4] === undefined ? '' : '[ ] '}`;
		const lines  = `${source.slice(0, position)}\n${added}${source.slice(position)}`.split('\n');

		if (/\d/.test(marker)) {
			renumber(lines, index + 1);
		}

		// After the new line's marker, which renumbering may have changed.
		const lineStart = lines.slice(0, index + 1).join('\n').length + 1;

		return { text: lines.join('\n'), caret: lineStart + (lines[index + 1]?.length ?? 0) - (end - position) };
	}

	const quote = QUOTE_LINE.exec(line.text);

	if (quote !== null && line.marks[0]?.kind === 'quote') {
		if (at < (quote[1] ?? '').length + (quote[2] ?? '').length) {
			return null;
		}

		if ((quote[3] ?? '').trim() === '') {
			return empty();
		}

		const lead = (quote[1] ?? '') + ((quote[2] ?? '').endsWith(' ') ? quote[2] ?? '' : `${quote[2] ?? ''} `);

		return { text: `${source.slice(0, position)}\n${lead}${source.slice(position)}`, caret: position + 1 + lead.length };
	}

	return null;
}

/**
 * A change to the body as a whole: its new text, and the selection to
 * leave in it.
 */
export interface Change {
	text: string;
	from: number;
	to: number;
}

export type InlineMark = '**' | '*' | '~~' | '`';

// How long the run of `character` is that ends at `at`, or starts there.
function runBefore(source: string, at: number, character: string): number {
	let length = 0;

	while (at - length > 0 && source[at - length - 1] === character) {
		length++;
	}

	return length;
}

function runAfter(source: string, at: number, character: string): number {
	let length = 0;

	while (source[at + length] === character) {
		length++;
	}

	return length;
}

// Whether a run of marks includes this one: strong is two stars, and
// emphasis one, so three is both.
function carries(run: number, mark: InlineMark): boolean {
	if (mark === '*') {
		return run % 2 === 1;
	}

	return run >= mark.length;
}

/**
 * Turns strong, emphasized, struck, or code text on or off for a
 * selection (⌘B, ⌘I, ⌘⇧X, ⌘E): the marks around it, or at its ends, are
 * taken away, else they're added. The spaces at a selection's ends stay
 * outside the marks, which can't close on a space. With nothing
 * selected, a pair of marks goes in with the caret between them.
 */
export function toggleMark(source: string, start: number, end: number, mark: InlineMark): Change {
	let from = start;
	let to   = end;

	while (from < to && /\s/.test(source[from] ?? '')) {
		from++;
	}

	while (to > from && /\s/.test(source[to - 1] ?? '')) {
		to--;
	}

	const size      = mark.length;
	const character = mark.charAt(0);
	const inner     = source.slice(from, to);

	// The marks are part of what's selected.
	if (inner.length >= size * 2 && carries(runAfter(inner, 0, character), mark) && carries(runBefore(inner, inner.length, character), mark)) {
		return { text: source.slice(0, from) + inner.slice(size, -size) + source.slice(to), from, to: to - size * 2 };
	}

	// The marks are around it.
	if (carries(runBefore(source, from, character), mark) && carries(runAfter(source, to, character), mark)) {
		return { text: source.slice(0, from - size) + inner + source.slice(to + size), from: from - size, to: to - size };
	}

	return { text: source.slice(0, from) + mark + inner + mark + source.slice(to), from: from + size, to: to + size };
}

const URL_ONLY = /^(?:https?:\/\/|mailto:|\/)\S*$/;

/**
 * Makes a selection a link. Given an address (a pasted one), the
 * selected text becomes its label; else a selected address becomes the
 * target, with the caret in the empty label, and selected words the
 * label, with the caret where the address goes.
 */
export function linked(source: string, start: number, end: number, url?: string): Change {
	const inner  = source.slice(start, end);
	const before = source.slice(0, start);
	const after  = source.slice(end);

	if (url !== undefined) {
		const text = `[${inner}](${url})`;

		return { text: before + text + after, from: start + text.length, to: start + text.length };
	}

	if (URL_ONLY.test(inner.trim()) && inner.trim() !== '') {
		return { text: `${before}[](${inner.trim()})${after}`, from: start + 1, to: start + 1 };
	}

	const caret = start + inner.length + 3;

	return { text: `${before}[${inner}]()${after}`, from: caret, to: caret };
}

/**
 * Whether pasted text is one address, to make a selection a link.
 */
export function isAddress(text: string): boolean {
	return /^(?:https?:\/\/|mailto:)\S+$/.test(text.trim());
}

/**
 * Nests the list items in a selection one level deeper (Tab), or one
 * level shallower (Shift+Tab), with what's nested under them: under the
 * item above at the same level, lined up with its text, or back to the
 * item it's under. A numbered item that starts a new level is numbered
 * from one, and the runs it left and joined are renumbered. `null` when
 * the first line isn't an item, or has nowhere to go.
 */
export function nested(source: string, start: number, end: number, outdent: boolean): Change | null {
	const text   = source.split('\n');
	const offset = (line: number): number => text.slice(0, line).join('\n').length + (line > 0 ? 1 : 0);
	const lineOf = (position: number): number => source.slice(0, position).split('\n').length - 1;
	const first  = lineOf(start);
	const item   = ITEM_LINE.exec(text[first] ?? '');

	if (item === null) {
		return null;
	}

	const indent = item[1]?.length ?? 0;
	let delta    = 0;

	if (outdent) {
		for (let at = first - 1; at >= 0; at--) {
			const above = ITEM_LINE.exec(text[at] ?? '');

			if (above !== null && (above[1]?.length ?? 0) < indent) {
				delta = (above[1]?.length ?? 0) - indent;
				break;
			}
		}

		if (delta === 0) {
			return null;
		}
	} else {
		for (let at = first - 1; at >= 0; at--) {
			const line  = text[at] ?? '';
			const above = ITEM_LINE.exec(line);

			if (line.trim() !== '' && above === null && (/^ */.exec(line)?.[0].length ?? 0) <= indent) {
				break;
			}

			if (above !== null && (above[1]?.length ?? 0) === indent) {
				delta = (above[2]?.length ?? 1) + Math.max(1, above[3]?.length ?? 1);
				break;
			}

			if (above !== null && (above[1]?.length ?? 0) < indent) {
				break;
			}
		}

		if (delta === 0) {
			return null;
		}
	}

	// The selected lines, and what's nested under the last of them.
	let last = Math.max(first, lineOf(end));

	while (last + 1 < text.length && (text[last + 1] ?? '').trim() !== '' && (/^ */.exec(text[last + 1] ?? '')?.[0].length ?? 0) > indent && ITEM_LINE.exec(text[last + 1] ?? '')?.[1]?.length !== indent) {
		last++;
	}

	for (let at = first; at <= last; at++) {
		const line = text[at] ?? '';

		if (line.trim() !== '') {
			const shift = delta > 0 ? delta : -Math.min(-delta, /^ */.exec(line)?.[0].length ?? 0);

			text[at] = shift > 0 ? ' '.repeat(shift) + line : line.slice(-shift);
		}
	}

	// A numbered item starting a level of its own starts at one.
	const now    = ITEM_LINE.exec(text[first] ?? '');
	const level  = now?.[1]?.length ?? 0;
	const before = ITEM_LINE.exec(text[first - 1] ?? '');

	if (now !== null && /\d/.test(now[2] ?? '') && !(before !== null && (before[1]?.length ?? 0) === level)) {
		text[first] = `${now[1] ?? ''}1${(now[2] ?? '1.').slice(-1)}${now[3] || ' '}${now[4] ?? ''}${now[5] ?? ''}`;
	}

	renumber(text, first);

	if (first > 0) {
		renumber(text, first - 1);
	}

	// Every change is at the front of a line, so a position keeps its
	// distance from the end of its line.
	const old = source.split('\n');
	const map = (position: number): number => {
		const line  = lineOf(position);
		const toEnd = old.slice(0, line + 1).join('\n').length - position;

		return offset(line) + Math.max(0, (text[line]?.length ?? 0) - toEnd);
	};
	const after = text.join('\n');

	return { text: after, from: map(start), to: map(end) };
}

// The line an offset is on, and where each line starts.
function lineAt(source: string, position: number): number {
	return source.slice(0, position).split('\n').length - 1;
}

function lineStarts(lines: string[]): number[] {
	const starts: number[] = [];
	let at = 0;

	for (const line of lines) {
		starts.push(at);
		at += line.length + 1;
	}

	return starts;
}

/**
 * Moves a position from one version of the lines to another: it keeps
 * its distance from the end of its line, since every change here is at
 * the front of a line or moves whole lines. `lineMap` says where each old
 * line went.
 */
function remap(before: string[], after: string[], position: number, lineMap: (line: number) => number): number {
	const source = before.join('\n');
	const line   = lineAt(source, position);
	const toEnd  = (lineStarts(before)[line] ?? 0) + (before[line]?.length ?? 0) - position;
	const moved  = lineMap(line);

	return (lineStarts(after)[moved] ?? 0) + Math.max(0, (after[moved]?.length ?? 0) - toEnd);
}

/**
 * Makes the lines in a selection headings of a level (⌘⌥1 to ⌘⌥6), or
 * paragraphs (level 0, ⌘⌥0): the hashes go after any quote or list
 * marks and replace any there. If every line is already that level, it
 * goes back to a paragraph. Code, directives' own lines, and blank lines
 * are left alone; `null` when nothing's left.
 */
export function withHeading(source: string, start: number, end: number, level: number): Change | null {
	const markdown = outline(source);
	const before   = source.split('\n');
	const text     = [...before];
	const first    = lineAt(source, start);
	const last     = Math.max(first, lineAt(source, end));
	const lines    = markdown.lines.slice(first, last + 1).filter((line) => (line.kind === 'text' || line.kind === 'heading') && line.text.trim() !== '' && line.marks.at(-1)?.kind !== 'rule');

	if (lines.length === 0) {
		return null;
	}

	// Where the heading's hashes go, and where any already there end.
	const parts = lines.map((line) => {
		const lead    = line.marks.filter((mark) => mark.kind === 'quote' || mark.kind === 'list').at(-1)?.end ?? 0;
		const heading = line.marks.find((mark) => mark.kind === 'heading');
		const hashes  = heading === undefined ? 0 : /#+/.exec(line.text.slice(lead, heading.end))?.[0].length ?? 0;

		return { line, lead, rest: heading?.end ?? lead, hashes };
	});

	const target = level === 0 || parts.every((part) => part.hashes === level) ? 0 : Math.min(6, level);

	for (const part of parts) {
		const index = markdown.lines.indexOf(part.line);
		const body  = part.line.text.slice(part.rest).replace(/^ +/, '');

		text[index] = part.line.text.slice(0, part.lead) + (target === 0 ? '' : `${'#'.repeat(target)} `) + (part.hashes === 0 ? part.line.text.slice(part.lead).replace(/^ {0,3}/, '') : body);
	}

	return { text: text.join('\n'), from: remap(before, text, start, (line) => line), to: remap(before, text, end, (line) => line) };
}

/**
 * A token from a line, flattened out of the marks it's nested in, with
 * its offsets (and its inner text's) in the body.
 */
interface BodyToken {
	kind: TokenKind;
	start: number;
	end: number;
	inner: { start: number; end: number } | null;
	// A link's or image's first character, to tell them apart.
	image: boolean;
}

/**
 * The line an offset is on, by index, or -1.
 */
function lineIndexAt(markdown: MarkdownOutline, offset: number): number {
	return markdown.lines.findIndex((line) => line.start <= offset && offset <= line.start + line.text.length);
}

function bodyTokens(line: Line, tokens: Token[] = line.tokens, found: BodyToken[] = []): BodyToken[] {
	for (const token of tokens) {
		found.push({
			kind: token.kind,
			start: line.start + token.start,
			end: line.start + token.end,
			inner: token.inner === undefined ? null : { start: line.start + token.inner.start, end: line.start + token.inner.end },
			image: line.text[token.start] === '!'
		});

		if (token.inner !== undefined) {
			bodyTokens(line, token.inner.tokens, found);
		}
	}

	return found;
}

/**
 * Whether Markdown's emphasis is emphasis at an offset (admin.md §8, The
 * toolbar): in prose, a paragraph, heading, list item, quote, a table's
 * cell, or a container's body; not in code, on a directive's own line, a
 * rule, or a table's delimiter row, where a pair of stars is two stars.
 */
export function inProse(markdown: MarkdownOutline, offset: number): boolean {
	const line = markdown.lines[lineIndexAt(markdown, offset)];

	if (line === undefined || (line.kind !== 'text' && line.kind !== 'heading')) {
		return false;
	}

	return line.marks.at(-1)?.kind !== 'rule' && !(TABLE.test(line.text) && DELIMITER.test(line.text) && line.text.includes('-')) && !isAttributeLine(line);
}

export type Emphasis = 'strong' | 'em' | 'strike';

/**
 * The emphasis of a kind that holds a selection (or the caret): the
 * innermost whose text it's in, or that it selects whole, marks and all.
 */
function emphasisSpan(markdown: MarkdownOutline, start: number, end: number, kind: Emphasis): BodyToken | null {
	const line = markdown.lines[lineIndexAt(markdown, start)];

	if (line === undefined || end > line.start + line.text.length) {
		return null;
	}

	let found: BodyToken | null = null;

	for (const token of bodyTokens(line)) {
		const inner = token.inner;

		if (token.kind !== kind || inner === null) {
			continue;
		}

		const holds = (start >= inner.start && end <= inner.end) || (start === token.start && end === token.end);

		if (holds && (found === null || token.end - token.start < found.end - found.start)) {
			found = token;
		}
	}

	return found;
}

/**
 * Which emphasis is on for a selection, read with the highlighter's own
 * patterns, so a button can't say text is bold while the ink beside it
 * says otherwise. Either mark counts: `*` or `_`, `**` or `__`.
 */
export function emphasisAt(markdown: MarkdownOutline, start: number, end: number): Record<Emphasis, boolean> {
	return {
		strong: emphasisSpan(markdown, start, end, 'strong') !== null,
		em: emphasisSpan(markdown, start, end, 'em') !== null,
		strike: emphasisSpan(markdown, start, end, 'strike') !== null
	};
}

// What a word is made of, for "nothing selected means this word".
const WORD_CHARACTER = /[\p{L}\p{N}'’-]/u;

/**
 * The word an offset is in or at the edge of, without a leading or
 * trailing apostrophe or hyphen, or `null`.
 */
export function wordAt(source: string, offset: number): { start: number; end: number } | null {
	let start = offset;
	let end   = offset;

	while (start > 0 && WORD_CHARACTER.test(source[start - 1] ?? '')) {
		start--;
	}

	while (end < source.length && WORD_CHARACTER.test(source[end] ?? '')) {
		end++;
	}

	while (start < end && /['’-]/.test(source[start] ?? '')) {
		start++;
	}

	while (end > start && /['’-]/.test(source[end - 1] ?? '')) {
		end--;
	}

	return start === end ? null : { start, end };
}

/**
 * Turns strong, emphasized, or struck text on or off (admin.md §8, The
 * toolbar): emphasis holding the selection loses its marks, whichever it
 * was written with; else the marks go around it. Strong is `**` and
 * struck `~~`; emphasis is `_`, which reads apart from `**` in the
 * source, but inside a word, where `_` isn't emphasis, `*`. Nothing
 * selected means the word at the caret, which stays where it was in it;
 * with no word there either, the marks go in empty with the caret
 * between them. A selection over more than one line is marked as it is.
 */
export function toggleEmphasis(source: string, start: number, end: number, kind: Emphasis): Change {
	const markdown = outline(source);
	const span     = emphasisSpan(markdown, start, end, kind);

	if (span !== null && span.inner !== null) {
		const inner  = span.inner;
		const size   = inner.start - span.start;
		const length = inner.end - inner.start;
		const text   = source.slice(0, span.start) + source.slice(inner.start, inner.end) + source.slice(span.end);
		const clamp  = (position: number): number => Math.max(span.start, Math.min(span.start + length, position - size));

		return start === span.start && end === span.end ? { text, from: span.start, to: span.start + length } : { text, from: clamp(start), to: clamp(end) };
	}

	if (source.slice(start, end).includes('\n')) {
		return toggleMark(source, start, end, kind === 'strong' ? '**' : (kind === 'strike' ? '~~' : '*'));
	}

	const collapsed = start === end;
	const word      = collapsed ? wordAt(source, start) : null;
	let from        = word?.start ?? start;
	let to          = word?.end ?? end;

	while (from < to && /\s/.test(source[from] ?? '')) {
		from++;
	}

	while (to > from && /\s/.test(source[to - 1] ?? '')) {
		to--;
	}

	const letter = /[\p{L}\p{N}]/u;
	const inWord = letter.test(source[from - 1] ?? '') || letter.test(source[to] ?? '');
	const mark   = kind === 'strong' ? '**' : (kind === 'strike' ? '~~' : (inWord ? '*' : '_'));
	const text   = source.slice(0, from) + mark + source.slice(from, to) + mark + source.slice(to);

	if (collapsed) {
		return { text, from: start + mark.length, to: start + mark.length };
	}

	return { text, from: from + mark.length, to: to + mark.length };
}

/**
 * A link in the body: where it is, its label as written, and its target
 * (the address, and any quoted title after it).
 */
export interface MarkdownLink {
	start: number;
	end: number;
	label: string;
	url: string;
	// What follows the address in the parentheses (` "a title"`).
	title: string;
}

/**
 * The link (not an image) holding a selection, or the caret, or `null`.
 */
export function linkAt(source: string, start: number, end: number): MarkdownLink | null {
	const markdown = outline(source);
	const line     = markdown.lines[lineIndexAt(markdown, start)];

	if (line === undefined || end > line.start + line.text.length) {
		return null;
	}

	const token = bodyTokens(line).filter((item) => item.kind === 'link' && !item.image && item.inner !== null && item.start <= start && end <= item.end)
		.sort((a, b) => (a.end - a.start) - (b.end - b.start))[0];

	if (token === undefined || token.inner === null) {
		return null;
	}

	const target = /^\s*(<[^>\n]*>|\S*)(.*)$/s.exec(source.slice(token.inner.end + 2, token.end - 1));
	const url    = target?.[1] ?? '';

	return {
		start: token.start,
		end: token.end,
		label: source.slice(token.inner.start, token.inner.end),
		url: url.startsWith('<') ? url.slice(1, -1) : url,
		title: target?.[2] ?? ''
	};
}

/**
 * A link's label as it reads, for a field: without the backslashes that
 * escape brackets.
 */
export function linkLabel(label: string): string {
	return label.replace(/\\([[\]])/g, '$1');
}

/**
 * Writes a link: over `link` when there is one (keeping its title), else
 * in place of the selection. The label's brackets are escaped, and an
 * address with spaces or parentheses is wrapped in `<…>`. The caret goes
 * after it.
 */
export function withLink(source: string, start: number, end: number, label: string, url: string, link: MarkdownLink | null): Change {
	const address = /[\s()<>]/.test(url.trim()) ? `<${url.trim().replace(/[<>]/g, (character) => encodeURIComponent(character))}>` : url.trim();
	const text    = `[${label.replace(/\s*\n\s*/g, ' ').replace(/([[\]])/g, '\\$1')}](${address}${link?.title ?? ''})`;
	const from    = link?.start ?? start;
	const to      = link?.end ?? end;

	return { text: source.slice(0, from) + text + source.slice(to), from: from + text.length, to: from + text.length };
}

/**
 * Takes a link away, leaving its label as written, selected.
 */
export function withoutLink(source: string, link: MarkdownLink): Change {
	return { text: source.slice(0, link.start) + link.label + source.slice(link.end), from: link.start, to: link.start + link.label.length };
}

/**
 * The closing fence the third backtick writes (admin.md §8, The third
 * backtick writes the block): when the caret is at the end of three
 * backticks alone on a line, and the fence lines in the body are an odd
 * number (this one opened something nothing closes), a line to write on
 * and a closing fence go after it. The caret stays where it is, where the
 * language goes. `null` otherwise.
 */
export function closingFence(source: string, position: number): Edit | null {
	const markdown = outline(source);
	const line     = markdown.lines[lineIndexAt(markdown, position)];
	const match    = /^( {0,3})```$/.exec(line?.text ?? '');

	if (line === undefined || match === null || position !== line.start + line.text.length || line.kind !== 'fence') {
		return null;
	}

	if (markdown.lines.filter((item) => item.kind === 'fence').length % 2 === 0) {
		return null;
	}

	const indent = match[1] ?? '';

	return { from: position, to: position, text: `\n${indent}\n${indent}\`\`\`` };
}

/**
 * Where Enter at the end of an opening fence goes when the line under it
 * is the block's empty first line: into it, rather than pushing another
 * blank line in above code not yet written. `null` otherwise.
 */
export function intoFence(source: string, position: number): number | null {
	const markdown = outline(source);
	const index    = lineIndexAt(markdown, position);
	const line     = markdown.lines[index];
	const next     = markdown.lines[index + 1];

	if (line === undefined || next === undefined || line.kind !== 'fence' || position !== line.start + line.text.length || next.kind !== 'code' || next.text.trim() !== '') {
		return null;
	}

	// An opening fence: the one before it, if any, closed its own block.
	const fences = markdown.lines.slice(0, index).filter((item) => item.kind === 'fence').length;

	return fences % 2 === 0 ? next.start + next.text.length : null;
}

/**
 * Swaps two runs of the body, `first` before `second`, leaving what's
 * between them where it is: the gaps belong to the document, so a wider
 * break before one element is still there after the move (admin.md §8,
 * Reordering). A position in either run moves with it; `keep` is the
 * selection to carry.
 */
export function swapped(source: string, first: { start: number; end: number }, second: { start: number; end: number }, keep: { from: number; to: number }): Change {
	const a    = source.slice(first.start, first.end);
	const b    = source.slice(second.start, second.end);
	const gap  = source.slice(first.end, second.start);
	const text = source.slice(0, first.start) + b + gap + a + source.slice(second.end);
	const map  = (position: number): number => {
		if (position >= first.start && position <= first.end) {
			return first.start + b.length + gap.length + (position - first.start);
		}

		if (position >= second.start && position <= second.end) {
			return first.start + (position - second.start);
		}

		return position;
	};

	return { text, from: map(keep.from), to: map(keep.to) };
}

/**
 * Renumbers the numbered list with an item on the line at `offset`, as
 * Enter does, from `start` (the number it started at before an item
 * moved), so a moved item doesn't leave it counting from the wrong one. The selection keeps its distance from its line's end.
 */
export function renumberedAt(change: Change, offset: number, start?: number): Change {
	const before = change.text.split('\n');
	const after  = [...before];

	renumber(after, lineAt(change.text, offset), start);

	const identity = (line: number): number => line;

	return { text: after.join('\n'), from: remap(before, after, change.from, identity), to: remap(before, after, change.to, identity) };
}

/**
 * What Backspace does just after a block's marker (admin.md §8,
 * Backspace takes the marker off; D-314): an indented list item comes
 * out a level, as Shift+Tab does; else the marker goes (a list item's
 * with its box, a quote's last `>`, a heading's hashes) and the words
 * stay, with the caret where the marker began. `null` anywhere else, so
 * Backspace deletes a character, or joins the line to the one above, as
 * it always does.
 */
export function unmarked(source: string, position: number): Change | null {
	const markdown = outline(source);
	const line     = markdown.lines[lineIndexAt(markdown, position)];
	const mark     = line?.marks.at(-1);

	if (line === undefined || mark === undefined || mark.kind === 'rule' || position !== line.start + mark.end) {
		return null;
	}

	if (mark.kind === 'list' && /^ +/.test(line.text)) {
		const lifted = nested(source, position, position, true);

		if (lifted !== null) {
			return lifted;
		}
	}

	const from = line.start + (line.marks.at(-2)?.end ?? 0);
	const text = source.slice(0, from) + source.slice(position);

	return { text, from, to: from };
}

/**
 * Pasted Markdown made safe to drop inside other containers (D-321): a
 * closing line that closes nothing in it (left over from copying part of
 * a container) is dropped, leaving a blank line, so it can't close the
 * container it lands in;
 * a container it leaves open is closed at its end, so it can't take in
 * what follows; and every fence is written `:::` (D-320), one closing
 * line per container. `block` says whether it holds a container or leaf
 * directive, which goes on lines of its own. Code is left alone.
 */
export function pasted(text: string): { text: string; block: boolean } {
	const markdown = outline(text.replace(/\r\n?/g, '\n'));
	const out: string[] = [];
	const open: number[] = [];
	let block = false;
	let gap   = false;

	for (const line of markdown.lines) {
		const indent = /^ */.exec(line.text)?.[0] ?? '';

		// A dropped closer is still a break between the blocks either side.
		if (gap && line.text.trim() !== '' && out.length > 0 && out.at(-1)?.trim() !== '') {
			out.push('');
		}

		gap = false;

		if (line.kind === 'open') {
			out.push(line.text.replace(/^( *):+/, '$1:::'));
			open.push(line.directive ?? -1);
			block = true;
		} else if (line.kind === 'close') {
			const closed = open.splice(open.indexOf(line.directive ?? -1));

			out.push(...closed.map(() => `${indent}:::`));
		} else if (line.kind === 'text' && CLOSE.test(line.text)) {
			gap = true;
		} else {
			block ||= line.kind === 'leaf';
			out.push(line.text);
		}
	}

	if (open.length > 0) {
		out.push(...open.map(() => ':::'));
	}

	return { text: out.join('\n'), block };
}

/**
 * Where text may go near `position` without breaking the syntax it
 * lands in (admin.md §8, You cannot write into the machinery; D-314),
 * and what to put before it. A directive's own line (its opening tag, a
 * leaf, or a closer) is moved past, onto a line of its own after it: the
 * body, for a container. Attributes at the end of a line, after a space,
 * describe the words before them, so text goes before them; inside an
 * image's attributes, it goes after them. Anywhere else, `position` as
 * it is.
 */
export function safeSpot(source: string, position: number): { at: number; before: string } {
	const markdown = outline(source);
	const line     = markdown.lines[lineIndexAt(markdown, position)];

	if (line === undefined) {
		return { at: position, before: '' };
	}

	const end = line.start + line.text.length;

	if (line.kind === 'open' || line.kind === 'leaf' || line.kind === 'close') {
		return position <= line.start + (line.text.length - line.text.trimStart().length) ? { at: position, before: '' } : { at: end, before: '\n' };
	}

	const trailing = (line.kind === 'text' || line.kind === 'heading') ? TRAILING.exec(line.text) : null;

	if (trailing !== null) {
		const from = line.start + trailing.index;

		if (position > from) {
			return { at: from, before: '' };
		}
	}

	for (const token of bodyTokens(line)) {
		if (token.kind === 'attributes' && token.start < position && position < token.end) {
			return { at: token.end, before: '' };
		}
	}

	return { at: position, before: '' };
}

/**
 * Where a character typed at `position` goes, when typing it there would
 * provably break something rather than edit it: right after a trailing
 * attribute block, at the very end of its line, it goes before the
 * block, at the end of the words; right after a directive's opening tag
 * or a leaf that ends in `]` or `}`, onto a new line after it. A
 * character typed inside braces or a name is someone editing by hand,
 * and stays. `null` when it stays.
 */
export function typedSpot(source: string, position: number): { at: number; before: string } | null {
	const markdown = outline(source);
	const line     = markdown.lines[lineIndexAt(markdown, position)];

	if (line === undefined || position !== line.start + line.text.length || position === line.start) {
		return null;
	}

	if ((line.kind === 'open' || line.kind === 'leaf') && /[\]}]\s*$/.test(line.text)) {
		return { at: position, before: '\n' };
	}

	const trailing = (line.kind === 'text' || line.kind === 'heading') ? TRAILING.exec(line.text) : null;

	return trailing === null ? null : { at: line.start + trailing.index, before: '' };
}

/**
 * The lines a selection covers, by index: from the line it starts on to
 * the one it ends on, not counting a line it ends at the very start of.
 */
function selectedLines(source: string, start: number, end: number): { first: number; last: number } {
	const first = lineAt(source, start);
	let last    = Math.max(first, lineAt(source, end));

	if (last > first && end === lineStarts(source.split('\n'))[last]) {
		last--;
	}

	return { first, last };
}

/**
 * Tab and Shift+Tab in a quote (admin.md §8, Tab moves a block; D-315):
 * one `>` level more, or one less (the last level off leaves a
 * paragraph), on every quote line of the selection, or with nothing
 * selected, of the whole quote the caret is in. The selection stays on
 * the same words. `null` when the selection doesn't start in a quote.
 */
export function quoted(source: string, start: number, end: number, outdent: boolean): Change | null {
	const markdown = outline(source);
	const index    = lineAt(source, start);
	const line     = markdown.lines[index];

	if (line === undefined || line.kind !== 'text' && line.kind !== 'heading' || line.marks[0]?.kind !== 'quote') {
		return null;
	}

	let { first, last } = selectedLines(source, start, end);

	if (start === end) {
		const block = blocks(markdown).find((item) => item.kind === 'quote' && item.first <= index && index <= item.last);

		first = block?.first ?? first;
		last  = block?.last ?? last;
	}

	const before = source.split('\n');
	const after  = [...before];
	let changed  = false;

	for (let at = first; at <= last; at++) {
		const match = QUOTE_LINE.exec(after[at] ?? '');

		if (match === null || markdown.lines[at]?.marks[0]?.kind !== 'quote') {
			continue;
		}

		const [, indent = '', marks = '', rest = ''] = match;

		after[at] = outdent ? indent + marks.replace(/^> ?/, '') + rest : `${indent}> ${marks}${rest}`;
		changed   = true;
	}

	if (!changed) {
		return null;
	}

	const identity = (line: number): number => line;

	return { text: after.join('\n'), from: remap(before, after, start, identity), to: remap(before, after, end, identity) };
}

/**
 * Tab and Shift+Tab over several lines of a fenced code block (D-315):
 * two spaces in front of each line, or up to two off. Blank lines and the
 * fences are left alone. `null` unless the selection spans lines and
 * starts in the block's code.
 */
export function indentedCode(source: string, start: number, end: number, outdent: boolean): Change | null {
	const markdown = outline(source);
	const { first, last } = selectedLines(source, start, end);

	if (first === last || markdown.lines[first]?.kind !== 'code') {
		return null;
	}

	const before = source.split('\n');
	const after  = [...before];
	let changed  = false;

	for (let at = first; at <= last; at++) {
		const text = after[at] ?? '';

		if (markdown.lines[at]?.kind !== 'code' || text.trim() === '') {
			continue;
		}

		const next = outdent ? text.replace(/^ {1,2}/, '') : `  ${text}`;

		changed   = changed || next !== text;
		after[at] = next;
	}

	if (!changed) {
		return null;
	}

	const identity = (line: number): number => line;

	return { text: after.join('\n'), from: remap(before, after, start, identity), to: remap(before, after, end, identity) };
}
