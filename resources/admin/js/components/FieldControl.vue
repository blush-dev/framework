<script setup lang="ts">
/**
 * One schema field in a form: its label, the control the server names
 * for it (`FieldInput`, D-766), and its help text. Fields the form can't
 * edit yet show their value read-only. An error (a required field left
 * empty when publishing) shows beneath the control, which is marked
 * invalid. Radio buttons and checkboxes are a group under the label; a
 * toggle sits at the end of its label's line.
 *
 * A group or a repeating group draws itself (`FieldGroup`,
 * `FieldRepeater`): in place at the first level (`nest` 0), and past it
 * as a row that opens a level down, where the screen can (D-768). `at`
 * says where the field is inside the form field it belongs to, so a
 * media control deep inside one can be picked for (`pick` gives the
 * field and its steps) and a level can be opened.
 *
 * A screen can put a line under the control (`after`), such as a value
 * offered for it, and say something in place of the help (`help`, given
 * the help's id), such as a warning; `warn` marks the control for one.
 * `narrow` is a side panel's: radio buttons with a few short options are
 * one segmented row there.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import type { FieldLevel } from '../field-levels';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';
import FieldRepeater from './FieldRepeater.vue';
import { control, help, isChoiceGroup, isShownOnly, label, type FieldStep, type FormValue } from '../fields';

const props = defineProps<{
	field: FieldDescription;
	error?: string;
	idPrefix?: string;
	pickable?: boolean;
	warn?: boolean;
	narrow?: boolean;
	nest?: number;
	at?: FieldLevel;
}>();

const model = defineModel<FormValue>({ required: true });

// A media control can open the media picker (D-247).
const emit = defineEmits<{ pick: [field: FieldDescription, steps: FieldStep[]] }>();

const id        = computed(() => `${props.idPrefix ?? 'field-'}${props.field.name}`);
const kind      = computed(() => control(props.field));
const where     = computed<FieldLevel>(() => props.at ?? { root: props.field.name, steps: [], trail: [label(props.field)] });
const group     = computed(() => isChoiceGroup(kind.value));
const labelOnly = computed(() => group.value || kind.value === 'toggle');
const errorId   = computed(() => props.error === undefined || props.error === '' ? undefined : `${id.value}-error`);
const described = computed(() => [help(props.field) === '' ? '' : `${id.value}-help`, isShownOnly(kind.value) ? `${id.value}-readonly` : '', errorId.value ?? ''].filter((part) => part !== '').join(' ') || undefined);
</script>

<template>
	<FieldGroup v-if="kind === 'group'" v-model="model" :field="field" :id-prefix="idPrefix" :error="error" :pickable="pickable" :narrow="narrow" :nest="nest ?? 0" :at="where" @pick="(target, steps) => emit('pick', target, steps)" />
	<FieldRepeater v-else-if="kind === 'repeater'" v-model="model" :field="field" :id-prefix="idPrefix" :error="error" :pickable="pickable" :narrow="narrow" :nest="nest ?? 0" :at="where" @pick="(target, steps) => emit('pick', target, steps)" />
	<div v-else class="field" :class="{ 'field--toggle': kind === 'toggle', 'field--warn': warn }">
		<span v-if="labelOnly" :id="`${id}-label`" class="field__label">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></span>
		<label v-else :for="id">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></label>

		<FieldInput v-model="model" :field="field" :id="id" :described-by="described" :invalid="errorId !== undefined" :labelled-by="labelOnly ? `${id}-label` : undefined" :pickable="pickable" :narrow="narrow" @pick="emit('pick', field, where.steps)" />

		<p v-if="isShownOnly(kind)" :id="`${id}-readonly`" class="field__help">Edit this one in the file for now.</p>
		<slot name="after" />
		<slot name="help" :id="`${id}-help`">
			<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>
		</slot>
		<p v-if="errorId" :id="errorId" class="field__error">{{ error }}</p>
	</div>
</template>
