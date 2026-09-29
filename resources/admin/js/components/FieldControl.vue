<script setup lang="ts">
/**
 * One schema field in the editor's form: its label, the control its type
 * needs (`fields.ts`), and its help text. Fields the form can't edit yet
 * show their value read-only. An error (a required field left empty when
 * publishing) shows beneath the control, which is marked invalid.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import { control, help, label, type FormValue } from '../fields';

const props = defineProps<{ field: FieldDescription; error?: string }>();
const model = defineModel<FormValue>({ required: true });

const id        = computed(() => `field-${props.field.name}`);
const kind      = computed(() => control(props.field));
const errorId   = computed(() => props.error === undefined || props.error === '' ? undefined : `${id.value}-error`);
const described = computed(() => [help(props.field) === '' ? '' : `${id.value}-help`, errorId.value ?? ''].filter((part) => part !== '').join(' ') || undefined);
const invalid   = computed(() => errorId.value === undefined ? undefined : 'true');

// Text-typed state for the controls that hold text.
const text = computed({
	get: () => typeof model.value === 'string' ? model.value : '',
	set: (value: string) => {
		model.value = value;
	}
});

const checked = computed({
	get: () => model.value === true,
	set: (value: boolean) => {
		model.value = value;
	}
});
</script>

<template>
	<div class="field" :class="{ 'field--inline': kind === 'checkbox' }">
		<template v-if="kind === 'checkbox'">
			<label class="checkbox" :for="id">
				<input :id="id" v-model="checked" type="checkbox" :aria-describedby="described" :aria-invalid="invalid">
				{{ label(field) }}
			</label>
		</template>
		<template v-else>
			<label :for="id">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></label>

			<textarea v-if="kind === 'textarea'" :id="id" v-model="text" rows="3" :aria-describedby="described" :aria-invalid="invalid" />
			<textarea v-else-if="kind === 'lines'" :id="id" v-model="text" class="mono" rows="2" :aria-describedby="described" :aria-invalid="invalid" />
			<select v-else-if="kind === 'select'" :id="id" v-model="text" :aria-describedby="described" :aria-invalid="invalid">
				<option value="">—</option>
				<option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
			</select>
			<input v-else-if="kind === 'datetime'" :id="id" v-model="text" type="datetime-local" :aria-describedby="described" :aria-invalid="invalid">
			<input v-else-if="kind === 'number'" :id="id" v-model="text" type="number" :min="field.min" :max="field.max" :step="field.integer ? 1 : 'any'" :aria-describedby="described" :aria-invalid="invalid">
			<pre v-else-if="kind === 'readonly'" :id="id" class="field__readonly" tabindex="0" :aria-describedby="`${id}-readonly`">{{ text || '—' }}</pre>
			<input v-else :id="id" v-model="text" :class="{ mono: field.type === 'slug' || field.type === 'reference' || field.type === 'media' }" :aria-describedby="described" :aria-invalid="invalid" autocomplete="off" spellcheck="false">
		</template>

		<p v-if="kind === 'readonly'" :id="`${id}-readonly`" class="field__help">Edit this one in the file for now.</p>
		<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>
		<p v-if="errorId" :id="errorId" class="field__error">{{ error }}</p>
	</div>
</template>
