<script setup lang="ts">
/**
 * A button that shows a short list of actions under it: a disclosure
 * (not an ARIA menu), so its items are ordinary links and buttons in the
 * tab order. It closes on Escape (focus goes back to the button), a click
 * outside, or choosing an item; the slot gets `close` for items that
 * don't navigate.
 *
 * A `floating` list is placed over the page beside the button (above it
 * when there's more room there), so a scrolling container such as a table
 * can't clip it. It keeps 16px from the window's edges and scrolls when
 * it's taller than the room it has; resizing the window fits it again,
 * and scrolling or resizing that moves the button closes it. Opening tells the page
 * (`open`), so it can close whatever else it had open.
 *
 * An item with `aria-current` is the choice in force, shown by filling
 * its row as a hovered row is, not with a tick (admin.md §7, D-313); an
 * item with `aria-pressed` is a format that's on, shown the same way.
 */

import { nextTick, onBeforeUnmount, onMounted, ref, useId } from 'vue';

const props = defineProps<{
	// The button's accessible name, when its content doesn't say it.
	label?: string;
	buttonClass?: string;
	// Which edge of the button the list lines up with.
	align?: 'start' | 'end';
	floating?: boolean;
}>();

const emit = defineEmits<{
	open: [];
}>();

const open   = ref(false);
const root   = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);
const list   = ref<HTMLElement | null>(null);
const place  = ref<{ top: string; left: string; maxHeight: string } | null>(null);
// Where the button was when its list was placed.
let anchor: { top: number; left: number } | null = null;
const id     = useId();

// Space kept between the list and the button.
const GAP = 6;

// Space kept between the list and the window's edges.
const EDGE = 16;

/**
 * Places a floating list under the button, or over it when there's more
 * room above, lined up with its end and kept in the window, EDGE from
 * its edges. A list taller than the room it has scrolls.
 */
function position(): void {
	const box = button.value?.getBoundingClientRect();
	const element = list.value;

	if (box === undefined || element === null) {
		return;
	}

	anchor = { top: box.top, left: box.left };

	// Its full height, even while a smaller one scrolls.
	const height = element.scrollHeight;
	const width  = element.offsetWidth;
	const below  = window.innerHeight - EDGE - (box.bottom + GAP);
	const above  = box.top - GAP - EDGE;
	const under  = height <= below || below >= above;
	const room   = Math.max(0, under ? below : above);
	const top    = under ? box.bottom + GAP : box.top - GAP - Math.min(height, room);
	const left   = props.align === 'start' ? box.left : box.right - width;

	place.value = { top: `${top}px`, left: `${Math.max(EDGE, Math.min(left, window.innerWidth - width - EDGE))}px`, maxHeight: `${room}px` };
}

async function toggle(): Promise<void> {
	open.value = !open.value;

	if (open.value) {
		emit('open');
	}

	if (open.value && props.floating) {
		place.value = null;
		await nextTick();
		position();
	}
}

// A floating list stays where it was put, so moving its button closes
// it. A scroll somewhere else on the page (a panel closing as it opens)
// doesn't.
function moved(): void {
	const box = button.value?.getBoundingClientRect();

	if (open.value && props.floating && (box === undefined || anchor === null || Math.abs(box.top - anchor.top) > 1 || Math.abs(box.left - anchor.left) > 1)) {
		close();
	}
}

// A resize that leaves the button where it was fits the list to the
// window again.
function resized(): void {
	moved();

	if (open.value && props.floating) {
		position();
	}
}

function close(refocus = false): void {
	open.value = false;

	if (refocus) {
		button.value?.focus();
	}
}

function outside(event: PointerEvent): void {
	if (open.value && event.target instanceof Node && root.value?.contains(event.target) !== true) {
		close();
	}
}

function keydown(event: KeyboardEvent): void {
	if (open.value && event.key === 'Escape') {
		event.stopPropagation();
		close(true);
	}
}

// Choosing an item closes the list.
function chosen(event: MouseEvent): void {
	if (event.target instanceof Element && event.target.closest('a, button') !== null) {
		close();
	}
}

onMounted(() => {
	document.addEventListener('pointerdown', outside);
	window.addEventListener('scroll', moved, { capture: true, passive: true });
	window.addEventListener('resize', resized, { passive: true });
});

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', outside);
	window.removeEventListener('scroll', moved, { capture: true });
	window.removeEventListener('resize', resized);
});
</script>

<template>
	<div ref="root" class="menu-button" @keydown="keydown">
		<button ref="button" type="button" :class="buttonClass" :aria-label="label" :aria-controls="id" :aria-expanded="open" @click="toggle">
			<slot name="button" />
		</button>
		<div
			v-show="open"
			:id="id"
			ref="list"
			class="menu-button__list"
			:class="floating ? 'menu-button__list--floating' : `menu-button__list--${align ?? 'end'}`"
			:style="floating ? (place ?? { visibility: 'hidden' }) : undefined"
			@click="chosen"
		>
			<slot :close="close" />
		</div>
	</div>
</template>

<style scoped>
.menu-button {
	position: relative;
	flex: none;
}

.menu-button__list {
	position: absolute;
	top: calc(100% + 6px);
	z-index: 40;
	display: grid;
	min-width: 212px;
	padding: 7px;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
}

.menu-button__list--end {
	right: 0;
}

.menu-button__list--start {
	left: 0;
}

.menu-button__list--floating {
	position: fixed;
	z-index: 60;
	overflow-y: auto;
	overscroll-behavior: contain;
}

.menu-button__list :deep(.menu-item) {
	display: flex;
	align-items: center;
	gap: 11px;
	width: 100%;
	padding: 8px 11px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font-size: var(--base);
	text-align: left;
	text-decoration: none;
	white-space: nowrap;
	cursor: pointer;
}

.menu-button__list :deep(.menu-item:hover) {
	background: var(--surface-2);
	color: var(--fg);
}

.menu-button__list :deep(.menu-item[aria-current='true']),
.menu-button__list :deep(.menu-item[aria-current='true'] svg),
.menu-button__list :deep(.menu-item[aria-current='true'] .menu-item__name),
.menu-button__list :deep(.menu-item[aria-pressed='true']),
.menu-button__list :deep(.menu-item[aria-pressed='true'] svg) {
	background: var(--accent-soft);
	color: var(--accent);
}

/* A format's keys, at the row's end. */
.menu-button__list :deep(.menu-item__shortcut) {
	margin-left: auto;
	padding-left: 16px;
}

.menu-button__list :deep(.menu-item:disabled) {
	opacity: .5;
	cursor: default;
}

.menu-button__list :deep(.menu-item--danger) {
	color: var(--danger);
}

.menu-button__list :deep(.menu-item--danger:hover) {
	background: var(--danger-soft);
	color: var(--danger);
}

.menu-button__list :deep(.menu-item svg) {
	flex: none;
	width: 15px;
	height: 15px;
}

.menu-button__list :deep(.menu-divider) {
	height: 1px;
	margin: 7px -7px;
	background: var(--border);
}

/* A section's name: a heading says how two groups differ, where a rule
   only says that they do. */
.menu-button__list :deep(.menu-heading) {
	padding: 8px 11px 4px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.menu-button__list :deep(.menu-heading:not(:first-child)) {
	margin-top: 6px;
}

/* A command's shortcut, right-aligned. */
.menu-button__list :deep(.menu-kbd) {
	margin-left: auto;
	padding-left: 12px;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
}

/* An item with a line saying what it does, under its name. */
.menu-button__list :deep(.menu-item--described) {
	align-items: flex-start;
	width: 300px;
	max-width: calc(100vw - 32px);
	padding: 10px 12px;
	white-space: normal;
}

.menu-button__list :deep(.menu-item--described svg) {
	margin-top: 2px;
	color: var(--fg-3);
}

.menu-button__list :deep(.menu-item--described:hover svg) {
	color: var(--accent);
}

.menu-button__list :deep(.menu-item__name) {
	display: block;
	color: var(--fg);
	font-weight: 500;
}

.menu-button__list :deep(.menu-item__text) {
	display: block;
	margin-top: 2px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	line-height: 1.45;
}
</style>
