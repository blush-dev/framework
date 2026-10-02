<script setup lang="ts">
/**
 * A modal (D-373; the profiles sketch's): a title, a body, and a footer
 * of buttons on a quieter ground, over a dimmed page. It's the native
 * `<dialog>`, shown modally while `open`, so focus stays inside and
 * Escape closes it (`close`); focus goes back where it was. The element
 * given `autofocus` in the body or footer takes the focus, else the
 * dialog does.
 */

import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';

const props = defineProps<{
	open: boolean;
	title: string;
	// A wider modal, for a list to pick from.
	wide?: boolean;
}>();

const emit = defineEmits<{
	close: [];
}>();

const dialog   = ref<HTMLDialogElement | null>(null);
const headId   = useId();
let returnTo: HTMLElement | null = null;

async function show(): Promise<void> {
	returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
	await nextTick();
	dialog.value?.showModal();
	(dialog.value?.querySelector<HTMLElement>('[autofocus]') ?? dialog.value)?.focus();
}

function hide(): void {
	if (dialog.value?.open) {
		dialog.value.close();
	}

	if (returnTo?.isConnected) {
		returnTo.focus();
	}

	returnTo = null;
}

watch(() => props.open, (value) => {
	if (value) {
		void show();
	} else {
		hide();
	}
}, { immediate: true });

onBeforeUnmount(hide);

// Escape, or a click on the backdrop, asks to close.
function cancel(event: Event): void {
	event.preventDefault();
	emit('close');
}

function backdrop(event: MouseEvent): void {
	if (event.target === dialog.value) {
		emit('close');
	}
}
</script>

<template>
	<dialog ref="dialog" class="prompt" :class="{ 'prompt--wide': wide }" :aria-labelledby="headId" tabindex="-1" @cancel="cancel" @click="backdrop">
		<div class="prompt__head">
			<h2 :id="headId">{{ title }}</h2>
		</div>
		<div class="prompt__body">
			<slot />
		</div>
		<div class="prompt__foot">
			<slot name="footer" />
		</div>
	</dialog>
</template>
