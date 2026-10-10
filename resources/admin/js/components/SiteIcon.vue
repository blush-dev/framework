<script setup lang="ts">
/**
 * One of the site's icons, by the name a type or a menu item gives it,
 * drawn as a mask in the text color like the icon picker's; else, while
 * the icons load or when no icon has the name, the `fallback` admin
 * icon. Decoration, like `AdminIcon`.
 */

import { computed, watchEffect } from 'vue';
import type { IconName } from '../icons';
import { iconMasks, loadIconMasks } from '../site-icons';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{
	name: string | null;
	fallback: IconName;
}>();

watchEffect(() => {
	if (props.name) {
		loadIconMasks();
	}
});

const mask = computed(() => props.name ? iconMasks.value[props.name] ?? null : null);
</script>

<template>
	<span v-if="mask" class="icon site-icon" :style="{ maskImage: mask }" aria-hidden="true" />
	<AdminIcon v-else :name="fallback" />
</template>

<style scoped>
.site-icon {
	display: inline-block;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}
</style>
