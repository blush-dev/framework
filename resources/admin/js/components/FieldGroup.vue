<script setup lang="ts">
/**
 * A group control (D-766, D-768): a few fields under one heading. At the
 * first level (`nest` 0) it's the heading, its help, and its fields in
 * place. Inside another group, where the screen can open a level down
 * (`OPEN_LEVEL`), it's a row saying how many of its fields are filled
 * in, which opens the group in its own level with a path back; where it
 * can't, it draws in place at every level.
 *
 * Its state is its fields' states (`groupOf`, `fromGroup`); an error is
 * the whole group's, from the server.
 */

import { computed, inject } from 'vue';
import type { FieldDescription } from '../api';
import { OPEN_LEVEL, type FieldLevel } from '../field-levels';
import AdminIcon from './AdminIcon.vue';
import FieldControl from './FieldControl.vue';
import { childrenOf, fromGroup, groupOf, help, isEmptyState, label, type FieldStep, type FormValue } from '../fields';
import { t } from '../messages';

const props = defineProps<{
	field: FieldDescription;
	at: FieldLevel;
	nest: number;
	idPrefix?: string;
	error?: string;
	pickable?: boolean;
	narrow?: boolean;
}>();

const model = defineModel<FormValue>({ required: true });

const emit = defineEmits<{ pick: [field: FieldDescription, steps: FieldStep[]] }>();

const openLevel = inject(OPEN_LEVEL, null);

const fields = computed(() => childrenOf(props.field));
const state  = computed(() => groupOf(model.value));
const filled = computed(() => fields.value.filter((child) => !isEmptyState(state.value[child.name])).length);
const id     = computed(() => `${props.idPrefix ?? 'field-'}${props.field.name}`);
const asRow  = computed(() => props.nest > 0 && openLevel !== null);

function set(name: string, value: FormValue): void {
	model.value = fromGroup({ ...state.value, [name]: value });
}

function where(child: FieldDescription): FieldLevel {
	return { root: props.at.root, steps: [...props.at.steps, child.name], trail: [...props.at.trail, label(child)] };
}
</script>

<template>
	<button v-if="asRow" :id="id" type="button" class="level-link level-link--boxed" :class="{ 'is-quiet': filled === 0 }" @click="openLevel?.(at)">
		<AdminIcon name="layers" />
		<span class="level-link__text"><strong>{{ label(field) }}</strong><span>{{ filled === 0 ? t('field_group.nothing_filled', 'Nothing filled in') : t('field_group.filled', '{filled} of {count, plural, one {# field} other {# fields} } filled in', { filled, count: fields.length }) }}</span></span>
		<AdminIcon name="chevron-right" class="level-link__go" />
	</button>
	<fieldset v-else :id="id" class="fieldset fieldset--group" :aria-describedby="help(field) !== '' ? `${id}-help` : undefined">
		<legend>{{ label(field) }}<span v-if="field.required" class="field__required"> {{ t('field_group.required', '(required)') }}</span></legend>
		<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>
		<FieldControl
			v-for="child in fields"
			:key="child.name"
			:field="child"
			:id-prefix="`${id}-`"
			:model-value="state[child.name] ?? ''"
			:pickable="pickable"
			:narrow="narrow"
			:nest="nest + 1"
			:at="where(child)"
			@update:model-value="set(child.name, $event)"
			@pick="(target, steps) => emit('pick', target, steps)"
		/>
		<p v-if="error" class="field__error">{{ error }}</p>
	</fieldset>
</template>
