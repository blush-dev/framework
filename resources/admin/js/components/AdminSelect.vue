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
 * Arrow keys move through it, Enter or Space chooses, Escape closes it
 * before anything else hears the key, and a click outside closes it.
 * The button is the control a `<label for>` names.
 */

import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';

export interface SelectOption {
	value: string;
	label: string;
	depth?: number;
	disabled?: boolean;
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
}>();

const model = defineModel<string>({ required: true });

const button = ref<HTMLButtonElement | null>(null);
const list   = ref<HTMLElement | null>(null);
const open   = ref(false);
const place  = ref<Record<string, string> | null>(null);
const listId = `${props.id}-list`;

const current = computed(() => props.options.find((option) => option.value === model.value) ?? props.options[0]);

function choose(option: SelectOption): void {
	if (option.disabled === true) {
		return;
	}

	model.value = option.value;
	close();
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

	open.value  = true;
	place.value = null;
	await nextTick();

	const box    = button.value?.getBoundingClientRect();
	const height = list.value?.offsetHeight ?? 0;
	const width  = list.value?.offsetWidth ?? 0;

	if (box === undefined) {
		return;
	}

	const below = box.bottom + height + 8 <= window.innerHeight;

	place.value = {
		left: `${Math.max(8, Math.min(box.left, window.innerWidth - Math.max(width, box.width) - 8))}px`,
		top: `${below ? box.bottom + 4 : Math.max(8, box.top - height - 4)}px`,
		minWidth: `${box.width}px`
	};

	document.addEventListener('pointerdown', outside, true);
	await nextTick();
	(list.value?.querySelector<HTMLElement>('[aria-selected="true"]') ?? list.value?.querySelector<HTMLElement>('button:not(:disabled)'))?.focus();
}

function close(refocus = true): void {
	if (!open.value) {
		return;
	}

	open.value = false;
	document.removeEventListener('pointerdown', outside, true);

	if (refocus) {
		button.value?.focus();
	}
}

function outside(event: PointerEvent): void {
	const target = event.target as Node;

	if (!list.value?.contains(target) && !button.value?.contains(target)) {
		close(false);
	}
}

function buttonKey(event: KeyboardEvent): void {
	if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
		event.preventDefault();
		void show();
	}
}

function listKey(event: KeyboardEvent): void {
	const items = [...(list.value?.querySelectorAll<HTMLElement>('button:not(:disabled)') ?? [])];
	const at    = items.indexOf(document.activeElement as HTMLElement);

	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		close();
	} else if (event.key === 'Tab') {
		close(false);
	} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		items[Math.min(items.length - 1, Math.max(0, at + (event.key === 'ArrowDown' ? 1 : -1)))]?.focus();
	} else if (event.key === 'Home' || event.key === 'End') {
		event.preventDefault();
		(event.key === 'Home' ? items[0] : items.at(-1))?.focus();
	}
}

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', outside, true);
});
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
			<span class="select__label">{{ current?.label ?? '' }}</span>
			<AdminIcon name="chevron-down" class="select__caret" />
		</button>
		<Teleport to="body">
			<div v-if="open" :id="listId" ref="list" class="select-list" role="listbox" :aria-labelledby="id" :style="place ?? { visibility: 'hidden' }" @keydown="listKey">
				<button
					v-for="option in options"
					:key="option.value"
					type="button"
					role="option"
					class="select-list__option"
					:aria-selected="option.value === model"
					:disabled="option.disabled"
					:style="option.depth ? { paddingLeft: `calc(var(--s-3) + ${option.depth} * var(--s-4))` } : undefined"
					@click="choose(option)"
				>
					<span class="select-list__label">{{ option.label }}</span>
					<AdminIcon v-if="option.value === model" name="check" class="select-list__tick" />
				</button>
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
	display: grid;
	max-width: calc(100vw - 16px);
	max-height: min(320px, 60vh);
	padding: 6px;
	overflow-y: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
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
}

.select-list__tick {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--accent);
}
</style>
