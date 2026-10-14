<script setup lang="ts">
/**
 * The block inserter (admin.md §8, The inserters; D-247, D-265): the
 * blocks and Markdown elements, which the admin offers alike
 * (D-532), in a panel that slides in from the left of the editor and
 * pushes the writing column aside, so it never covers the sentence being
 * written, and stays open while you browse. Inline blocks aren't
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
import MessageText from './MessageText.vue';
import { blockIcon, groupOf, groupsOf, matches, rank, type BlockDescription } from '../blocks';
import { gridColumns, gridMove, numbered, type Section } from '../grid';
import { t } from '../messages';

const props = defineProps<{
	blocks: BlockDescription[];
	// Typed in the document after `/`, rather than in the search field.
	slash?: boolean;
	failed?: boolean;
	// Why only some blocks are offered here.
	note?: string;
}>();

const query = defineModel<string>('query', { required: true });

const emit = defineEmits<{
	choose: [block: BlockDescription];
	close: [];
}>();

const input  = ref<HTMLInputElement | null>(null);
const grid   = ref<HTMLElement | null>(null);
const active = ref(0);

const groups = computed(() => groupsOf(props.blocks));

// What's shown, in sections; each tile has its place in keyboard order.
const sections = computed<Section<BlockDescription>[]>(() => {
	if (query.value.trim() !== '') {
		const results = rank(props.blocks.filter((block) => matches(block, query.value)), query.value);

		return numbered([{ heading: t('block_panel.matches', '{count, plural, one {# match} other {# matches}}', { count: results.length }), items: results }]);
	}

	return numbered(groups.value.map((item) => ({
		heading: item.source === undefined ? item.label : `${item.label} · ${item.source}`,
		items: props.blocks.filter((block) => groupOf(block).key === item.key)
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
 * Inserts the highlighted block, if any.
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
			<h2 id="block-panel-heading">{{ t('block_panel.heading', 'Insert') }}</h2>
			<span class="inserter__count">{{ query.trim() === '' ? blocks.length : t('block_panel.shown', '{shown} of {total}', { shown, total: blocks.length }) }}</span>
			<div class="panel__actions">
				<button type="button" class="button button--ghost button--small button--icon" @click="emit('close')">
					<AdminIcon name="x" />
					<span class="visually-hidden">{{ t('block_panel.close', 'Close the blocks') }}</span>
				</button>
			</div>
		</div>

		<label class="inserter__search">
			<AdminIcon name="search" />
			<input
				ref="input"
				v-model="query"
				type="text"
				:placeholder="t('block_panel.search_placeholder', 'Search blocks…')"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				:aria-label="t('block_panel.search_label', 'Search blocks')"
				aria-expanded="true"
				aria-controls="block-panel-grid"
				:aria-activedescendant="current ? `block-tile-${active}` : undefined"
				:readonly="slash"
				@keydown="keydown"
			>
		</label>

		<p v-if="note" class="inserter__note">{{ note }}</p>

		<div id="block-panel-grid" ref="grid" class="inserter__grid" role="listbox" aria-labelledby="block-panel-heading">
			<p v-if="failed" class="inserter__empty">{{ t('block_panel.failed', "The blocks couldn't be loaded.") }}</p>
			<p v-else-if="!blocks.length" class="inserter__empty">{{ t('block_panel.none', 'No blocks are registered.') }}</p>
			<p v-else-if="!tiles.length" class="inserter__empty"><MessageText :text="t('block_panel.no_match', 'Nothing matches {query}.')"><template #query><strong>{{ query }}</strong></template></MessageText><br>{{ t('block_panel.no_match_hint', 'Try a broader word.') }}</p>
			<div v-for="section in sections" :key="section.heading" role="group" :aria-label="section.heading">
				<p class="eyebrow inserter__group" aria-hidden="true">{{ section.heading }}</p>
				<div class="inserter__tiles">
					<div
						v-for="tile in section.cells"
						:id="`block-tile-${tile.index}`"
						:key="tile.item.name"
						class="inserter__tile"
						:class="{ 'is-active': tile.index === active }"
						role="option"
						:aria-selected="tile.index === active"
						@pointermove="active = tile.index"
						@mousedown.prevent
						@click="emit('choose', tile.item)"
					>
						<AdminIcon :name="blockIcon(tile.item)" />
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
			<p v-else class="inserter__preview-text">{{ t('block_panel.preview_empty', 'Search, or scroll the {count} blocks by category.', { count: blocks.length }) }}</p>
			<p class="inserter__preview-hint">
				<template v-if="slash"><MessageText :text="t('block_panel.hint_slash', 'Typing after {key}')"><template #key><kbd>/</kbd></template></MessageText></template>
				<template v-else><MessageText :text="t('block_panel.hint', 'Type {key} on an empty line to open this at the cursor')"><template #key><kbd>/</kbd></template></MessageText></template>
			</p>
		</div>
	</div>
</template>

<style scoped>
.inserter__note {
	margin: 0;
	padding: var(--s-2) var(--padding-inline);
	border-bottom: 1px solid var(--border-color);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.inserter {
	display: flex;
	flex-direction: column;
	width: var(--inserter);
	height: 100%;
	min-height: 0;
}

.inserter__count {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

/* One band, one control: the grid's headings already show the
   categories. */
.inserter__search {
	display: flex;
	flex: none;
	align-items: center;
	gap: 11px;
	padding: 14px var(--padding-inline);
	border-bottom: 1px solid var(--border-color);
	color: var(--color-text-muted);
}

.inserter__search input {
	flex: 1;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--color-text);
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
	border-radius: var(--border-radius-medium);
	color: var(--color-text-secondary);
	cursor: pointer;
	transition: background 100ms, color 100ms;
}

.inserter__tile > :deep(svg) {
	width: 22px;
	height: 22px;
	stroke-width: 1.5;
}

.inserter__tile:hover {
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.inserter__tile.is-active {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.inserter__name {
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	font-weight: 500;
	line-height: 1.35;
	text-align: center;
	overflow-wrap: break-word;
	hyphens: auto;
}

.inserter__tile.is-active .inserter__name {
	color: var(--color-accent);
}

.inserter__source {
	padding: 2px 8px;
	border-radius: 99px;
	background: var(--color-warning-subtle);
	color: var(--color-warning);
	font-size: var(--font-size-xxsmall);
	font-weight: 500;
}

.inserter__empty {
	padding: 32px 10px;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
	line-height: 1.6;
	text-align: center;
}

.inserter__preview {
	display: grid;
	flex: none;
	gap: 4px;
	min-height: 76px;
	padding: var(--s-4) var(--padding-inline);
	border-top: 1px solid var(--border-color);
	background: var(--color-background);
}

.inserter__preview-title {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	color: var(--color-text);
	font-size: var(--font-size-small);
	font-weight: 500;
}

.inserter__preview-text {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	line-height: 1.5;
}

.inserter__preview-hint {
	margin-top: 4px;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
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
