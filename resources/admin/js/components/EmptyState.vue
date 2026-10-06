<script setup lang="ts">
/**
 * What a screen or a list with nothing in it says (D-509): a glyph, a
 * heading, what it's for or why it's empty (`text`, or the default slot
 * for text with markup), and what to do about it (the `actions` slot).
 * The heading is a `<p>` unless `tag` says it's a heading in the page's
 * outline.
 */

import AdminIcon from './AdminIcon.vue';
import type { IconName } from '../icons';

defineProps<{
	icon: IconName;
	heading: string;
	text?: string;
	tag?: 'h2' | 'h3';
	headingId?: string;
}>();
</script>

<template>
	<div class="empty">
		<AdminIcon :name="icon" />
		<component :is="tag ?? 'p'" :id="headingId" class="empty__heading">{{ heading }}</component>
		<p v-if="text || $slots.default" class="empty__text"><slot>{{ text }}</slot></p>
		<slot name="actions" />
	</div>
</template>
