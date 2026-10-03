<script setup lang="ts">
/**
 * The control a schema field is edited with (D-337), on its own: the one
 * the server names (`control`), without the label, help, or error around
 * it, so forms with their own layout (`FieldControl`, the Settings
 * screens) draw the same controls.
 *
 * A checkbox says the field's `caption`, or its label. Radio buttons and
 * checkboxes are a group, labeled by `labelledBy`, one per option, each
 * with its `choices` label when the field has one; an optional choice's
 * empty option says the `caption`, or "None" (radio buttons) and "—" (a
 * menu). Options with `details` (D-404) are a list, each its name, then
 * a sentence and the machine names it covers. A date opens a month (`DatePicker`) and can be cleared. A
 * reference picks from the entries it points at (`ReferencePicker`). A
 * media field, when `pickable`, has a **Choose** button for the media
 * picker beside it (D-247); otherwise its path is typed. A field the form
 * can't edit shows its value read-only.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import AdminSelect from './AdminSelect.vue';
import DatePicker from './DatePicker.vue';
import ReferencePicker from './ReferencePicker.vue';
import { control, label, type FormValue } from '../fields';

const props = defineProps<{
	field: FieldDescription;
	id: string;
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
	// What labels a group of radio buttons or checkboxes.
	labelledBy?: string;
	pickable?: boolean;
}>();

const model = defineModel<FormValue>({ required: true });

// A media field can open the media picker (D-247).
const emit = defineEmits<{ pick: [] }>();

const kind    = computed(() => control(props.field));
const invalid = computed(() => props.invalid === true ? 'true' : undefined);

function choice(option: string): string {
	const choices = props.field.choices ?? props.field.item?.choices;

	return typeof choices === 'object' && choices !== null && option in choices ? String((choices as Record<string, unknown>)[option]) : option;
}

// Text-typed state for the controls that hold text.
const text = computed({
	get: () => typeof model.value === 'string' ? model.value : '',
	set: (value: string) => {
		model.value = value;
	}
});

// The options ticked, for checkboxes: the state holds one per line.
const ticked = computed(() => text.value.split('\n').filter((line) => line !== ''));

// A choice's options, or a list of choices' item options.
const choicesOf = computed(() => kind.value === 'checks' ? (props.field.item?.options ?? []) : (props.field.options ?? []));

// An option's details, if the field has them.
function detail(option: string): { text: string; code: string } | null {
	return props.field.details?.[option] ?? null;
}

function tick(option: string, on: boolean): void {
	text.value = choicesOf.value.filter((item) => item === option ? on : ticked.value.includes(item)).join('\n');
}

const checked = computed({
	get: () => model.value === true,
	set: (value: boolean) => {
		model.value = value;
	}
});
</script>

<template>
	<label v-if="kind === 'checkbox'" class="checkbox" :for="id">
		<input :id="id" v-model="checked" type="checkbox" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid">
		{{ field.caption ?? label(field) }}
	</label>
	<div v-else-if="kind === 'radios'" class="field-input__choices" role="radiogroup" :aria-labelledby="labelledBy" :aria-describedby="describedBy" :aria-invalid="invalid">
		<label v-if="!field.required" class="checkbox"><input :id="id" v-model="text" type="radio" :name="id" value="" :disabled="disabled"> {{ field.caption ?? 'None' }}</label>
		<label v-for="(option, index) in choicesOf" :key="option" class="checkbox"><input :id="index === 0 && field.required ? id : undefined" v-model="text" type="radio" :name="id" :value="option" :disabled="disabled"> {{ choice(option) }}</label>
	</div>
	<div v-else-if="kind === 'checks'" class="field-input__choices" :class="{ 'field-input__choices--detailed': field.details }" role="group" :aria-labelledby="labelledBy" :aria-describedby="describedBy" :aria-invalid="invalid">
		<label v-for="(option, index) in choicesOf" :key="option" class="checkbox">
			<input :id="index === 0 ? id : undefined" type="checkbox" :checked="ticked.includes(option)" :disabled="disabled" @change="tick(option, ($event.target as HTMLInputElement).checked)">
			<span v-if="detail(option)" class="field-input__choice">
				<span class="field-input__name">{{ choice(option) }}</span>
				<span class="field-input__text">{{ detail(option)?.text }}</span>
				<span class="field-input__code">{{ detail(option)?.code }}</span>
			</span>
			<template v-else>{{ choice(option) }}</template>
		</label>
	</div>
	<textarea v-else-if="kind === 'textarea'" :id="id" v-model="text" rows="3" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" />
	<textarea v-else-if="kind === 'lines'" :id="id" v-model="text" class="mono" rows="3" spellcheck="false" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" />
	<AdminSelect v-else-if="kind === 'select'" :id="id" v-model="text" :options="[...(field.required && text !== '' ? [] : [{ value: '', label: field.caption ?? '—' }]), ...choicesOf.map((option) => ({ value: option, label: choice(option) }))]" :disabled="disabled" :described-by="describedBy" :invalid="invalid === 'true'" />
	<ReferencePicker v-else-if="kind === 'reference'" :id="id" v-model="text" :field="field" :described-by="describedBy" :invalid="invalid === 'true'" />
	<div v-else-if="kind === 'date'" class="field-input__date">
		<DatePicker :id="id" v-model="text" :described-by="describedBy" :invalid="invalid === 'true'" />
		<button v-if="text" type="button" class="button button--ghost button--small" :disabled="disabled" @click="text = ''">Clear<span class="visually-hidden"> {{ label(field).toLowerCase() }}</span></button>
	</div>
	<input v-else-if="kind === 'number'" :id="id" v-model="text" type="number" :min="field.min" :max="field.max" :step="field.integer ? 1 : 'any'" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid">
	<pre v-else-if="kind === 'readonly'" :id="id" class="field__readonly" tabindex="0" :aria-describedby="describedBy">{{ text || '—' }}</pre>
	<div v-else-if="kind === 'media' && pickable" class="field-input__pick">
		<input :id="id" v-model="text" class="mono" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" autocomplete="off" spellcheck="false" placeholder="No file chosen">
		<button type="button" class="button button--small" :disabled="disabled" @click="emit('pick')">Choose<span class="visually-hidden"> {{ label(field).toLowerCase() }}</span></button>
	</div>
	<input v-else :id="id" v-model="text" :class="{ mono: kind !== 'text' }" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" autocomplete="off" :spellcheck="kind === 'text'">
</template>

<style scoped>
.field-input__choices {
	display: grid;
	gap: 7px;
}

/* Only the name is a name: the sentence and the machine names under it
   are quieter, so a list of them stays readable. */
.field-input__choices--detailed {
	gap: var(--s-4);
}

.field-input__choices--detailed .checkbox {
	align-items: flex-start;
	line-height: 1.45;
}

.field-input__choices--detailed input {
	margin-top: 2px;
}

.field-input__choice {
	display: grid;
	gap: 1px;
	min-width: 0;
}

.field-input__name {
	color: var(--fg);
	font-weight: 500;
}

.field-input__text {
	color: var(--fg-2);
}

.field-input__code {
	margin-top: 3px;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	overflow-wrap: anywhere;
}

.field-input__date {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 6px;
}

.field-input__pick {
	display: flex;
	gap: 6px;
}

.field-input__pick input {
	flex: 1;
	min-width: 0;
}

.field-input__pick .button {
	flex: none;
}
</style>
