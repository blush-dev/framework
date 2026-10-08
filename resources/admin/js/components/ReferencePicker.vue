<script setup lang="ts">
/**
 * A reference field in the editor (D-242's picker, D-281): the entries
 * of another type it points at, by slug, from `GET references/{type}`.
 * The relation decides how it's picked (D-599, D-607), and each control
 * is its own component over what they share (`picker.ts`):
 *
 * - **Select** (`ReferenceSelect`): exactly one, whatever the purpose.
 * - **People** (`ReferencePeople`): credits, several, usually in order.
 * - **Tree** (`ReferenceTree`): terms that nest, several at once.
 * - **Cards** (`ReferenceCards`): other entries, told apart by a
 *   picture and a date.
 * - **Tokens** (`ReferenceTokens`): flat terms or short targets.
 *
 * Without a relation (a plain reference field), people are people, one
 * value is a select, a nesting type's terms are a tree, and anything
 * else is tokens.
 */

import { computed } from 'vue';
import type { FieldDescription, InheritedValues } from '../api';
import { findType } from '../types';
import ReferenceCards from './ReferenceCards.vue';
import ReferencePeople from './ReferencePeople.vue';
import ReferenceSelect from './ReferenceSelect.vue';
import ReferenceTokens from './ReferenceTokens.vue';
import ReferenceTree from './ReferenceTree.vue';

const props = defineProps<{
	id: string;
	field: FieldDescription;
	// Whether it points at people (the site's profiles).
	people?: boolean;
	// Whether the last person stays (an entry's main byline, or a
	// required people field).
	keepLast?: boolean;
	// The entry's own slug, which is never offered.
	self?: string;
	// A term's parent: its own branch is left out too.
	branch?: boolean;
	invalid?: boolean;
	describedBy?: string;
	// A single value drawn as a value in a row, not a box.
	plain?: boolean;
	// What a translation uses from its original.
	inherited?: InheritedValues;
	// Whether publishing was tried with something missing.
	attempted?: boolean;
}>();

const model = defineModel<string>({ required: true });

const control = computed(() => {
	const named = props.field.relation?.control;

	if (props.field.multiple === false) {
		return 'select';
	}

	if (props.people === true || named === 'people') {
		return 'people';
	}

	return named ?? (findType(props.field.to ?? '')?.hierarchical === true ? 'tree' : 'tokens');
});

const shared = computed(() => ({
	id: props.id,
	field: props.field,
	keepLast: props.keepLast,
	self: props.self,
	invalid: props.invalid,
	describedBy: props.describedBy,
	plain: props.plain,
	inherited: props.inherited,
	attempted: props.attempted
}));
</script>

<template>
	<ReferenceSelect v-if="control === 'select'" v-bind="shared" v-model="model" :people="people" :branch="branch" />
	<ReferencePeople v-else-if="control === 'people'" v-bind="shared" v-model="model" />
	<ReferenceTree v-else-if="control === 'tree'" v-bind="shared" v-model="model" />
	<ReferenceCards v-else-if="control === 'cards'" v-bind="shared" v-model="model" />
	<ReferenceTokens v-else v-bind="shared" v-model="model" />
</template>
