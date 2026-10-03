<script setup lang="ts">
/**
 * One field of a content type, as a form (D-311): its label and key, its
 * type, help, whether it's required, its default, and its type's own
 * options (a number's limits, a choice's options, a list's item type, a
 * reference's type). Changing the type keeps what the new type shares
 * and drops the rest. A new field's key follows its label until it's
 * typed.
 *
 * It edits a copy and hands it back with **Done**, which waits for a key
 * that's a key and isn't another field's.
 *
 * The types, their names, and their controls come from the site's field
 * type catalog (D-337), so an extension's types are offered too, with
 * their own options drawn from the JSON Schemas they describe them with.
 * A type edited with more than one control offers the others, the
 * type's default first.
 */

import { computed, ref, watch } from 'vue';
import type { ContentTypeSummary, FieldDescription, JsonSchema } from '../api';
import AdminSelect from './AdminSelect.vue';
import { catalog, fieldType, loadFieldTypes } from '../field-types';
import { copy, keyOf } from '../type-form';

const props = defineProps<{
	field: FieldDescription;
	// The other fields' keys, which this one can't take.
	taken: string[];
	types: ContentTypeSummary[];
	idPrefix: string;
	isNew?: boolean;
}>();

const emit = defineEmits<{
	done: [field: FieldDescription];
	cancel: [];
	remove: [];
}>();

void loadFieldTypes().catch(() => undefined);

// The types this editor draws its own options for; any other type's
// options are drawn from its JSON Schemas.
const BUILT_IN = ['text', 'markdown', 'number', 'bool', 'date', 'enum', 'list', 'reference', 'media', 'slug', 'object'];

// Controls that edit a value on one line, which a list's items need to be
// written one per line.
const ONE_LINE = ['text', 'mono', 'number', 'select', 'radios', 'date', 'reference', 'media'];

/**
 * The field types offered: every type in the catalog that can be edited,
 * not only shown (objects are kept as written, not edited here).
 */
const TYPES = computed(() => (catalog.value?.types ?? [])
	.filter((item) => item.controls.some((control) => control.value !== 'readonly'))
	.map((item) => ({ value: item.type, label: item.label })));

// What a media field takes (D-314); the picker offers only those.
const MEDIA_KINDS = [
	{ value: '', label: 'Any file' },
	{ value: 'image', label: 'Images' },
	{ value: 'video', label: 'Videos' },
	{ value: 'audio', label: 'Sound' },
	{ value: 'document', label: 'Documents' },
	{ value: 'file', label: 'Other Files' }
];

// A list's items: the types edited on one line.
const ITEM_TYPES = computed(() => (catalog.value?.types ?? [])
	.filter((item) => ONE_LINE.includes(item.controls[0]?.value ?? ''))
	.map((item) => ({ value: item.type, label: item.label })));

const draft      = ref<FieldDescription>(copy(props.field));
const keyTouched = ref(!props.isNew);

// Each option as text, as typed.
const label       = ref(draft.value.label ?? '');
const options     = ref((draft.value.options ?? []).join('\n'));
const itemOptions = ref((draft.value.item?.options ?? []).join('\n'));
const min         = ref<string | number>(draft.value.min === undefined ? '' : String(draft.value.min));
const max         = ref<string | number>(draft.value.max === undefined ? '' : String(draft.value.max));
const defaultText = ref<string | number>(draft.value.default === undefined || draft.value.default === null ? '' : String(draft.value.default));

watch(() => props.field, (field) => {
	draft.value = copy(field);
});

watch(label, (value) => {
	draft.value.label = value;

	if (!keyTouched.value) {
		draft.value.name = keyOf(value);
	}
});

const type     = computed(() => draft.value.type);
const itemType = computed({
	get: () => draft.value.item?.type ?? 'text',
	set: (value: string) => {
		draft.value.item = value === 'reference' ? { name: 'item', type: value, to: draft.value.item?.to ?? props.types[0]?.name } : { name: 'item', type: value };
	}
});

const typeOptions = computed(() => props.types.map((item) => ({ value: item.name, label: item.labels.plural })));

/**
 * The controls this field can be edited with: its type's, less the ones
 * its settings rule out (the server checks the same, D-337). A list
 * writes items one per line when they fit on one, and as checkboxes when
 * they're choices; the entry picker needs the type it picks from.
 */
const controls = computed(() => (fieldType(type.value)?.controls ?? []).filter((control) => {
	if (type.value === 'list') {
		const item = fieldType(itemType.value)?.controls[0]?.value ?? '';

		return control.value === 'readonly'
			|| (control.value === 'lines' && ONE_LINE.includes(item))
			|| (control.value === 'checks' && itemType.value === 'enum');
	}

	if (type.value === 'reference' && control.value === 'reference') {
		return (draft.value.to ?? '') !== '';
	}

	return true;
}));

const controlOptions = computed(() => controls.value.map((control, index) => ({
	value: index === 0 ? '' : control.value,
	label: index === 0 ? `${control.label} (default)` : control.label
})));

// The control chosen, or `''` for the type's default.
const chosenControl = computed({
	get: () => controls.value.slice(1).some((control) => control.value === draft.value.control) ? String(draft.value.control) : '',
	set: (value: string) => {
		if (value === '') {
			delete draft.value.control;
		} else {
			draft.value.control = value;
		}
	}
});

/**
 * An extension type's own options, from its JSON Schemas: each drawn as
 * the input its schema's type needs.
 */
const extraOptions = computed(() => BUILT_IN.includes(type.value) ? [] : Object.entries(fieldType(type.value)?.options ?? {}).flatMap(([key, schema]) => {
	const input = inputFor(schema);

	return input === null ? [] : [{ key, schema, input }];
}));

function inputFor(schema: JsonSchema): 'text' | 'number' | 'checkbox' | 'select' | 'lines' | null {
	const kind = Array.isArray(schema.type) ? schema.type[0] : schema.type;

	if (Array.isArray(schema.enum)) {
		return 'select';
	}

	switch (kind) {
		case 'string':
			return 'text';
		case 'number':
		case 'integer':
			return 'number';
		case 'boolean':
			return 'checkbox';
		case 'array':
			return schema.items?.type === 'string' ? 'lines' : null;
		default:
			return null;
	}
}

// An extension option's value as its input holds it.
function optionText(key: string): string {
	const value = draft.value[key];

	return Array.isArray(value) ? value.map(String).join('\n') : (value === undefined || value === null ? '' : String(value as string | number));
}

function setOption(key: string, input: string, value: string | boolean): void {
	if (value === '' || value === false) {
		delete draft.value[key];

		return;
	}

	if (input === 'number') {
		draft.value[key] = Number(value);
	} else if (input === 'lines') {
		draft.value[key] = String(value).split('\n').map((line) => line.trim()).filter((line) => line !== '');
	} else {
		draft.value[key] = value;
	}
}

function changeType(value: string): void {
	const kept: FieldDescription = { name: draft.value.name, type: value };

	for (const key of ['label', 'description', 'required', 'aliases'] as const) {
		if (draft.value[key] !== undefined) {
			kept[key] = draft.value[key] as never;
		}
	}

	if (value === 'reference') {
		kept.to = props.types[0]?.name;
	}

	if (value === 'list') {
		kept.item = { name: 'item', type: 'text' };
	}

	draft.value       = kept;
	itemOptions.value = '';
	defaultText.value = '';
	options.value     = '';
	min.value         = '';
	max.value         = '';
}

const keyError = computed(() => {
	const name = draft.value.name;

	if (name === '') {
		return 'Give the field a key.';
	}

	if (!/^[a-z_][a-z0-9_]*$/.test(name)) {
		return 'A key uses lowercase letters, digits, and underscores, and doesn\'t start with a digit.';
	}

	return props.taken.includes(name) ? `Another field already uses “${name}”.` : '';
});

const choices     = computed(() => options.value.split('\n').map((line) => line.trim()).filter((line) => line !== ''));
const itemChoices = computed(() => itemOptions.value.split('\n').map((line) => line.trim()).filter((line) => line !== ''));

// The field as it'd be written, with its options and default from the
// text typed for them.
function finished(): FieldDescription {
	const field: FieldDescription = { ...draft.value };

	delete field.options;
	delete field.min;
	delete field.max;
	delete field.default;

	if (field.label === '') {
		delete field.label;
	}

	if (field.description === '') {
		delete field.description;
	}

	if (field.required !== true) {
		delete field.required;
	}

	if (type.value === 'enum') {
		field.options = choices.value;
	}

	if (type.value === 'number') {
		// A number input's model is a number once typed in.
		if (String(min.value).trim() !== '') {
			field.min = Number(min.value);
		}

		if (String(max.value).trim() !== '') {
			field.max = Number(max.value);
		}

		if (field.integer !== true) {
			delete field.integer;
		}
	}

	if (type.value === 'reference' && field.multiple !== false) {
		delete field.multiple;
	}

	if (type.value === 'list' && field.item?.type === 'enum') {
		field.item = { ...field.item, options: itemChoices.value };
	}

	// A control the field's settings no longer allow goes back to the
	// type's default.
	if (field.control !== undefined && !controls.value.slice(1).some((control) => control.value === field.control)) {
		delete field.control;
	}

	const fallback = String(defaultText.value).trim();

	if (fallback !== '') {
		field.default = defaultKind.value === 'number' ? Number(fallback) : (type.value === 'bool' ? fallback === 'true' : fallback);
	}

	return field;
}

// A built-in type takes a default when one makes sense for it; an
// extension type, when it's edited on one line.
const hasDefault  = computed(() => BUILT_IN.includes(type.value)
	? ['text', 'markdown', 'number', 'bool', 'date', 'enum', 'slug'].includes(type.value)
	: ['text', 'mono', 'number', 'select', 'radios'].includes(fieldType(type.value)?.controls[0]?.value ?? ''));
const defaultKind = computed(() => type.value === 'number' || (!BUILT_IN.includes(type.value) && fieldType(type.value)?.controls[0]?.value === 'number') ? 'number' : 'text');

const multiple = computed({
	get: () => draft.value.multiple !== false,
	set: (value: boolean) => {
		draft.value.multiple = value;
	}
});

const id = (name: string): string => `${props.idPrefix}${name}`;
</script>

<template>
	<div class="field-editor">
		<div class="field-editor__row">
			<div class="field">
				<label :for="id('label')">Label</label>
				<input :id="id('label')" v-model="label" placeholder="Cook time" autocomplete="off">
			</div>
			<div class="field">
				<label :for="id('key')">Key</label>
				<input :id="id('key')" v-model="draft.name" class="mono" placeholder="cook_time" autocomplete="off" spellcheck="false" :aria-invalid="keyError !== '' ? 'true' : undefined" :aria-describedby="keyError ? id('key-error') : id('key-help')" @input="keyTouched = true">
				<p v-if="keyError" :id="id('key-error')" class="field__error">{{ keyError }}</p>
				<p v-else :id="id('key-help')" class="field__help">The front matter key entries use.</p>
			</div>
		</div>

		<div class="field-editor__row">
			<div class="field">
				<label :for="id('type')">Type</label>
				<AdminSelect :id="id('type')" :model-value="type" :options="TYPES" @update:model-value="changeType" />
			</div>
			<div v-if="hasDefault" class="field">
				<label :for="id('default')">Default</label>
				<AdminSelect v-if="type === 'bool'" :id="id('default')" :model-value="String(defaultText)" @update:model-value="defaultText = $event" :options="[{ value: '', label: 'None' }, { value: 'true', label: 'Yes' }, { value: 'false', label: 'No' }]" />
				<AdminSelect v-else-if="type === 'enum'" :id="id('default')" :model-value="String(defaultText)" @update:model-value="defaultText = $event" :options="[{ value: '', label: 'None' }, ...choices.map((choice) => ({ value: choice, label: choice }))]" />
				<input v-else :id="id('default')" v-model="defaultText" :type="defaultKind" autocomplete="off">
			</div>
		</div>

		<div v-if="controls.length > 1" class="field">
			<label :for="id('control')">Edited with</label>
			<AdminSelect :id="id('control')" v-model="chosenControl" :options="controlOptions" />
		</div>

		<div v-if="type === 'number'" class="field-editor__row">
			<div class="field">
				<label :for="id('min')">Lowest</label>
				<input :id="id('min')" v-model="min" type="number">
			</div>
			<div class="field">
				<label :for="id('max')">Highest</label>
				<input :id="id('max')" v-model="max" type="number">
			</div>
		</div>
		<label v-if="type === 'number'" class="checkbox"><input v-model="draft.integer" type="checkbox"> Whole numbers only</label>

		<div v-if="type === 'enum'" class="field">
			<label :for="id('options')">Options</label>
			<textarea :id="id('options')" v-model="options" class="mono" rows="3" :aria-describedby="id('options-help')" />
			<p :id="id('options-help')" class="field__help">One per line, as front matter writes them.</p>
		</div>

		<div v-if="type === 'list'" class="field-editor__row">
			<div class="field">
				<label :for="id('item')">Each item is</label>
				<AdminSelect :id="id('item')" v-model="itemType" :options="ITEM_TYPES" />
			</div>
			<div v-if="itemType === 'reference' && draft.item" class="field">
				<label :for="id('item-to')">Of</label>
				<AdminSelect :id="id('item-to')" :model-value="String(draft.item.to ?? '')" :options="typeOptions" @update:model-value="draft.item = { ...draft.item, to: $event }" />
			</div>
		</div>

		<div v-if="type === 'list' && itemType === 'enum'" class="field">
			<label :for="id('item-options')">Options</label>
			<textarea :id="id('item-options')" v-model="itemOptions" class="mono" rows="3" :aria-describedby="id('item-options-help')" />
			<p :id="id('item-options-help')" class="field__help">One per line, as front matter writes them.</p>
		</div>

		<div v-if="type === 'reference'" class="field-editor__row">
			<div class="field">
				<label :for="id('to')">Points at</label>
				<AdminSelect :id="id('to')" :model-value="String(draft.to ?? '')" :options="typeOptions" @update:model-value="draft.to = $event" />
			</div>
			<div class="field field--end">
				<label class="checkbox"><input v-model="multiple" type="checkbox"> More than one</label>
			</div>
		</div>

		<div v-if="type === 'media'" class="field">
			<label :for="id('kind')">Takes</label>
			<AdminSelect :id="id('kind')" :model-value="draft.kind ?? ''" :options="MEDIA_KINDS" @update:model-value="draft.kind = $event === '' ? undefined : $event as FieldDescription['kind']" />
		</div>

		<template v-for="option in extraOptions" :key="option.key">
			<label v-if="option.input === 'checkbox'" class="checkbox"><input type="checkbox" :checked="draft[option.key] === true" @change="setOption(option.key, 'checkbox', ($event.target as HTMLInputElement).checked)"> {{ option.schema.description ?? option.key }}</label>
			<div v-else class="field">
				<label :for="id(`option-${option.key}`)">{{ option.key }}</label>
				<AdminSelect v-if="option.input === 'select'" :id="id(`option-${option.key}`)" :model-value="optionText(option.key)" :options="[{ value: '', label: 'None' }, ...(option.schema.enum ?? []).map((value) => ({ value: String(value), label: String(value) }))]" :described-by="option.schema.description ? id(`option-${option.key}-help`) : undefined" @update:model-value="setOption(option.key, 'select', $event)" />
				<textarea v-else-if="option.input === 'lines'" :id="id(`option-${option.key}`)" class="mono" rows="3" :value="optionText(option.key)" :aria-describedby="option.schema.description ? id(`option-${option.key}-help`) : undefined" @input="setOption(option.key, 'lines', ($event.target as HTMLTextAreaElement).value)" />
				<input v-else :id="id(`option-${option.key}`)" :type="option.input" :value="optionText(option.key)" autocomplete="off" :aria-describedby="option.schema.description ? id(`option-${option.key}-help`) : undefined" @input="setOption(option.key, option.input, ($event.target as HTMLInputElement).value)">
				<p v-if="option.schema.description" :id="id(`option-${option.key}-help`)" class="field__help">{{ option.schema.description }}</p>
			</div>
		</template>

		<div class="field">
			<label :for="id('description')">Help</label>
			<input :id="id('description')" v-model="draft.description" autocomplete="off" placeholder="Shown under the field in the editor">
		</div>
		<label class="checkbox"><input v-model="draft.required" type="checkbox"> Required to publish</label>

		<div class="field-editor__actions">
			<button type="button" class="button button--primary button--small" :disabled="keyError !== '' || (type === 'enum' && choices.length === 0) || (type === 'list' && itemType === 'enum' && itemChoices.length === 0)" @click="emit('done', finished())">Done</button>
			<button type="button" class="button button--small" @click="emit('cancel')">Cancel</button>
			<button v-if="!isNew" type="button" class="button button--danger button--small field-editor__remove" @click="emit('remove')">Remove field</button>
		</div>
	</div>
</template>

<style scoped>
.field-editor {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-4) var(--pad-x) var(--s-5);
	border-bottom: 1px solid var(--border);
	background: var(--surface-2);
}

.field-editor__row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	gap: var(--s-4);
}

.field--end {
	align-content: end;
	padding-bottom: 6px;
}

.field-editor__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.field-editor__remove {
	margin-left: auto;
}

@media (width <= 760px) {
	.field-editor__row {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
