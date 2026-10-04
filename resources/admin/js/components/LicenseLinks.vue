<script setup lang="ts">
/**
 * An extension's license, as its parts (D-426): each license it names,
 * a common one linked to its text, and the `or`, `and`, or `with`
 * between them, muted. A dash when it has none.
 */

import type { LicensePart } from '../api';

defineProps<{ parts: LicensePart[] }>();
</script>

<template>
	<template v-if="parts.length === 0">—</template>
	<template v-for="(part, index) in parts" :key="index">
		<template v-if="index > 0">{{ ' ' }}</template>
		<a v-if="part.url" :href="part.url" target="_blank" rel="noopener">{{ part.text }}<span class="visually-hidden"> (new tab)</span></a>
		<span v-else-if="part.operator" class="license-operator">{{ part.text }}</span>
		<template v-else>{{ part.text }}</template>
	</template>
</template>

<style scoped>
.license-operator {
	color: var(--fg-3);
}
</style>
