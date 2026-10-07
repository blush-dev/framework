<script setup lang="ts">
/**
 * An extension as a row (D-509, D-565): a plugin, or a theme or an icon
 * pack in a compact list. Its mark (the `mark` slot: the kind's glyph, or
 * a pack's first icon) is the admin's gray, and green while `is-on` is
 * on the row; then its label, linked to its details, with its pills and
 * its version, what it's for, the default slot (its facts, and what's
 * wrong), and at the end its controls (`end`). One without `to` is broken, and goes by
 * its folder. A row that's off is dimmed by `is-off` on it. Its props and
 * slots match `ExtensionCard`'s (`mark` for `media`, `end` for `foot`), so
 * a list can draw either.
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
	<li class="extension-row">
		<span class="extension-row__mark" aria-hidden="true"><slot name="mark" /></span>
		<div class="extension-row__main">
			<p class="extension__name">
				<RouterLink v-if="to" class="extension__label" :to="to">{{ label }}</RouterLink>
				<span v-else class="extension__label mono">{{ label }}</span>
				<slot name="pills" />
				<span v-if="version" class="extension__version mono">{{ version }}</span>
			</p>
			<p v-if="description" class="extension__description">{{ description }}</p>
			<slot />
		</div>
		<div class="extension-row__end">
			<slot name="end" />
		</div>
	</li>
</template>
