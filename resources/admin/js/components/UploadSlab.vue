<script setup lang="ts">
/**
 * What takes a drop zone's place once a file is chosen (D-392, D-898):
 * one box, with its heading (`head`, an icon and a few words) over what
 * it says (the default slot, paragraphs and a `<dl>` of facts). `kind`
 * colors it: `good` done, `warn` a choice to make, `bad` nothing done,
 * or plain. `progress` (0 to 1) draws how much of the file has been
 * sent, under the heading, while it's sending.
 */

defineProps<{
	kind?: 'good' | 'warn' | 'bad';
	progress?: number | null;
}>();
</script>

<template>
	<div class="upload-slab" :class="kind ? `upload-slab--${kind}` : undefined">
		<p class="upload-slab__head"><slot name="head" /></p>
		<div v-if="progress !== undefined && progress !== null" class="upload-slab__bar"><i :style="{ width: `${Math.round(progress * 100)}%` }" /></div>
		<slot />
	</div>
</template>

<style scoped>
.upload-slab {
	margin-top: var(--s-4);
	padding: var(--s-5);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface-subtle);
}

.upload-slab--good {
	border-color: var(--color-success-indicator);
	background: var(--color-success-subtle);
}

.upload-slab--warn {
	border-color: var(--color-warning-indicator);
	background: var(--color-warning-subtle);
}

.upload-slab--bad {
	border-color: var(--color-danger-indicator);
	background: var(--color-danger-subtle);
}

.upload-slab :deep(p) {
	margin: 0 0 var(--s-2);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.upload-slab :deep(p:last-child) {
	margin-bottom: 0;
}

.upload-slab .upload-slab__head {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	color: var(--color-text);
	font-size: var(--font-size-base);
	font-weight: 600;
}

.upload-slab--good .upload-slab__head {
	color: var(--color-success);
}

.upload-slab--warn .upload-slab__head {
	color: var(--color-warning);
}

.upload-slab--bad .upload-slab__head {
	color: var(--color-danger);
}

.upload-slab__head :deep(svg) {
	flex: none;
	width: 15px;
	height: 15px;
}

.upload-slab :deep(dl) {
	display: grid;
	grid-template-columns: auto 1fr;
	gap: var(--s-1) var(--s-4);
	margin: var(--s-3) 0 0;
	font-size: var(--font-size-small);
}

.upload-slab :deep(dt) {
	color: var(--color-text-muted);
}

.upload-slab :deep(dd) {
	margin: 0;
	color: var(--color-text);
}

.upload-slab__bar {
	height: 5px;
	margin: var(--s-3) 0 var(--s-2);
	overflow: hidden;
	border-radius: 999px;
	background: var(--color-surface-strong);
}

.upload-slab__bar i {
	display: block;
	height: 100%;
	border-radius: 999px;
	background: var(--color-accent);
	transition: width .3s ease-out;
}
</style>
