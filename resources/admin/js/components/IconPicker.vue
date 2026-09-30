<script setup lang="ts">
/**
 * The editor's icon picker (admin.md §8, The inserters; D-265): an icon
 * set is a library, so it's a modal, the same shell the media picker
 * uses. A search (names, labels, keywords, and groups), the groups down
 * the left (the core categories, then where the rest come from), a grid
 * of icons with their names, and a footer naming the selection with the
 * directive it writes.
 *
 * A click selects an icon; **Insert**, Enter, or a double click inserts
 * it. The arrow keys move through the grid from the search field (left
 * and right only while it's empty, so they still edit text). A search
 * selects its best match, so typing a name and pressing Enter inserts
 * it. Escape, **Cancel**, or the close button leave without one.
 */

import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { gridMove } from '../grid';
import { iconGroups, iconMask, iconMatches, loadIcons, type SiteIcon } from '../site-icons';

defineProps<{
	// The directive an icon would write, for the footer.
	preview: (icon: SiteIcon) => string;
}>();

const emit = defineEmits<{
	choose: [icon: SiteIcon];
	close: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);
const input  = ref<HTMLInputElement | null>(null);
const pane   = ref<HTMLElement | null>(null);
const icons  = ref<SiteIcon[]>([]);
const failed = ref(false);
const loaded = ref(false);
const query  = ref('');
const group  = ref('all');
const active = ref(-1);

loadIcons().then((list) => {
	icons.value = list;
}, () => {
	failed.value = true;
}).finally(() => {
	loaded.value = true;
});

const groups = computed(() => iconGroups(icons.value));

interface Section {
	heading: string;
	cells: { index: number; icon: SiteIcon }[];
}

// What's shown, in sections; each icon has its place in keyboard order.
// A search or a chosen group is one section; otherwise every group is.
const sections = computed<Section[]>(() => {
	const found: Section[] = [];
	let index = 0;

	const section = (heading: string, items: SiteIcon[]): void => {
		if (items.length > 0) {
			found.push({ heading, cells: items.map((icon) => ({ index: index++, icon })) });
		}
	};

	const pool = group.value === 'all' ? icons.value : groups.value.find((item) => item.key === group.value)?.icons ?? [];

	if (query.value.trim() !== '' || group.value !== 'all') {
		const results = pool.filter((icon) => iconMatches(icon, query.value));

		section(results.length === 1 ? '1 icon' : `${results.length} icons`, results);

		return found;
	}

	for (const item of groups.value) {
		section(item.label, item.icons);
	}

	return found;
});

const cells   = computed(() => sections.value.flatMap((section) => section.cells));
const current = computed(() => cells.value[active.value]?.icon);

watch(query, (value) => {
	active.value = value.trim() === '' ? -1 : 0;
});

watch(group, () => {
	active.value = -1;
});

watch(active, async () => {
	await nextTick();
	pane.value?.querySelector('.icon-picker__cell.is-active')?.scrollIntoView({ block: 'nearest' });
});

// The grid reflows with the modal's width, so steps are read off it.
function columns(): number {
	const grid = pane.value?.querySelector('.icon-picker__grid');

	return grid === null || grid === undefined ? 1 : Math.max(1, getComputedStyle(grid).gridTemplateColumns.split(' ').length);
}

function keydown(event: KeyboardEvent): void {
	const next = gridMove(event.key, Math.max(0, active.value), cells.value.length, columns(), query.value !== '');

	if (next !== null) {
		event.preventDefault();
		active.value = active.value === -1 && (event.key === 'ArrowDown' || event.key === 'ArrowRight') ? 0 : next;
	} else if (event.key === 'Enter') {
		event.preventDefault();
		use(current.value);
	}
}

function pick(key: string): void {
	group.value = key;
	query.value = '';
	input.value?.focus();
}

// The dialog closes first: while it's open and modal, nothing outside it
// can take focus, so the editor couldn't insert at its caret.
function use(icon: SiteIcon | undefined): void {
	if (icon !== undefined) {
		dialog.value?.close();
		emit('choose', icon);
	}
}

onMounted(() => {
	dialog.value?.showModal();
	input.value?.focus();
});
</script>

<template>
	<dialog ref="dialog" class="modal icon-picker" aria-labelledby="icon-picker-heading" @close="emit('close')" @keydown.esc.prevent.stop="dialog?.close()">
		<div class="modal__head">
			<h2 id="icon-picker-heading">Insert an Icon</h2>
			<button type="button" class="button button--ghost button--icon" @click="dialog?.close()">
				<AdminIcon name="x" />
				<span class="visually-hidden">Close</span>
			</button>
		</div>

		<div class="modal__bar">
			<label class="search-field">
				<AdminIcon name="search" />
				<input
					ref="input"
					v-model="query"
					type="search"
					placeholder="Search icons…"
					autocomplete="off"
					spellcheck="false"
					role="combobox"
					aria-label="Search icons"
					aria-expanded="true"
					aria-controls="icon-picker-pane"
					:aria-activedescendant="current ? `icon-${active}` : undefined"
					@keydown="keydown"
				>
			</label>
		</div>

		<div class="icon-picker__split">
			<nav class="icon-picker__groups" aria-label="Icon groups">
				<button type="button" :aria-current="group === 'all'" @click="pick('all')">
					<AdminIcon name="layout-grid" />All Icons<span class="icon-picker__n mono">{{ icons.length }}</span>
				</button>
				<button v-for="item in groups" :key="item.key" type="button" :aria-current="group === item.key" @click="pick(item.key)">
					<AdminIcon :name="item.icon" />{{ item.label }}<span class="icon-picker__n mono">{{ item.icons.length }}</span>
				</button>
			</nav>

			<div id="icon-picker-pane" ref="pane" class="icon-picker__pane" role="listbox" aria-label="Icons">
				<div v-if="!loaded" class="icon-picker__grid" aria-hidden="true">
					<span v-for="cell in 24" :key="cell" class="skeleton icon-picker__skeleton" />
				</div>
				<div v-else-if="!cells.length" class="empty">
					<AdminIcon name="search" />
					<p class="empty__heading">
						<template v-if="failed">The Icons Couldn't Be Loaded</template>
						<template v-else-if="query">No Icon Called That</template>
						<template v-else>This Site Has No Icons</template>
					</p>
					<p v-if="query && !failed" class="empty__text">Try a broader word, or pick a group on the left.</p>
				</div>
				<div v-for="section in sections" :key="section.heading" role="group" :aria-label="section.heading">
					<p class="icon-picker__heading" aria-hidden="true">{{ section.heading }}</p>
					<div class="icon-picker__grid">
						<div
							v-for="cell in section.cells"
							:id="`icon-${cell.index}`"
							:key="cell.icon.name"
							class="icon-picker__cell"
							:class="{ 'is-active': cell.index === active }"
							role="option"
							:aria-selected="cell.index === active"
							@mousedown.prevent
							@click="active = cell.index"
							@dblclick="use(cell.icon)"
						>
							<span v-if="cell.icon.svg" class="icon-picker__glyph" :style="{ maskImage: iconMask(cell.icon) }" />
							<span class="icon-picker__name">{{ cell.icon.label }}</span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="modal__foot">
			<p class="modal__selected" aria-live="polite">
				<template v-if="current"><b>{{ current.label }}</b> · <code>{{ preview(current) }}</code></template>
				<template v-else>Choose an icon.</template>
			</p>
			<button type="button" class="button" @click="dialog?.close()">Cancel</button>
			<button type="button" class="button button--primary" :disabled="current === undefined" @click="use(current)">Insert</button>
		</div>
	</dialog>
</template>

<style scoped>
/* Groups down the left: a wide modal has the column to spare, and the
   list holds its shape as the set grows. */
.icon-picker__split {
	display: flex;
	flex: 1;
	min-height: 0;
	border-top: 1px solid var(--border);
}

.icon-picker__groups {
	display: flex;
	flex: none;
	flex-direction: column;
	gap: 2px;
	width: 210px;
	padding: var(--s-3);
	overflow-y: auto;
	border-right: 1px solid var(--border);
	background: var(--bg);
}

.icon-picker__groups button {
	display: flex;
	align-items: center;
	gap: 11px;
	width: 100%;
	padding: 9px 11px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.icon-picker__groups button:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.icon-picker__groups button[aria-current="true"] {
	background: var(--surface-2);
	color: var(--fg);
	font-weight: 500;
}

.icon-picker__n {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 400;
}

.icon-picker__pane {
	flex: 1;
	min-width: 0;
	padding: var(--s-5);
	overflow-y: auto;
}

.icon-picker__heading {
	padding-bottom: var(--s-3);
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

[role="group"] + [role="group"] .icon-picker__heading {
	padding-top: var(--s-6);
}

.icon-picker__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
	gap: var(--s-2);
}

.icon-picker__cell {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 10px;
	min-width: 0;
	padding: var(--s-4) var(--s-2);
	border-radius: var(--r-2);
	color: var(--fg-2);
	cursor: pointer;
}

.icon-picker__cell:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.icon-picker__cell.is-active {
	background: var(--accent-soft);
	color: var(--accent);
}

.icon-picker__glyph {
	display: block;
	flex: none;
	width: 22px;
	height: 22px;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

.icon-picker__name {
	max-width: 100%;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-xs);
	line-height: 1.3;
	text-align: center;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.icon-picker__cell:hover .icon-picker__name {
	color: var(--fg-2);
}

.icon-picker__cell.is-active .icon-picker__name {
	color: var(--accent);
}

.icon-picker__skeleton {
	height: 76px;
	border-radius: var(--r-2);
}

/* Narrow: the groups turn into one scrolling row. */
@media (width <= 760px) {
	.icon-picker__split {
		flex-direction: column;
	}

	.icon-picker__groups {
		flex-direction: row;
		width: auto;
		overflow-x: auto;
		border-right: 0;
		border-bottom: 1px solid var(--border);
	}

	.icon-picker__groups button {
		width: auto;
		white-space: nowrap;
	}

	.icon-picker__n {
		display: none;
	}
}
</style>
