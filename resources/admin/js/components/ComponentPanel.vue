<script setup lang="ts">
/**
 * The block component inserter (admin.md §8, The inserters; D-247,
 * D-265): a panel that slides in from the left of the editor and pushes
 * the writing column aside, so it never covers the sentence being
 * written, and stays open while you browse. Inline components aren't
 * here; they have their own menu. The search field is the panel's only
 * control, over a grid three across, headed by category, whose tiles are
 * an icon and a name and nothing else; the strip at the foot describes
 * the highlighted one, with where it comes from when that's a theme or
 * an extension.
 *
 * The keyboard moves through the grid (`grid.ts`), a row at a time as
 * the grid lays out; Enter inserts, Escape closes. Inside a container
 * that holds only some things (a gallery's images, D-314), the editor
 * passes only those, and `note` says why the list is short.
 * Opened by typing `/` at the start of a line, the query is typed
 * in the document and the editor forwards the keys with `move()`,
 * `choose()`, and `close`; the search field then shows the query.
 */

import { computed, nextTick, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { componentIcon, groupOf, groupsOf, matches, rank, type ComponentDescription } from '../components';
import { gridMove } from '../grid';

const props = defineProps<{
	components: ComponentDescription[];
	// Typed in the document after `/`, rather than in the search field.
	slash?: boolean;
	failed?: boolean;
	// Why only some components are offered here.
	note?: string;
}>();

const query = defineModel<string>('query', { required: true });

const emit = defineEmits<{
	choose: [component: ComponentDescription];
	close: [];
}>();

const input  = ref<HTMLInputElement | null>(null);
const grid   = ref<HTMLElement | null>(null);
const active = ref(0);

const groups = computed(() => groupsOf(props.components));

interface Section {
	heading: string;
	tiles: { index: number; component: ComponentDescription }[];
}

// What's shown, in sections; each tile has its place in keyboard order.
const sections = computed<Section[]>(() => {
	const found: Section[] = [];
	let index = 0;

	const section = (heading: string, items: ComponentDescription[]): void => {
		if (items.length > 0) {
			found.push({ heading, tiles: items.map((component) => ({ index: index++, component })) });
		}
	};

	if (query.value.trim() !== '') {
		const results = rank(props.components.filter((component) => matches(component, query.value)), query.value);

		section(results.length === 1 ? '1 match' : `${results.length} matches`, results);

		return found;
	}

	for (const item of groups.value) {
		section(item.source === undefined ? item.label : `${item.label} · ${item.source}`, props.components.filter((component) => groupOf(component).key === item.key));
	}

	return found;
});

const tiles   = computed(() => sections.value.flatMap((section) => section.tiles));
const current = computed(() => tiles.value[active.value]?.component);
const shown   = computed(() => tiles.value.length);

watch(query, () => {
	active.value = 0;
});

watch(active, async () => {
	await nextTick();
	grid.value?.querySelector('.inserter__tile.is-active')?.scrollIntoView({ block: 'nearest' });
});

// The tiles reflow with the panel's width, so steps are read off it.
function columns(): number {
	const tiles = grid.value?.querySelector('.inserter__tiles');

	return tiles === null || tiles === undefined ? 1 : Math.max(1, getComputedStyle(tiles).gridTemplateColumns.split(' ').length);
}

/**
 * Moves the highlight by rows (the editor forwards up and down while a
 * slash is being typed).
 */
function move(rows: number): void {
	active.value = Math.max(0, Math.min(tiles.value.length - 1, active.value + rows * columns()));
}

/**
 * Inserts the highlighted component, if any.
 */
function choose(): void {
	if (current.value !== undefined) {
		emit('choose', current.value);
	}
}

function keydown(event: KeyboardEvent): void {
	const next = gridMove(event.key, active.value, tiles.value.length, columns(), query.value !== '');

	if (next !== null) {
		event.preventDefault();
		active.value = next;
	} else if (event.key === 'Enter') {
		event.preventDefault();
		choose();
	} else if (event.key === 'Escape') {
		event.preventDefault();
		emit('close');
	}
}

/**
 * Puts focus in the search field.
 */
function focus(): void {
	input.value?.focus();
}

defineExpose({ move, choose, focus });
</script>

<template>
	<div class="inserter">
		<div class="inserter__head">
			<h2 id="component-panel-heading">Insert</h2>
			<span class="inserter__count">{{ query.trim() === '' ? components.length : `${shown} of ${components.length}` }}</span>
			<button type="button" class="button button--ghost button--icon" @click="emit('close')">
				<AdminIcon name="x" />
				<span class="visually-hidden">Close the components</span>
			</button>
		</div>

		<label class="inserter__search">
			<AdminIcon name="search" />
			<input
				ref="input"
				v-model="query"
				type="text"
				placeholder="Search components…"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				aria-label="Search components"
				aria-expanded="true"
				aria-controls="component-panel-grid"
				:aria-activedescendant="current ? `component-tile-${active}` : undefined"
				:readonly="slash"
				@keydown="keydown"
			>
		</label>

		<p v-if="note" class="inserter__note">{{ note }}</p>

		<div id="component-panel-grid" ref="grid" class="inserter__grid" role="listbox" aria-labelledby="component-panel-heading">
			<p v-if="failed" class="inserter__empty">The components couldn't be loaded.</p>
			<p v-else-if="!components.length" class="inserter__empty">No components are registered.</p>
			<p v-else-if="!tiles.length" class="inserter__empty">Nothing matches <strong>{{ query }}</strong>.<br>Try a broader word.</p>
			<div v-for="section in sections" :key="section.heading" role="group" :aria-label="section.heading">
				<p class="inserter__group" aria-hidden="true">{{ section.heading }}</p>
				<div class="inserter__tiles">
					<div
						v-for="tile in section.tiles"
						:id="`component-tile-${tile.index}`"
						:key="tile.component.name"
						class="inserter__tile"
						:class="{ 'is-active': tile.index === active }"
						role="option"
						:aria-selected="tile.index === active"
						@pointermove="active = tile.index"
						@mousedown.prevent
						@click="emit('choose', tile.component)"
					>
						<AdminIcon :name="componentIcon(tile.component)" />
						<span class="inserter__name">{{ tile.component.label }}</span>
					</div>
				</div>
			</div>
		</div>

		<div class="inserter__preview" aria-live="polite">
			<template v-if="current">
				<p class="inserter__preview-title">
					{{ current.label }}
					<span v-if="current.source && current.source.kind !== 'site'" class="inserter__source">{{ current.source.label }}</span>
				</p>
				<p class="inserter__preview-text">{{ current.description || current.name }}</p>
			</template>
			<p v-else class="inserter__preview-text">Search, or scroll the {{ components.length }} components by category.</p>
			<p class="inserter__preview-hint">
				<template v-if="slash">Typing after <kbd>/</kbd></template>
				<template v-else>Type <kbd>/</kbd> on an empty line to open this at the cursor</template>
			</p>
		</div>
	</div>
</template>

<style scoped>
.inserter__note {
	margin: 0;
	padding: var(--s-2) var(--s-5);
	border-bottom: 1px solid var(--border);
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.inserter {
	display: flex;
	flex-direction: column;
	width: var(--inserter);
	height: 100%;
	min-height: 0;
}

.inserter__head {
	display: flex;
	flex: none;
	align-items: center;
	gap: var(--s-2);
	min-height: 62px;
	padding: var(--s-3) var(--s-3) var(--s-3) var(--s-5);
	border-bottom: 1px solid var(--border);
}

.inserter__head h2 {
	flex: 1;
	min-width: 0;
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
}

.inserter__count {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

/* One band, one control: the grid's headings already show the
   categories. */
.inserter__search {
	display: flex;
	flex: none;
	align-items: center;
	gap: 11px;
	padding: 14px var(--s-5);
	border-bottom: 1px solid var(--border);
	color: var(--fg-3);
}

.inserter__search input {
	flex: 1;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}

.inserter__grid {
	flex: 1;
	min-height: 0;
	padding: var(--s-1) var(--s-3) var(--s-5);
	overflow-y: auto;
}

.inserter__group {
	padding: var(--s-5) 2px var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .08em;
	text-transform: uppercase;
}

[role="group"]:first-child .inserter__group {
	padding-top: var(--s-4);
}

.inserter__tiles {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 2px;
}

/* An icon and a name: the highlight shows only where the pointer or the
   keyboard is. */
.inserter__tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 9px;
	padding: var(--s-3) 5px;
	border-radius: var(--r-2);
	color: var(--fg-2);
	cursor: pointer;
	transition: background 100ms, color 100ms;
}

.inserter__tile > :deep(svg) {
	width: 22px;
	height: 22px;
	stroke-width: 1.5;
}

.inserter__tile:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.inserter__tile.is-active {
	background: var(--accent-soft);
	color: var(--accent);
}

.inserter__name {
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 500;
	line-height: 1.35;
	text-align: center;
	overflow-wrap: break-word;
	hyphens: auto;
}

.inserter__tile.is-active .inserter__name {
	color: var(--accent);
}

.inserter__source {
	padding: 2px 8px;
	border-radius: 99px;
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-2xs);
	font-weight: 500;
}

.inserter__empty {
	padding: 32px 10px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	line-height: 1.6;
	text-align: center;
}

.inserter__preview {
	display: grid;
	flex: none;
	gap: 4px;
	min-height: 76px;
	padding: var(--s-4) var(--s-5);
	border-top: 1px solid var(--border);
	background: var(--bg);
}

.inserter__preview-title {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	color: var(--fg);
	font-size: var(--text-sm);
	font-weight: 500;
}

.inserter__preview-text {
	color: var(--fg-3);
	font-size: var(--text-xs);
	line-height: 1.5;
}

.inserter__preview-hint {
	margin-top: 4px;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

@media (width <= 640px) {
	.inserter__head,
	.inserter__search,
	.inserter__preview {
		padding-inline: var(--s-4);
	}
}

@media (prefers-reduced-motion: reduce) {
	.inserter__tile {
		transition: none;
	}
}
</style>
