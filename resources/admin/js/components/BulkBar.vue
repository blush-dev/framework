<script setup lang="ts">
/**
 * A list's bulk bar (admin.md §7, D-301; D-509): a pill fixed to the
 * bottom while rows are selected, with how many, the list's actions
 * (the default slot, split into groups by `.bulk-bar__divider`s), and
 * **Clear** at the end.
 */

defineProps<{
	count: number;
	label: string;
}>();

defineEmits<{ clear: [] }>();
</script>

<template>
	<div class="bulk-bar" role="region" :aria-label="label">
		<span class="bulk-bar__count" aria-live="polite">{{ count }} selected</span>
		<span class="bulk-bar__divider" aria-hidden="true" />
		<slot />
		<button type="button" class="button button--ghost button--small" @click="$emit('clear')">Clear</button>
	</div>
</template>
