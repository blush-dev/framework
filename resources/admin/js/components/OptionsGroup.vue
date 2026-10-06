<script setup lang="ts">
/**
 * A group on the editor's side panel (D-245, D-268, D-509): its heading
 * (`heading`, or the `heading` slot for one holding a label), a hint at
 * the heading's end (the `hint` slot: a count, or how it's kept), and
 * what's in it. A group without a heading is a note's.
 */

defineProps<{
	heading?: string;
}>();
</script>

<template>
	<div class="options__group">
		<p v-if="heading || $slots.heading" class="options__heading">
			<slot name="heading">{{ heading }}</slot>
			<span v-if="$slots.hint" class="options__hint"><slot name="hint" /></span>
		</p>
		<slot />
	</div>
</template>
