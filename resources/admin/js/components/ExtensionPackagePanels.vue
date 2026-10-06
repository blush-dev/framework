<script setup lang="ts">
/**
 * An extension's package links (D-509), as every kind's details screen
 * shows them: Requires, checked against the site (D-431), then, when its
 * manifest has them, Conflicts (D-435), Replaces (D-436), Provides
 * (D-439), and Suggests (D-434). A theme that isn't active is checked as
 * if it were (`asIfActive`). `part` draws only Requires, or only the
 * rest, for a screen that puts Requires beside its Details.
 */

import { computed } from 'vue';
import ExtensionProvides from './ExtensionProvides.vue';
import ExtensionRequirements from './ExtensionRequirements.vue';
import ExtensionSuggestions from './ExtensionSuggestions.vue';
import type { ExtensionSummary } from '../api';

const props = defineProps<{
	extension: ExtensionSummary;
	// What isn't so while one it replaces is on: "It doesn't run".
	replacesHint: string;
	asIfActive?: boolean;
	part?: 'requires' | 'others';
}>();

const asIf = computed(() => props.asIfActive === true ? ', as if it were active' : '');
</script>

<template>
	<section v-if="part !== 'others'" class="panel" aria-labelledby="requires-heading">
		<header class="panel__header">
			<h2 id="requires-heading">Requires</h2>
			<p class="panel__hint">Checked against this site{{ asIf }}</p>
		</header>
		<div class="panel__body">
			<ExtensionRequirements :requirements="extension.requirements" />
		</div>
	</section>

	<template v-if="part !== 'requires'">
		<section v-if="extension.conflicts.length" class="panel" aria-labelledby="conflicts-heading">
			<header class="panel__header">
				<h2 id="conflicts-heading">Conflicts</h2>
				<p class="panel__hint">Checked against what's on{{ asIf }}</p>
			</header>
			<div class="panel__body">
				<ExtensionRequirements :requirements="extension.conflicts" list="conflicts" />
			</div>
		</section>

		<section v-if="extension.replaces.length" class="panel" aria-labelledby="replaces-heading">
			<header class="panel__header">
				<h2 id="replaces-heading">Replaces</h2>
				<p class="panel__hint">{{ replacesHint }} while one of these is on</p>
			</header>
			<div class="panel__body">
				<ExtensionRequirements :requirements="extension.replaces" list="replaces" />
			</div>
		</section>

		<section v-if="extension.provides.length" class="panel" aria-labelledby="provides-heading">
			<header class="panel__header">
				<h2 id="provides-heading">Provides</h2>
				<p class="panel__hint">Meets a requirement of any of these while it runs</p>
			</header>
			<div class="panel__body">
				<ExtensionProvides :provides="extension.provides" />
			</div>
		</section>

		<section v-if="extension.suggests.length" class="panel" aria-labelledby="suggests-heading">
			<header class="panel__header">
				<h2 id="suggests-heading">Suggests</h2>
				<p class="panel__hint">Works well with these; none is needed</p>
			</header>
			<div class="panel__body">
				<ExtensionSuggestions :suggestions="extension.suggests" />
			</div>
		</section>
	</template>
</template>
