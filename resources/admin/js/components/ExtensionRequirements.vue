<script setup lang="ts">
/**
 * An extension's `require`, each checked against the site (D-385,
 * D-431), the same for plugins, themes, and icon packs, as rows of its
 * Dependencies panel (D-565): met or not, a required extension linked
 * by its label with its name and versions beside it, and what the site
 * has. Its `conflict` (D-435) is drawn the same way as the `conflicts`
 * list, each met when the site doesn't have what it names on, and its
 * `replace` (D-436) as the `replaces` list, each met when what it
 * replaces isn't on. A requirement met by an extension that replaces or
 * provides what it names links to that one.
 */

import AdminIcon from './AdminIcon.vue';
import type { ExtensionRequirement } from '../api';
import { extensionRoute, requirementKind, requirementText } from '../extensions';

defineProps<{ requirements: ExtensionRequirement[]; list?: 'requires' | 'conflicts' | 'replaces' }>();

// What a screen reader hears before each, as the icon shows it.
const STATUS = {
	requires:  ['Met:', 'Not met:'],
	conflicts: ['No conflict:', 'Conflicts:'],
	replaces:  ['Not on:', 'On:']
} as const;

// The name and versions beside a linked extension's label.
function versions(requirement: ExtensionRequirement): string {
	return requirement.constraint === '*' ? requirement.name : `${requirement.name} ${requirement.constraint}`;
}
</script>

<template>
	<ul class="dependencies">
		<li v-for="requirement in requirements" :key="requirement.name">
			<AdminIcon :name="requirement.met ? 'circle-check' : 'circle-x'" :class="requirement.met ? 'is-met' : 'is-unmet'" />
			<div class="dependencies__ref">
				<span class="visually-hidden">{{ STATUS[list ?? 'requires'][requirement.met ? 0 : 1] }}</span>
				<template v-if="requirementKind(requirement)">
					<RouterLink :to="extensionRoute(requirementKind(requirement) ?? 'plugin', requirement.metBy || requirement.name)">{{ requirement.label || requirement.name }}</RouterLink>
					<span class="mono dependencies__name">{{ versions(requirement) }}</span>
				</template>
				<span v-else :class="{ mono: requirement.kind === 'unknown' || requirement.kind === 'missing' || requirement.kind === 'library' || requirement.kind === 'composer' }">{{ requirementText(requirement) }}</span>
				<span v-if="requirement.note" class="dependencies__note" :class="{ 'is-unmet': !requirement.met }">{{ requirement.note }}</span>
			</div>
		</li>
	</ul>
</template>
