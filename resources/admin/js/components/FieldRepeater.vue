<script setup lang="ts">
/**
 * A repeating group control (D-766, D-768): a list of items, each with
 * the same fields, sized for the editor's side panel. Each item is one
 * line while it's closed, summed up by the fields its `summary` names
 * (the first few fields' values when it names none, or "{Item} 2" when
 * they're empty); one opens at a time, its fields in place, with ways to
 * move it, remove it, and close it. **Add {Item}** adds one and opens
 * it, until the list's `max`.
 *
 * Inside another group, where the screen can open a level down
 * (`OPEN_LEVEL`), it's a row saying how many items it has, which opens
 * the list in its own level with a path back; where it can't, it draws
 * in place at every level.
 */

import { computed, inject, nextTick, ref } from 'vue';
import type { FieldDescription } from '../api';
import { OPEN_LEVEL, type FieldLevel } from '../field-levels';
import AdminIcon from './AdminIcon.vue';
import FieldControl from './FieldControl.vue';
import { childrenOf, choiceLabel, fromItems, help, isEmptyState, itemsOf, label, type FieldStep, type FormGroup, type FormValue } from '../fields';
import { plural, titleCase } from '../format';

const props = defineProps<{
	field: FieldDescription;
	at: FieldLevel;
	nest: number;
	idPrefix?: string;
	error?: string;
	pickable?: boolean;
	narrow?: boolean;
}>();

const model = defineModel<FormValue>({ required: true });

const emit = defineEmits<{ pick: [field: FieldDescription, steps: FieldStep[]] }>();

const openLevel = inject(OPEN_LEVEL, null);

const fields = computed(() => childrenOf(props.field));
const items  = computed(() => itemsOf(model.value));
const noun   = computed(() => props.field.itemLabel !== undefined && props.field.itemLabel !== '' ? props.field.itemLabel : 'Item');
const max    = computed(() => typeof props.field.max === 'number' ? props.field.max : null);
const id     = computed(() => `${props.idPrefix ?? 'field-'}${props.field.name}`);
const asRow  = computed(() => props.nest > 0 && openLevel !== null);

// The item open, or `-1`.
const open = ref(-1);

/**
 * An item's line: the values of the fields its summary names, or of the
 * first three fields with text.
 */
function summary(item: FormGroup, index: number): string {
	const names = props.field.summary !== undefined && props.field.summary.length > 0 ? props.field.summary : fields.value.map((child) => child.name);
	const parts = names.flatMap((name) => {
		const value = item[name];
		const child = fields.value.find((candidate) => candidate.name === name);

		if (typeof value !== 'string' || value.trim() === '' || value.trim().startsWith('{') || value.trim().startsWith('[') || child === undefined) {
			return [];
		}

		return [value.split('\n').map((line) => choiceLabel(child, line.trim())).join(', ')];
	});

	return parts.length === 0 ? `${noun.value} ${index + 1}` : parts.slice(0, 3).join(' · ');
}

function write(next: FormGroup[]): void {
	model.value = fromItems(next);
}

function set(index: number, name: string, value: FormValue): void {
	write(items.value.map((item, at) => at === index ? { ...item, [name]: value } : item));
}

async function add(): Promise<void> {
	write([...items.value, Object.fromEntries(fields.value.map((child) => [child.name, '']))]);
	open.value = items.value.length - 1;

	await nextTick();
	document.getElementById(`${id.value}-${open.value}`)?.querySelector<HTMLElement>('input, textarea, select, button:not(.repeater__row)')?.focus();
}

function move(index: number, by: number): void {
	const next = [...items.value];
	const [item] = next.splice(index, 1);

	if (item === undefined) {
		return;
	}

	next.splice(index + by, 0, item);
	write(next);
	open.value = index + by;
}

function remove(index: number): void {
	write(items.value.filter((_, at) => at !== index));
	open.value = -1;
}

function where(index: number, child: FieldDescription): FieldLevel {
	return { root: props.at.root, steps: [...props.at.steps, index, child.name], trail: [...props.at.trail, `${noun.value} ${index + 1}`, label(child)] };
}

function blank(item: FormGroup): boolean {
	return Object.values(item).every(isEmptyState);
}
</script>

<template>
	<button v-if="asRow" :id="id" type="button" class="level-link level-link--boxed" :class="{ 'is-quiet': items.length === 0 }" @click="openLevel?.(at)">
		<AdminIcon name="list" />
		<span class="level-link__text"><strong>{{ label(field) }}</strong><span>{{ items.length === 0 ? 'None yet' : plural(items.length, noun.toLowerCase()) }}</span></span>
		<AdminIcon name="chevron-right" class="level-link__go" />
	</button>
	<fieldset v-else :id="id" class="fieldset fieldset--group" :aria-describedby="help(field) !== '' ? `${id}-help` : undefined">
		<legend>{{ label(field) }}<span v-if="field.required" class="field__required"> (required)</span></legend>
		<p v-if="help(field) !== ''" :id="`${id}-help`" class="field__help">{{ help(field) }}</p>

		<ol v-if="items.length" class="repeater">
			<li v-for="(item, index) in items" :id="`${id}-${index}`" :key="index" class="repeater__item" :class="{ 'is-open': open === index }">
				<button type="button" class="repeater__row" :aria-expanded="open === index" @click="open = open === index ? -1 : index">
					<span class="repeater__summary" :class="{ 'is-blank': blank(item) }">{{ summary(item, index) }}</span>
					<AdminIcon name="chevron-down" class="repeater__caret" />
				</button>
				<div v-if="open === index" class="repeater__body">
					<FieldControl
						v-for="child in fields"
						:key="child.name"
						:field="child"
						:id-prefix="`${id}-${index}-`"
						:model-value="item[child.name] ?? ''"
						:pickable="pickable"
						:narrow="narrow"
						:nest="nest + 1"
						:at="where(index, child)"
						@update:model-value="set(index, child.name, $event)"
						@pick="(target, steps) => emit('pick', target, steps)"
					/>
					<div class="repeater__actions">
						<button type="button" class="button button--ghost button--small button--icon" :disabled="index === 0" @click="move(index, -1)"><AdminIcon name="arrow-up" /><span class="visually-hidden">Move {{ summary(item, index) }} up</span></button>
						<button type="button" class="button button--ghost button--small button--icon" :disabled="index === items.length - 1" @click="move(index, 1)"><AdminIcon name="arrow-down" /><span class="visually-hidden">Move {{ summary(item, index) }} down</span></button>
						<button type="button" class="button button--ghost button--small button--danger" @click="remove(index)">Remove<span class="visually-hidden"> {{ summary(item, index) }}</span></button>
						<button type="button" class="button button--small repeater__done" @click="open = -1">Done</button>
					</div>
				</div>
			</li>
		</ol>

		<button v-if="max === null || items.length < max" type="button" class="button button--ghost button--small repeater__add" @click="add"><AdminIcon name="plus" />{{ titleCase(`Add ${noun}`) }}</button>
		<p v-else class="field__help">{{ titleCase(noun) }}s are at their most ({{ max }}).</p>
		<p v-if="error" class="field__error">{{ error }}</p>
	</fieldset>
</template>

<style scoped>
.repeater {
	display: grid;
	margin: 0;
	padding: 0;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	list-style: none;
}

.repeater__item + .repeater__item {
	border-top: 1px solid var(--border);
}

.repeater__row {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	padding: var(--s-2) var(--s-3);
	border: 0;
	background: none;
	color: var(--fg);
	font: inherit;
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.repeater__row:hover {
	background: var(--surface-2);
}

.repeater__summary {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.repeater__summary.is-blank {
	color: var(--fg-3);
}

.repeater__caret {
	flex: none;
	width: 13px;
	height: 13px;
	color: var(--fg-3);
	transition: transform .15s;
}

.repeater__item.is-open .repeater__caret {
	transform: rotate(180deg);
}

.repeater__body {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-3) var(--s-3) var(--s-4);
	background: var(--surface-2);
}

.repeater__actions {
	display: flex;
	align-items: center;
	gap: var(--s-1);
}

.repeater__done {
	margin-left: auto;
}

.repeater__add {
	justify-self: start;
}
</style>
