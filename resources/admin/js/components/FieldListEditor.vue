<script setup lang="ts">
/**
 * A content type's fields (D-311): a row each, with its label, key,
 * type, and whether it's required, opened in place to edit it
 * (`FieldDefinitionEditor`), moved up or down, or removed, and **Add
 * field** below. One field is open at a time. Object fields aren't
 * edited here: their row says to edit them in the type's file, and they
 * stay as written.
 */

import { computed, ref } from 'vue';
import type { ContentTypeSummary, FieldDescription } from '../api';
import AdminIcon from './AdminIcon.vue';
import FieldDefinitionEditor from './FieldDefinitionEditor.vue';
import { label as labelOf } from '../fields';
import { loadFieldTypes, typeName as nameOfType } from '../field-types';

const props = defineProps<{
	types: ContentTypeSummary[];
	idPrefix: string;
}>();

const fields = defineModel<FieldDescription[]>({ required: true });

// The open row: an index, `'new'` for a field being added, or `null`.
const open = ref<number | 'new' | null>(null);

// The type names come from the catalog (D-337); until it loads, the keys.
void loadFieldTypes().catch(() => undefined);

function typeName(field: FieldDescription): string {
	const name = nameOfType(field);

	if (field.type === 'reference' && field.to) {
		return `${name} to ${props.types.find((type) => type.name === field.to)?.labels.items ?? field.to}`;
	}

	return name;
}

function taken(except: number | 'new'): string[] {
	return fields.value.filter((_, index) => index !== except).map((field) => field.name);
}

function save(index: number | 'new', field: FieldDescription): void {
	const next = [...fields.value];

	if (index === 'new') {
		next.push(field);
	} else {
		next[index] = field;
	}

	fields.value = next;
	open.value   = null;
}

function remove(index: number): void {
	fields.value = fields.value.filter((_, at) => at !== index);
	open.value   = null;
}

function move(index: number, by: number): void {
	const next   = [...fields.value];
	const target = index + by;
	const moved  = next[index];
	const other  = next[target];

	if (moved === undefined || other === undefined) {
		return;
	}

	next[index]  = other;
	next[target] = moved;
	fields.value = next;
}

const blank = computed<FieldDescription>(() => ({ name: '', type: 'text' }));
</script>

<template>
	<div class="field-list">
		<ul v-if="fields.length" class="field-list__rows">
			<li v-for="(field, index) in fields" :key="`${index}-${field.name}`">
				<div class="field-row" :class="{ 'field-row--open': open === index }">
					<span class="field-row__text">
						<span class="field-row__label">{{ labelOf(field) }}</span>
						<span class="field-row__key mono">{{ field.name }}</span>
					</span>
					<span v-if="field.required" class="field-row__required">Required</span>
					<span class="field-row__type">{{ typeName(field) }}</span>
					<span class="field-row__actions">
						<button type="button" class="button button--ghost button--small button--icon" :disabled="index === 0" @click="move(index, -1)"><AdminIcon name="arrow-up" /><span class="visually-hidden">Move {{ labelOf(field) }} up</span></button>
						<button type="button" class="button button--ghost button--small button--icon" :disabled="index === fields.length - 1" @click="move(index, 1)"><AdminIcon name="arrow-down" /><span class="visually-hidden">Move {{ labelOf(field) }} down</span></button>
						<button v-if="field.type !== 'object'" type="button" class="button button--ghost button--small button--icon" :aria-expanded="open === index" @click="open = open === index ? null : index"><AdminIcon name="pen-line" /><span class="visually-hidden">Edit {{ labelOf(field) }}</span></button>
						<button v-else type="button" class="button button--ghost button--small button--icon" @click="remove(index)"><AdminIcon name="x" /><span class="visually-hidden">Remove {{ labelOf(field) }}</span></button>
					</span>
				</div>
				<p v-if="field.type === 'object'" class="field-list__note field__help">A group of fields; edit it in the type's file. It's kept as written.</p>
				<FieldDefinitionEditor
					v-if="open === index"
					:field="field"
					:taken="taken(index)"
					:types="types"
					:id-prefix="`${idPrefix}${index}-`"
					@done="save(index, $event)"
					@cancel="open = null"
					@remove="remove(index)"
				/>
			</li>
		</ul>

		<FieldDefinitionEditor
			v-if="open === 'new'"
			:field="blank"
			:taken="taken('new')"
			:types="types"
			:id-prefix="`${idPrefix}new-`"
			is-new
			@done="save('new', $event)"
			@cancel="open = null"
		/>

		<div class="field-list__add">
			<button v-if="open !== 'new'" type="button" class="button button--small" @click="open = 'new'"><AdminIcon name="plus" />Add field</button>
		</div>
	</div>
</template>

<style scoped>
.field-list__rows {
	margin: 0;
	padding: 0;
	list-style: none;
}

.field-row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	padding: 10px var(--pad-x);
	border-bottom: 1px solid var(--border);
}

.field-row--open {
	background: var(--surface-2);
}

.field-row__text {
	display: flex;
	flex: 1;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 8px;
	min-width: 0;
}

.field-row__label {
	font-weight: 500;
}

.field-row__key {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.field-row__required {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.field-row__type {
	color: var(--fg-2);
	font-size: var(--text-sm);
	white-space: nowrap;
}

.field-row__actions {
	display: flex;
	gap: 2px;
}

.field-list__note {
	margin: 0;
	padding: 0 var(--pad-x) 10px;
	border-bottom: 1px solid var(--border);
}

.field-list__add {
	padding: var(--s-4) var(--pad-x);
}
</style>
