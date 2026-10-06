<script setup lang="ts">
/**
 * One schema field in a form: its label, the control the server names
 * for it (`FieldInput`, D-337), and its help text. Fields the form can't
 * edit yet show their value read-only. An error (a required field left
 * empty when publishing) shows beneath the control, which is marked
 * invalid. Radio buttons and checkboxes are a group under the label.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import FieldInput from './FieldInput.vue';
import { control, help, label, type FormValue } from '../fields';

const props = defineProps<{ field: FieldDescription; error?: string; idPrefix?: string; pickable?: boolean }>();
const model = defineModel<FormValue>({ required: true });

// A media field can open the media picker (D-247).
const emit = defineEmits<{ pick: [] }>();

const id        = computed(() => `${props.idPrefix ?? 'field-'}${props.field.name}`);
const kind      = computed(() => control(props.field));
const group     = computed(() => kind.value === 'radios' || kind.value === 'checks');
const errorId   = computed(() => props.error === undefined || props.error === '' ? undefined : `${id.value}-error`);
const described = computed(() => [help(props.field) === '' ? '' : `${id.value}-help`, kind.value === 'readonly' ? `${id.value}-readonly` : '', errorId.value ?? ''].filter((part) => part !== '').join(' ') || undefined);
</script>

<template>
	<div class="field" :class="{ 'field--inline': kind === 'checkbox' }">
		<span v-if="group" :id="`${id}-label`" class="field__label">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></span>
		<label v-else-if="kind !== 'checkbox'" :for="id">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></label>

		<FieldInput v-model="model" :field="field" :id="id" :described-by="described" :invalid="errorId !== undefined" :labelled-by="group ? `${id}-label` : undefined" :pickable="pickable" @pick="emit('pick')" />

		<p v-if="kind === 'readonly'" :id="`${id}-readonly`" class="field__help">Edit this one in the file for now.</p>
		<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>
		<p v-if="errorId" :id="errorId" class="field__error">{{ error }}</p>
	</div>
</template>
