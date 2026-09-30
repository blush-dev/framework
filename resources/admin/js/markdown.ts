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
	'(?<link>!?\\[(?<label>[^\\]\\n]*)\\]\\([^)\\n]*\\))',
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
		}

		lines.push(line);
		start = end + 1;
	}

	return { lines, directives };
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
 * Part of a line, `from` to `to`, with its tokens.
 */
function inlineHtml(text: string, tokens: Token[], from: number, to: number, current: number, pipes = false): string {
	let html = '';
	let at   = from;

	for (const token of tokens) {
		html += plainHtml(text.slice(at, token.start), pipes) + tokenHtml(text, token, current);
		at    = token.end;
	}

	return html + plainHtml(text.slice(at, to), pipes);
}

/**
 * A token: its marks muted around what's inside them. A link's label is
 * read in the sentence and takes the accent; its address steps back. An
 * image's alternative text is quieter than a link's label.
 */
function tokenHtml(text: string, token: Token, current: number): string {
	const source = text.slice(token.start, token.end);

	switch (token.kind) {
		case 'directive':
			return directiveHtml(source, token.directive === current);
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
function lineHtml(line: Line, current: number): string {
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
 * at `current` boxed and, for a container, its body tinted. Every
 * character of the source is in it, escaped, so it lines up with the text
 * area over it.
 */
export function highlight(markdown: MarkdownOutline, current: number): string {
	const active = markdown.directives[current];

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

				return escape(line.text.slice(0, indent)) + directiveHtml(line.text.slice(indent), line.directive === current);
			}
			default:
				html = lineHtml(line, current);
		}

		// The body of the container the caret is in is tinted, so its
		// extent shows without a border anywhere.
		const inside = active?.kind === 'container' && line.start > active.start && line.start + line.text.length <= active.end;

		return inside && line.text !== '' ? `<span class="md-inside">${html}</span>` : html;
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
 * The edit that removes a directive. A container's body and an inline
 * directive's label stay, as text; a leaf goes with its line.
 */
export function withoutDirective(source: string, directive: Directive): Edit {
	if (directive.kind === 'inline') {
		return { from: directive.start, to: directive.end, text: directiveHead(source, directive).label?.text ?? '' };
	}

	const lineStart = source.lastIndexOf('\n', directive.start - 1) + 1;

	if (directive.kind === 'leaf') {
		const end = lineEnd(source, directive.start);

		return { from: lineStart, to: end < source.length ? end + 1 : end, text: '' };
	}

	// A container: its opening and closing lines go, and the body is left.
	const openEnd    = lineEnd(source, directive.start);
	const closeEnd   = directive.end;
	const closeStart = directive.closed === true ? source.lastIndexOf('\n', closeEnd - 1) + 1 : closeEnd + 1;
	const bodyStart  = Math.min(openEnd + 1, closeStart);
	const inner      = closeStart > bodyStart ? source.slice(bodyStart, closeStart).replace(/\n$/, '') : '';

	return { from: lineStart, to: closeEnd, text: inner };
}

/**
 * A Markdown image, `![alt](src)`, and where the caret goes in it: in
 * the alternative text when there's none yet, else after the image. A
 * `title` (which the site shows as the figure's caption) is written in
 * quotes. The address is wrapped in `<…>` when it has spaces or
 * parentheses.
 */
export function imageText(src: string, alt = '', title = ''): { text: string; caret: number } {
	const address = /[\s()<>]/.test(src) ? `<${src.replace(/[<>]/g, (character) => encodeURIComponent(character))}>` : src;
	const label   = alt.replace(/[\\[\]]/g, '\\$&').replace(/\s*\n\s*/g, ' ');
	const caption = title === '' ? '' : ` "${title.replace(/["\\]/g, '\\$&')}"`;
	const text    = `![${label}](${address}${caption})`;

	return { text, caret: label === '' ? 2 : text.length };
}
