<script setup lang="ts">
/**
 * Values kept as written (D-509): each name over its value, in mono, for
 * what a form doesn't edit, such as an entry's other front matter, a
 * media file's other metadata, or a component's undeclared attributes.
 * A value that isn't a string is shown as JSON.
 */

defineProps<{
	entries: [string, unknown][];
}>();

function shown(value: unknown): string {
	return typeof value === 'string' ? value : JSON.stringify(value);
}
</script>

<template>
	<dl class="raw-values">
		<div v-for="[key, value] in entries" :key="key">
			<dt class="mono">{{ key }}</dt>
			<dd class="mono">{{ shown(value) }}</dd>
		</div>
	</dl>
</template>
