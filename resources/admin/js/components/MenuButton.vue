<script setup lang="ts">
/**
 * A button that shows a short list of actions under it: a disclosure
 * (not an ARIA menu), so its items are ordinary links and buttons in the
 * tab order. It closes on Escape (focus goes back to the button), a click
 * outside, or choosing an item; the slot gets `close` for items that
 * don't navigate.
 *
 * A `floating` list is placed over the page beside the button (above it
 * when there's no room below), so a scrolling container such as a table
 * can't clip it; scrolling or resizing closes it.
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

const open   = ref(false);
const root   = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);
const list   = ref<HTMLElement | null>(null);
const place  = ref<{ top: string; left: string } | null>(null);
const id     = useId();

// Space kept between the list and the button or the window's edge.
const GAP = 6;

/**
 * Places a floating list under the button, or over it when there's no
 * room below, lined up with its end and kept in the window.
 */
function position(): void {
	const box = button.value?.getBoundingClientRect();
	const element = list.value;

	if (box === undefined || element === null) {
		return;
	}

	const height = element.offsetHeight;
	const width  = element.offsetWidth;
	const top    = box.bottom + GAP + height <= window.innerHeight - GAP ? box.bottom + GAP : Math.max(GAP, box.top - GAP - height);
	const left   = props.align === 'start' ? box.left : box.right - width;

	place.value = { top: `${top}px`, left: `${Math.max(GAP, Math.min(left, window.innerWidth - width - GAP))}px` };
}

async function toggle(): Promise<void> {
	open.value = !open.value;

	if (open.value && props.floating) {
		place.value = null;
		await nextTick();
		position();
	}
}

// A floating list stays where it was put, so moving the page closes it.
function moved(): void {
	if (open.value && props.floating) {
		close();
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
	window.addEventListener('resize', moved, { passive: true });
});

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', outside);
	window.removeEventListener('scroll', moved, { capture: true });
	window.removeEventListener('resize', moved);
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
