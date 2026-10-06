<script setup lang="ts">
/**
 * The editor's link form (⌘K; D-313, D-509), opened under its toolbar
 * button: **Text** and **Address**, filled from what the editor found
 * (`start`: the selection, the word at the caret, or the link the caret
 * is in), with the first empty one focused. **Add link** (or **Update**)
 * and **Remove** are the editor's to write; Escape asks it to close the
 * form and put the caret back.
 */

import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
	// What the form opens with; a new one fills it again.
	start: { text: string; url: string };
	// Whether it edits a link that's there, which can be removed.
	editing: boolean;
}>();

const emit = defineEmits<{
	apply: [text: string, url: string];
	remove: [];
	close: [];
}>();

const text = ref('');
const url  = ref('');
const form = ref<HTMLFormElement | null>(null);

watch(() => props.start, async (start) => {
	text.value = start.text;
	url.value  = start.url;
	await nextTick();

	// The first empty field: the address, when there's text to hang it on.
	const fields = [...(form.value?.querySelectorAll<HTMLInputElement>('input') ?? [])];

	(fields.find((field) => field.value === '') ?? fields[0])?.focus();
}, { immediate: true });

function submit(): void {
	if (url.value.trim() !== '') {
		emit('apply', text.value, url.value);
	}
}

function key(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		emit('close');
	}
}
</script>

<template>
	<form id="editor-link" ref="form" class="popover-form" role="dialog" aria-label="Link" @submit.prevent="submit" @keydown="key">
		<div class="field">
			<label for="editor-link-text">Text</label>
			<input id="editor-link-text" v-model="text" type="text" autocomplete="off">
		</div>
		<div class="field">
			<label for="editor-link-url">Address</label>
			<input id="editor-link-url" v-model="url" type="text" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="https://">
		</div>
		<p class="link-form__buttons">
			<button v-if="editing" type="button" class="button button--small button--ghost" @click="emit('remove')">Remove</button>
			<button type="submit" class="button button--small button--primary" :disabled="url.trim() === ''">{{ editing ? 'Update' : 'Add link' }}</button>
		</p>
	</form>
</template>

<style scoped>
.link-form__buttons {
	display: flex;
	justify-content: flex-end;
	gap: var(--s-2);
	margin: 0;
}
</style>
