<script setup lang="ts">
/**
 * The filters over a list of extensions (D-565, the extensions sketch's):
 * words to type, a Status (on or off, in the kind's own words, or Needs
 * attention) and a Source, then, on a list with cards, Cards or a
 * Compact list (`extensionView`, kept in this browser). Under them, how
 * many there are, or how many of them match and what's in force, with
 * **Clear Filters**. The values are the list's address (`useExtensionFilter()`).
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import { extensionView } from '../density';
import type { ExtensionSource } from '../extensions';

const props = defineProps<{
	// The kind, plural and lowercase: "themes", "icon packs".
	noun: string;
	// What on and off are called: Active and Inactive for themes.
	on: string;
	off: string;
	// The sources this kind can come from.
	sources: ExtensionSource[];
	shown: number;
	total: number;
	// After the count while nothing filters it: "1 active".
	summary: string;
	filtered: boolean;
	// Offer Cards or a Compact list.
	views?: boolean;
}>();

const query  = defineModel<string>('query', { required: true });
const status = defineModel<string>('status', { required: true });
const source = defineModel<string>('source', { required: true });

const emit = defineEmits<{ clear: [] }>();

const id = computed(() => `extensions-${props.noun.replace(/\s+/g, '-')}`);

const SOURCE_LABELS: Record<ExtensionSource, string> = {
	composer: 'Composer',
	local: 'A folder in extensions/',
	framework: 'Ships with Blush'
};

const statusOptions = computed<SelectOption[]>(() => [
	{ value: '', label: 'Any status' },
	{ value: 'on', label: props.on },
	{ value: 'off', label: props.off },
	{ value: 'attention', label: 'Needs attention' }
]);

const sourceOptions = computed<SelectOption[]>(() => [
	{ value: '', label: 'Any source' },
	...props.sources.map((value) => ({ value, label: SOURCE_LABELS[value] }))
]);

// How many, then what's in force, or what the whole list is.
const report = computed(() => {
	const parts = [
		statusOptions.value.find((option) => option.value === status.value && option.value !== '')?.label,
		sourceOptions.value.find((option) => option.value === source.value && option.value !== '')?.label,
		query.value.trim() === '' ? undefined : `“${query.value.trim()}”`
	].filter((part) => part !== undefined);

	return props.filtered
		? [`${props.shown} of ${props.total} ${props.noun}`, ...parts].join(' · ')
		: [`${props.total} ${props.noun}`, props.summary].filter((part) => part !== '').join(' · ');
});
</script>

<template>
	<div class="toolbar extension-filters" role="search">
		<label class="search-field toolbar__search">
			<AdminIcon name="search" />
			<span class="visually-hidden">Filter {{ noun }}</span>
			<input v-model="query" type="search" :placeholder="`Filter ${noun}`" autocomplete="off">
		</label>
		<div class="toolbar__filter">
			<label class="visually-hidden" :for="`${id}-status`">Status</label>
			<AdminSelect :id="`${id}-status`" v-model="status" :options="statusOptions" />
		</div>
		<div class="toolbar__filter">
			<label class="visually-hidden" :for="`${id}-source`">Source</label>
			<AdminSelect :id="`${id}-source`" v-model="source" :options="sourceOptions" />
		</div>
		<div v-if="views" class="segmented segmented--icons toolbar__end" role="group" aria-label="View">
			<button type="button" :aria-pressed="extensionView === 'cards'" title="Cards" @click="extensionView = 'cards'">
				<AdminIcon name="layout-grid" /><span class="visually-hidden">Cards</span>
			</button>
			<button type="button" :aria-pressed="extensionView === 'list'" title="Compact list" @click="extensionView = 'list'">
				<AdminIcon name="rows-3" /><span class="visually-hidden">Compact list</span>
			</button>
		</div>
	</div>

	<p class="filter-report extension-filters__report" aria-live="polite">
		{{ report }}
		<button v-if="filtered" type="button" class="button button--ghost button--small" @click="emit('clear')">Clear Filters</button>
	</p>
</template>
