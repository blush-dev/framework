<script setup lang="ts">
/**
 * A content type's icon: the site icon its `icon` setting names, drawn
 * as a mask in the text color like the icon picker's, or else its kind's
 * admin icon. Decoration, like `AdminIcon`.
 */

import { computed } from 'vue';
import type { ContentTypeSummary } from '../api';
import { typeIcon, typeMask } from '../types';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{ type: Pick<ContentTypeSummary, 'kind' | 'icon'> }>();

const mask = computed(() => typeMask(props.type));
</script>

<template>
	<span v-if="mask" class="icon type-icon" :style="{ maskImage: mask }" aria-hidden="true" />
	<AdminIcon v-else :name="typeIcon(type)" />
</template>

<style scoped>
.type-icon {
	display: inline-block;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}
</style>
