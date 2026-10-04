<script setup lang="ts">
/**
 * What an extension provides (D-439), as Composer's `provide` has it, the
 * same for plugins, themes, and icon packs: each package it implements,
 * with the versions it provides. Nothing is checked: while it runs, a
 * `require` of one of them is met by it. Its icon lines the rows up with
 * the other lists', and is the same for each, since there's no status.
 */

import AdminIcon from './AdminIcon.vue';

defineProps<{ provides: { name: string; constraint: string }[] }>();
</script>

<template>
	<ul class="provides">
		<li v-for="provided in provides" :key="provided.name">
			<AdminIcon name="package" />
			<span class="mono">{{ provided.name }}</span>
			<span v-if="provided.constraint && provided.constraint !== '*'" class="provides__versions mono">{{ provided.constraint }}</span>
		</li>
	</ul>
</template>

<style scoped>
.provides {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	font-size: var(--text-sm);
	list-style: none;
}

.provides li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.provides .icon {
	flex: none;
	width: 15px;
	height: 15px;
	color: var(--fg-3);
}

.provides__versions {
	color: var(--fg-3);
}
</style>
