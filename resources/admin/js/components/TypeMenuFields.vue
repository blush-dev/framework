<script setup lang="ts">
/**
 * Where a content type sits in the Content menu (D-726): the type it's
 * under, one level deep, and its position among its siblings. A type
 * with types under it stays at the top level.
 */

import { computed } from 'vue';
import AdminSelect from './AdminSelect.vue';
import type { TypeForm } from '../type-form';
import { t } from '../messages';
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
	{ value: '', label: t('type_menu_fields.top_level', 'The top level') },
	...contentMenu(types.value).filter(({ type }) => type.name !== props.name).map(({ type }) => ({ value: type.name, label: type.labels.menu }))
]);
</script>

<template>
	<div class="field-pair">
		<div class="field">
			<label :for="`${idPrefix}under`">{{ t('type_menu_fields.under', 'Sits under') }}</label>
			<AdminSelect :id="`${idPrefix}under`" v-model="form.under" :options="options" :disabled="children.length > 0" :described-by="`${idPrefix}under-help`" />
			<p :id="`${idPrefix}under-help`" class="field__help">
				<template v-if="children.length">{{ t('type_menu_fields.has_children', '{names} {count, plural, one {sits} other {sit} } under it, so it stays at the top level.', { names: children.map((type) => type.labels.menu).join(' and '), count: children.length }) }}</template>
				<template v-else>{{ t('type_menu_fields.under_help', 'Nests it under another type in the Content menu, one level deep.') }}</template>
			</p>
		</div>
		<div class="field">
			<label :for="`${idPrefix}position`">{{ t('type_menu_fields.position', 'Position') }}</label>
			<input :id="`${idPrefix}position`" v-model="form.position" type="number" min="0" step="1" inputmode="numeric" :placeholder="t('type_menu_fields.position_placeholder', 'None')" :aria-describedby="`${idPrefix}position-help`">
			<p :id="`${idPrefix}position-help`" class="field__help">{{ t('type_menu_fields.position_help', 'Lower comes first among its siblings. Without one, after those with one, by name.') }}</p>
		</div>
	</div>
</template>
