<script setup lang="ts">
/**
 * The control a schema field is edited with (D-337), on its own: the one
 * the server names (`control`), without the label, help, or error around
 * it, so forms with their own layout (`FieldControl`, the Settings
 * screens) draw the same controls.
 *
 * A toggle is a switch (`ToggleSwitch`) named by the field's label.
 * Radio buttons and checkboxes are a group, labeled by `labelledBy`, one
 * per option, each
 * with its `choices` label when the field has one; an optional choice's
 * empty option says the `caption`, or "None" (radio buttons) and "—" (a
 * menu), and a long menu has a search (D-443). Radio buttons and
 * checkboxes whose options have `details` (D-404, D-469) are a list,
 * each its name, then a sentence and any machine names it covers. In a
 * `narrow` panel, radio buttons with up to four short options and no
 * details are one segmented row; choosing the one chosen clears an
 * optional field. A multi-select is chips with an Add menu
 * (`ChoiceChips`). A date opens a month (`DatePicker`), without a time
 * when its field has none, and can be cleared. An icon opens the site's
 * icons (`IconField`). A
 * reference picks from the entries it points at (`ReferencePicker`). A
 * media control, when `pickable`, opens the media picker (D-247): an
 * image is shown as itself (`ImagePreview`), and other kinds by the
 * library's name for the file (`MediaChoice`); otherwise the value (the
 * file's id, D-773) is typed. A
 * link and an email are typed boxes of their kind, and a text box says
 * its `placeholder` while it's empty. A field the form can't edit shows
 * its value read-only.
 */

import { computed } from 'vue';
import type { FieldDescription } from '../api';
import AdminSelect from './AdminSelect.vue';
import ChoiceChips from './ChoiceChips.vue';
import DatePicker from './DatePicker.vue';
import IconField from './IconField.vue';
import ImagePreview from './ImagePreview.vue';
import MediaChoice from './MediaChoice.vue';
import ReferencePicker from './ReferencePicker.vue';
import ToggleSwitch from './ToggleSwitch.vue';
import { choiceLabel, control, isMedia, isShownOnly, label, type FormValue } from '../fields';

const props = defineProps<{
	field: FieldDescription;
	id: string;
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
	// What labels a group of radio buttons or checkboxes.
	labelledBy?: string;
	pickable?: boolean;
	narrow?: boolean;
}>();

const model = defineModel<FormValue>({ required: true });

// A media field can open the media picker (D-247).
const emit = defineEmits<{ pick: [] }>();

// A menu longer than this has a search (D-443), such as the time zones.
const SEARCHABLE = 20;

const kind    = computed(() => control(props.field));
const invalid = computed(() => props.invalid === true ? 'true' : undefined);

function choice(option: string): string {
	return choiceLabel(props.field, option);
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
const choicesOf = computed(() => kind.value === 'checkboxes' || kind.value === 'multiselect' ? (props.field.item?.options ?? []) : (props.field.options ?? []));

// Radio buttons as one segmented row, in a narrow panel: a few short
// options without details.
const segmented = computed(() => props.narrow === true
	&& props.field.details === undefined
	&& choicesOf.value.length > 0
	&& choicesOf.value.length <= 4
	&& choicesOf.value.every((option) => choice(option).length <= 14));

function press(option: string): void {
	text.value = text.value === option && props.field.required !== true ? '' : option;
}

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
	<ToggleSwitch v-if="kind === 'toggle'" :checked="checked" :label="label(field)" form :locked="disabled" :described-by="describedBy" @change="checked = $event" />
	<div v-else-if="kind === 'radios' && segmented" :id="id" class="segmented" role="group" :aria-labelledby="labelledBy" :aria-describedby="describedBy">
		<button v-for="option in choicesOf" :key="option" type="button" :aria-pressed="text === option" :disabled="disabled" @click="press(option)">{{ choice(option) }}</button>
	</div>
	<div v-else-if="kind === 'radios'" class="choices" :class="{ 'choices--detailed': field.details }" role="radiogroup" :aria-labelledby="labelledBy" :aria-describedby="describedBy" :aria-invalid="invalid">
		<label v-if="!field.required" class="checkbox"><input :id="id" v-model="text" type="radio" :name="id" value="" :disabled="disabled"> {{ field.caption ?? 'None' }}</label>
		<label v-for="(option, index) in choicesOf" :key="option" class="checkbox">
			<input :id="index === 0 && field.required ? id : undefined" v-model="text" type="radio" :name="id" :value="option" :disabled="disabled">
			<span v-if="detail(option)" class="choice">
				<span class="choice__name">{{ choice(option) }}</span>
				<span class="choice__text">{{ detail(option)?.text }}</span>
				<span v-if="detail(option)?.code" class="choice__code">{{ detail(option)?.code }}</span>
			</span>
			<template v-else>{{ choice(option) }}</template>
		</label>
	</div>
	<ChoiceChips v-else-if="kind === 'multiselect'" :id="id" v-model="text" :options="choicesOf" :label-of="choice" :name="label(field)" :described-by="describedBy" :labelled-by="labelledBy" :invalid="invalid === 'true'" :disabled="disabled" />
	<div v-else-if="kind === 'checkboxes'" class="choices" :class="{ 'choices--detailed': field.details }" role="group" :aria-labelledby="labelledBy" :aria-describedby="describedBy" :aria-invalid="invalid">
		<label v-for="(option, index) in choicesOf" :key="option" class="checkbox">
			<input :id="index === 0 ? id : undefined" type="checkbox" :checked="ticked.includes(option)" :disabled="disabled" @change="tick(option, ($event.target as HTMLInputElement).checked)">
			<span v-if="detail(option)" class="choice">
				<span class="choice__name">{{ choice(option) }}</span>
				<span class="choice__text">{{ detail(option)?.text }}</span>
				<span v-if="detail(option)?.code" class="choice__code">{{ detail(option)?.code }}</span>
			</span>
			<template v-else>{{ choice(option) }}</template>
		</label>
	</div>
	<textarea v-else-if="kind === 'textarea' || kind === 'markdown'" :id="id" v-model="text" rows="3" :placeholder="field.placeholder" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" />
	<AdminSelect v-else-if="kind === 'select'" :id="id" v-model="text" :searchable="choicesOf.length > SEARCHABLE" :options="[...(field.required && text !== '' ? [] : [{ value: '', label: field.caption ?? '—' }]), ...choicesOf.map((option) => ({ value: option, label: choice(option) }))]" :disabled="disabled" :described-by="describedBy" :invalid="invalid === 'true'" />
	<ReferencePicker v-else-if="kind === 'reference'" :id="id" v-model="text" :field="field" :described-by="describedBy" :invalid="invalid === 'true'" />
	<div v-else-if="kind === 'date'" class="field-input__date">
		<DatePicker :id="id" v-model="text" :day-only="field.time === false" :described-by="describedBy" :invalid="invalid === 'true'" />
		<button v-if="text" type="button" class="button button--ghost button--small" :disabled="disabled" @click="text = ''">Clear<span class="visually-hidden"> {{ label(field).toLowerCase() }}</span></button>
	</div>
	<input v-else-if="kind === 'number'" :id="id" v-model="text" type="number" :min="field.min" :max="field.max" :step="field.step ?? (field.integer ? 1 : 'any')" :placeholder="field.placeholder" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid">
	<pre v-else-if="isShownOnly(kind)" :id="id" class="field__readonly" tabindex="0" :aria-describedby="describedBy">{{ text || '—' }}</pre>
	<IconField v-else-if="kind === 'icon'" :id="id" v-model="text" :described-by="describedBy" :preview="(icon) => `${field.name}: ${icon.name}`" />
	<ImagePreview v-else-if="kind === 'image' && pickable" :src="text" :noun="label(field).toLowerCase()" @pick="emit('pick')" @remove="text = ''" />
	<MediaChoice v-else-if="isMedia(kind) && pickable" :id="id" v-model="text" :noun="label(field).toLowerCase()" :described-by="describedBy" :invalid="invalid === 'true'" :disabled="disabled" @pick="emit('pick')" />
	<input v-else :id="id" v-model="text" :type="kind === 'link' ? 'url' : (kind === 'email' ? 'email' : 'text')" :placeholder="field.placeholder" :class="{ mono: kind === 'code' || isMedia(kind) }" :disabled="disabled" :aria-describedby="describedBy" :aria-invalid="invalid" autocomplete="off" :spellcheck="kind === 'text'">
</template>

<style scoped>
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
