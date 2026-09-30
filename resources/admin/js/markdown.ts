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
 *   a closing line closes the outermost container it's long enough for,
 *   and everything inside it;
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
 * outermost it's long enough for), or -1.
 */
function closes(open: { fence: number }[], length: number): number {
	return open.findIndex((item) => item.fence <= length);
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

/**
 * One line of text (not code or a directive's own line), with its marks
 * and tokens.
 */
function lineHtml(line: Line, current: Current): string {
	// A table's delimiter row is all syntax.
	if (TABLE.test(line.text) && DELIMITER.test(line.text) && line.text.includes('-')) {
		return markHtml(line.text);
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
 */
export function highlight(markdown: MarkdownOutline, directive: number, image = -1): string {
	const at = markdown.images[image]?.start ?? -1;

	return markdown.lines.map((line) => {
		let html: string;

		switch (line.kind) {
			case 'fence': {
				// A fence is the one place the source is the content, so it
				// sits on a slab, with its language named.
				const parts = /^(\s*)(`{3,}|~{3,})(.*)$/.exec(line.text);

				html = `<span class="md-fence">${parts === null ? escape(line.text) : escape(parts[1] ?? '') + markHtml(parts[2] ?? '') + (parts[3] ? `<span class="md-fence__lang">${escape(parts[3])}</span>` : '')}</span>`;
				break;
			}
			case 'code':
				html = `<span class="md-code-block">${escape(line.text)}</span>`;
				break;
			case 'open':
			case 'leaf':
			case 'close': {
				const indent = line.text.length - line.text.trimStart().length;

				return escape(line.text.slice(0, indent)) + directiveHtml(line.text.slice(indent), line.directive === directive);
			}
			default:
				html = lineHtml(line, { directive, image: at, line: line.start });
		}

		return html;
	}).join('\n');
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

export type BlockKind = 'heading' | 'paragraph' | 'item' | 'quote' | 'code' | 'table' | 'rule';

/**
 * A block of Markdown outside directives' own lines: a heading, a
 * paragraph, one list item (with its continuation lines), a quote, a
 * fenced code block, a table, or a rule.
 */
export interface MarkdownBlock {
	kind: BlockKind;
	// Its first and last lines, by index.
	first: number;
	last: number;
	// From its attributes' own line, if it has one, to the end of its
	// last line.
	start: number;
	end: number;
	// The attributes' body, without the braces, and where it is: on a line
	// of its own above (`own`), or at the end of the last line.
	attributes: { start: number; end: number; text: string; own: boolean } | null;
}

// A line that's nothing but a block of attributes, and attributes at the
// end of a line, after a space (right after an image, they're the
// image's).
const ATTRIBUTE_LINE = new RegExp(`^ {0,3}(${ATTRIBUTES})[ \\t]*$`);
const TRAILING       = new RegExp(`[ \\t]+(${ATTRIBUTES})[ \\t]*$`);
const SETEXT         = /^ {0,3}(=+|-+)[ \t]*$/;

function blank(line: Line | undefined): boolean {
	return line === undefined || line.text.trim() === '';
}

function isAttributeLine(line: Line): boolean {
	return line.kind === 'text' && line.marks.length === 0 && ATTRIBUTE_LINE.test(line.text);
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
 * The body's blocks, in order.
 */
export function blocks(markdown: MarkdownOutline): MarkdownBlock[] {
	const { lines } = markdown;
	const found: MarkdownBlock[] = [];
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
			above = line;
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
		} else {
			more((next) => SETEXT.test(next.text) || !startsBlock(next, lines[last + 2]));

			if (last > index && SETEXT.test(lines[last]?.text ?? '')) {
				kind = 'heading';
			}
		}

		const end = lines[last] as Line;
		let attributes: MarkdownBlock['attributes'] = null;

		if (above !== null) {
			const brace = above.text.indexOf('{');

			attributes = { start: above.start + brace + 1, end: above.start + above.text.lastIndexOf('}'), text: above.text.slice(brace + 1, above.text.lastIndexOf('}')), own: true };
		} else if (kind === 'heading' || kind === 'paragraph' || kind === 'item') {
			// A setext heading's attributes are on its text, not its underline.
			const holder = kind === 'heading' && last > index ? line : end;
			const match  = TRAILING.exec(holder.text);

			if (match !== null) {
				const brace = holder.text.length - match[0].length + match[0].indexOf('{');

				attributes = { start: holder.start + brace + 1, end: holder.start + holder.text.lastIndexOf('}'), text: (match[1] ?? '').slice(1, -1), own: false };
			}
		}

		found.push({ kind, first: index, last, start: above?.start ?? line.start, end: end.start + end.text.length, attributes });
		above = null;
		index = last + 1;
	}

	return found;
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

	if (block.kind === 'heading' || block.kind === 'paragraph' || block.kind === 'item') {
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
