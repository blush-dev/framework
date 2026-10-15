<script setup lang="ts">
/**
 * A type's Fields (D-858): the library fields it carries, in its
 * sections, as a tree over the shared `ItemTree`. A row is a field, with
 * its control's glyph, its label as this type asks for it, its key, and
 * whether it's required, or a section, a heading only for the editor's
 * sidebar, with its fields under it. A field goes into a section by
 * indenting it under one (⌥→), and out by outdenting (⌥←); sections are
 * one level deep.
 *
 * **Add Field** picks a field from the library (`LibraryFieldPicker`),
 * or makes a new one there, on its own screen (`/fields/new?type=`),
 * which comes back here with the field added (`?add=`), to save with the
 * type's other changes. The new type wizard, with no type to come back
 * to yet, only picks.
 * A field's form changes only how this type asks for it: its label, its
 * help, and whether it's required to publish, each the field's own until
 * it's changed; the field itself is edited in the library. A section's
 * form is its heading and help. Removing a section keeps its fields.
 *
 * It edits the type's `fields` (`v-model`, D-863) and writes them back
 * after each change.
 * A type from code may have fields of its own, listed above, read only.
 * A `locked` one (types aren't changed here) only opens rows. A
 * `bare` one is drawn inside another panel (the new type wizard).
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import type { FieldDescription, LibraryFieldOption, TypeFieldItem } from '../api';
import { controlIcon, controlLabel } from '../controls';
import { humanize, label as labelOf } from '../fields';
import { loadFieldTypes } from '../field-types';
import { fieldNode, placedNames, sectionNode, toTypeFieldNodes, toTypeFields, typeFieldsAccept, type TypeFieldNode } from '../type-fields';
import { t } from '../messages';
import { find, flatten, type ItemTreeApi, type ItemTreeWords } from '../tree';
import AdminIcon from './AdminIcon.vue';
import EmptyState from './EmptyState.vue';
import ItemTree from './ItemTree.vue';
import LibraryFieldPicker from './LibraryFieldPicker.vue';
import ToggleSwitch from './ToggleSwitch.vue';

const props = withDefaults(defineProps<{
	// The field library, to add from.
	library: LibraryFieldOption[];
	// The fields its code gives it, shown read only.
	own?: FieldDescription[];
	headingId: string;
	idPrefix: string;
	// What carries the fields: "recipes".
	noun?: string;
	locked?: boolean;
	bare?: boolean;
	// The type's name, to come back to from a new field's screen; none in
	// the new type wizard.
	type?: string | null;
}>(), { type: null, own: () => [], noun: t('type_fields.noun', 'entries'), locked: false, bare: false });

const items = defineModel<TypeFieldItem[]>({ required: true });

void loadFieldTypes().catch(() => undefined);

const route  = useRoute();
const router = useRouter();
const tree   = ref<ItemTreeApi<TypeFieldNode> | null>(null);
const nodes  = ref<TypeFieldNode[]>(toTypeFieldNodes(items.value));
const known  = computed(() => props.library);
const placed = computed(() => placedNames(nodes.value));
const total  = computed(() => placed.value.length);

const picking = ref<((name: string | null) => void) | null>(null);

// Written back after each change; fields from outside (Revert) start
// the nodes over.
let written = JSON.stringify(items.value);

watch(items, (value) => {
	if (JSON.stringify(value) !== written) {
		nodes.value = toTypeFieldNodes(value);
		written     = JSON.stringify(value);
	}
}, { deep: true });

function changed(): void {
	const next = toTypeFields(nodes.value);

	written      = JSON.stringify(next);
	items.value  = next;
}

const words: ItemTreeWords = {
	under: (count) => t('type_fields.under', '{count, plural, one {# field} other {# fields}}', { count }),
	added: (place, name) => place === null
		? t('type_fields.added', 'Added a field.')
		: t('type_fields.added_at', 'Added a field {place, select, above {above} below {after} other {inside}} {name}.', { place, name }),
	addAbove: t('type_fields.add_above', 'Add field above'),
	addBelow: t('type_fields.add_below', 'Add field below'),
	removedWith: (name, count) => t('type_fields.removed_with', 'Removed {name} and its {count, plural, one {# field} other {# fields}}', { name, count }),
	showUnder: (name) => t('type_fields.show_under', 'Show the fields under {name}', { name }),
	hideUnder: (name) => t('type_fields.hide_under', 'Hide the fields under {name}', { name })
};

function libraryField(node: TypeFieldNode): LibraryFieldOption | undefined {
	return node.field === null ? undefined : known.value.find((field) => field.name === node.field);
}

function name(node: TypeFieldNode): string {
	if (node.field === null) {
		return node.label || t('type_fields.untitled_section', 'Untitled section');
	}

	return node.label || libraryField(node)?.label || humanize(node.field);
}

function required(node: TypeFieldNode): boolean {
	return node.required ?? libraryField(node)?.required ?? false;
}

function changes(node: TypeFieldNode): boolean {
	return Boolean(node.label) || Boolean(node.description) || node.required !== undefined;
}

// Asks which library field to add: one this type doesn't have, or a new
// one.
function create(): Promise<TypeFieldNode | null> {
	return new Promise((resolve) => {
		picking.value = (name) => {
			resolve(name === null ? null : fieldNode(name));
		};
	});
}

function chose(name: string | null): void {
	const resolve = picking.value;

	picking.value = null;
	resolve?.(name);
}

// New Field: made on its own screen, which comes back with it.
function makeField(): void {
	chose(null);

	if (props.type !== null) {
		void router.push({ name: 'field-new', query: { type: props.type } });
	}
}

// A field just made for this type (`?add=`) joins its fields, unsaved.
const added = typeof route.query.add === 'string' ? route.query.add : '';

if (added !== '' && !props.locked && props.library.some((field) => field.name === added) && !placedNames(nodes.value).includes(added)) {
	nodes.value.push(fieldNode(added));
	changed();
	void router.replace({ query: { ...route.query, add: undefined } });
}

const offered = computed(() => known.value.filter((field) => !placed.value.includes(field.name)));

async function addField(): Promise<void> {
	await tree.value?.add('below', null);
}

async function addSection(): Promise<void> {
	await tree.value?.add('below', null, sectionNode({ section: t('type_fields.new_section', 'New Section'), fields: [] }));
}

// A section goes, but its fields stay, where it was.
function confirmRemove(node: TypeFieldNode): Promise<boolean> {
	const at = find(nodes.value, node.id);

	if (node.field === null && at !== undefined && node.children.length > 0) {
		at.list.splice(at.index + 1, 0, ...node.children);
		node.children = [];
	}

	return Promise.resolve(true);
}

// A type's change to a field: a value back at the field's own isn't one.
function setText(node: TypeFieldNode, key: 'label' | 'description', value: string): void {
	node[key] = value.trim() === '' ? undefined : value;
	changed();
}

function setRequired(node: TypeFieldNode, on: boolean): void {
	node.required = on === (libraryField(node)?.required ?? false) ? undefined : on;
	changed();
}

const sections = computed(() => flatten(nodes.value).filter((item) => item.node.field === null).length);
</script>

<template>
	<component :is="bare ? 'div' : 'section'" :class="bare ? 'type-fields type-fields--bare' : 'panel type-fields'" :aria-labelledby="bare ? undefined : headingId">
		<header :class="bare ? 'type-fields__bar' : 'panel__header'">
			<h2 v-if="!bare" :id="headingId">{{ t('type_fields.heading', 'Fields') }}</h2>
			<p v-if="!bare" class="panel__hint">{{ t('type_fields.hint', '{count, plural, one {# field} other {# fields} } from the library{sections, plural, =0 {} one {, in # section} other {, in # sections} }', { count: total, sections }) }}</p>
			<div v-if="!locked" class="panel__actions">
				<button type="button" class="button button--ghost button--small" @click="addSection"><AdminIcon name="heading" />{{ t('type_fields.add_section', 'Add Section') }}</button>
				<button type="button" class="button button--small" @click="addField"><AdminIcon name="plus" />{{ t('type_fields.add_field', 'Add Field') }}</button>
			</div>
		</header>

		<p v-if="own.length" class="panel__note">
			<AdminIcon name="lock" />
			{{ t('type_fields.own', 'Its plugin gives it {fields}, changed in code.', { fields: own.map((field) => labelOf(field)).join(', ') }) }}
		</p>

		<EmptyState v-if="!nodes.length" icon="group" :heading="t('type_fields.empty', 'No Fields Yet')" :text="locked ? t('type_fields.empty_locked', 'It has no fields from the library.') : t('type_fields.empty_text', 'Add a field from the library, or make a new one, and every one of the {noun} has it.', { noun })">
			<template v-if="!locked" #actions>
				<button type="button" class="button button--primary" @click="addField"><AdminIcon name="plus" />{{ t('type_fields.add_field', 'Add Field') }}</button>
			</template>
		</EmptyState>

		<ItemTree
			v-show="nodes.length"
			ref="tree"
			:nodes="nodes"
			:name="name"
			:words="words"
			:labelled-by="headingId"
			:blank="() => sectionNode({ section: '', fields: [] })"
			:accepts="typeFieldsAccept"
			:locked="locked"
			:create="create"
			:confirm-remove="confirmRemove"
			:focus-on-edit="(node) => `${idPrefix}${node.id}-label`"
			@change="changed"
		>
			<template #pick="{ placed: row }">
				<template v-if="row.node.field === null">
					<span class="item-tree__kind" :title="t('type_fields.section', 'Section')"><AdminIcon name="heading" /><span class="visually-hidden">{{ t('type_fields.section', 'Section') }}: </span></span>
					<span class="item-tree__title">{{ name(row.node) }}</span>
				</template>
				<template v-else>
					<span class="item-tree__kind" :title="controlLabel(libraryField(row.node)?.control ?? 'text')"><AdminIcon :name="controlIcon(libraryField(row.node)?.control ?? 'text')" /><span class="visually-hidden">{{ controlLabel(libraryField(row.node)?.control ?? 'text') }}: </span></span>
					<span class="item-tree__title">{{ name(row.node) }}</span>
					<span class="type-fields__key mono">{{ row.node.field }}</span>
					<span v-if="!libraryField(row.node)" class="pill pill--warn">{{ t('type_fields.not_in_library', 'Not in the Library') }}</span>
					<span v-else-if="required(row.node)" class="pill">{{ t('type_fields.required', 'Required') }}</span>
					<span v-if="changes(row.node)" class="pill pill--accent">{{ t('type_fields.changed_here', 'Changed Here') }}</span>
				</template>
			</template>

			<template #fields="{ placed: row, done }">
				<div class="field-form">
					<template v-if="row.node.field === null">
						<div class="field-form__row">
							<div class="field">
								<label :for="`${idPrefix}${row.node.id}-label`">{{ t('type_fields.section_heading', 'Heading') }}</label>
								<input :id="`${idPrefix}${row.node.id}-label`" :value="row.node.label ?? ''" autocomplete="off" :readonly="locked" :placeholder="t('type_fields.section_placeholder', 'Timing')" @input="setText(row.node, 'label', ($event.target as HTMLInputElement).value)">
							</div>
							<div class="field">
								<label :for="`${idPrefix}${row.node.id}-help`">{{ t('type_fields.help', 'Help') }}</label>
								<input :id="`${idPrefix}${row.node.id}-help`" :value="row.node.description ?? ''" autocomplete="off" :readonly="locked" @input="setText(row.node, 'description', ($event.target as HTMLInputElement).value)">
							</div>
						</div>
						<p class="field__help">{{ t('type_fields.section_help', 'A heading over its fields in the editor\'s sidebar. It saves nothing to entries, and removing it keeps its fields.') }}</p>
					</template>
					<template v-else>
						<div class="field-form__row">
							<div class="field">
								<label :for="`${idPrefix}${row.node.id}-label`">{{ t('type_fields.label', 'Label') }}</label>
								<input :id="`${idPrefix}${row.node.id}-label`" :value="row.node.label ?? ''" autocomplete="off" :readonly="locked" :placeholder="libraryField(row.node)?.label || humanize(row.node.field)" @input="setText(row.node, 'label', ($event.target as HTMLInputElement).value)">
							</div>
							<div class="field">
								<label :for="`${idPrefix}${row.node.id}-help`">{{ t('type_fields.help', 'Help') }}</label>
								<input :id="`${idPrefix}${row.node.id}-help`" :value="row.node.description ?? ''" autocomplete="off" :readonly="locked" :placeholder="libraryField(row.node)?.description" @input="setText(row.node, 'description', ($event.target as HTMLInputElement).value)">
							</div>
						</div>
						<ToggleSwitch :checked="required(row.node)" :label="t('type_fields.required_label', 'Required to publish')" form :locked="locked" @change="setRequired(row.node, $event)">
							{{ t('type_fields.required_label', 'Required to publish') }}
							<small>{{ row.node.required === undefined ? t('type_fields.required_own', 'As the field has it.') : t('type_fields.required_changed', 'Changed for these {noun}; the field has it {on, select, true {on} other {off} }.', { noun, on: String(libraryField(row.node)?.required ?? false) }) }}</small>
						</ToggleSwitch>
						<p class="field__help">
							{{ t('type_fields.field_help', 'Empty, each is the field\'s own. Only how these {noun} ask for it changes; its key and settings are the same on every type.', { noun }) }}
							<RouterLink v-if="libraryField(row.node)" :to="{ name: 'field', params: { name: row.node.field } }">{{ t('type_fields.edit_field', 'Edit the field in the library') }}</RouterLink>
						</p>
					</template>
					<div class="field-form__actions">
						<span class="field__help">{{ t('type_fields.unsaved', 'Nothing is written until you save.') }}</span>
						<button type="button" class="button button--primary button--small" @click="done">{{ t('common.done', 'Done') }}</button>
					</div>
				</div>
			</template>
		</ItemTree>

		<LibraryFieldPicker v-if="picking" :fields="offered" :can-create="type !== null" @choose="chose" @create="makeField" @close="chose(null)" />
	</component>
</template>

<style scoped>
.type-fields__bar {
	display: flex;
	justify-content: flex-end;
	gap: var(--s-2);
	padding: 0 var(--padding-inline) var(--s-3);
}

.type-fields--bare :deep(.item-tree) {
	border-top: 1px solid var(--border-color);
}

.type-fields__key {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}
</style>
