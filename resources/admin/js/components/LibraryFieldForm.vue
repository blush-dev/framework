<script setup lang="ts">
/**
 * A field of the field library (D-858) as a form: what every field has,
 * as `FieldForm` draws it on a screen or in a dialog (`page`), and for an
 * Object or a List of Objects, its fields as a tree in a box of their
 * own (`FieldNodeTree`, one level, D-770), with the same rows, moves,
 * and forms as before. A saved field's key is fixed (D-859).
 *
 * It edits one definition (`v-model`, with its control) and writes it
 * back after each change; one from outside (Revert) starts it over.
 * `check()` says whether it can be saved, focusing what's wrong.
 */

import { computed, ref, watch } from 'vue';
import type { FieldDescription } from '../api';
import { holdsFields } from '../controls';
import { isContainer, toDefinitions, toFieldNodes, type FieldNode } from '../field-tree';
import { label as labelOf } from '../fields';
import { t } from '../messages';
import FieldForm from './FieldForm.vue';
import FieldNodeTree from './FieldNodeTree.vue';

const props = withDefaults(defineProps<{
	// The key it was saved with, or `null` for a new field.
	saved: string | null;
	idPrefix: string;
	// How many entries have a value for it, and for its fields by both
	// keys (`details.servings`).
	counts?: Record<string, number>;
	// Keys it can't take, with where each is used.
	taken?: Record<string, string>;
	locked?: boolean;
}>(), { counts: () => ({}), taken: () => ({}), locked: false });

const definition = defineModel<FieldDescription>({ required: true });

const form  = ref<{ check: () => boolean } | null>(null);
const nodes = ref<FieldNode[]>(toFieldNodes([definition.value]));
const node  = computed(() => nodes.value[0]!);
// `FieldForm` keeps its own draft, so it's drawn again when the field is
// put back from outside.
const drawn = ref(0);

// Written back after each change; a definition from outside (Revert)
// starts the nodes over.
let written = JSON.stringify(definition.value);

watch(definition, (value) => {
	if (JSON.stringify(value) !== written) {
		nodes.value = toFieldNodes([value]);
		written     = JSON.stringify(value);
		drawn.value++;
	}
}, { deep: true });

function changed(): void {
	const next = toDefinitions(nodes.value)[0]!;

	written          = JSON.stringify(next);
	definition.value = next;
}

function update(field: FieldDescription): void {
	node.value.field = field;

	// A control that no longer holds fields lets go of the ones inside it.
	if (!holdsFields(field.control ?? '')) {
		node.value.children = [];
	}

	changed();
}

const name = computed(() => node.value.field.label || (node.value.field.name ? labelOf(node.value.field) : t('library_field_form.untitled', 'Untitled field')));

function check(): boolean {
	return form.value?.check() ?? false;
}

defineExpose({ check });
</script>

<template>
	<FieldForm
		:key="drawn"
		ref="form"
		:field="node.field"
		:saved="saved"
		:taken="taken"
		:count="saved === null ? 0 : (counts[saved] ?? 0)"
		:inside="node.children.map((child) => ({ name: child.field.name, label: child.field.label || labelOf(child.field) }))"
		:locked="locked"
		:id-prefix="idPrefix"
		page
		@update="update"
	>
		<template v-if="isContainer(node)" #inside>
			<FieldNodeTree
				:nodes="node.children"
				:depth="1"
				:labelled-by="`${idPrefix}inside-label`"
				:id-prefix="idPrefix"
				:locked="locked"
				:group="name"
				:counts="counts"
				:at="saved"
				alone
				@change="changed"
			/>
		</template>
	</FieldForm>
</template>
