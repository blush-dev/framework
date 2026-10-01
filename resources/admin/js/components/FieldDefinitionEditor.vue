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
 */

import { computed, ref, watch } from 'vue';
import type { ContentTypeSummary, FieldDescription } from '../api';
import AdminSelect from './AdminSelect.vue';
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

/**
 * The field types the editor offers, with names for people. Objects are
 * kept as written, not edited here.
 */
const TYPES = [
	{ value: 'text', label: 'Text' },
	{ value: 'markdown', label: 'Formatted text' },
	{ value: 'number', label: 'Number' },
	{ value: 'bool', label: 'Yes or no' },
	{ value: 'date', label: 'Date and time' },
	{ value: 'enum', label: 'Choice' },
	{ value: 'list', label: 'List' },
	{ value: 'reference', label: 'Reference' },
	{ value: 'media', label: 'Media file' },
	{ value: 'slug', label: 'Slug' }
];

// What a media field takes (D-314); the picker offers only those.
const MEDIA_KINDS = [
	{ value: '', label: 'Any file' },
	{ value: 'image', label: 'Images' },
	{ value: 'video', label: 'Videos' },
	{ value: 'audio', label: 'Sound' },
	{ value: 'file', label: 'Other files' }
];

const ITEM_TYPES = TYPES.filter((type) => ['text', 'number', 'date', 'reference', 'media', 'slug'].includes(type.value));

const draft      = ref<FieldDescription>(copy(props.field));
const keyTouched = ref(!props.isNew);

// Each option as text, as typed.
const label       = ref(draft.value.label ?? '');
const options     = ref((draft.value.options ?? []).join('\n'));
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

const choices = computed(() => options.value.split('\n').map((line) => line.trim()).filter((line) => line !== ''));

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

	const fallback = String(defaultText.value).trim();

	if (fallback !== '') {
		field.default = type.value === 'number' ? Number(fallback) : (type.value === 'bool' ? fallback === 'true' : fallback);
	}

	return field;
}

const hasDefault = computed(() => ['text', 'markdown', 'number', 'bool', 'date', 'enum', 'slug'].includes(type.value));

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
				<input v-else :id="id('default')" v-model="defaultText" :type="type === 'number' ? 'number' : 'text'" autocomplete="off">
			</div>
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

		<div class="field">
			<label :for="id('description')">Help</label>
			<input :id="id('description')" v-model="draft.description" autocomplete="off" placeholder="Shown under the field in the editor">
		</div>
		<label class="checkbox"><input v-model="draft.required" type="checkbox"> Required to publish</label>

		<div class="field-editor__actions">
			<button type="button" class="button button--primary button--small" :disabled="keyError !== '' || (type === 'enum' && choices.length === 0)" @click="emit('done', finished())">Done</button>
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
