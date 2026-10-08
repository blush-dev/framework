<script setup lang="ts">
/**
 * The Markdown source editor (D-241, D-245): a plain text area, so
 * typing, undo, spelling, and screen readers work as in any field, over a
 * highlighted copy of the same text (`markdown.ts`), where the syntax is
 * faint scaffolding and the words keep full ink (D-253, D-265), and the
 * directive or image selected (`directive`, `image`; by default, the
 * directive the caret is in) is boxed; a container only on its opening
 * and closing lines (D-268). The copy is decoration and hidden from
 * assistive tech. It sits bare in the editor's writing column and grows
 * with its text; the editor around it scrolls, counts, and holds the
 * inserters.
 *
 * Enter in a list item, quote, or table row carries its marker to the
 * next line, and on an empty one ends it (admin.md §8, Enter carries the
 * marker). The third backtick alone on a line writes the block's closing
 * fence, and Enter from the opening fence steps into it (D-313). Enter
 * at the end of a container's opening line left open writes its closing
 * line, with the caret on the line between (D-639).
 * Formatting has its usual keys (D-284, D-313): ⌘B strong, ⌘I emphasis
 * (`toggleEmphasis()`: either mark counts, nothing selected means the
 * word at the caret), ⌘E code, and ⌘⇧X struck text, each on or off; ⌘K
 * asks for the link form (`link`), and ⌘⇧K takes away the link the caret
 * is in; ⌘⌥1 to ⌘⌥6 make the line a heading of that level (again, a
 * paragraph) and ⌘⌥0 a paragraph; ⌥↑ and ⌥↓ ask to move the element the
 * caret is in (`move`); and an address pasted over selected text links
 * it. Backspace just after a block's marker takes the marker off (an
 * indented list item comes out a level first), leaving the words (D-314).
 * Text can't land in the syntax around it (D-314, `safeSpot()`,
 * `typedSpot()`): a character typed after a trailing attribute block
 * goes before it, one typed after a directive's tag that ends in `]` or
 * `}` goes on a new line, and an inserted or pasted piece is moved out of
 * a directive's own line and in front of trailing attributes. Tab and
 * Shift+Tab nest a list item one level deeper or shallower, give a quote
 * one `>` level more or less (the whole quote, or the selected lines;
 * D-315), and indent several selected lines of code by two spaces;
 * anywhere else Tab leaves the field, as in any form. Files dropped or pasted into the
 * text are passed on (`files`) to upload and insert.
 *
 * Typing `/` at the start of an empty line reports the query after it
 * (`slash`), so the editor can open the block panel filtered by it;
 * while it's open, the keys that drive the panel are passed on
 * (`slashKey`) rather than typed. The query stays in the text until a
 * block replaces it (`insert()`), so an abandoned slash is just text.
 *
 * Typing `@` in prose, given `people`, lists the profiles whose names
 * match what follows it under the caret (D-498): arrows move, Enter or
 * Tab writes `@slug`, and Escape leaves what's typed as text. Mentions
 * are highlighted where the site links them (`config.mentions`).
 * Inserting and removing go through the browser's own editing, so undo
 * takes them back in one step.
 */

import { computed, nextTick, ref } from 'vue';
import { debounced, latest } from '../action';
import { config } from '../config';
import { useFileDrop } from '../drop';
import { listMove } from '../grid';
import { htmlCheck } from '../html';
import { blocks, closingContainer, closingFence, setHtmlCheck, setMentions, continuation, directiveAt, editBetween, highlight, indentedCode, intoFence, isAddress, linkAt, linked, nested, outline, pasted, quoted, safeSpot, toggleEmphasis, toggleMark, typedSpot, unmarked, withHeading, withoutLink, type Change, type Edit, type Emphasis, type MarkdownBlock, type MarkdownOutline } from '../markdown';
import { directiveText, type DirectiveDescription } from '../directives';

const props = defineProps<{
	id: string;
	placeholder?: string;
	label?: string;
	// Whether the block panel is open for a slash, so its keys are
	// passed on.
	slashOpen?: boolean;
	// What's selected, boxed in the text: a directive's or an image's
	// index in the outline, or -1.
	directive?: number;
	image?: number;
	// Shown, not edited: a trashed entry's body (D-276).
	readonly?: boolean;
	// The body already read, when the page around has it (D-316), so a
	// keystroke reads it once.
	parsed?: MarkdownOutline;
	blocks?: MarkdownBlock[];
	// Finds profiles to mention by what's typed after `@` (D-498); without
	// it, typing `@` suggests nothing.
	people?: (query: string) => Promise<MentionSuggestion[]>;
}>();

export interface MentionSuggestion {
	slug: string;
	title: string;
}

const model = defineModel<string>({ required: true });

// Where the caret is, kept while the field doesn't have focus, so the
// settings and inserters can follow it.
const caret = defineModel<number>('caret', { default: 0 });

// Where the selection ends, which is the caret when nothing's selected.
const extent = defineModel<number>('extent', { default: 0 });

const emit = defineEmits<{
	// The query typed after a slash at the start of a line, or `null` when
	// there's no slash (any more).
	slash: [query: string | null];
	// A key for the block panel while a slash has it open.
	slashKey: [key: 'ArrowUp' | 'ArrowDown' | 'Enter' | 'Escape'];
	// Files dropped or pasted into the text.
	files: [files: File[]];
	// ⌘K: the link form, for the selection or the word at the caret.
	link: [];
	// ⌥↑ and ⌥↓: the element the caret is in, up or down.
	move: [up: boolean];
}>();

const markdown = computed(() => props.parsed ?? outline(model.value));
const current  = computed(() => props.directive ?? (props.image === undefined ? directiveAt(markdown.value.directives, caret.value) : -1));

// Raw HTML and addresses the account couldn't add are marked (D-495),
// and mentions, where they link (D-493).
setHtmlCheck(htmlCheck());
setMentions(config.mentions);

// The container whose opening or closing line the caret is on, whose
// two lines are marked as a pair, or -1.
const paired = computed(() => {
	const line = markdown.value.lines.find((item) => item.start <= caret.value && caret.value <= item.start + item.text.length);

	return (line?.kind === 'open' || line?.kind === 'close') && line.directive !== undefined ? line.directive : -1;
});

const html = computed(() => highlight(markdown.value, current.value, props.image ?? -1, props.blocks ?? blocks(markdown.value), paired.value));

const field  = ref<HTMLTextAreaElement | null>(null);
const source = ref<HTMLElement | null>(null);

// Where the slash being typed is, and one closed with Escape, which stays
// closed until it's gone.
const slash     = ref<number | null>(null);
const dismissed = ref<number | null>(null);

function track(event: Event): void {
	const element = event.target as HTMLTextAreaElement;

	caret.value  = element.selectionStart;
	extent.value = element.selectionEnd;
}

/**
 * The slash being typed at the caret, if any: `/` then a query, on a
 * line with nothing else (up to three spaces before it), outside code.
 */
function slashAt(): { at: number; query: string } | null {
	const element = field.value;

	if (element === null || element.selectionStart !== element.selectionEnd) {
		return null;
	}

	// The field's own text: the model catches up after this input event.
	const position  = element.selectionStart;
	const value     = element.value;
	const lineStart = value.lastIndexOf('\n', position - 1) + 1;
	const lineEnd   = value.indexOf('\n', position);
	const match     = /^ {0,3}\/([\w /-]*)$/.exec(value.slice(lineStart, position));

	if (match === null || value.slice(position, lineEnd === -1 ? undefined : lineEnd).trim() !== '') {
		return null;
	}

	if (outline(value).lines.find((item) => item.start === lineStart)?.kind === 'code') {
		return null;
	}

	return { at: lineStart + match[0].indexOf('/'), query: match[1] ?? '' };
}

/**
 * The box of the character at an offset in a text node (its left edge),
 * or of the one before it (its right edge) at the node's end or a line's,
 * or `null` when there's none to measure. A collapsed range would be
 * simpler, but at the start of a node on a line of its own Chrome gives
 * it no box at all, which put the mention list at the window's top.
 */
function characterRect(node: Node, at: number): { left: number; top: number; bottom: number } | null {
	const length = node.nodeValue?.length ?? 0;
	const range  = document.createRange();
	const after  = at < length && node.nodeValue?.[at] !== '\n';

	if (after) {
		range.setStart(node, at);
		range.setEnd(node, at + 1);
	} else if (at > 0 && node.nodeValue?.[at - 1] !== '\n') {
		range.setStart(node, at - 1);
		range.setEnd(node, at);
	} else {
		// An empty line: between two breaks, the collapsed range is all
		// there is.
		range.setStart(node, at);
		range.collapse(true);
	}

	const rect = range.getClientRects()[0] ?? range.getBoundingClientRect();

	if (rect === undefined || (rect.width === 0 && rect.height === 0)) {
		return null;
	}

	return { left: after ? rect.left : rect.right, top: rect.top, bottom: rect.bottom };
}

/**
 * Where an offset in the body is on screen, measured on the highlighted
 * copy, which has every character of the text in the same place but one:
 * a code block is a block of its own, so the line break after it isn't
 * in the copy, and is counted here.
 */
function caretRect(offset: number): { left: number; top: number; bottom: number } {
	const copy = source.value?.querySelector('.md__highlight');

	if (copy instanceof HTMLElement) {
		const walker = document.createTreeWalker(copy, NodeFilter.SHOW_TEXT);
		let seen  = 0;
		let block: Element | null = null;

		for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
			const length = node.nodeValue?.length ?? 0;
			const inside = node.parentElement?.closest('.md-codeblock') ?? null;

			if (block !== null && inside !== block) {
				seen++;
			}

			block = inside;

			if (seen + length >= offset) {
				// Never before the node: the break after a code block is at its start.
				const at   = Math.max(0, offset - seen);
				const rect = characterRect(node, at);

				if (rect !== null) {
					return rect;
				}
			}

			seen += length;
		}
	}

	const rect = field.value?.getBoundingClientRect();

	return { left: rect?.left ?? 0, top: rect?.top ?? 0, bottom: rect?.top ?? 0 };
}

// The mention being typed (D-498): where its `@` is, what follows it,
// the profiles that match, the one picked, and where the list goes.
// One closed with Escape stays closed until it's gone.
const mention          = ref<{ at: number; query: string } | null>(null);
const mentionDismissed = ref<number | null>(null);
const suggestions      = ref<MentionSuggestion[]>([]);
const suggested        = ref(0);
const suggestAt        = ref<{ left: number; top: number; above: boolean } | null>(null);
// Whether an answer for what's typed is still to come.
const searching        = ref(false);
const suggestLater     = debounced((query: string) => void suggest(query), 120);
const suggestAsk       = latest();

// What a mention typed so far may be: nothing yet, or a name's start.
const TYPED_MENTION = /(?:^|[^\p{L}\p{N}_@`])@([A-Za-z0-9_-]{0,64})$/u;

/**
 * The mention being typed at the caret, if any: `@` and a name's start,
 * not inside a word, an address, or code.
 */
function mentionAt(): { at: number; query: string } | null {
	const element = field.value;

	if (element === null || props.people === undefined || !config.mentions || element.selectionStart !== element.selectionEnd) {
		return null;
	}

	const position  = element.selectionStart;
	const value     = element.value;
	const lineStart = value.lastIndexOf('\n', position - 1) + 1;
	const before    = value.slice(lineStart, position);
	const match     = TYPED_MENTION.exec(before);

	// Inside inline code, the backticks before it are odd.
	if (match === null || (before.split('`').length - 1) % 2 === 1) {
		return null;
	}

	const kind = outline(value).lines.find((item) => item.start === lineStart)?.kind;

	if (kind !== 'text' && kind !== 'heading') {
		return null;
	}

	const query = match[1] ?? '';

	return { at: position - query.length - 1, query };
}

function closeSuggestions(): void {
	suggestLater.cancel();
	// An answer still to come is no longer the latest.
	suggestAsk();
	mention.value     = null;
	suggestions.value = [];
	suggestAt.value   = null;
	searching.value   = false;
}

// Whether a profile matches what's typed after `@`, as the server
// searches: its name or slug has it in it.
function matches(person: MentionSuggestion, query: string): boolean {
	const typed = query.toLowerCase();

	return person.slug.toLowerCase().includes(typed) || person.title.toLowerCase().includes(typed);
}

/**
 * Follows the mention at the caret, asking for the profiles that match.
 * Only typing opens the list (`open`); moving the caret onto a mention
 * already written doesn't, but moving off one closes it.
 */
function followMention(open = true): void {
	const found = mentionAt();

	if (found === null || found.at === mentionDismissed.value) {
		if (found === null) {
			mentionDismissed.value = null;
		}

		if (mention.value !== null) {
			closeSuggestions();
		}

		return;
	}

	if (mention.value?.at === found.at && mention.value.query === found.query) {
		return;
	}

	if (mention.value === null && !open) {
		return;
	}

	mention.value = found;

	// Until the answer comes, only what's listed that still matches stays,
	// so Enter can't pick a name typed past; the list stays open,
	// saying it's looking when nothing listed matches.
	suggestions.value = suggestions.value.filter((person) => matches(person, found.query));
	suggested.value   = 0;
	searching.value   = true;
	placeSuggestions();
	suggestLater(found.query);
}

async function suggest(query: string): Promise<void> {
	const current = suggestAsk();
	const people  = props.people;

	if (people === undefined) {
		return;
	}

	let found: MentionSuggestion[] = [];

	try {
		found = await people(query);
	} catch {
		found = [];
	}

	const typed = mention.value;

	if (!current() || typed === null) {
		return;
	}

	// An answer for less than what's typed now is narrowed to it, and the
	// answer for the rest is still to come.
	suggestions.value = found.filter((person) => matches(person, typed.query)).slice(0, 8);
	suggested.value   = 0;
	searching.value   = typed.query !== query;
	placeSuggestions();
}

// Under the `@`, or over it when there's more room above.
function placeSuggestions(): void {
	const at  = mention.value?.at;
	const box = source.value?.getBoundingClientRect();

	if (at === undefined || box === undefined) {
		suggestAt.value = null;

		return;
	}

	const rect  = caretRect(at);
	const above = window.innerHeight - rect.bottom < 260 && rect.top > window.innerHeight - rect.bottom;

	suggestAt.value = { left: Math.max(0, Math.min(rect.left - box.left, box.width - 240)), top: (above ? rect.top : rect.bottom) - box.top, above };
}

/**
 * Writes the profile picked as `@slug`, in place of what's typed, with a
 * space after it at the end of a line.
 */
function chooseMention(person: MentionSuggestion | undefined): void {
	const element = field.value;
	const typed   = mention.value;

	if (element === null || typed === null || person === undefined) {
		return;
	}

	const end   = typed.at + 1 + typed.query.length;
	const next  = element.value[end];
	const space = next === undefined || next === '\n' ? ' ' : '';

	mentionDismissed.value = typed.at;
	closeSuggestions();
	replace(element, typed.at, end, `@${person.slug}${space}`);
}

// While the list is open, it takes the keys that drive it: the arrows,
// Enter, and Tab while it has names, and Escape. Returns whether the key
// was used.
function mentionKey(event: KeyboardEvent): boolean {
	const count = suggestions.value.length;

	if (mention.value === null || suggestAt.value === null || event.metaKey || event.ctrlKey || event.altKey) {
		return false;
	}

	if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && count > 0) {
		suggested.value = listMove(event.key, suggested.value, count) ?? 0;
	} else if ((event.key === 'Enter' || event.key === 'Tab') && count > 0) {
		chooseMention(suggestions.value[suggested.value]);
	} else if (event.key === 'Escape') {
		mentionDismissed.value = mention.value.at;
		closeSuggestions();
	} else {
		return false;
	}

	event.preventDefault();
	event.stopPropagation();

	return true;
}

// Reports a slash as it's typed, changed, or gone.
function followSlash(): void {
	const found = slashAt();

	if (found === null || found.at === dismissed.value) {
		if (found === null) {
			dismissed.value = null;
		}

		if (slash.value !== null) {
			slash.value = null;
			emit('slash', null);
		}

		return;
	}

	slash.value = found.at;
	emit('slash', found.query);
}

/**
 * Leaves the slash being typed as text: the panel was closed.
 */
function dismissSlash(): void {
	if (slash.value !== null) {
		dismissed.value = slash.value;
		slash.value     = null;
	}
}

function input(event: Event): void {
	track(event);
	followSlash();
	followMention();

	// The third backtick writes the rest of the block, as an edit of its
	// own, so undo takes back the closing fence first.
	const element = field.value;

	if (event instanceof InputEvent && event.inputType === 'insertText' && event.data === '`' && element !== null && !props.readonly) {
		const edit = closingFence(element.value, element.selectionStart);

		if (edit !== null) {
			apply(edit, edit.from);
		}
	}
}

// A key that moves the caret may leave the mention being typed.
function keyup(event: KeyboardEvent): void {
	track(event);

	if (!['ArrowDown', 'ArrowUp', 'Enter', 'Tab', 'Escape'].includes(event.key) || suggestions.value.length === 0) {
		followMention(false);
	}
}

// Clicking elsewhere in the text leaves a slash as text.
function clicked(event: Event): void {
	track(event);
	followMention(false);

	if (slash.value !== null) {
		dismissSlash();
		emit('slash', null);
	}
}

// While a slash has the panel open, it takes the keys that drive it;
// otherwise Enter in a list, quote, or table carries its marker down.
function keydown(event: KeyboardEvent): void {
	if (mentionKey(event)) {
		return;
	}

	if (!props.slashOpen || slash.value === null) {
		if (!shortcut(event)) {
			carry(event);
		}

		return;
	}

	const key = event.key === 'Tab' ? 'Enter' : event.key;

	if (key === 'ArrowUp' || key === 'ArrowDown' || key === 'Enter' || key === 'Escape') {
		event.preventDefault();

		if (key === 'Escape') {
			dismissSlash();
		}

		emit('slashKey', key);
	}
}

// ⌘ and a letter; struck and highlighted text take ⇧ as well.
const MARKS: Record<string, Emphasis | '`'> = { b: 'strong', i: 'em', e: '`', x: 'strike', h: 'marked' };
const SHIFTED = new Set(['x', 'h']);

/**
 * The editing keys: formatting, a link, and nesting a list item. Returns
 * whether the key was used.
 */
function shortcut(event: KeyboardEvent): boolean {
	const element = field.value;
	const command = event.metaKey || event.ctrlKey;
	const key     = event.key.toLowerCase();

	if (element === null || props.readonly || event.isComposing) {
		return false;
	}

	// Headings by level: the digit's key, since Option changes the
	// character it types on a Mac.
	const digit = /^(?:Digit|Numpad)([0-6])$/.exec(event.code)?.[1];

	if (command && event.altKey && !event.shiftKey && digit !== undefined) {
		event.preventDefault();
		heading(Number(digit));

		return true;
	}

	if (event.altKey && !command && !event.shiftKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
		event.preventDefault();
		emit('move', event.key === 'ArrowUp');

		return true;
	}

	if (event.altKey) {
		return false;
	}

	const mark = MARKS[key];

	if (command && SHIFTED.has(key) === event.shiftKey && mark !== undefined) {
		event.preventDefault();

		if (mark === '`') {
			code();
		} else {
			emphasis(mark);
		}

		return true;
	}

	// In the text, ⌘K is a link, and ⌘⇧K takes one away; the command
	// palette keeps ⌘K everywhere else.
	if (command && key === 'k') {
		event.preventDefault();
		event.stopPropagation();

		if (event.shiftKey) {
			unlink();
		} else {
			emit('link');
		}

		return true;
	}

	if (key === 'backspace' && !command && !event.shiftKey && element.selectionStart === element.selectionEnd) {
		const change = unmarked(element.value, element.selectionStart);

		if (change !== null) {
			event.preventDefault();
			applyChange(change);

			return true;
		}
	}

	// Tab moves a block (D-284, D-315): a list item nests, a quote gains or
	// loses a level, and several lines of code indent. Elsewhere it leaves
	// the text, as in any form.
	if (key === 'tab' && !command) {
		const [start, end, outdent] = [element.selectionStart, element.selectionEnd, event.shiftKey];
		const change = nested(element.value, start, end, outdent) ?? quoted(element.value, start, end, outdent) ?? indentedCode(element.value, start, end, outdent);

		if (change !== null) {
			event.preventDefault();
			applyChange(change);

			return true;
		}
	}

	return false;
}

/**
 * Applies a change to the whole text as one edit undo takes back, and
 * leaves its selection.
 */
function applyChange(change: Change): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	const edit = editBetween(element.value, change.text);

	replace(element, edit.from, edit.to, edit.text);
	element.setSelectionRange(change.from, change.to);
	caret.value  = change.from;
	extent.value = change.to;
}

/**
 * Turns strong, emphasized, struck, or highlighted text on or off for
 * the selection, or the word at the caret.
 */
function emphasis(kind: Emphasis): void {
	const element = field.value;

	if (element !== null && !props.readonly) {
		applyChange(toggleEmphasis(element.value, element.selectionStart, element.selectionEnd, kind));
	}
}

/**
 * Turns inline code on or off for the selection.
 */
function code(): void {
	const element = field.value;

	if (element !== null && !props.readonly) {
		applyChange(toggleMark(element.value, element.selectionStart, element.selectionEnd, '`'));
	}
}

/**
 * Takes away the link the caret is in, leaving its words.
 */
function unlink(): void {
	const element = field.value;
	const found   = element === null || props.readonly ? null : linkAt(element.value, element.selectionStart, element.selectionEnd);

	if (element !== null && found !== null) {
		applyChange(withoutLink(element.value, found));
	}
}

/**
 * Makes the selected lines headings of a level, or paragraphs (0).
 */
function heading(level: number): void {
	const element = field.value;
	const change  = element === null || props.readonly ? null : withHeading(element.value, element.selectionStart, element.selectionEnd, level);

	if (change !== null) {
		applyChange(change);
	}
}


/**
 * A character typed where it would break the syntax goes where it means
 * what it looks like instead.
 */
function beforeinput(event: InputEvent): void {
	const element = field.value;

	if (element === null || props.readonly || event.isComposing || event.inputType !== 'insertText' || event.data === null || event.data.length !== 1 || element.selectionStart !== element.selectionEnd) {
		return;
	}

	const spot = typedSpot(element.value, element.selectionStart);

	if (spot !== null) {
		event.preventDefault();
		apply({ from: spot.at, to: spot.at, text: spot.before + event.data });
	}
}

// An address pasted over selected words links them; files are passed on;
// text pasted into a directive's tag or attributes goes where it's safe.
// Pasted containers are balanced and written `:::`, and a directive goes
// on lines of its own, as the inserter puts it (D-321).
function paste(event: ClipboardEvent): void {
	const element = field.value;
	const data    = event.clipboardData;

	if (element === null || data === null || props.readonly) {
		return;
	}

	const files = [...data.files];

	if (files.length > 0) {
		event.preventDefault();
		emit('files', files);

		return;
	}

	const raw      = data.getData('text/plain');
	const piece    = pasted(raw);
	const text     = piece.text;
	const selected = element.value.slice(element.selectionStart, element.selectionEnd);

	if (selected !== '' && !selected.includes('\n') && !isAddress(selected) && isAddress(text)) {
		event.preventDefault();
		applyChange(linked(element.value, element.selectionStart, element.selectionEnd, text.trim()));

		return;
	}

	if (piece.block) {
		const spot = place(false);

		event.preventDefault();

		if (spot !== null) {
			write(spot, text, text.length);
		}

		return;
	}

	if (selected === '') {
		const spot = safeSpot(element.value, element.selectionStart);

		if (spot.at !== element.selectionStart || spot.before !== '' || text !== raw) {
			event.preventDefault();
			apply({ from: spot.at, to: spot.at, text: spot.before + text });
		}
	} else if (text !== raw) {
		event.preventDefault();
		apply({ from: element.selectionStart, to: element.selectionEnd, text });
	}
}

// Files dropped on the text go where the caret is.
const { dragging: dropping, over: dragover, leave: dragleave, drop } = useFileDrop((files) => emit('files', files), () => !props.readonly);

/**
 * Enter in a list item, quote, or table row starts the next one, and on
 * an empty one ends it (`continuation()`), as one edit undo takes back.
 */
function carry(event: KeyboardEvent): void {
	const element = field.value;

	if (event.key !== 'Enter' || event.shiftKey || event.metaKey || event.ctrlKey || event.altKey || event.isComposing || props.readonly || element === null || element.selectionStart !== element.selectionEnd) {
		return;
	}

	// From an opening fence, into the block's empty first line.
	const into = intoFence(element.value, element.selectionStart);

	if (into !== null) {
		event.preventDefault();
		element.setSelectionRange(into, into);
		caret.value = extent.value = into;

		return;
	}

	// From a container's opening line left open, its closing line too,
	// with the caret on the line between.
	const closer = closingContainer(element.value, element.selectionStart);

	if (closer !== null) {
		event.preventDefault();
		apply(closer.edit, closer.caret);

		return;
	}

	const next = continuation(element.value, element.selectionStart);

	if (next !== null) {
		event.preventDefault();
		apply(editBetween(element.value, next.text), next.caret);
	}
}

/**
 * Replaces part of the text the way typing would, so undo takes it back.
 */
function replace(element: HTMLTextAreaElement, from: number, to: number, text: string): void {
	// Select first: focusing scrolls to the selection, which is at the
	// end of the text in a field that hasn't had focus yet.
	element.setSelectionRange(from, to);
	element.focus();

	// `execCommand` is deprecated but still the only way to edit a text
	// area that the browser's undo knows about.
	if (!document.execCommand('insertText', false, text)) {
		element.setRangeText(text, from, to, 'end');
		element.dispatchEvent(new Event('input', { bubbles: true }));
	}
}

/**
 * Applies an edit the way typing would, so undo takes it back, and puts
 * the caret at `at` (an offset in the new text), or after the edit.
 */
function apply(edit: Edit, at?: number): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	replace(element, edit.from, edit.to, edit.text);

	const position = at ?? edit.from + edit.text.length;

	element.setSelectionRange(position, position);
	caret.value = extent.value = position;
}

/**
 * Puts the caret at an offset and scrolls it into view.
 */
function focusAt(offset: number): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	element.focus({ preventScroll: true });
	element.setSelectionRange(offset, offset);
	caret.value = extent.value = offset;
	reveal();
}

/**
 * Scrolls the caret into view when it's near an edge or out of it.
 */
function reveal(): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	const rect     = caretRect(element.selectionStart);
	const scroller = element.closest('[data-scroller]');

	if (scroller instanceof HTMLElement) {
		const box = scroller.getBoundingClientRect();

		if (rect.top < box.top + 40 || rect.bottom > box.bottom - 40) {
			scroller.scrollTop += rect.top - box.top - box.height / 3;
		}
	}
}

/**
 * Where text goes: an inline piece at the caret (replacing any
 * selection); a block on lines of its own, with a blank line either side
 * (after the caret's line when it has text, or in place of a line that's
 * empty or selected whole). The slash being typed, if any, is replaced.
 * Returns the range to replace, what goes before and after, and the
 * selected text.
 */
function place(inline: boolean): { from: number; to: number; before: string; after: string; inner: string } | null {
	const element = field.value;

	if (element === null) {
		return null;
	}

	const value = element.value;
	let from    = slash.value ?? element.selectionStart;
	let to      = slash.value === null ? element.selectionEnd : element.selectionStart;
	const inner = slash.value === null ? value.slice(from, to) : '';

	const lineStart = value.lastIndexOf('\n', from - 1) + 1;
	const lineEnd   = value.indexOf('\n', to) === -1 ? value.length : value.indexOf('\n', to);
	const alone     = value.slice(lineStart, from).trim() === '' && value.slice(to, lineEnd).trim() === '';

	let before = '';
	let after  = '';

	// An inline piece can't go in a directive's tag or attributes.
	if (inline && from === to) {
		const spot = safeSpot(value, from);

		from   = to = spot.at;
		before = spot.before;
	}

	if (!inline) {
		if (!alone && inner === '') {
			// A block can't start mid-line: it goes after this one.
			from = to = lineEnd;
			before = '\n\n';
		} else {
			from = alone ? lineStart : from;
			to   = alone ? lineEnd : to;
			before = from > 0 && value[from - 1] !== '\n' ? '\n\n' : (from > 1 && value[from - 2] !== '\n' ? '\n' : '');
		}

		const next = value.slice(to);

		after = next === '' || next.startsWith('\n\n') ? '' : (next.startsWith('\n') ? '\n' : '\n\n');
	}

	return { from, to, before, after, inner };
}

/**
 * Writes `text` where `place()` says, the way typing would, with the
 * caret at `at` in it, or `at` to `end` selected.
 */
function write(spot: { from: number; to: number; before: string; after: string }, text: string, at: number, end = at): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	const base = spot.from + spot.before.length;

	slash.value = null;
	replace(element, spot.from, spot.to, spot.before + text + spot.after);
	element.setSelectionRange(base + at, base + end);
	caret.value  = element.selectionStart;
	extent.value = element.selectionEnd;
}

/**
 * Inserts a directive: an inline one at the caret, the rest on lines of
 * their own (D-247). Selected text becomes its label or body; `values`
 * are its first attributes.
 */
function insert(described: DirectiveDescription, values: Record<string, string> = {}): void {
	const selected = field.value === null ? '' : field.value.value.slice(field.value.selectionStart, field.value.selectionEnd);
	const inline   = described.kind === 'inline' && !selected.includes('\n');
	const spot     = place(inline);

	if (spot !== null) {
		const { text, caret: at } = directiveText(described, inline, spot.inner, values);

		write(spot, text, at);
	}
}

/**
 * Inserts a block of Markdown (such as an image) on lines of its own,
 * with the caret at `caret` in it, or `caret` to `end` selected (a
 * placeholder, which the first keystroke replaces). `text` is given the
 * selected text, if any, to build from.
 */
function insertBlock(build: (selected: string) => { text: string; caret: number; end?: number }): void {
	const spot = place(false);

	if (spot !== null) {
		const { text, caret: at, end } = build(spot.inner);

		write(spot, text, at, end);
	}
}

/**
 * Inserts text at the caret (replacing any selection), with the caret
 * after it.
 */
function insertText(text: string): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	const spot = element.selectionStart === element.selectionEnd ? safeSpot(element.value, element.selectionStart) : null;

	apply(spot === null ? { from: element.selectionStart, to: element.selectionEnd, text } : { from: spot.at, to: spot.at, text: spot.before + text });
}

/**
 * The selected text, if any.
 */
function selection(): string {
	const element = field.value;

	return element === null ? '' : element.value.slice(element.selectionStart, element.selectionEnd);
}

/**
 * Applies a change to the text, then scrolls the caret into view, once
 * the highlighted copy it's measured on has caught up.
 */
async function change(next: Change): Promise<void> {
	applyChange(next);
	await nextTick();
	reveal();
}

defineExpose({ apply, change, focusAt, insert, insertBlock, insertText, selection, dismissSlash, emphasis, code, unlink, heading });
</script>

<template>
	<div class="md">
		<div ref="source" class="md__source" :class="{ 'is-dropping': dropping }" @dragover="dragover" @dragleave="dragleave" @drop="drop">
			<pre class="md__highlight" aria-hidden="true" v-html="html" />
			<textarea
				:id="props.id"
				ref="field"
				v-model="model"
				class="md__field"
				rows="1"
				:spellcheck="!props.readonly"
				:readonly="props.readonly"
				:aria-label="props.label"
				:placeholder="props.placeholder"
				:aria-controls="suggestAt ? `${props.id}-people` : undefined"
				:aria-activedescendant="suggestAt && suggestions.length ? `${props.id}-person-${suggested}` : undefined"
				@beforeinput="beforeinput"
				@input="input"
				@keydown="keydown"
				@keyup="keyup"
				@click="clicked"
				@select="track"
				@focus="track"
				@blur="closeSuggestions"
				@paste="paste"
			/>
			<ul
				v-if="suggestAt"
				:id="`${props.id}-people`"
				class="md__people"
				:class="{ 'is-above': suggestAt.above }"
				:style="{ left: `${suggestAt.left}px`, top: `${suggestAt.top}px` }"
				role="listbox"
				aria-label="Profiles to mention"
			>
				<li
					v-for="(person, index) in suggestions"
					:id="`${props.id}-person-${index}`"
					:key="person.slug"
					class="md__person"
					:class="{ 'is-active': index === suggested }"
					role="option"
					:aria-selected="index === suggested"
					@mousedown.prevent="chooseMention(person)"
					@mouseenter="suggested = index"
				>
					<span class="md__person-name">{{ person.title }}</span>
					<span class="md__person-slug">@{{ person.slug }}</span>
				</li>
				<li v-if="suggestions.length === 0" class="md__people-note" role="presentation">{{ searching ? 'Finding profiles…' : 'No profile matches' }}</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
/*
 * The field and its highlighted copy share one grid cell and every
 * property that affects where text falls, so each character of the copy
 * sits under the same character in the field. Highlights never change a
 * character's advance: color, weight (Fira Code's own faces), slant (it
 * has no italic), background, decoration, vertical padding, radius, and
 * box shadow only; never size, letter spacing, family, or horizontal
 * padding or margin (D-253, D-254, D-265). The column can't grow past its
 * container for a long word or address; those wrap.
 */

.md__source {
	position: relative;
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	min-height: 40vh;
}

.md__highlight,
.md__field {
	grid-area: 1 / 1;
	margin: 0;
	padding: 0;
	border: 0;
	font-family: var(--font-mono);
	font-size: var(--doc);
	font-weight: 400;
	line-height: 2;
	letter-spacing: normal;
	tab-size: 4;
	white-space: pre-wrap;
	overflow-wrap: break-word;
	word-break: normal;
}

.md__highlight {
	color: var(--fg);
	pointer-events: none;
}

.md__field {
	width: 100%;
	min-width: 0;
	height: 100%;
	overflow: hidden;
	background: transparent;
	color: transparent;
	caret-color: var(--accent);
	resize: none;
}

.md__field::placeholder {
	color: var(--fg-3);
}

/* The field's text is transparent, so its selection is a tint over the
   highlighted copy, never a fill that would hide it. */
.md__field::selection {
	background: color-mix(in srgb, var(--accent) 26%, transparent);
	color: transparent;
}

.md__highlight ::selection {
	background: transparent;
}

/* A file held over the text says it can be dropped there. */
.md__source.is-dropping {
	border-radius: var(--r-2);
	box-shadow: 0 0 0 2px var(--accent-line);
}

.md__field:focus-visible {
	outline: none;
}

.md__highlight :deep(.md-mark) {
	color: var(--fg-3);
}

/* Headings read by weight, not size. */
.md__highlight :deep(.md-heading) {
	color: var(--fg);
	font-weight: 500;
}

.md__highlight :deep(.md-heading--1),
.md__highlight :deep(.md-heading--2),
.md__highlight :deep(.md-strong__text) {
	font-weight: 600;
}

.md__highlight :deep(.md-strong__text),
.md__highlight :deep(.md-em__text) {
	color: var(--fg);
}

.md__highlight :deep(.md-em__text) {
	font-style: italic;
}

.md__highlight :deep(.md-strike__text) {
	color: var(--fg-3);
	text-decoration: line-through;
}

/* Raw HTML reads as markup (D-495); what the account couldn't add is
   underlined in the danger color, and a save that adds it is refused. */
.md__highlight :deep(.md-html) {
	color: var(--fg-3);
}

.md__highlight :deep(.md-refused) {
	color: var(--danger);
	text-decoration: wavy underline;
	text-decoration-color: var(--danger);
}

/* A mention reads as a link would (D-493). */
.md__highlight :deep(.md-mention) {
	color: var(--accent);
}

/* The profiles to mention, under the `@` being typed (D-498). */
.md__people {
	position: absolute;
	z-index: 50;
	display: grid;
	width: 240px;
	max-height: 240px;
	margin: 6px 0 0;
	padding: 5px;
	overflow-y: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
	font-family: var(--font-ui);
	font-size: var(--base);
	list-style: none;
	overscroll-behavior: contain;
}

.md__people.is-above {
	margin: 0;
	transform: translateY(calc(-100% - 6px));
}

.md__person {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: var(--s-3);
	padding: var(--s-2) var(--s-3);
	border-radius: var(--r-1);
	color: var(--fg-2);
	cursor: pointer;
}

.md__person.is-active {
	background: var(--accent-soft);
	color: var(--accent);
}

.md__people-note {
	padding: var(--s-2) var(--s-3);
	color: var(--fg-3);
}

.md__person-slug {
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* A highlighter's yellow, as the site's <mark> is by default. */
.md__highlight :deep(.md-marked__text) {
	background: var(--warn-soft);
	color: var(--fg);
}

.md__highlight :deep(.md-quote) {
	color: var(--fg-2);
}

.md__highlight :deep(.md-bullet) {
	color: var(--fg-2);
	font-weight: 600;
}

/* A task's box; its text is never struck through. */
.md__highlight :deep(.md-task) {
	color: var(--fg-3);
	font-weight: 500;
}

.md__highlight :deep(.md-task--done) {
	color: var(--good);
}

/* A table's header cells, and a delimiter row's alignment colons, the
   one part of it that says something. */
.md__highlight :deep(.md-th) {
	color: var(--fg);
	font-weight: 600;
}

.md__highlight :deep(.md-th .md-mark) {
	font-weight: 400;
}

.md__highlight :deep(.md-talign) {
	color: var(--fg-2);
	font-weight: 600;
}

/* A definition list's term reads heavier; its definitions are prose. */
.md__highlight :deep(.md-dt) {
	color: var(--fg);
	font-weight: 600;
}

.md__highlight :deep(.md-dd) {
	color: var(--fg);
}

/* A rule is a divider: it should divide. */
.md__highlight :deep(.md-rule) {
	color: var(--fg-2);
	font-weight: 500;
}

/* A fenced block is the one place the source is the content, so the
   whole run, fences included, is one box (admin.md §8, The fenced block
   is a box). Horizontally, a negative margin cancels the padding, so the
   text keeps the column's width and wraps where the field wraps it.
   Vertically, nothing: the line height's own leading is the inset, and
   padding, margins, or a border would add height the field doesn't
   have, so the hairline is an inset shadow. */
.md__highlight :deep(.md-codeblock) {
	display: block;
	margin-inline: calc(-1 * var(--s-3));
	padding-inline: var(--s-3);
	border-radius: var(--r-2);
	background: var(--surface-2);
	box-shadow: inset 0 0 0 1px var(--border);
	color: var(--fg-2);
}

.md__highlight :deep(.md-fence__lang) {
	color: var(--fg-2);
	font-weight: 500;
}

.md__highlight :deep(.md-code) {
	padding-block: 1px;
	border-radius: 3px;
	background: var(--surface-2);
	color: var(--fg-2);
}

.md__highlight :deep(.md-code),
.md__highlight :deep(.md-attr),
.md__highlight :deep(.md-directive.is-current),
.md__highlight :deep(.md-selected) {
	-webkit-box-decoration-break: clone;
	box-decoration-break: clone;
}

/* A link's label is read in the sentence; its address isn't. An image is
   a link that points at a picture, so its alt text reads the same way. */
.md__highlight :deep(.md-link__text),
.md__highlight :deep(.md-image__text),
.md__highlight :deep(.md-footnote) {
	color: var(--accent);
}

.md__highlight :deep(.md-link__url) {
	color: var(--fg-3);
}

/* An image's quoted title is its caption on the site, which the reader
   sees: full ink, a step heavier. */
.md__highlight :deep(.md-image__caption) {
	color: var(--fg);
	font-weight: 500;
}

/* A directive is named in the accent and left unboxed; the box is for
   the one the caret is in. */
.md__highlight :deep(.md-directive__name) {
	color: var(--accent);
	font-weight: 500;
}

.md__highlight :deep(.md-directive__label) {
	color: var(--fg);
}

/* A container's opening and closing colons, while the caret is on
   either line: a pair, as matching brackets are. */
.md__highlight :deep(.md-mark--pair) {
	color: var(--accent);
}

/* A closing line names what it closes, by its full name, after its
   colons. Out of the line's flow, so it takes no width the field's
   line doesn't; a step smaller than the source, so it reads as a note
   on the line rather than part of it. */
.md__highlight :deep(.md-closes)::after {
	content: attr(data-name);
	position: absolute;
	margin-inline-start: var(--s-2);
	font-size: var(--text-sm);
	line-height: calc(var(--doc) * 2);
	color: var(--fg-3);
	white-space: nowrap;
}

.md__highlight :deep(.md-directive.is-current),
.md__highlight :deep(.md-selected) {
	padding-block: 1px;
	border-radius: 3px;
	background: var(--accent-soft);
	box-shadow: 0 0 0 1px var(--accent-line);
}

/* Attributes are metadata about the line they hang off: a gray chip,
   wherever they appear, with the names in full ink. */
.md__highlight :deep(.md-attr) {
	padding-block: 1px;
	border-radius: 3px;
	background: var(--surface-2);
	box-shadow: 0 0 0 1px var(--border);
	color: var(--fg-3);
}

.md__highlight :deep(.md-attr__name) {
	color: var(--fg);
	font-weight: 500;
}

.md__highlight :deep(.md-attr__key),
.md__highlight :deep(.md-attr__value) {
	color: var(--fg-2);
}
</style>
