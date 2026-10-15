<script setup lang="ts">
/**
 * Choosing how a field is entered (the fields sketch's picker, D-766): a
 * modal in the library pickers' shell (`.modal`), its search in a band
 * of its own under the heading, then the controls by the catalog's
 * sections (Structure first), each a tile with its glyph over its name.
 * A tile has no border of its own: pointing at one tints it, and only
 * the field's own control is outlined. The footer says what the tile
 * under the pointer or the focus is for, beside **Cancel**.
 *
 * A saved field (`current`) is offered only the controls that store the
 * same value (D-768); a field inside an Object isn't offered the
 * Structure controls (`flat`, D-770), which the heading says. Choosing one closes it
 * (`choose`); Escape, **Cancel**, the close button, or a click outside
 * it leave it.
 */

import { computed, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { useModalDialog } from '../dialog';
import { catalogControl, controlIcon, offered, switchesTo } from '../controls';
import { loadFieldTypes } from '../field-types';

const props = defineProps<{
	// The control of a saved field, which only switches to its shape.
	current?: string;
	flat?: boolean;
}>();

const emit = defineEmits<{ choose: [key: string]; close: [] }>();

void loadFieldTypes().catch(() => undefined);

const input  = ref<HTMLInputElement | null>(null);
const query  = ref('');
const hinted = ref<string | null>(props.current ?? null);

const { dialog, close, backdrop } = useModalDialog(() => input.value?.focus());

const allowed = computed(() => props.current === undefined ? null : switchesTo(props.current));

const sections = computed(() => {
	const words = query.value.trim().toLowerCase();

	return offered.value
		.filter((section) => !(props.flat && section.value === 'structure'))
		.map((section) => ({
			...section,
			controls: section.controls.filter((control) => (allowed.value === null || allowed.value.includes(control.value))
				&& (words === '' || `${control.label} ${control.description}`.toLowerCase().includes(words)))
		}))
		.filter((section) => section.controls.length > 0);
});

const hint = computed(() => hinted.value === null ? undefined : catalogControl(hinted.value));

// The dialog closes first, so focus can go back to the form.
function choose(key: string): void {
	close();
	emit('choose', key);
}
</script>

<template>
	<dialog ref="dialog" class="modal control-picker" aria-labelledby="control-picker-heading" @close="emit('close')" @click="backdrop" @keydown.esc.prevent.stop="close()">
		<div class="panel__header">
			<div class="control-picker__title">
				<h2 id="control-picker-heading">{{ current === undefined ? t('control_picker.choose', 'Choose a Control') : t('control_picker.change', 'Change the Control') }}</h2>
				<p v-if="flat" class="panel__hint">{{ t('control_picker.flat_hint', 'This one sits inside an Object, so it can\'t be one itself.') }}</p>
				<p v-else-if="current !== undefined" class="panel__hint">{{ t('control_picker.same_value_hint', 'These store the same value, so what entries have is kept.') }}</p>
			</div>
			<div class="panel__actions">
				<button type="button" class="button button--ghost button--small button--icon" @click="close()">
					<AdminIcon name="x" />
					<span class="visually-hidden">{{ t('common.close', 'Close') }}</span>
				</button>
			</div>
		</div>

		<div class="modal__bar">
			<label class="search-field control-picker__search">
				<AdminIcon name="search" />
				<input ref="input" v-model="query" type="search" :placeholder="t('control_picker.search_placeholder', 'Search controls…')" autocomplete="off" spellcheck="false" :aria-label="t('control_picker.search_label', 'Search controls')">
			</label>
		</div>

		<div class="modal__body">
			<p v-if="!sections.length" class="control-picker__none">{{ t('control_picker.no_match', 'No control matches that.') }}</p>
			<section v-for="section in sections" :key="section.value" class="control-picker__section" :aria-labelledby="`control-section-${section.value}`">
				<h3 :id="`control-section-${section.value}`" class="eyebrow control-picker__heading">{{ section.label }}</h3>
				<div class="control-picker__grid" role="radiogroup" :aria-labelledby="`control-section-${section.value}`">
					<button
						v-for="control in section.controls"
						:key="control.value"
						type="button"
						role="radio"
						class="control-picker__tile"
						:aria-checked="control.value === current"
						:title="control.description"
						aria-describedby="control-picker-hint"
						@mouseenter="hinted = control.value"
						@focus="hinted = control.value"
						@click="choose(control.value)"
					>
						<AdminIcon :name="controlIcon(control.value)" />
						<span class="control-picker__name">{{ control.label }}</span>
					</button>
				</div>
			</section>
		</div>

		<div class="modal__foot">
			<p id="control-picker-hint" class="modal__selected control-picker__hint" aria-live="polite">
				<template v-if="hint"><b>{{ hint.label }}</b><span>{{ hint.description }}</span></template>
				<span v-else class="control-picker__quiet">{{ t('control_picker.point_hint', 'Point at a control to see what it holds.') }}</span>
			</p>
			<button type="button" class="button" @click="close()">{{ t('common.cancel', 'Cancel') }}</button>
		</div>
	</dialog>
</template>

<style scoped>
/* Fifteen tiles or so, not a library: a narrower modal, as tall as it
   needs to be. */
.control-picker {
	width: min(680px, calc(100vw - 32px));
	height: auto;
	max-height: calc(100vh - 48px);
}

.control-picker__title {
	display: grid;
	gap: 4px;
}

.control-picker__search {
	flex: 1;
}

.control-picker__section + .control-picker__section {
	padding-top: var(--s-4);
}

.control-picker__heading {
	padding-bottom: var(--s-2);
}

.control-picker__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(108px, 1fr));
	gap: var(--s-2);
}

.control-picker__tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	min-height: 86px;
	padding: 12px 8px;
	border: 1px solid transparent;
	border-radius: var(--border-radius-medium);
	background: transparent;
	color: var(--color-text);
	font: inherit;
	text-align: center;
	cursor: pointer;
}

.control-picker__tile:hover,
.control-picker__tile:focus-visible {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.control-picker__tile[aria-checked="true"] {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.control-picker__tile .icon {
	width: 20px;
	height: 20px;
	color: var(--color-text-secondary);
}

.control-picker__tile:hover .icon,
.control-picker__tile:focus-visible .icon,
.control-picker__tile[aria-checked="true"] .icon {
	color: var(--color-accent);
}

.control-picker__name {
	font-size: var(--font-size-xsmall);
	font-weight: 500;
	line-height: 1.3;
	text-wrap: balance;
}

.control-picker__none {
	padding: var(--s-5) 0;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

/* What the footer says keeps its height, so the button never moves. */
.control-picker__hint {
	display: flex;
	flex-direction: column;
	justify-content: center;
	gap: 1px;
	min-height: 34px;
	white-space: normal;
}

.control-picker__hint span {
	color: var(--color-text-secondary);
}

.control-picker__quiet {
	color: var(--color-text-muted);
}
</style>
