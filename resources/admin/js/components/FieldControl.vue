<script setup lang="ts">
/**
 * One schema field in the editor's form: its label, the control its type
 * needs (`fields.ts`), and its help text. Fields the form can't edit yet
 * show their value read-only.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import { control, help, label, type FormValue } from '../fields';

const props = defineProps<{ field: FieldDescription }>();
const model = defineModel<FormValue>({ required: true });

const id     = computed(() => `field-${props.field.name}`);
const kind   = computed(() => control(props.field));
const helpId = computed(() => help(props.field) === '' ? undefined : `${id.value}-help`);

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
				<input :id="id" v-model="checked" type="checkbox" :aria-describedby="helpId">
				{{ label(field) }}
			</label>
		</template>
		<template v-else>
			<label :for="id">{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></label>

			<textarea v-if="kind === 'textarea'" :id="id" v-model="text" rows="3" :aria-describedby="helpId" />
			<textarea v-else-if="kind === 'lines'" :id="id" v-model="text" class="mono" rows="2" :aria-describedby="helpId" />
			<select v-else-if="kind === 'select'" :id="id" v-model="text" :aria-describedby="helpId">
				<option value="">—</option>
				<option v-for="option in field.options ?? []" :key="option" :value="option">{{ option }}</option>
			</select>
			<input v-else-if="kind === 'datetime'" :id="id" v-model="text" type="datetime-local" :aria-describedby="helpId">
			<input v-else-if="kind === 'number'" :id="id" v-model="text" type="number" :min="field.min" :max="field.max" :step="field.integer ? 1 : 'any'" :aria-describedby="helpId">
			<pre v-else-if="kind === 'readonly'" :id="id" class="field__readonly" tabindex="0" :aria-describedby="`${id}-readonly`">{{ text || '—' }}</pre>
			<input v-else :id="id" v-model="text" :class="{ mono: field.type === 'slug' || field.type === 'reference' || field.type === 'media' }" :aria-describedby="helpId" autocomplete="off" spellcheck="false">
		</template>

		<p v-if="kind === 'readonly'" :id="`${id}-readonly`" class="field__help">Edit this one in the file for now.</p>
		<p v-if="helpId" :id="helpId" class="field__help">{{ help(field) }}</p>
	</div>
</template>
