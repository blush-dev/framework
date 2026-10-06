<script setup lang="ts">
/**
 * The extensions, of every kind, that require one (D-431), as a row of
 * its details' facts, **Required by**, each linked to its details. Not
 * drawn when none does. With `label`, the same row for the other side of
 * its package links (D-440): what conflicts with it, replaces it, or
 * provides it.
 *
 * Its row is a fragment inside the details' `.facts`, whose global rules
 * style its `dt` and `dd`.
 */

import type { ExtensionDependent } from '../api';
import { extensionRoute } from '../extensions';

withDefaults(defineProps<{ dependents: ExtensionDependent[]; label?: string }>(), { label: 'Required by' });
</script>

<template>
	<template v-if="dependents.length">
		<dt>{{ label }}</dt>
		<dd>
			<template v-for="(other, index) in dependents" :key="other.name">
				<RouterLink :to="extensionRoute(other.kind, other.name)">{{ other.label }}</RouterLink><template v-if="index < dependents.length - 1">, </template>
			</template>
		</dd>
	</template>
</template>
