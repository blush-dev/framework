<script setup lang="ts">
/**
 * The extensions, of every kind, that require one (D-431), as a row of
 * its details' facts, **Required by**, each linked to its details. Not
 * drawn when none does. With `label`, the same row for the other side of
 * its package links (D-440): what conflicts with it, replaces it, or
 * provides it.
 *
 * Its row is a fragment, so the details' scoped `dt` and `dd` rules
 * don't reach it; it styles its own to match.
 */

import type { ExtensionDependent } from '../api';
import { extensionRoute } from '../extensions';

withDefaults(defineProps<{ dependents: ExtensionDependent[]; label?: string }>(), { label: 'Required by' });
</script>

<template>
	<template v-if="dependents.length">
		<dt class="extension-dependents__label">{{ label }}</dt>
		<dd class="extension-dependents">
			<template v-for="(other, index) in dependents" :key="other.name">
				<RouterLink :to="extensionRoute(other.kind, other.name)">{{ other.label }}</RouterLink><template v-if="index < dependents.length - 1">, </template>
			</template>
		</dd>
	</template>
</template>

<style scoped>
.extension-dependents__label {
	color: var(--fg-3);
}

.extension-dependents {
	min-width: 0;
	margin: 0;
	overflow-wrap: anywhere;
}
</style>
