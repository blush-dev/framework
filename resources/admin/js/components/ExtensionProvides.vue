<script setup lang="ts">
/**
 * What an extension provides (D-439), as Composer's `provide` has it, the
 * same for plugins, themes, and icon packs, as rows of its Dependencies
 * panel (D-565): each package it implements, with the versions it
 * provides. Nothing is checked: while it runs, a `require` of one of
 * them is met by it. Its glyph is the neutral package, since there's no
 * status.
 */

import AdminIcon from './AdminIcon.vue';

defineProps<{ provides: { name: string; constraint: string }[] }>();
</script>

<template>
	<ul class="dependencies">
		<li v-for="provided in provides" :key="provided.name">
			<AdminIcon name="package" />
			<div class="dependencies__ref">
				<span class="mono">{{ provided.name }}</span>
				<span v-if="provided.constraint && provided.constraint !== '*'" class="mono dependencies__name">{{ provided.constraint }}</span>
			</div>
		</li>
	</ul>
</template>
