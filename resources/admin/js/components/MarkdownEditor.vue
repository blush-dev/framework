<script setup lang="ts">
/**
 * The Markdown source editor (D-241, D-245): a plain text area, so
 * typing, undo, spelling, and screen readers work as in any field, over a
 * highlighted copy of the same text (`markdown.ts`) that shows headings,
 * strong and emphasized text, quotes, lists, code, links, and component
 * directives, with their marks muted (D-253) and the directive the caret
 * is in marked. The copy is decoration and hidden from assistive tech. It
 * sits bare in the editor's writing column and grows with its text; the
 * editor around it scrolls, counts, and holds the inserters.
 *
 * Typing `/` at the start of an empty line reports the query after it
 * (`slash`), so the editor can open the component panel filtered by it;
 * while it's open, the keys that drive the panel are passed on
 * (`slashKey`) rather than typed. The query stays in the text until a
 * component replaces it (`insert()`), so an abandoned slash is just text.
 * Inserting and removing go through the browser's own editing, so undo
 * takes them back in one step.
 */

import { computed } from 'vue';
import { ref } from 'vue';
import { directiveAt, highlight, outline, type Edit } from '../markdown';
import { directiveText, type ComponentDescription } from '../components';

const props = defineProps<{
	id: string;
	placeholder?: string;
	label?: string;
	// Whether the component panel is open for a slash, so its keys are
	// passed on.
	slashOpen?: boolean;
}>();

const model = defineModel<string>({ required: true });

// Where the caret is, kept while the field doesn't have focus, so the
// settings and inserters can follow it.
const caret = defineModel<number>('caret', { default: 0 });

const emit = defineEmits<{
	// Keys moved in the text (the editor's chrome recedes).
	typed: [];
	// The query typed after a slash at the start of a line, or `null` when
	// there's no slash (any more).
	slash: [query: string | null];
	// A key for the component panel while a slash has it open.
	slashKey: [key: 'ArrowUp' | 'ArrowDown' | 'Enter' | 'Escape'];
}>();

const markdown = computed(() => outline(model.value));
const current  = computed(() => directiveAt(markdown.value.directives, caret.value));

// A trailing space gives a final empty line its height, as in the field.
const html = computed(() => `${highlight(markdown.value, current.value)} `);

const field  = ref<HTMLTextAreaElement | null>(null);
const source = ref<HTMLElement | null>(null);

// Where the slash being typed is, and one closed with Escape, which stays
// closed until it's gone.
const slash     = ref<number | null>(null);
const dismissed = ref<number | null>(null);

function track(event: Event): void {
	caret.value = (event.target as HTMLTextAreaElement).selectionStart;
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
	emit('typed');
	followSlash();
}

// Clicking elsewhere in the text leaves a slash as text.
function clicked(event: Event): void {
	track(event);

	if (slash.value !== null) {
		dismissSlash();
		emit('slash', null);
	}
}

// While a slash has the panel open, it takes the keys that drive it.
function keydown(event: KeyboardEvent): void {
	if (!props.slashOpen || slash.value === null) {
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
	caret.value = position;
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
	caret.value = offset;

	const rect     = caretRect(offset);
	const scroller = element.closest('[data-scroller]');

	if (scroller instanceof HTMLElement) {
		const box = scroller.getBoundingClientRect();

		if (rect.top < box.top + 40 || rect.bottom > box.bottom - 40) {
			scroller.scrollTop += rect.top - box.top - box.height / 3;
		}
	}
}

/**
 * Inserts a component: an inline one at the caret, the rest on lines of
 * their own, with a blank line either side (after the caret's line when
 * it has text). Selected text becomes its label or body; `values` are its
 * first attributes.
 */
function insert(component: ComponentDescription, values: Record<string, string> = {}): void {
	const element = field.value;

	if (element === null) {
		return;
	}

	const value = element.value;
	let from    = slash.value ?? element.selectionStart;
	let to      = slash.value === null ? element.selectionEnd : element.selectionStart;
	const inner = slash.value === null ? value.slice(from, to) : '';

	const lineStart = value.lastIndexOf('\n', from - 1) + 1;
	const lineEnd   = value.indexOf('\n', to) === -1 ? value.length : value.indexOf('\n', to);
	const alone     = value.slice(lineStart, from).trim() === '' && value.slice(to, lineEnd).trim() === '';
	// Its kind decides: an inline component goes at the caret, the rest
	// on lines of their own (D-247).
	const inline    = component.kind === 'inline' && !inner.includes('\n');

	let before = '';
	let after  = '';

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

	const { text, caret: at } = directiveText(component, inline, inner, values);

	slash.value = null;
	replace(element, from, to, before + text + after);
	element.setSelectionRange(from + before.length + at, from + before.length + at);
	caret.value = element.selectionStart;
}

/**
 * Inserts text at the caret (replacing any selection), with the caret
 * after it.
 */
function insertText(text: string): void {
	const element = field.value;

	if (element !== null) {
		apply({ from: element.selectionStart, to: element.selectionEnd, text });
	}
}

/**
 * The selected text, if any.
 */
function selection(): string {
	const element = field.value;

	return element === null ? '' : element.value.slice(element.selectionStart, element.selectionEnd);
}

defineExpose({ apply, focusAt, insert, insertText, selection, dismissSlash });
</script>

<template>
	<div class="md">
		<div ref="source" class="md__source">
			<pre class="md__highlight" aria-hidden="true" v-html="html" />
			<textarea
				:id="props.id"
				ref="field"
				v-model="model"
				class="md__field"
				rows="1"
				spellcheck="true"
				:aria-label="props.label"
				:placeholder="props.placeholder"
				@input="input"
				@keydown="keydown"
				@keyup="track"
				@click="clicked"
				@select="track"
				@focus="track"
			/>
		</div>
	</div>
</template>

<style scoped>
/*
 * The field and its highlighted copy share one grid cell and every
 * property that affects where text falls, so each character of the copy
 * sits under the same character in the field. Highlights never change
 * width: color, background, Fira Code's own semibold face, and a slant
 * for italic (it has none), all of which keep its advance (D-253,
 * D-254). The column can't grow past its container for a long word or
 * address; those wrap.
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
	line-height: 1.85;
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

.md__field::selection {
	background: var(--accent-soft);
	color: transparent;
}

.md__field:focus-visible {
	outline: none;
}

.md__highlight :deep(.md-mark) {
	color: var(--fg-3);
}

.md__highlight :deep(.md-mark--list) {
	color: var(--accent);
}

.md__highlight :deep(.md-heading),
.md__highlight :deep(.md-strong) {
	color: var(--fg);
	font-weight: 600;
}

.md__highlight :deep(.md-em) {
	font-style: italic;
}

.md__highlight :deep(.md-strike__text) {
	color: var(--fg-2);
	text-decoration: line-through;
}

.md__highlight :deep(.md-quote) {
	color: var(--fg-2);
	font-style: italic;
}

.md__highlight :deep(.md-code-block) {
	color: var(--fg-2);
}

.md__highlight :deep(.md-code) {
	border-radius: var(--r-1);
	background: var(--surface-2);
	color: var(--fg-2);
}

.md__highlight :deep(.md-link__text) {
	color: var(--accent);
}

.md__highlight :deep(.md-directive) {
	border-radius: var(--r-1);
	background: var(--accent-soft);
	box-shadow: 0 0 0 1px var(--accent-line);
	color: var(--accent);
}

.md__highlight :deep(.md-directive__rest) {
	color: var(--fg-2);
}

.md__highlight :deep(.md-directive.is-current) {
	background: var(--accent);
	box-shadow: 0 0 0 1px var(--accent);
	color: var(--accent-fg);
}

.md__highlight :deep(.md-directive.is-current .md-directive__rest) {
	color: var(--accent-fg);
}
</style>
