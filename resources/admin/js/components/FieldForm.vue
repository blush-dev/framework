<script setup lang="ts">
/**
 * One field, as a form under its row in a set's tree (the fields sketch,
 * D-766): what every field has (its label, its key, which follows the
 * label until it's typed or saved, and its control, a tile that opens
 * `ControlPicker`), then its default, placeholder, and help, whether
 * it's required to publish, what its control adds (a choice's options,
 * a number's limits, whether a date has a time, a repeating group's
 * item name, limits, and summary), and, under Advanced, the older keys
 * it still reads. A group's form holds its fields in a box: the `inside`
 * slot, a tree of their own with **Add Field** at its foot (D-776).
 *
 * A default is drawn as the sketch draws it: a drawn select of the
 * options (with None) for one choice, chips for several, a switch for a
 * toggle, and the field's own control for the rest.
 *
 * It changes the field as it's edited (`update`), so closing its row
 * keeps what was typed; **Done** closes it once the key is a key no field
 * beside it has and a choice has options, and **Cancel** puts back what
 * the field was when it opened (D-776). Changing a saved key that
 * entries have values under asks on Done, and keeps the old key as an
 * alias unless told not to; closed another way, the old key is kept. A group holds no
 * value, so it takes no default and isn't required. A `locked` form (a
 * set from code) is read only.
 */

import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { FieldDescription } from '../api';
import { confirmChecked } from '../confirm';
import { catalogControl, controlIcon, controlLabel, holdsFields, isChoice, isManyChoice, optionsOf, takesPlaceholder, withControl, withOptions } from '../controls';
import { fromForm, label as labelOf, toForm, type FormValue } from '../fields';
import { plural } from '../format';
import { copy, keyOf } from '../type-form';
import AdminIcon from './AdminIcon.vue';
import AdminSelect from './AdminSelect.vue';
import ChoiceOptions, { type ChoiceOption } from './ChoiceOptions.vue';
import ControlPicker from './ControlPicker.vue';
import FieldInput from './FieldInput.vue';
import ToggleSwitch from './ToggleSwitch.vue';

const props = defineProps<{
	field: FieldDescription;
	// The key it was saved with, or `null` for a new field.
	saved: string | null;
	// Keys it can't take: other fields', with where each is.
	taken: Record<string, string>;
	// How many entries have a value under its saved key.
	count: number;
	// The fields inside it, for a repeating group's summary.
	inside: { name: string; label: string }[];
	// Inside a group, where it can't be a group itself (D-770).
	nested?: boolean;
	locked?: boolean;
	idPrefix: string;
}>();

const emit = defineEmits<{ update: [field: FieldDescription]; done: []; closed: []; remove: [] }>();

const draft   = ref<FieldDescription>(copy(props.field));
const opened  = copy(props.field);
// Whether a changed key's old one has been asked about.
let keyAsked  = false;

// Each change is the field's at once.
watch(draft, (value) => {
	emit('update', copy(value));
}, { deep: true });

// Closed without Done, a saved key that changed keeps being read.
onBeforeUnmount(() => {
	const saved = props.saved;

	if (!keyAsked && saved !== null && saved !== draft.value.name && props.count > 0 && !(draft.value.aliases ?? []).includes(saved)) {
		emit('update', { ...copy(draft.value), aliases: [...(draft.value.aliases ?? []), saved] });
	}

	emit('closed');
});

function cancel(): void {
	keyAsked = true;
	emit('update', copy(opened));
	emit('done');
}
const keyed   = ref(props.saved !== null);
const picking = ref(false);
const tried   = ref(false);

const control  = computed(() => draft.value.control ?? 'text');
const holds    = computed(() => !holdsFields(control.value));
const id       = (name: string): string => `${props.idPrefix}${name}`;
const options  = computed<ChoiceOption[]>({
	get: () => optionsOf(draft.value),
	set: (value) => {
		draft.value = withOptions(draft.value, value);
	}
});

const label = computed({
	get: () => draft.value.label ?? '',
	set: (value: string) => {
		draft.value.label = value;

		if (!keyed.value) {
			draft.value.name = keyOf(value);
		}
	}
});

const keyError = computed(() => {
	const name = draft.value.name;

	if (name === '') {
		return 'Give the field a key.';
	}

	if (!/^[a-z_][a-z0-9_]*$/.test(name)) {
		return 'A key uses lowercase letters, digits, and underscores, and doesn\'t start with a digit.';
	}

	const where = props.taken[name];

	return where === undefined ? '' : `${where} already has a field called “${name}”.`;
});

const optionsError = computed(() => {
	if (!isChoice(control.value)) {
		return '';
	}

	const values = options.value.map((option) => option.value);

	if (values.length === 0 || values.some((value) => value === '')) {
		return 'Give every option a key, and add at least one.';
	}

	return new Set(values).size === values.length ? '' : 'Two options can\'t share a key.';
});

// The default's own control: the field as forms draw it, with plain
// options and their labels.
const defaultField = computed<FieldDescription>(() => {
	const shown   = { ...draft.value, name: 'default' } as FieldDescription;
	const values  = options.value.map((option) => option.value);
	const choices = Object.fromEntries(options.value.map((option) => [option.value, option.label]));

	if (isManyChoice(control.value)) {
		shown.item = { ...(draft.value.item ?? { name: '', type: 'enum' }), options: values, choices };
	} else if (isChoice(control.value)) {
		shown.options = values;
		shown.choices = choices;
	}

	return shown;
});

// What takes a default: a value someone types or picks, not a file.
const takesDefault = computed(() => holds.value && !['image', 'video', 'audio', 'file', 'code', 'readonly', 'reference'].includes(control.value));

// One choice's default, from its options or none.
const defaultChoice = computed({
	get: () => typeof draft.value.default === 'string' ? draft.value.default : '',
	set: (value: string) => {
		if (value === '') {
			delete draft.value.default;
		} else {
			draft.value.default = value;
		}
	}
});

// Several choices' default: the options on, of those there are.
const defaultMany = computed(() => Array.isArray(draft.value.default) ? (draft.value.default as unknown[]).filter((value): value is string => typeof value === 'string' && options.value.some((option) => option.value === value)) : []);

function toggleDefault(value: string): void {
	const next = defaultMany.value.includes(value) ? defaultMany.value.filter((item) => item !== value) : [...defaultMany.value, value];

	if (next.length) {
		draft.value.default = next;
	} else {
		delete draft.value.default;
	}
}

function setDefaultOn(on: boolean): void {
	if (on) {
		draft.value.default = true;
	} else {
		delete draft.value.default;
	}
}

const defaultState = computed<FormValue>({
	get: () => toForm(defaultField.value, draft.value.default),
	set: (value) => {
		const next = fromForm(defaultField.value, value, draft.value.default);

		if (next === null || next === false) {
			delete draft.value.default;
		} else {
			draft.value.default = next;
		}
	}
});

const required = computed({
	get: () => draft.value.required === true,
	set: (value: boolean) => {
		if (value) {
			draft.value.required = true;
		} else {
			delete draft.value.required;
		}
	}
});

const aliases = computed({
	get: () => (draft.value.aliases ?? []).join(', '),
	set: (value: string) => {
		const keys = value.split(/[\s,]+/).filter((key) => key !== '');

		if (keys.length) {
			draft.value.aliases = keys;
		} else {
			delete draft.value.aliases;
		}
	}
});

// A number's or a list's own setting, as text while it's typed.
function numberText(key: 'min' | 'max' | 'step'): string {
	const value = draft.value[key];

	return typeof value === 'number' ? String(value) : '';
}

function setNumber(key: 'min' | 'max' | 'step', text: string): void {
	const value = Number(text);

	if (text.trim() === '' || Number.isNaN(value)) {
		delete draft.value[key];
	} else {
		draft.value[key] = value;
	}
}

function setText(key: 'placeholder' | 'description' | 'itemLabel', text: string): void {
	if (text === '') {
		delete draft.value[key];
	} else {
		draft.value[key] = text;
	}
}

function setFlag(key: 'integer', on: boolean): void {
	if (on) {
		draft.value[key] = true;
	} else {
		delete draft.value[key];
	}
}

function setTime(on: boolean): void {
	if (on) {
		delete draft.value.time;
	} else {
		draft.value.time = false;
	}
}

function toggleSummary(name: string): void {
	const summary = draft.value.summary ?? [];
	const next    = summary.includes(name) ? summary.filter((key) => key !== name) : [...summary, name];

	if (next.length) {
		draft.value.summary = next;
	} else {
		delete draft.value.summary;
	}
}

function choose(key: string): void {
	picking.value = false;
	draft.value   = withControl(draft.value, key);
}

async function finish(): Promise<void> {
	tried.value = true;

	if (keyError.value !== '' || optionsError.value !== '') {
		document.getElementById(id(keyError.value !== '' ? 'key' : 'options'))?.focus();

		return;
	}

	const saved = props.saved;

	if (saved !== null && saved !== draft.value.name && props.count > 0) {
		const keep = await confirmChecked({
			title: 'Change the Key?',
			body: `${plural(props.count, 'entry has', 'entries have')} a value under ${saved}. Their files keep it; the field reads it there only while the old key is kept.`,
			check: `Keep reading their values from ${saved}`,
			confirm: 'Change Key'
		});

		if (keep === null) {
			return;
		}

		keyAsked = true;

		if (keep) {
			draft.value.aliases = [...new Set([...(draft.value.aliases ?? []), saved])];
		}
	}

	emit('update', copy(draft.value));
	emit('done');
}
</script>

<template>
	<div class="field-form">
		<div class="field-form__ident">
			<div class="field">
				<label :for="id('label')">Label</label>
				<input :id="id('label')" v-model="label" autocomplete="off" placeholder="Prep time" :readonly="locked">
			</div>
			<div class="field">
				<label :for="id('key')">Key</label>
				<input :id="id('key')" v-model="draft.name" class="mono" autocomplete="off" spellcheck="false" placeholder="prep_time" :readonly="locked" :aria-invalid="tried && keyError !== '' ? 'true' : undefined" :aria-describedby="id('key-help')" @input="keyed = true">
				<p v-if="(tried || keyed) && keyError" :id="id('key-help')" class="field__error">{{ keyError }}</p>
				<p v-else :id="id('key-help')" class="field__help">{{ holds ? 'How it\'s saved in each entry. Follows the label until you change it.' : 'The group\'s name in this set. Its fields are saved under their own keys.' }}</p>
			</div>
			<div class="field">
				<span :id="id('control-label')" class="field__label">Control</span>
				<button type="button" class="field-form__control" :aria-labelledby="`${id('control-label')} ${id('control')}`" aria-haspopup="dialog" :disabled="locked" @click="picking = true">
					<AdminIcon :name="controlIcon(control)" />
					<strong :id="id('control')">{{ controlLabel(control) }}</strong>
					<span v-if="!locked" class="field-form__change">Change</span>
				</button>
			</div>
		</div>

		<div class="field-form__row">
			<div v-if="takesDefault" class="field">
				<span :id="id('default-label')" class="field__label">Default</span>
				<div v-if="isManyChoice(control)" class="chip-toggles" role="group" :aria-labelledby="id('default-label')">
					<button v-for="option in options" :key="option.value" type="button" :aria-pressed="defaultMany.includes(option.value)" :disabled="locked || option.value === ''" @click="toggleDefault(option.value)"><AdminIcon name="check" />{{ option.label || option.value }}</button>
					<span v-if="!options.length" class="field__help">No options to pick from.</span>
				</div>
				<AdminSelect v-else-if="isChoice(control)" :id="id('default')" v-model="defaultChoice" :options="[{ value: '', label: 'None' }, ...options.filter((option) => option.value !== '').map((option) => ({ value: option.value, label: option.label || option.value }))]" :disabled="locked" />
				<ToggleSwitch v-else-if="control === 'toggle'" :checked="draft.default === true" label="On by default" form :locked="locked" @change="setDefaultOn">On by default</ToggleSwitch>
				<FieldInput v-else :id="id('default')" v-model="defaultState" :field="defaultField" :labelled-by="id('default-label')" :disabled="locked" />
			</div>
			<div v-if="takesPlaceholder(control)" class="field">
				<label :for="id('placeholder')">Placeholder</label>
				<input :id="id('placeholder')" :value="draft.placeholder ?? ''" autocomplete="off" placeholder="None" :readonly="locked" :aria-describedby="id('placeholder-help')" @input="setText('placeholder', ($event.target as HTMLInputElement).value)">
				<p :id="id('placeholder-help')" class="field__help">A prompt in the empty box. Never saved.</p>
			</div>
			<div class="field field-form__help">
				<label :for="id('help')">Help</label>
				<textarea :id="id('help')" :value="draft.description ?? ''" rows="2" :readonly="locked" :aria-describedby="id('help-help')" @input="setText('description', ($event.target as HTMLTextAreaElement).value)" />
				<p :id="id('help-help')" class="field__help">Shown under the field in the editor.</p>
			</div>
		</div>

		<div v-if="holdsFields(control) && $slots.inside" class="field">
			<span :id="`${idPrefix}inside-label`" class="field__label">{{ control === 'repeater' ? 'Each item has' : 'Its fields' }}</span>
			<slot name="inside" />
			<p class="field__help">{{ control === 'repeater' ? 'Every item in the list has these, in this order.' : 'These are saved under their own keys, next to the set\'s other fields.' }}</p>
		</div>

		<p v-if="control === 'group'" class="field-form__note"><AdminIcon name="info" /><span>A group holds no value of its own. Defaults, Required to publish, and older keys belong to the fields inside it.</span></p>
		<ToggleSwitch v-else :checked="required" label="Required to publish" form :locked="locked" @change="required = $event">
			Required to publish
			<small>{{ control === 'repeater' ? 'A draft still saves. Publishing needs at least one item.' : 'A draft still saves. Publishing names the field.' }}</small>
		</ToggleSwitch>

		<section v-if="isChoice(control) || ['number', 'date', 'repeater'].includes(control)" class="field-form__section" :aria-labelledby="id('section')">
			<h3 :id="id('section')" class="eyebrow field-form__section-head"><AdminIcon :name="controlIcon(control)" />{{ catalogControl(control)?.label ?? controlLabel(control) }}</h3>

			<div v-if="isChoice(control)" :id="id('options')" tabindex="-1">
				<ChoiceOptions v-model="options" :id-prefix="idPrefix" :locked="locked" />
				<p v-if="tried && optionsError" class="field__error">{{ optionsError }}</p>
			</div>

			<template v-if="control === 'number'">
				<div class="field-form__row field-form__row--three">
					<div class="field">
						<label :for="id('min')">Lowest</label>
						<input :id="id('min')" :value="numberText('min')" type="number" step="any" :readonly="locked" @input="setNumber('min', ($event.target as HTMLInputElement).value)">
					</div>
					<div class="field">
						<label :for="id('max')">Highest</label>
						<input :id="id('max')" :value="numberText('max')" type="number" step="any" :readonly="locked" @input="setNumber('max', ($event.target as HTMLInputElement).value)">
					</div>
					<div class="field">
						<label :for="id('step')">Step</label>
						<input :id="id('step')" :value="numberText('step')" type="number" step="any" min="0" :readonly="locked" :aria-describedby="id('step-help')" @input="setNumber('step', ($event.target as HTMLInputElement).value)">
						<p :id="id('step-help')" class="field__help">How far apart values are.</p>
					</div>
				</div>
				<ToggleSwitch :checked="draft.integer === true" label="Whole numbers only" form :locked="locked" @change="setFlag('integer', $event)">Whole numbers only</ToggleSwitch>
			</template>

			<ToggleSwitch v-if="control === 'date'" :checked="draft.time !== false" label="Includes a time" form :locked="locked" @change="setTime">
				Includes a time
				<small>Without one, only the day is kept.</small>
			</ToggleSwitch>

			<template v-if="control === 'repeater'">
				<div class="field-form__row field-form__row--three">
					<div class="field">
						<label :for="id('item')">Call each item</label>
						<input :id="id('item')" :value="draft.itemLabel ?? ''" autocomplete="off" placeholder="Item" :readonly="locked" :aria-describedby="id('item-help')" @input="setText('itemLabel', ($event.target as HTMLInputElement).value)">
						<p :id="id('item-help')" class="field__help">“Add {{ draft.itemLabel || 'Item' }}” in the editor.</p>
					</div>
					<div class="field">
						<label :for="id('min')">Fewest</label>
						<input :id="id('min')" :value="numberText('min')" type="number" min="0" step="1" :readonly="locked" @input="setNumber('min', ($event.target as HTMLInputElement).value)">
					</div>
					<div class="field">
						<label :for="id('max')">Most</label>
						<input :id="id('max')" :value="numberText('max')" type="number" min="0" step="1" :readonly="locked" @input="setNumber('max', ($event.target as HTMLInputElement).value)">
					</div>
				</div>
				<div v-if="inside.length" class="field">
					<span :id="id('summary-label')" class="field__label">Sum up each item with</span>
					<div class="chip-toggles" role="group" :aria-labelledby="id('summary-label')" :aria-describedby="id('summary-help')">
						<button v-for="child in inside" :key="child.name" type="button" :aria-pressed="(draft.summary ?? []).includes(child.name)" :disabled="locked || child.name === ''" @click="toggleSummary(child.name)"><AdminIcon name="check" />{{ child.label }}</button>
					</div>
					<p :id="id('summary-help')" class="field__help">What each item's line says while it's closed.</p>
				</div>
			</template>
		</section>

		<details v-if="control !== 'group'" class="field-form__more">
			<summary><AdminIcon name="chevron-right" />Advanced</summary>
			<div class="field">
				<label :for="id('aliases')">Older keys</label>
				<input :id="id('aliases')" v-model.lazy="aliases" class="mono" autocomplete="off" spellcheck="false" placeholder="none" :readonly="locked" :aria-describedby="id('aliases-help')">
				<p :id="id('aliases-help')" class="field__help">Other names this field still reads, so a renamed field keeps its values.</p>
			</div>
		</details>

		<div class="field-form__actions">
			<button v-if="!locked" type="button" class="button button--ghost button--danger button--small" @click="emit('remove')">Remove Field</button>
			<span class="field__help">{{ locked ? 'From code, so it\'s shown here.' : 'Nothing is written until you save.' }}</span>
			<button type="button" class="button button--small" @click="locked ? emit('done') : cancel()">{{ locked ? 'Close' : 'Cancel' }}</button>
			<button v-if="!locked" type="button" class="button button--primary button--small" @click="finish">Done</button>
		</div>

		<ControlPicker v-if="picking" :current="saved === null ? undefined : (field.control ?? undefined)" :flat="nested" @choose="choose" @close="picking = false" />
	</div>
</template>

<style scoped>
.field-form {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x) var(--s-5) calc(var(--pad-x) + var(--tree-indent, 0px));
	border-top: 1px solid var(--border);
	background: var(--surface-2);
}

.field-form__ident,
.field-form__row {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	gap: var(--s-4);
	align-items: start;
}

/* The control box follows the drawn select (`AdminSelect`): the whole
   box is the hit area, and it says it changes. */
.field-form__control {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-height: var(--ctl);
	padding: 0 10px 0 12px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg);
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.field-form__control:hover:not(:disabled) {
	border-color: var(--fg-3);
}

.field-form__control:focus-visible {
	border-color: var(--accent);
	outline-offset: 0;
}

.field-form__control:disabled {
	border-color: var(--border);
	background: var(--surface-2);
	cursor: default;
}

.field-form__control .icon {
	flex: none;
	width: 16px;
	height: 16px;
	color: var(--fg-3);
}

.field-form__control strong {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	font-size: var(--text-sm);
	font-weight: 500;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.field-form__change {
	color: var(--accent);
	font-size: var(--text-sm);
}

.field-form__section-head {
	display: flex;
	align-items: center;
	gap: 6px;
}

.field-form__section-head .icon {
	width: 12px;
	height: 12px;
}

.field-form__note {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.field-form__section {
	display: grid;
	gap: var(--s-3);
	padding-top: var(--s-4);
	border-top: 1px solid var(--border);
}

.field-form__more {
	padding-top: var(--s-3);
	border-top: 1px solid var(--border);
}

.field-form__more > summary {
	display: inline-flex;
	align-items: center;
	gap: var(--s-1);
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
	cursor: pointer;
	list-style: none;
}

.field-form__more > summary::-webkit-details-marker {
	display: none;
}

.field-form__more > summary .icon {
	width: 13px;
	height: 13px;
	transition: transform .12s;
}

.field-form__more[open] > summary .icon {
	transform: rotate(90deg);
}

.field-form__more[open] > .field {
	margin-top: var(--s-3);
}

.field-form__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.field-form__actions > .field__help {
	margin-right: auto;
}

@media (prefers-reduced-motion: reduce) {
	.field-form__more > summary .icon {
		transition: none;
	}
}
</style>
