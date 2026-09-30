<script setup lang="ts">
/**
 * One schema field in the editor's form: its label, the control its type
 * needs (`fields.ts`), and its help text. Fields the form can't edit yet
 * show their value read-only. An error (a required field left empty when
 * publishing) shows beneath the control, which is marked invalid. A
 * choice shows its `choices` label when the field has one (a
 * component's props do, D-245). A date opens a month, with a 12-hour
 * time (`DatePicker`), and can be cleared. A reference picks from the
 * entries it points at (`ReferencePicker`). A media field, when `pickable`, has a
 * **Choose** button for the media picker beside it (D-247).
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import AdminSelect from './AdminSelect.vue';
import DatePicker from './DatePicker.vue';
import ReferencePicker from './ReferencePicker.vue';
import { control, help, label, type FormValue } from '../fields';

const props = defineProps<{ field: FieldDescription; error?: string; idPrefix?: string; pickable?: boolean }>();
const model = defineModel<FormValue>({ required: true });

// A media field can open the media picker (D-247).
const emit = defineEmits<{ pick: [] }>();

const id        = computed(() => `${props.idPrefix ?? 'field-'}${props.field.name}`);

function choice(option: string): string {
	const choices = props.field.choices;

	return typeof choices === 'object' && choices !== null && option in choices ? String((choices as Record<string, unknown>)[option]) : option;
}
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
			<AdminSelect v-else-if="kind === 'select'" :id="id" v-model="text" :options="[{ value: '', label: '—' }, ...(field.options ?? []).map((option) => ({ value: option, label: choice(option) }))]" :described-by="described" :invalid="invalid === 'true'" />
			<ReferencePicker v-else-if="field.type === 'reference' && field.to" :id="id" v-model="text" :field="field" :described-by="described" :invalid="invalid === 'true'" />
			<div v-else-if="kind === 'datetime'" class="field__date">
				<DatePicker :id="id" v-model="text" :described-by="described" :invalid="invalid === 'true'" />
				<button v-if="text" type="button" class="button button--ghost button--small" @click="text = ''">Clear<span class="visually-hidden"> {{ label(field).toLowerCase() }}</span></button>
			</div>
			<input v-else-if="kind === 'number'" :id="id" v-model="text" type="number" :min="field.min" :max="field.max" :step="field.integer ? 1 : 'any'" :aria-describedby="described" :aria-invalid="invalid">
			<pre v-else-if="kind === 'readonly'" :id="id" class="field__readonly" tabindex="0" :aria-describedby="`${id}-readonly`">{{ text || '—' }}</pre>
			<div v-else-if="field.type === 'media' && pickable" class="field__pick">
				<input :id="id" v-model="text" class="mono" :aria-describedby="described" :aria-invalid="invalid" autocomplete="off" spellcheck="false" placeholder="No file chosen">
				<button type="button" class="button button--small" @click="emit('pick')">Choose<span class="visually-hidden"> {{ label(field).toLowerCase() }}</span></button>
			</div>
			<input v-else :id="id" v-model="text" :class="{ mono: field.type === 'slug' || field.type === 'reference' || field.type === 'media' }" :aria-describedby="described" :aria-invalid="invalid" autocomplete="off" spellcheck="false">
		</template>

		<p v-if="kind === 'readonly'" :id="`${id}-readonly`" class="field__help">Edit this one in the file for now.</p>
		<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>
		<p v-if="errorId" :id="errorId" class="field__error">{{ error }}</p>
	</div>
</template>

<style scoped>
.field__date {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 6px;
}

.field__pick {
	display: flex;
	gap: 6px;
}

.field__pick input {
	flex: 1;
	min-width: 0;
}

.field__pick .button {
	flex: none;
}
</style>
