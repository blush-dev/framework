<script setup lang="ts">
/**
 * A select whose open list is drawn by the admin, not the operating
 * system (admin.md §7, Selects): a native popup ignores the admin's
 * theme, type, and checkmark. The real `<select>` stays in the page and
 * holds the value, visually hidden (never `display: none`, which would
 * drop it from a form); a button beside it shows the chosen option's
 * label and opens the list.
 *
 * The list takes at least the button's width and opens above it when
 * there's no room below; the chosen option is ticked, not just tinted;
 * an option's `depth` indents it, which is how a tree reaches the list.
 * An option may have a `hint`, a quieter second name after its label
 * (D-442: a language's name in English beside its own), and the `lang`
 * its label is written in, which reads in its own direction.
 * Arrow keys move through it, typing jumps to the option whose label
 * or hint starts with what's typed (D-441: a long list, such as the time zones
 * or the languages), Enter or Space chooses, Escape closes it before
 * anything else hears the key, and a click outside closes it.
 * The button is the control a `<label for>` names.
 *
 * A `searchable` list (D-443) opens with a search field over it, focused,
 * and stays one height while it filters. A search matches an option's
 * label, hint, and `search` words (a locale's code) anywhere in them,
 * ignoring case, accents, spaces, and punctuation (`frca` finds
 * `fr_CA`), and a match under another (`depth`) keeps the options above
 * it in view, as the hierarchical terms box does. A `pinned` option
 * (Other…) is always shown, last, under "No match" when nothing else is.
 * Down Arrow moves into the list and Up Arrow from its top back to the
 * search; Enter in the search chooses the first match; Escape clears a
 * search, then closes; typing in the list, or on the closed button,
 * types in the search. Choosing
 * emits `picked`, with the search and whether anything matched it.
 *
 * Options may be listed under headings (`group`, D-444), as the time
 * zones are under their regions; a search matches a heading's name too
 * (`europe` lists Europe's).
 *
 * A `remote` list's options are searched by whoever gives them (D-607:
 * a relation's picker over 50 candidates asks the server): typing emits
 * `search`, and the list shows the options as given. A `note` is a
 * quiet line at the list's foot (how many more there are, or what's
 * left out and why), and an option's `mark` is a person's initials,
 * drawn as an avatar before its label, in the list and on the button.
 */

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePopover } from '../popover';
import AdminIcon from './AdminIcon.vue';

export interface SelectOption {
	value: string;
	label: string;
	depth?: number;
	disabled?: boolean;
	hint?: string | null;
	lang?: string;
	// More words a search finds it by.
	search?: string;
	// Always shown, at the end, whatever the search.
	pinned?: boolean;
	// The heading it's listed under (D-444: a time zone's region); one
	// heading starts each run of options that share it.
	group?: string | null;
	// A person's initials, drawn as an avatar (D-607).
	mark?: string;
}

const props = defineProps<{
	id: string;
	options: SelectOption[];
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
	// Drawn as a value in a row of settings, not a box (the document
	// panel's Parent).
	plain?: boolean;
	// A search field over the open list (D-443).
	searchable?: boolean;
	// Searched by whoever gives the options, which `search` asks for.
	remote?: boolean;
	// A line at the open list's foot.
	note?: string;
}>();

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ picked: [value: string, query: string, matched: boolean]; search: [query: string] }>();

const button = ref<HTMLButtonElement | null>(null);
const list   = ref<HTMLElement | null>(null);
const listId = `${props.id}-list`;
const search = ref<HTMLInputElement | null>(null);
const query  = ref('');

watch(query, (text) => {
	if (props.remote) {
		emit('search', text);
	}
});

const popover                = usePopover(button, list, { gap: 4, matchWidth: true });
const { open, place, layer, close } = popover;

// What's been typed in the open list, and when it's forgotten.
let typed = '';
let typedTimer: ReturnType<typeof setTimeout> | undefined;

const current = computed(() => props.options.find((option) => option.value === model.value) ?? props.options[0]);

// Text as a search compares it: lowercase, without accents, spaces, or
// punctuation.
function folded(text: string): string {
	return text.normalize('NFD').toLowerCase().replace(/[^\p{L}\p{N}]/gu, '');
}

const searchText = computed(() => props.options.map((option) => folded([option.label, option.hint ?? '', option.search ?? '', option.group ?? ''].join(' '))));

// The options the search finds, with the ones above each match (by
// depth), and whether anything matched; pinned ones come after.
const found = computed(() => {
	const text   = folded(query.value);
	const pinned = props.options.filter((option) => option.pinned === true);
	const rest   = props.options.filter((option) => option.pinned !== true);

	if (!props.searchable || text === '') {
		return { shown: [...rest, ...pinned], matched: true };
	}

	if (props.remote) {
		return { shown: [...rest, ...pinned], matched: rest.length > 0 };
	}

	const keep = new Set<number>();

	props.options.forEach((option, index) => {
		if (option.pinned === true || !(searchText.value[index] ?? '').includes(text)) {
			return;
		}

		keep.add(index);

		let depth = option.depth ?? 0;

		for (let above = index - 1; above >= 0 && depth > 0; above--) {
			const at = props.options[above]?.depth ?? 0;

			if (at < depth) {
				keep.add(above);
				depth = at;
			}
		}
	});

	return { shown: [...props.options.filter((option, index) => keep.has(index)), ...pinned], matched: keep.size > 0 };
});

function choose(option: SelectOption): void {
	if (option.disabled === true) {
		return;
	}

	const search  = query.value.trim();
	const matched = found.value.matched;

	model.value = option.value;
	close();
	emit('picked', option.value, search, matched);
}

function nativeChange(event: Event): void {
	model.value = (event.target as HTMLSelectElement).value;
}

/**
 * Opens the list under the button, or over it when there's no room, and
 * focuses the chosen option.
 */
async function show(): Promise<void> {
	if (props.disabled === true) {
		return;
	}

	query.value = '';
	await popover.show();
	await nextTick();

	const chosen = list.value?.querySelector<HTMLElement>('[aria-selected="true"]');

	if (props.searchable) {
		chosen?.scrollIntoView({ block: 'center' });
		search.value?.focus();

		return;
	}

	(chosen ?? list.value?.querySelector<HTMLElement>('button:not(:disabled)'))?.focus();
}

function items(): HTMLElement[] {
	return [...(list.value?.querySelectorAll<HTMLElement>('button:not(:disabled)') ?? [])];
}

// The search field's keys; none reach the list's.
function searchKey(event: KeyboardEvent): void {
	event.stopPropagation();

	if (event.key === 'Escape') {
		event.preventDefault();

		if (query.value !== '') {
			query.value = '';
		} else {
			close();
		}
	} else if (event.key === 'Tab') {
		close(false);
	} else if (event.key === 'ArrowDown') {
		event.preventDefault();
		items()[0]?.focus();
	} else if (event.key === 'Enter') {
		event.preventDefault();

		const first = found.value.shown.find((option) => option.disabled !== true);

		if (first !== undefined) {
			choose(first);
		}
	}
}

function buttonKey(event: KeyboardEvent): void {
	if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
		event.preventDefault();
		void show();
	} else if (props.searchable && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
		// Typing on a closed list with a search starts the search.
		event.preventDefault();
		void show().then(() => {
			query.value = event.key;
		});
	}
}

function listKey(event: KeyboardEvent): void {
	const buttons = items();
	const at      = buttons.indexOf(document.activeElement as HTMLElement);

	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		close();
	} else if (event.key === 'Tab') {
		close(false);
	} else if (event.key === 'ArrowUp' && at <= 0 && props.searchable) {
		event.preventDefault();
		search.value?.focus();
	} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		buttons[Math.min(buttons.length - 1, Math.max(0, at + (event.key === 'ArrowDown' ? 1 : -1)))]?.focus();
	} else if (event.key === 'Home' || event.key === 'End') {
		event.preventDefault();
		(event.key === 'Home' ? buttons[0] : buttons.at(-1))?.focus();
	} else if (event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
		event.preventDefault();

		if (props.searchable) {
			query.value += event.key;
			search.value?.focus();
		} else {
			typeAhead(event.key, buttons, at);
		}
	}
}

/**
 * Focuses the next option whose label starts with what's been typed; the
 * same letter again moves on to the next one that starts with it.
 */
function typeAhead(key: string, items: HTMLElement[], at: number): void {
	clearTimeout(typedTimer);
	typedTimer = setTimeout(() => {
		typed = '';
	}, 700);

	typed += key.toLowerCase();

	const repeat = [...typed].every((letter) => letter === typed[0]);
	const prefix = repeat ? typed[0] ?? '' : typed;
	const starts = (item: HTMLElement): boolean => [item.dataset.label, item.dataset.hint].some((name) => name?.toLowerCase().startsWith(prefix) === true);
	const from   = repeat ? at + 1 : Math.max(0, at);

	(items.slice(from).find(starts) ?? items.find(starts))?.focus();
}

onBeforeUnmount(() => clearTimeout(typedTimer));
</script>

<template>
	<div class="select">
		<select class="select__native" :value="model" tabindex="-1" aria-hidden="true" :disabled="disabled" @change="nativeChange">
			<option v-for="option in options" :key="option.value" :value="option.value" :disabled="option.disabled">{{ option.label }}</option>
		</select>
		<button
			:id="id"
			ref="button"
			type="button"
			class="select__button"
			:class="{ 'select__button--plain': plain }"
			aria-haspopup="listbox"
			:aria-expanded="open"
			:aria-controls="listId"
			:aria-describedby="describedBy"
			:aria-invalid="invalid ? 'true' : undefined"
			:disabled="disabled"
			@click="open ? close() : show()"
			@keydown="buttonKey"
		>
			<span v-if="current?.mark" class="avatar select__mark" aria-hidden="true">{{ current.mark }}</span>
			<span class="select__label">
				<span :lang="current?.lang" dir="auto">{{ current?.label ?? '' }}</span>
				<span v-if="current?.hint" class="select__hint">{{ current.hint }}</span>
			</span>
			<AdminIcon name="chevron-down" class="select__caret" />
		</button>
		<Teleport :to="layer">
			<div v-if="open" ref="list" class="select-list" :class="{ 'select-list--search': searchable }" :style="place ?? { visibility: 'hidden' }" @keydown="listKey">
				<div v-if="searchable" class="select-list__search">
					<AdminIcon name="search" />
					<input
						ref="search"
						v-model="query"
						type="search"
						aria-label="Search"
						:aria-controls="listId"
						autocomplete="off"
						spellcheck="false"
						placeholder="Search…"
						@keydown="searchKey"
					>
				</div>
				<div :id="listId" class="select-list__options" role="listbox" :aria-labelledby="id">
					<p v-if="!found.matched" class="select-list__empty">No match</p>
					<template v-for="(option, index) in found.shown" :key="option.value">
						<p v-if="option.group && option.group !== found.shown[index - 1]?.group" class="select-list__group" aria-hidden="true">{{ option.group }}</p>
						<button
							type="button"
							role="option"
							class="select-list__option"
							:class="{ 'select-list__option--pinned': option.pinned }"
							:aria-selected="option.value === model"
							:disabled="option.disabled"
							:style="option.depth ? { paddingLeft: `calc(var(--s-3) + ${option.depth} * var(--s-4))` } : undefined"
							:data-label="option.label"
							:data-hint="option.hint ?? undefined"
							@click="choose(option)"
						>
							<span v-if="option.mark" class="avatar select__mark" aria-hidden="true">{{ option.mark }}</span>
							<span class="select-list__label" :lang="option.lang" dir="auto">{{ option.label }}</span>
							<span v-if="option.hint" class="select-list__hint">{{ option.hint }}</span>
							<AdminIcon v-if="option.value === model" name="check" class="select-list__tick" />
						</button>
					</template>
				</div>
				<p v-if="note" class="select-list__note">{{ note }}</p>
			</div>
		</Teleport>
	</div>
</template>

<style scoped>
.select {
	position: relative;
	width: 100%;
	min-width: 0;
}

/* Holds the value, out of sight and out of the tab order. */
.select__native {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip-path: inset(50%);
	opacity: 0;
	pointer-events: none;
}

.select__button {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-height: var(--ctl);
	padding: 0 10px 0 12px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg);
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.select__button:hover {
	border-color: var(--fg-3);
}

.select__button:focus-visible,
.select__button[aria-expanded="true"] {
	border-color: var(--accent);
	outline-offset: 0;
}

.select__button[aria-invalid="true"] {
	border-color: var(--danger);
}

.select__button--plain {
	min-height: 0;
	padding: 7px 8px;
	border-color: transparent;
	background: none;
	color: var(--accent);
	font-size: var(--text-sm);
}

.select__button--plain:hover {
	border-color: transparent;
	background: var(--surface-2);
}

.select__button:disabled {
	opacity: .5;
	cursor: default;
}

.select__label {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.select__hint {
	margin-left: var(--s-2);
	color: var(--fg-3);
}

.select__mark {
	flex: none;
	width: 20px;
	height: 20px;
	font-size: var(--text-2xs);
}

.select__caret {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}
</style>

<style>
/* The list is placed over the page, so it isn't clipped by a scrolling
   panel. */
.select-list {
	position: fixed;
	z-index: 70;
	display: flex;
	flex-direction: column;
	max-width: calc(100vw - 16px);
	max-height: min(320px, 60vh);
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
}

/* A list with a search keeps one height as it filters, so it doesn't
   jump about under the field. */
.select-list--search {
	width: 400px;
	height: min(360px, 60vh);
	max-height: none;
}

.select-list__search {
	display: flex;
	flex: none;
	align-items: center;
	gap: 9px;
	height: var(--ctl);
	padding: 0 12px;
	border-bottom: 1px solid var(--border);
	color: var(--fg-3);
}

.select-list__search svg {
	flex: none;
	width: 14px;
	height: 14px;
}

.select-list__search input {
	flex: 1;
	min-width: 0;
	height: 22px;
	padding: 0;
	border: 0;
	background: none;
	color: var(--fg);
	font: inherit;
	font-size: var(--text-sm);
}

.select-list__search input:focus-visible {
	outline: none;
}

.select-list__options {
	display: grid;
	align-content: start;
	min-height: 0;
	padding: 6px;
	overflow-y: auto;
}

.select-list__group {
	margin: 0;
	padding: var(--s-3) var(--s-3) var(--s-1);
	color: var(--fg-3);
	font-size: var(--text-sm);
	font-weight: 600;
}

.select-list__group:first-child {
	padding-top: var(--s-1);
}

.select-list__note {
	flex: none;
	margin: 0;
	padding: var(--s-2) var(--s-3);
	border-top: 1px solid var(--border);
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-variant-numeric: tabular-nums;
}

.select-list__empty {
	margin: 0;
	padding: 7px var(--s-3);
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* What's always there (Other…) sits apart from what the search finds. */
.select-list__option:not(.select-list__option--pinned) + .select-list__option--pinned {
	margin-top: 6px;
	box-shadow: 0 -4px 0 -3px var(--border);
}

.select-list__option {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding: 7px var(--s-3);
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.select-list__option:hover,
.select-list__option:focus-visible {
	background: var(--surface-2);
	color: var(--fg);
	outline: none;
}

.select-list__option.is-active {
	background: var(--surface-2);
	color: var(--fg);
}

.select-list__option[aria-selected="true"] {
	color: var(--fg);
	font-weight: 500;
}

.select-list__option:disabled {
	opacity: .5;
	cursor: default;
}

.select-list__label {
	flex: 1;
	min-width: 0;
	text-align: left;
}

.select-list__hint {
	flex: none;
	color: var(--fg-3);
	font-size: var(--text-sm);
	font-weight: 400;
}

.select-list__icon {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.select-list__tick {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--accent);
}
</style>
