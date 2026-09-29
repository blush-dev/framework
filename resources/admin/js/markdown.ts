/**
 * Reads a Markdown body the way the editor shows it (D-241): which lines
 * are headings, code, or component directives, where each directive
 * starts and ends, and the body as highlighted HTML for the editor's
 * source view. Directives follow the server's parser (D-026,
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

type TokenKind = 'code' | 'directive' | 'link' | 'strong';

interface Token {
	kind: TokenKind;
	// Offsets in the line.
	start: number;
	end: number;
	directive?: number;
}

interface Line {
	kind: LineKind;
	start: number;
	text: string;
	// The directive an `open`, `close`, or `leaf` line belongs to.
	directive?: number;
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
const INLINE    = new RegExp(`(?<code>(\`+).+?\\2(?!\`))|(?<directive>(?<![\\p{L}\\p{N}_:]):(?<name>${NAME})\\[[^\\]\\n]*\\](?:\\{[^}\\n]*\\})?)|(?<link>!?\\[[^\\]\\n]*\\]\\([^)\\n]*\\))|(?<strong>\\*\\*[^*\\n]+\\*\\*|__[^_\\n]+__)`, 'gu');

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
		const line: Line = { kind: 'text', start, text, tokens: [] };
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
			line.kind   = HEADING.test(text) ? 'heading' : 'text';
			line.tokens = inline(text, start, directives);
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
 * Finds a line's inline code, directives, links, and strong text.
 */
function inline(text: string, start: number, directives: Directive[]): Token[] {
	const tokens: Token[] = [];

	for (const match of text.matchAll(INLINE)) {
		const groups = match.groups ?? {};
		const kind   = (['code', 'directive', 'link', 'strong'] as const).find((name) => groups[name] !== undefined) ?? 'code';
		const token: Token = { kind, start: match.index, end: match.index + match[0].length };

		if (kind === 'directive') {
			token.directive = directives.push({ kind: 'inline', name: groups.name ?? '', start: start + token.start, end: start + token.end }) - 1;
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
 * A directive's marker and name, then the rest (label and attributes)
 * muted.
 */
function directiveHtml(text: string, current: boolean): string {
	const split = text.search(/[[{]/);
	const head  = split === -1 ? text : text.slice(0, split);
	const rest  = split === -1 ? '' : text.slice(split);

	return `<span class="md-directive${current ? ' is-current' : ''}">${escape(head)}${rest === '' ? '' : `<span class="md-directive__rest">${escape(rest)}</span>`}</span>`;
}

/**
 * The body as HTML for the editor's highlighted copy, with the directive
 * at `current` marked. Every character of the source is in it, escaped,
 * so it lines up with the text area over it.
 */
export function highlight(markdown: MarkdownOutline, current: number): string {
	return markdown.lines.map((line) => {
		switch (line.kind) {
			case 'fence':
			case 'code':
				return `<span class="md-code-block">${escape(line.text)}</span>`;
			case 'open':
			case 'leaf':
			case 'close': {
				const indent = line.text.length - line.text.trimStart().length;

				return escape(line.text.slice(0, indent)) + directiveHtml(line.text.slice(indent), line.directive === current);
			}
		}

		let html = '';
		let at   = 0;

		for (const token of line.tokens) {
			const text = line.text.slice(token.start, token.end);

			html += escape(line.text.slice(at, token.start));
			html += token.kind === 'directive' ? directiveHtml(text, token.directive === current) : `<span class="md-${token.kind}">${escape(text)}</span>`;
			at    = token.end;
		}

		html += escape(line.text.slice(at));

		return line.kind === 'heading' ? `<span class="md-heading">${html}</span>` : html;
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
