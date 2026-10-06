<script setup lang="ts">
/**
 * A theme or an icon pack as a card (D-509): what it looks like (the
 * `media` slot), its label, linked to its details, with its pills and
 * its version, what it's for, then the default slot (its facts, and
 * what's wrong), and a foot of actions. One without `to` is broken, and
 * goes by its folder.
 */

import type { RouteLocationRaw } from 'vue-router';

defineProps<{
	label: string;
	to?: RouteLocationRaw;
	version?: string;
	description?: string;
}>();
</script>

<template>
	<article class="extension-card">
		<slot name="media" />
		<div class="extension-card__body">
			<p class="extension__name">
				<RouterLink v-if="to" class="extension__label" :to="to">{{ label }}</RouterLink>
				<span v-else class="extension__label mono">{{ label }}</span>
				<slot name="pills" />
				<span v-if="version" class="extension__version mono">{{ version }}</span>
			</p>
			<p v-if="description" class="extension__description">{{ description }}</p>
			<slot />
		</div>
		<div v-if="$slots.foot" class="extension-card__foot">
			<slot name="foot" />
		</div>
	</article>
</template>
