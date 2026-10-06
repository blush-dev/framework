<script setup lang="ts">
/**
 * Says an extension is abandoned (D-433), on its details screen: its
 * author no longer maintains it, and, when its manifest names one, the
 * package to use instead, linked to its details when it's an installed
 * extension. Only a warning, as in Composer: it still runs. Not drawn for
 * one that isn't abandoned.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionDependent } from '../api';
import { extensionRoute } from '../extensions';

defineProps<{
	// `plugin`, `theme`, or `icon pack`.
	noun: string;
	abandoned: boolean | string;
	replacement: ExtensionDependent | null;
}>();
</script>

<template>
	<p v-if="abandoned !== false" class="notice notice--warn abandoned-notice">
		<AdminIcon name="triangle-alert" />
		<span>
			This {{ noun }} is abandoned: its author no longer maintains it. It still works, but won't get fixes or updates.
			<template v-if="replacement">Use <RouterLink :to="extensionRoute(replacement.kind, replacement.name)">{{ replacement.label }}</RouterLink> instead.</template>
			<template v-else-if="typeof abandoned === 'string'">Its author suggests <span class="mono">{{ abandoned }}</span> instead.</template>
		</span>
	</p>
</template>

<style scoped>
.abandoned-notice a {
	color: inherit;
}
</style>
