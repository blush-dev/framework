<script setup lang="ts">
/**
 * What can't be taken back (D-509): a quiet strip at the end of a screen,
 * saying what the action does, with its button at the end (the `action`
 * slot), and the reason it was refused under that.
 */

defineProps<{
	error?: string;
}>();
</script>

<template>
	<div class="danger-zone">
		<p class="danger-zone__text"><slot /></p>
		<div class="danger-zone__actions"><slot name="action" /></div>
		<p v-if="error" class="field__error danger-zone__error" role="alert">{{ error }}</p>
	</div>
</template>

<style scoped>
.danger-zone {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-4);
	padding: var(--s-4) var(--padding-inline);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-large);
	background: var(--color-surface);
}

.danger-zone__text {
	flex: 1 1 20rem;
	margin: 0;
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.danger-zone__actions {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	gap: var(--s-2);
	margin-left: auto;
}

.danger-zone__error {
	flex-basis: 100%;
	margin: 0;
}
</style>
