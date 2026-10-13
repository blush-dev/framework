<script setup lang="ts">
/**
 * A choice field's options (the fields sketch's options editor, D-766):
 * a box of rows under a Label and Key head, each with its label (what
 * the editor shows) and its key (what entries keep), so a label can be
 * reworded without touching saved values. A new option's key follows its
 * label until it's typed. A row moves by its grip, or from its menu (up,
 * down, remove); **Add Option** at the foot adds one and focuses it. Two
 * options can't share a key.
 */

import { nextTick, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';
import { keyOf } from '../type-form';

export interface ChoiceOption {
	value: string;
	label: string;
}

const props = defineProps<{
	idPrefix: string;
	locked?: boolean;
}>();

const options = defineModel<ChoiceOption[]>({ required: true });

// The rows whose key has been typed, or was saved, so it's no longer
// made from the label.
const typed    = ref(new Set(options.value.map((_, index) => index)));
const dragging = ref<number | null>(null);
const drop     = ref<{ index: number; before: boolean } | null>(null);
const said     = ref('');

function setLabel(index: number, label: string): void {
	options.value = options.value.map((option, at) => at === index
		? { label, value: typed.value.has(index) ? option.value : keyOf(label) }
		: option);
}

function setKey(index: number, value: string): void {
	typed.value.add(index);
	options.value = options.value.map((option, at) => at === index ? { ...option, value: keyOf(value) || value.trim() } : option);
}

function moveTo(from: number, to: number): void {
	const next = [...options.value];
	const [option] = next.splice(from, 1);

	if (option === undefined) {
		return;
	}

	next.splice(to, 0, option);
	options.value = next;
	typed.value   = new Set(next.map((_, index) => index));
	said.value    = `Moved ${option.label || 'the option'} to ${to + 1} of ${next.length}.`;
}

function remove(index: number): void {
	const gone = options.value[index];

	options.value = options.value.filter((_, at) => at !== index);
	typed.value   = new Set([...typed.value].filter((at) => at !== index).map((at) => at > index ? at - 1 : at));
	said.value    = `Removed ${gone?.label || 'the option'}.`;
}

async function add(): Promise<void> {
	options.value = [...options.value, { label: '', value: '' }];

	await nextTick();
	document.getElementById(`${props.idPrefix}option-${options.value.length - 1}-label`)?.focus();
}

function shared(index: number): boolean {
	const value = options.value[index]?.value ?? '';

	return value !== '' && options.value.some((option, at) => at !== index && option.value === value);
}

function dragStart(event: DragEvent, index: number): void {
	if ((event.target as Element).closest('input')) {
		event.preventDefault();

		return;
	}

	dragging.value = index;
	event.dataTransfer?.setData('text/plain', String(index));
}

function dragOver(event: DragEvent, index: number): void {
	if (dragging.value === null || dragging.value === index) {
		return;
	}

	event.preventDefault();

	const box = (event.currentTarget as HTMLElement).getBoundingClientRect();

	drop.value = { index, before: (event.clientY - box.top) / box.height < .5 };
}

function dropped(): void {
	const from = dragging.value;
	const at   = drop.value;

	dragging.value = null;
	drop.value     = null;

	if (from === null || at === null) {
		return;
	}

	const to = at.index + (at.before ? 0 : 1);

	moveTo(from, from < to ? to - 1 : to);
}
</script>

<template>
	<div class="nest choice-options">
		<p v-if="!options.length" class="nest__none">No options yet. Add the ones people can pick.</p>
		<template v-else>
			<div class="choice-options__head" aria-hidden="true"><span /><span>Label</span><span>Key</span><span /></div>
			<div
				v-for="(option, index) in options"
				:key="index"
				class="choice-options__row"
				:class="{ 'is-dragging': dragging === index, 'is-drop-before': drop?.index === index && drop.before, 'is-drop-after': drop?.index === index && !drop.before }"
				:draggable="!locked"
				@dragstart="dragStart($event, index)"
				@dragover="dragOver($event, index)"
				@drop.prevent="dropped"
				@dragend="dragging = null; drop = null"
			>
				<span class="choice-options__grip" aria-hidden="true"><AdminIcon v-if="!locked" name="grip-vertical" /></span>
				<input :id="`${idPrefix}option-${index}-label`" class="input" :value="option.label" placeholder="Label" autocomplete="off" :aria-label="`Label for option ${index + 1}`" :readonly="locked" @input="setLabel(index, ($event.target as HTMLInputElement).value)">
				<input class="input mono" :value="option.value" placeholder="key" autocomplete="off" spellcheck="false" :aria-label="`Key for option ${index + 1}`" :aria-invalid="shared(index) ? 'true' : undefined" :readonly="locked" @change="setKey(index, ($event.target as HTMLInputElement).value)">
				<MenuButton v-if="!locked" button-class="button button--ghost button--small button--icon" :label="`Actions for the option ${option.label || index + 1}`" floating>
					<template #button><AdminIcon name="ellipsis-vertical" /></template>
					<button type="button" class="menu-item" :disabled="index === 0" @click="moveTo(index, index - 1)"><AdminIcon name="arrow-up" />Move up</button>
					<button type="button" class="menu-item" :disabled="index === options.length - 1" @click="moveTo(index, index + 1)"><AdminIcon name="arrow-down" />Move down</button>
					<div class="menu-divider" />
					<button type="button" class="menu-item menu-item--danger" @click="remove(index)"><AdminIcon name="trash-2" />Remove option</button>
				</MenuButton>
				<span v-else />
			</div>
		</template>
		<div v-if="!locked" class="nest__foot">
			<button type="button" class="button button--ghost button--small" @click="add"><AdminIcon name="plus" />Add Option</button>
		</div>
		<span class="visually-hidden" aria-live="polite">{{ said }}</span>
	</div>
	<p v-if="options.some((_, index) => shared(index))" class="field__error">Two options can't share a key.</p>
</template>

<style scoped>
.choice-options__head,
.choice-options__row {
	display: grid;
	grid-template-columns: 18px minmax(0, 1.4fr) minmax(0, 1fr) var(--ctl-sm);
	gap: var(--s-2);
	align-items: center;
	padding: 5px 8px 5px 6px;
	border-bottom: 1px solid var(--border-color);
}

.choice-options__head {
	padding-block: 6px;
	color: var(--color-text-muted);
	font-size: var(--font-size-xxsmall);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.choice-options__row:has(+ .nest__foot),
.choice-options__row:has(+ .visually-hidden) {
	border-bottom: 0;
}

.choice-options__row:hover {
	background: var(--color-surface-subtle);
}

.choice-options__row .input {
	width: 100%;
	min-height: var(--ctl-sm);
	padding-inline: 9px;
	font-size: var(--font-size-small);
}

.choice-options__grip {
	display: grid;
	place-items: center;
	color: var(--color-text-muted);
	cursor: grab;
	opacity: .55;
}

.choice-options__row:hover .choice-options__grip {
	opacity: 1;
}

.choice-options__grip .icon {
	width: 14px;
	height: 14px;
}

.choice-options__row.is-dragging {
	opacity: .45;
}

.choice-options__row.is-drop-before {
	box-shadow: inset 0 2px 0 var(--color-accent);
}

.choice-options__row.is-drop-after {
	box-shadow: inset 0 -2px 0 var(--color-accent);
}
</style>
