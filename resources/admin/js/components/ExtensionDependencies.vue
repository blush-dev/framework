<script setup lang="ts">
/**
 * An extension's package links as one panel (D-565, the extensions
 * sketch's Dependencies), the same for every kind: a section for each
 * list its manifest has, Requires (D-431), Conflicts (D-435), Replaces
 * (D-436), Provides (D-439), and Suggests (D-434), each checked against
 * the site (a theme that isn't active as if it were, `asIfActive`); then,
 * under "What others say about it", those on the other side (D-440):
 * Required by, Conflicts with it, Replaced by, and Also provided by.
 * A theme adds its Falls back to section first (`own`), and Used as
 * fallback by (`others`). One with none says so.
 */

import { computed, useSlots } from 'vue';
import ExtensionDependents from './ExtensionDependents.vue';
import ExtensionProvides from './ExtensionProvides.vue';
import ExtensionRequirements from './ExtensionRequirements.vue';
import ExtensionSuggestions from './ExtensionSuggestions.vue';
import type { ExtensionSummary } from '../api';

const props = defineProps<{
	extension: ExtensionSummary;
	// The kind, lowercase: "plugin", "theme", "icon pack".
	noun: string;
	// What isn't so while one it replaces is on: "It doesn't run".
	replacesHint: string;
	asIfActive?: boolean;
}>();

const slots = useSlots();

const others = computed(() => [
	{ key: 'required', label: 'Required by', list: props.extension.requiredBy },
	{ key: 'conflicted', label: 'Conflicts with it', list: props.extension.conflictedBy },
	{ key: 'replaced', label: 'Replaced by', list: props.extension.replacedBy },
	{ key: 'provided', label: 'Also provided by', list: props.extension.providedBy }
].filter((group) => group.list.length > 0));

const hasOwn = computed(() => Boolean(slots.own) || [props.extension.requirements, props.extension.conflicts, props.extension.replaces, props.extension.provides, props.extension.suggests].some((list) => list.length > 0));
const hasOthers = computed(() => Boolean(slots.others) || others.value.length > 0);
</script>

<template>
	<section class="panel" aria-labelledby="dependencies-heading">
		<header class="panel__header">
			<h2 id="dependencies-heading">Dependencies</h2>
			<p class="panel__hint">Checked against this site{{ asIfActive ? ', as if it were active' : '' }}</p>
		</header>

		<p v-if="!hasOwn && !hasOthers" class="panel__section field__help">This {{ noun }} declares none, and nothing depends on it.</p>

		<slot name="own" />
		<section v-if="extension.requirements.length" class="panel__section" aria-labelledby="requires-heading">
			<div class="panel__section-head"><h3 id="requires-heading" class="eyebrow">Requires</h3><span>Must be installed and on</span></div>
			<ExtensionRequirements :requirements="extension.requirements" />
		</section>
		<section v-if="extension.conflicts.length" class="panel__section" aria-labelledby="conflicts-heading">
			<div class="panel__section-head"><h3 id="conflicts-heading" class="eyebrow">Conflicts</h3><span>Can't be on at the same time</span></div>
			<ExtensionRequirements :requirements="extension.conflicts" list="conflicts" />
		</section>
		<section v-if="extension.replaces.length" class="panel__section" aria-labelledby="replaces-heading">
			<div class="panel__section-head"><h3 id="replaces-heading" class="eyebrow">Replaces</h3><span>{{ replacesHint }} while one of these is on</span></div>
			<ExtensionRequirements :requirements="extension.replaces" list="replaces" />
		</section>
		<section v-if="extension.provides.length" class="panel__section" aria-labelledby="provides-heading">
			<div class="panel__section-head"><h3 id="provides-heading" class="eyebrow">Provides</h3><span>Meets these requirements for others while it runs</span></div>
			<ExtensionProvides :provides="extension.provides" />
		</section>
		<section v-if="extension.suggests.length" class="panel__section" aria-labelledby="suggests-heading">
			<div class="panel__section-head"><h3 id="suggests-heading" class="eyebrow">Suggests</h3><span>Works well with these; none is needed</span></div>
			<ExtensionSuggestions :suggestions="extension.suggests" />
		</section>

		<template v-if="hasOthers">
			<div class="panel__section panel__section--turn">
				<div class="panel__section-head"><h3 class="eyebrow">What others say about it</h3></div>
			</div>
			<slot name="others" />
			<section v-for="group in others" :key="group.key" class="panel__section" :aria-labelledby="`${group.key}-heading`">
				<div class="panel__section-head"><h3 :id="`${group.key}-heading`" class="eyebrow">{{ group.label }}</h3></div>
				<ExtensionDependents :dependents="group.list" />
			</section>
		</template>
	</section>
</template>
