<script setup lang="ts">
/**
 * A modal's drop zone (D-392, D-898: installing an extension, uploading a
 * backup): the upload icon, a heading, what goes in it (the default
 * slot), its button (`button`), and the limits (`hint`) stated before
 * anyone tries. The button has no icon: the large one above already says
 * it. `over` is a file being dragged over the modal. The media picker's
 * Upload tab has a larger zone of its own.
 */

import AdminIcon from './AdminIcon.vue';

defineProps<{
	heading: string;
	hint?: string | null;
	over?: boolean;
}>();
</script>

<template>
	<div class="upload-drop" :class="{ 'is-over': over }">
		<AdminIcon name="upload" />
		<p class="upload-drop__heading">{{ heading }}</p>
		<p class="upload-drop__text"><slot /></p>
		<slot name="button" />
		<p v-if="hint" class="upload-drop__hint">{{ hint }}</p>
	</div>
</template>

<style scoped>
.upload-drop {
	display: grid;
	justify-items: center;
	gap: var(--s-2);
	margin-top: var(--s-4);
	padding: var(--s-6) var(--s-5);
	border: 1px dashed var(--border-color-strong);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface-subtle);
	text-align: center;
	transition: border-color .14s ease-out, background .14s ease-out;
}

.upload-drop.is-over {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
}

.upload-drop > :deep(svg) {
	width: 24px;
	height: 24px;
	margin-bottom: var(--s-1);
	color: var(--color-text-muted);
}

.upload-drop__heading {
	margin: 0;
	font-family: var(--font-family-display);
	font-weight: var(--font-weight-display);
}

.upload-drop__text {
	max-width: 40ch;
	margin: 0 0 var(--s-2);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.upload-drop__hint {
	margin: var(--s-1) 0 0;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}
</style>
