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
 * fence, and Enter from the opening fence steps into it (D-313).
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
 * (`slash`), so the editor can open the component panel filtered by it;
 * while it's open, the keys that drive the panel are passed on
 * (`slashKey`) rather than typed. The query stays in the text until a
 * component replaces it (`insert()`), so an abandoned slash is just text.
 * Inserting and removing go through the browser's own editing, so undo
 * takes them back in one step.
 */

import { computed, nextTick, ref } from 'vue';
import { blocks, closingFence, continuation, directiveAt, editBetween, highlight, indentedCode, intoFence, isAddress, linkAt, linked, nested, outline, pasted, quoted, safeSpot, toggleEmphasis, toggleMark, typedSpot, unmarked, withHeading, withoutLink, type Change, type Edit, type Emphasis, type MarkdownBlock, type MarkdownOutline } from '../markdown';
import { directiveText, type ComponentDescription } from '../components';

const props = defineProps<{
	id: string;
	placeholder?: string;
	label?: string;
	// Whether the component panel is open for a slash, so its keys are
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
}>();

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
	// A key for the component panel while a slash has it open.
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

const html = computed(() => highlight(markdown.value, current.value, props.image ?? -1, props.blocks ?? blocks(markdown.value)));

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
 * Where an offset in the body is on screen, measured on the highlighted
 * copy, which has every character of the text in the same place.
 */
function caretRect(offset: number): { left: number; top: number; bottom: number } {
	const copy = source.value?.querySelector('.md__highlight');

	if (copy instanceof HTMLElement) {
		const walker = document.createTreeWalker(copy, NodeFilter.SHOW_TEXT);
		let seen = 0;

		for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
			const length = node.nodeValue?.length ?? 0;

			if (seen + length >= offset) {
				const range = document.createRange();

				range.setStart(node, offset - seen);
				range.collapse(true);

				const rect = range.getBoundingClientRect();

				return { left: rect.left, top: rect.top, bottom: rect.bottom };
			}

			seen += length;
		}
	}

	const rect = field.value?.getBoundingClientRect();

	return { left: rect?.left ?? 0, top: rect?.top ?? 0, bottom: rect?.top ?? 0 };
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

// Clicking elsewhere in the text leaves a slash as text.
function clicked(event: Event): void {
	track(event);

	if (slash.value !== null) {
		dismissSlash();
		emit('slash', null);
	}
}

// While a slash has the panel open, it takes the keys that drive it;
// otherwise Enter in a list, quote, or table carries its marker down.
function keydown(event: KeyboardEvent): void {
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

const MARKS: Record<string, Emphasis | '`'> = { b: 'strong', i: 'em', e: '`', x: 'strike' };

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

	if (command && (key === 'x' ? event.shiftKey : !event.shiftKey) && mark !== undefined) {
		event.preventDefault();

		if (mark === '`') {
			applyChange(toggleMark(element.value, element.selectionStart, element.selectionEnd, '`'));
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
 * Turns strong, emphasized, or struck text on or off for the selection,
 * or the word at the caret.
 */
function emphasis(kind: Emphasis): void {
	const element = field.value;

	if (element !== null && !props.readonly) {
		applyChange(toggleEmphasis(element.value, element.selectionStart, element.selectionEnd, kind));
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
// Pasted containers are balanced and written `:::`, and a component goes
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
const dropping = ref(false);

function dragover(event: DragEvent): void {
	if (!props.readonly && [...(event.dataTransfer?.types ?? [])].includes('Files')) {
		event.preventDefault();
		dropping.value = true;
	}
}

function drop(event: DragEvent): void {
	dropping.value = false;

	const files = [...(event.dataTransfer?.files ?? [])];

	if (files.length > 0 && !props.readonly) {
		event.preventDefault();
		emit('files', files);
	}
}

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
	element.focus();
	element.setSelectionRange(from, to);

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
 * Inserts a component: an inline one at the caret, the rest on lines of
 * their own (D-247). Selected text becomes its label or body; `values`
 * are its first attributes.
 */
function insert(component: ComponentDescription, values: Record<string, string> = {}): void {
	const selected = field.value === null ? '' : field.value.value.slice(field.value.selectionStart, field.value.selectionEnd);
	const inline   = component.kind === 'inline' && !selected.includes('\n');
	const spot     = place(inline);

	if (spot !== null) {
		const { text, caret: at } = directiveText(component, inline, spot.inner, values);

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

defineExpose({ apply, change, focusAt, insert, insertBlock, insertText, selection, dismissSlash, emphasis, unlink, heading });
</script>

<template>
	<div class="md">
		<div ref="source" class="md__source" :class="{ 'is-dropping': dropping }" @dragover="dragover" @dragleave="dropping = false" @drop="drop">
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
				@beforeinput="beforeinput"
				@input="input"
				@keydown="keydown"
				@keyup="track"
				@click="clicked"
				@select="track"
				@focus="track"
				@paste="paste"
			/>
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
