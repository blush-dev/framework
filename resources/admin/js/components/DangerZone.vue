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
	padding: var(--s-4) var(--pad-x);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
}

.danger-zone__text {
	flex: 1 1 20rem;
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
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
