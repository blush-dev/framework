<script setup lang="ts">
/**
 * A set's fields, or a type's own, as a tree in a panel (the fields
 * sketch, D-766): the top level of fields (`FieldNodeTree`, over the
 * shared `ItemTree`), each row a field's control tile, its label, and
 * whether it's required. A group's fields are counted on its row and
 * edited in its form, in a box of their own (D-776). **Add Field** asks
 * for the control first.
 *
 * It edits a list of definitions (`v-model`, each with its control) and
 * writes it back after each change (`toDefinitions`). A `locked` tree (a
 * set from code) only opens fields to read them. A `bare` tree is drawn
 * inside another panel (the new type wizard), its button on a row of its
 * own.
 */

import { computed, ref, watch } from 'vue';
import type { FieldDescription } from '../api';
import { toDefinitions, toFieldNodes, type FieldNode } from '../field-tree';
import { plural } from '../format';
import { flatten } from '../tree';
import AdminIcon from './AdminIcon.vue';
import EmptyState from './EmptyState.vue';
import FieldNodeTree from './FieldNodeTree.vue';

const props = withDefaults(defineProps<{
	headingId: string;
	heading?: string;
	hint?: string;
	idPrefix: string;
	// What the fields are saved in: "recipes", "each entry".
	noun?: string;
	locked?: boolean;
	// How many entries have a value under each saved key.
	counts?: Record<string, number>;
	// Keys the top level can't take, with where each is used.
	taken?: Record<string, string>;
	bare?: boolean;
}>(), { heading: 'Fields', hint: '', noun: 'entries', locked: false, counts: () => ({}), taken: () => ({}) });

const definitions = defineModel<FieldDescription[]>({ required: true });

const top   = ref<{ add: () => Promise<void> } | null>(null);
const nodes = ref<FieldNode[]>(toFieldNodes(definitions.value));
const total = computed(() => flatten(nodes.value).length);

// Written back after each change; a list from outside (Revert) starts
// the nodes over.
let written = JSON.stringify(definitions.value);

watch(definitions, (value) => {
	if (JSON.stringify(value) !== written) {
		nodes.value = toFieldNodes(value);
		written     = JSON.stringify(value);
	}
}, { deep: true });

function changed(): void {
	const next = toDefinitions(nodes.value);

	written           = JSON.stringify(next);
	definitions.value = next;
}

async function add(): Promise<void> {
	await top.value?.add();
}

defineExpose({ add });
</script>

<template>
	<component :is="bare ? 'div' : 'section'" :class="bare ? 'field-tree field-tree--bare' : 'panel field-tree'" :aria-labelledby="bare ? undefined : headingId">
		<header :class="bare ? 'field-tree__bar' : 'panel__header'">
			<h2 v-if="!bare" :id="headingId">{{ heading }}</h2>
			<p v-if="!bare" class="panel__hint">{{ hint || plural(total, 'field') }}</p>
			<div class="panel__actions">
				<button v-if="!locked" type="button" class="button button--small" @click="add"><AdminIcon name="plus" />Add Field</button>
			</div>
		</header>

		<slot name="notice" />

		<EmptyState v-if="!nodes.length" icon="group" heading="No Fields Yet" :text="locked ? 'It has no fields.' : `Add the first field, and every one of the ${noun} gets it.`">
			<template v-if="!locked" #actions>
				<button type="button" class="button button--primary" @click="add"><AdminIcon name="plus" />Add Field</button>
			</template>
		</EmptyState>

		<FieldNodeTree
			ref="top"
			:nodes="nodes"
			:labelled-by="headingId"
			:id-prefix="idPrefix"
			:locked="locked"
			:counts="counts"
			:taken="taken"
			@change="changed"
		/>
	</component>
</template>

<style scoped>
.field-tree__bar {
	display: flex;
	justify-content: flex-end;
	padding: 0 var(--padding-inline) var(--s-3);
}

.field-tree--bare :deep(.item-tree) {
	border-top: 1px solid var(--border-color);
}
</style>
