/**
 * `blush/messages` (D-831): text in the browser, as `t()` gives it in
 * PHP (D-784, D-816). The page prints the catalogs its scripts read as
 * JSON (`<script type="application/json" data-blush-messages="{domain}"
 * data-blush-catalog="{name}">`), in the page's language, and maps this
 * module in its import map, so a plugin's script imports it by name and
 * leaves it out of its build:
 *
 *     import { messages } from 'blush/messages';
 *
 *     const t = messages('acme/gallery');
 *
 *     t('gallery.next', 'Next');
 *     t('gallery.count', '{count, plural, one {# photo} other {# photos}}', { count: 3 });
 *
 * `t(key, text?, params?)` returns the catalog's message when one of the
 * domain's printed catalogs has the key, else the text written beside
 * it, else the key, formatted with its named parameters. The domain is
 * written in the call, since a script has no namespace to read it from.
 *
 * Messages are ICU MessageFormat, as PHP's catalogs are, for what those
 * allow: `{name}`, `plural` (with `#`, `=0`, and `offset:`), `select`,
 * `selectordinal`, `number` (with `integer` or `percent`), `date`, and
 * `time` (with `short`, `medium`, `long`, or `full`), and apostrophes
 * quoting as ICU's do. It's written on `Intl`, with no library, so the
 * admin, which bundles it, stays in its size budget (D-510).
 */

export type Params = Record<string, unknown>;
export type Translate = (key: string, text?: string, params?: Params) => string;

const domains = new Map<string, Map<string, string>>();
const patterns = new Map<string, Node[]>();

/**
 * Returns `t()` for a domain: `core`, or an extension's `vendor/name`.
 */
export function messages(domain: string): Translate {
	return (key, text, params = {}) => format(catalog(domain).get(key) ?? text ?? key, params);
}

/**
 * The language the page is in, which messages are formatted in.
 */
export function locale(): string {
	return document.documentElement.lang || navigator.language || 'en';
}

/**
 * Returns a domain's messages: every catalog the page printed for it,
 * read once.
 */
function catalog(domain: string): Map<string, string> {
	let found = domains.get(domain);

	if (found !== undefined) {
		return found;
	}

	found = new Map();

	for (const element of document.querySelectorAll<HTMLScriptElement>('script[type="application/json"][data-blush-messages]')) {
		if (element.dataset.blushMessages !== domain) {
			continue;
		}

		try {
			const data: unknown = JSON.parse(element.textContent || '{}');

			if (data !== null && typeof data === 'object') {
				for (const [key, message] of Object.entries(data)) {
					if (typeof message === 'string') {
						found.set(key, message);
					}
				}
			}
		} catch {
			// A damaged catalog leaves the code's text showing.
		}
	}

	domains.set(domain, found);

	return found;
}

type Node =
	| string
	| { kind: 'pound' }
	| { kind: 'argument'; name: string; type: string; style: string }
	| { kind: 'choice'; name: string; type: 'plural' | 'selectordinal' | 'select'; offset: number; options: Map<string, Node[]> };

/**
 * Formats an ICU message with named parameters. A message that can't be
 * parsed comes back as written, as PHP's do.
 */
export function format(pattern: string, params: Params = {}): string {
	if (!pattern.includes('{') && !pattern.includes('\'')) {
		return pattern;
	}

	let nodes = patterns.get(pattern);

	if (nodes === undefined) {
		try {
			nodes = new Parser(pattern).parse();
		} catch {
			return pattern;
		}

		patterns.set(pattern, nodes);
	}

	return render(nodes, params, locale(), null);
}

function render(nodes: Node[], params: Params, language: string, pound: { value: number; name: string } | null): string {
	let out = '';

	for (const node of nodes) {
		if (typeof node === 'string') {
			out += node;
		} else if (node.kind === 'pound') {
			out += pound === null ? '#' : new Intl.NumberFormat(language).format(pound.value);
		} else if (node.kind === 'argument') {
			out += argument(node, params, language);
		} else {
			out += choice(node, params, language);
		}
	}

	return out;
}

function argument(node: { name: string; type: string; style: string }, params: Params, language: string): string {
	if (!(node.name in params)) {
		return `{${node.name}}`;
	}

	const value = params[node.name];

	switch (node.type) {
		case 'number':
			return new Intl.NumberFormat(language, node.style === 'integer' ? { maximumFractionDigits: 0 } : node.style === 'percent' ? { style: 'percent' } : {}).format(Number(value));
		case 'date':
		case 'time': {
			const date  = value instanceof Date ? value : new Date(typeof value === 'number' ? value : String(value));
			const style = (['short', 'medium', 'long', 'full'].includes(node.style) ? node.style : 'medium') as 'short' | 'medium' | 'long' | 'full';

			return new Intl.DateTimeFormat(language, node.type === 'date' ? { dateStyle: style } : { timeStyle: style }).format(date);
		}
		default:
			return typeof value === 'number' ? new Intl.NumberFormat(language).format(value) : String(value);
	}
}

function choice(node: Extract<Node, { kind: 'choice' }>, params: Params, language: string): string {
	if (!(node.name in params)) {
		return `{${node.name}}`;
	}

	const value = params[node.name];

	if (node.type === 'select') {
		const options = node.options.get(String(value)) ?? node.options.get('other') ?? [];

		return render(options, params, language, null);
	}

	const number  = Number(value);
	const exact   = node.options.get(`=${number}`);
	const shifted = number - node.offset;
	const rule    = new Intl.PluralRules(language, { type: node.type === 'selectordinal' ? 'ordinal' : 'cardinal' }).select(shifted);
	const options = exact ?? node.options.get(rule) ?? node.options.get('other') ?? [];

	return render(options, params, language, { value: shifted, name: node.name });
}

/**
 * Reads an ICU message into text and arguments. Apostrophes quote as
 * ICU's do: `''` is one, and `'` before `{`, `}`, `#` (in a plural), or
 * `|` starts quoted text that runs to the next lone `'`.
 */
class Parser {
	private at = 0;

	constructor(private readonly pattern: string) {}

	parse(): Node[] {
		const nodes = this.message(false);

		if (this.at < this.pattern.length) {
			throw new Error('Unmatched }');
		}

		return nodes;
	}

	private message(inPlural: boolean): Node[] {
		const nodes: Node[] = [];
		let text = '';

		while (this.at < this.pattern.length) {
			const char = this.pattern[this.at] as string;

			if (char === '}') {
				break;
			}

			if (char === '{') {
				if (text !== '') {
					nodes.push(text);
					text = '';
				}

				nodes.push(this.argument());
				continue;
			}

			if (char === '#' && inPlural) {
				if (text !== '') {
					nodes.push(text);
					text = '';
				}

				nodes.push({ kind: 'pound' });
				this.at++;
				continue;
			}

			if (char === '\'') {
				text += this.quoted(inPlural);
				continue;
			}

			text += char;
			this.at++;
		}

		if (text !== '') {
			nodes.push(text);
		}

		return nodes;
	}

	private quoted(inPlural: boolean): string {
		const next = this.pattern[this.at + 1];

		if (next === '\'') {
			this.at += 2;

			return '\'';
		}

		if (next === undefined || !['{', '}', '|', ...(inPlural ? ['#'] : [])].includes(next)) {
			this.at++;

			return '\'';
		}

		let text = '';

		this.at++;

		while (this.at < this.pattern.length) {
			const char = this.pattern[this.at] as string;

			if (char === '\'') {
				if (this.pattern[this.at + 1] === '\'') {
					text += '\'';
					this.at += 2;
					continue;
				}

				this.at++;

				return text;
			}

			text += char;
			this.at++;
		}

		return text;
	}

	private argument(): Node {
		this.at++;

		const name = this.word();

		if (name === '') {
			throw new Error('An argument needs a name');
		}

		this.space();

		if (this.eat('}')) {
			return { kind: 'argument', name, type: '', style: '' };
		}

		this.expect(',');
		this.space();

		const type = this.word();

		this.space();

		if (type === 'plural' || type === 'selectordinal' || type === 'select') {
			this.expect(',');

			return this.choice(name, type);
		}

		let style = '';

		if (this.eat(',')) {
			const end = this.pattern.indexOf('}', this.at);

			if (end === -1) {
				throw new Error('Unclosed argument');
			}

			style   = this.pattern.slice(this.at, end).trim();
			this.at = end;
		}

		this.expect('}');

		return { kind: 'argument', name, type, style };
	}

	private choice(name: string, type: 'plural' | 'selectordinal' | 'select'): Node {
		const options = new Map<string, Node[]>();
		let offset = 0;

		this.space();

		if (type !== 'select' && this.pattern.startsWith('offset:', this.at)) {
			this.at += 'offset:'.length;
			this.space();
			offset = Number(this.word());
			this.space();
		}

		while (!this.eat('}')) {
			const selector = this.word();

			if (selector === '') {
				throw new Error('A choice needs a selector');
			}

			this.space();
			this.expect('{');
			options.set(selector, this.message(type !== 'select'));
			this.expect('}');
			this.space();
		}

		if (!options.has('other')) {
			throw new Error('A choice needs "other"');
		}

		return { kind: 'choice', name, type, offset, options };
	}

	private word(): string {
		const match = /^[^\s{}',#]+/u.exec(this.pattern.slice(this.at));
		const word  = match?.[0] ?? '';

		this.at += word.length;

		return word;
	}

	private space(): void {
		while (/\s/u.test(this.pattern[this.at] ?? '')) {
			this.at++;
		}
	}

	private eat(char: string): boolean {
		if (this.pattern[this.at] === char) {
			this.at++;

			return true;
		}

		return false;
	}

	private expect(char: string): void {
		if (!this.eat(char)) {
			throw new Error(`Expected ${char}`);
		}
	}
}
