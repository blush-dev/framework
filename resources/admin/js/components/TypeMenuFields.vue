<script setup lang="ts">
/**
 * Where a content type sits in the Content menu (D-726): the type it's
 * under, one level deep, and its position among its siblings. A type
 * with types under it stays at the top level.
 */

import { computed } from 'vue';
import AdminSelect from './AdminSelect.vue';
import type { TypeForm } from '../type-form';
import { contentMenu, types } from '../types';

const props = defineProps<{
	idPrefix: string;
	// The type's own name, which it can't sit under.
	name: string;
}>();

const form = defineModel<TypeForm>({ required: true });

// The types under it now, which keep it at the top level.
const children = computed(() => types.value.filter((type) => type.under === props.name));

// The top-level types, in the menu's order.
const options = computed(() => [
	{ value: '', label: 'The top level' },
	...contentMenu(types.value).filter(({ type }) => type.name !== props.name).map(({ type }) => ({ value: type.name, label: type.labels.menu }))
]);
</script>

<template>
	<div class="field-pair">
		<div class="field">
			<label :for="`${idPrefix}under`">Sits under</label>
			<AdminSelect :id="`${idPrefix}under`" v-model="form.under" :options="options" :disabled="children.length > 0" :described-by="`${idPrefix}under-help`" />
			<p :id="`${idPrefix}under-help`" class="field__help">
				<template v-if="children.length">{{ children.map((type) => type.labels.menu).join(' and ') }} {{ children.length === 1 ? 'sits' : 'sit' }} under it, so it stays at the top level.</template>
				<template v-else>Nests it under another type in the Content menu, one level deep.</template>
			</p>
		</div>
		<div class="field">
			<label :for="`${idPrefix}position`">Position</label>
			<input :id="`${idPrefix}position`" v-model="form.position" type="number" min="0" step="1" inputmode="numeric" placeholder="None" :aria-describedby="`${idPrefix}position-help`">
			<p :id="`${idPrefix}position-help`" class="field__help">Lower comes first among its siblings. Without one, after those with one, by name.</p>
		</div>
	</div>
</template>
