<script setup lang="ts">
/**
 * An icon from `icons.ts`. Icons are decoration: the text beside them
 * (visible or visually hidden) names what they stand for.
 */

import { icons, type IconName } from '../icons';

defineProps<{ name: IconName }>();
</script>

<template>
	<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" v-html="icons[name]" />
</template>

