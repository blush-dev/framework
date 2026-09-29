<script setup lang="ts">
/**
 * A button that shows a short list of actions under it: a disclosure
 * (not an ARIA menu), so its items are ordinary links and buttons in the
 * tab order. It closes on Escape (focus goes back to the button), a click
 * outside, or choosing an item; the slot gets `close` for items that
 * don't navigate.
 */

import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

defineProps<{
	// The button's accessible name, when its content doesn't say it.
	label?: string;
	buttonClass?: string;
	// Which edge of the button the list lines up with.
	align?: 'start' | 'end';
}>();

const open   = ref(false);
const root   = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);
const id     = useId();

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

onMounted(() => document.addEventListener('pointerdown', outside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside));
</script>

<template>
	<div ref="root" class="menu-button" @keydown="keydown">
		<button ref="button" type="button" :class="buttonClass" :aria-label="label" :aria-controls="id" :aria-expanded="open" @click="open = !open">
			<slot name="button" />
		</button>
		<div v-show="open" :id="id" class="menu-button__list" :class="`menu-button__list--${align ?? 'end'}`" @click="chosen">
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
	min-width: 200px;
	padding: 4px;
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

.menu-button__list :deep(.menu-item) {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	padding: 6px 8px;
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
	margin: 4px -4px;
	background: var(--border);
}
</style>
