<script setup lang="ts">
/**
 * The block inserter (admin.md §8, The inserters; D-247, D-265): the
 * directives and Markdown elements, which the admin calls blocks
 * (D-532), in a panel that slides in from the left of the editor and
 * pushes the writing column aside, so it never covers the sentence being
 * written, and stays open while you browse. Inline directives aren't
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
import { directiveIcon, groupOf, groupsOf, matches, rank, type DirectiveDescription } from '../directives';
import { gridColumns, gridMove, numbered, type Section } from '../grid';

const props = defineProps<{
	directives: DirectiveDescription[];
	// Typed in the document after `/`, rather than in the search field.
	slash?: boolean;
	failed?: boolean;
	// Why only some directives are offered here.
	note?: string;
}>();

const query = defineModel<string>('query', { required: true });

const emit = defineEmits<{
	choose: [directive: DirectiveDescription];
	close: [];
}>();

const input  = ref<HTMLInputElement | null>(null);
const grid   = ref<HTMLElement | null>(null);
const active = ref(0);

const groups = computed(() => groupsOf(props.directives));

// What's shown, in sections; each tile has its place in keyboard order.
const sections = computed<Section<DirectiveDescription>[]>(() => {
	if (query.value.trim() !== '') {
		const results = rank(props.directives.filter((directive) => matches(directive, query.value)), query.value);

		return numbered([{ heading: results.length === 1 ? '1 match' : `${results.length} matches`, items: results }]);
	}

	return numbered(groups.value.map((item) => ({
		heading: item.source === undefined ? item.label : `${item.label} · ${item.source}`,
		items: props.directives.filter((directive) => groupOf(directive).key === item.key)
	})));
});

const tiles   = computed(() => sections.value.flatMap((section) => section.cells));
const current = computed(() => tiles.value[active.value]?.item);
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
	return gridColumns(grid.value?.querySelector('.inserter__tiles'));
}

/**
 * Moves the highlight by rows (the editor forwards up and down while a
 * slash is being typed).
 */
function move(rows: number): void {
	active.value = Math.max(0, Math.min(tiles.value.length - 1, active.value + rows * columns()));
}

/**
 * Inserts the highlighted directive, if any.
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
		<div class="panel__header">
			<h2 id="directive-panel-heading">Insert</h2>
			<span class="inserter__count">{{ query.trim() === '' ? directives.length : `${shown} of ${directives.length}` }}</span>
			<div class="panel__actions">
				<button type="button" class="button button--ghost button--small button--icon" @click="emit('close')">
					<AdminIcon name="x" />
					<span class="visually-hidden">Close the blocks</span>
				</button>
			</div>
		</div>

		<label class="inserter__search">
			<AdminIcon name="search" />
			<input
				ref="input"
				v-model="query"
				type="text"
				placeholder="Search blocks…"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				aria-label="Search blocks"
				aria-expanded="true"
				aria-controls="directive-panel-grid"
				:aria-activedescendant="current ? `directive-tile-${active}` : undefined"
				:readonly="slash"
				@keydown="keydown"
			>
		</label>

		<p v-if="note" class="inserter__note">{{ note }}</p>

		<div id="directive-panel-grid" ref="grid" class="inserter__grid" role="listbox" aria-labelledby="directive-panel-heading">
			<p v-if="failed" class="inserter__empty">The blocks couldn't be loaded.</p>
			<p v-else-if="!directives.length" class="inserter__empty">No blocks are registered.</p>
			<p v-else-if="!tiles.length" class="inserter__empty">Nothing matches <strong>{{ query }}</strong>.<br>Try a broader word.</p>
			<div v-for="section in sections" :key="section.heading" role="group" :aria-label="section.heading">
				<p class="eyebrow inserter__group" aria-hidden="true">{{ section.heading }}</p>
				<div class="inserter__tiles">
					<div
						v-for="tile in section.cells"
						:id="`directive-tile-${tile.index}`"
						:key="tile.item.name"
						class="inserter__tile"
						:class="{ 'is-active': tile.index === active }"
						role="option"
						:aria-selected="tile.index === active"
						@pointermove="active = tile.index"
						@mousedown.prevent
						@click="emit('choose', tile.item)"
					>
						<AdminIcon :name="directiveIcon(tile.item)" />
						<span class="inserter__name">{{ tile.item.label }}</span>
					</div>
				</div>
			</div>
		</div>

		<div class="inserter__preview" aria-live="polite">
			<template v-if="current">
				<p class="inserter__preview-title">
					{{ current.label }}
					<span v-if="current.source" class="inserter__source">{{ current.source.label }}</span>
				</p>
				<p class="inserter__preview-text">{{ current.description || current.name }}</p>
			</template>
			<p v-else class="inserter__preview-text">Search, or scroll the {{ directives.length }} blocks by category.</p>
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
	padding: var(--s-2) var(--pad-x);
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
	padding: 14px var(--pad-x);
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
	letter-spacing: .08em;
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
	padding: var(--s-4) var(--pad-x);
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
	.inserter .panel__header,
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
