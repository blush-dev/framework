<script setup lang="ts">
/**
 * An extension's `require`, each checked against the site (D-385,
 * D-431), the same for plugins, themes, and icon packs: met or not, what
 * the site has, and a required extension linked to its details. Its
 * `conflict` (D-435) is drawn the same way with `conflicts`, each met
 * when the site doesn't have what it names on.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionRequirement } from '../api';
import { extensionRoute, requirementKind, requirementText } from '../extensions';

defineProps<{ requirements: ExtensionRequirement[]; conflicts?: boolean }>();
</script>

<template>
	<ul v-if="requirements.length" class="requirements">
		<li v-for="requirement in requirements" :key="requirement.name">
			<AdminIcon :name="requirement.met ? 'circle-check' : 'circle-x'" :class="requirement.met ? 'is-met' : 'is-unmet'" />
			<span v-if="conflicts" class="visually-hidden">{{ requirement.met ? 'No conflict:' : 'Conflicts:' }}</span>
			<span v-else class="visually-hidden">{{ requirement.met ? 'Met:' : 'Not met:' }}</span>
			<RouterLink v-if="requirementKind(requirement)" :to="extensionRoute(requirementKind(requirement) ?? 'plugin', requirement.name)">{{ requirementText(requirement) }}</RouterLink>
			<span v-else :class="{ mono: requirement.kind === 'unknown' || requirement.kind === 'missing' }">{{ requirementText(requirement) }}</span>
			<span v-if="requirement.note" class="requirements__note" :class="{ 'is-unmet': !requirement.met }">{{ requirement.note }}</span>
		</li>
	</ul>
	<p v-else class="field__help">Nothing: its manifest has no <code>require</code>.</p>
</template>

<style scoped>
.requirements {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	font-size: var(--text-sm);
	list-style: none;
}

.requirements li {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.requirements .icon {
	flex: none;
	width: 15px;
	height: 15px;
}

.requirements .is-met {
	color: var(--good);
}

.requirements .is-unmet {
	color: var(--danger);
}

.requirements__note {
	color: var(--fg-3);
}

.requirements__note.is-unmet {
	color: var(--warn);
}
</style>
