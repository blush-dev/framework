<script setup lang="ts">
/**
 * A menu's items, as a tree in a panel (from the menus sketch). Each row
 * shows the item's icon (its link's kind's, until it's given one), what
 * it's called, a pill when its link isn't on the site as it is, and,
 * when it's closed, how many items are under it; its own buttons move it (up, down, out beside its parent, in under the
 * item above), open its fields under it (`MenuItemFields`), and hold
 * more (add an item above or below, duplicate, remove).
 *
 * The keys, on a row: ↑ and ↓ go to the next row shown, Home and End to
 * the ends, → opens an item or goes to its first child, ← closes it or
 * goes to its parent, F2 opens its fields, and ⌥ with an arrow moves it,
 * as the editor moves elements (D-314). Rows can be dragged too: onto the
 * top or bottom of a row to go before or after it, onto its middle to go
 * in as its first child. Only the selected row's buttons are in the tab
 * order. Removing and duplicating offer Undo. What moved, and where to,
 * is read out.
 */

import { computed, nextTick, ref } from 'vue';
import { plural } from '../format';
import { blankNode, canMove, copyNode, empty, find, flatten, kindLabel, linkIcon, linkPill, moveNode, moveTo, shown, textOf, within, type MenuDetail, type MenuNode, type Move, type Placed } from '../menus';
import { toast } from '../toast';
import AdminIcon from './AdminIcon.vue';
import EmptyState from './EmptyState.vue';
import MenuButton from './MenuButton.vue';
import MenuItemFields from './MenuItemFields.vue';
import SiteIcon from './SiteIcon.vue';

const props = defineProps<{
	nodes: MenuNode[];
	locale: string;
	kinds: MenuDetail['kinds'];
}>();

const emit = defineEmits<{ change: [] }>();

const root     = ref<HTMLElement | null>(null);
const selected = ref<string | null>(null);
const editing  = ref<string | null>(null);
const dragging = ref<string | null>(null);
const drop     = ref<{ id: string; where: 'before' | 'after' | 'inside'; depth: number } | null>(null);
const said     = ref('');

const rows    = computed(() => flatten(props.nodes, true));
const total   = computed(() => flatten(props.nodes).length);
const parents = computed(() => flatten(props.nodes).some((placed) => placed.node.children.length > 0));
const closed  = computed(() => flatten(props.nodes).some((placed) => placed.node.children.length > 0 && !placed.node.open));
const current = computed(() => selected.value !== null && rows.value.some((placed) => placed.node.id === selected.value) ? selected.value : rows.value[0]?.node.id ?? null);

const MOVES: { move: Move; icon: 'arrow-up' | 'arrow-down' | 'indent-decrease' | 'indent-increase'; tip: string; label: string; said: string }[] = [
	{ move: 'up', icon: 'arrow-up', tip: 'Move Up (⌥↑)', label: 'Move up', said: 'Moved up' },
	{ move: 'down', icon: 'arrow-down', tip: 'Move Down (⌥↓)', label: 'Move down', said: 'Moved down' },
	{ move: 'out', icon: 'indent-decrease', tip: 'Outdent (⌥←)', label: 'Outdent', said: 'Outdented' },
	{ move: 'in', icon: 'indent-increase', tip: 'Indent (⌥→)', label: 'Indent', said: 'Indented' }
];

const name = (node: MenuNode): string => shown(node, props.locale);

function say(message: string): void {
	said.value = '';
	requestAnimationFrame(() => {
		said.value = message;
	});
}

function where(id: string): string {
	const placed = find(props.nodes, id);

	if (placed === undefined) {
		return '';
	}

	return `level ${placed.depth + 1}, ${placed.index + 1} of ${placed.list.length}${placed.parent ? ` inside ${name(placed.parent)}` : ' at the top'}`;
}

function under(node: MenuNode): number {
	return flatten(node.children).length;
}

async function focus(id: string, selector = '.menu-tree__pick'): Promise<void> {
	await nextTick();

	const row = root.value?.querySelector<HTMLElement>(`[data-id="${id}"] > .menu-tree__row`);
	const to  = row?.querySelector<HTMLElement>(`${selector}:not([disabled])`) ?? row?.querySelector<HTMLElement>('.menu-tree__pick');

	to?.focus({ preventScroll: true });
	row?.scrollIntoView({ block: 'nearest' });
}

function select(id: string, then?: string): void {
	if (editing.value !== null && editing.value !== id) {
		editing.value = null;
	}

	selected.value = id;
	void focus(id, then);
}

function act(id: string, move: Move, then?: string): void {
	if (!moveNode(props.nodes, id, move)) {
		say('Can\'t move it that way from here.');

		return;
	}

	emit('change');
	selected.value = id;
	say(`${MOVES.find((item) => item.move === move)!.said}. ${name(find(props.nodes, id)!.node)} is ${where(id)}.`);
	void focus(id, then ?? '.menu-tree__pick');
}

function twist(placed: Placed): void {
	placed.node.open = !placed.node.open;

	// Closing an item over the selected row selects the item.
	if (!placed.node.open && selected.value !== null && within(placed.node, selected.value)) {
		selected.value = placed.node.id;

		if (editing.value !== null && within(placed.node, editing.value)) {
			editing.value = null;
		}
	}

	void focus(current.value ?? placed.node.id);
}

async function edit(id: string): Promise<void> {
	selected.value = id;
	editing.value  = editing.value === id ? null : id;

	if (editing.value === null) {
		void focus(id, '.menu-tree__edit');

		return;
	}

	await nextTick();
	document.getElementById(`menu-item-${id}-label`)?.focus();
	say(`Editing ${name(find(props.nodes, id)!.node)}`);
}

function closeFields(id: string): void {
	editing.value = null;
	void focus(id, '.menu-tree__edit');
}

/**
 * Adds an item: after the selected one, or above or below a row (its
 * menu's), at the same level, with its fields open.
 */
async function add(place: 'above' | 'below' = 'below', id: string | null = current.value): Promise<void> {
	const node   = blankNode();
	const placed = id === null ? undefined : find(props.nodes, id);

	if (placed === undefined) {
		props.nodes.push(node);
	} else {
		placed.list.splice(placed.index + (place === 'above' ? 0 : 1), 0, node);
	}

	selected.value = node.id;
	editing.value  = node.id;
	emit('change');
	say(`Added an item${placed ? ` ${place === 'above' ? 'above' : 'after'} ${name(placed.node)}` : ''}. Give it a label, then a link, or leave the link empty for plain text.`);

	await nextTick();
	document.getElementById(`menu-item-${node.id}-label`)?.focus();
	root.value?.querySelector(`[data-id="${node.id}"]`)?.scrollIntoView({ block: 'nearest' });
}

function remove(id: string): void {
	const placed = find(props.nodes, id);

	if (placed === undefined) {
		return;
	}

	const { node, list, index, parent } = placed;
	const count = under(node);

	list.splice(index, 1);
	emit('change');

	if (editing.value !== null && (editing.value === id || within(node, editing.value))) {
		editing.value = null;
	}

	const next = list[index] ?? list[index - 1] ?? parent;

	selected.value = next?.id ?? null;

	if (next) {
		void focus(next.id);
	}

	toast(`Removed ${name(node)}${count ? ` and its ${plural(count, 'sub-item')}` : ''}`, {
		undo: () => {
			list.splice(index, 0, node);
			emit('change');
			select(node.id);
		}
	});
}

function duplicate(id: string): void {
	const placed = find(props.nodes, id);

	if (placed === undefined) {
		return;
	}

	const copy = copyNode(placed.node);

	placed.list.splice(placed.index + 1, 0, copy);
	emit('change');
	select(copy.id);

	toast(`Duplicated ${name(placed.node)}`, {
		undo: () => {
			const there = find(props.nodes, copy.id);

			there?.list.splice(there.index, 1);
			emit('change');
			select(id);
		}
	});
}

/**
 * Opens every item, or closes them all, keeping the selection on a row
 * that's shown.
 */
function toggleAll(): void {
	const open = closed.value;

	flatten(props.nodes).forEach((placed) => {
		if (placed.node.children.length) {
			placed.node.open = open;
		}
	});

	if (!open && selected.value !== null) {
		let top = find(props.nodes, selected.value);

		while (top?.parent) {
			top = find(props.nodes, top.parent.id);
		}

		selected.value = top?.node.id ?? null;
		editing.value  = editing.value !== null && editing.value === selected.value ? editing.value : null;
	}
}

const ARROWS: Record<string, Move> = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'out', ArrowRight: 'in' };

function key(event: KeyboardEvent, placed: Placed): void {
	const target = event.target as HTMLElement;
	const move   = ARROWS[event.key];

	if (event.altKey && move !== undefined) {
		event.preventDefault();
		act(placed.node.id, move, target.matches('.menu-tree__move') ? `.menu-tree__move[data-move="${move}"]` : undefined);

		return;
	}

	if (!target.matches('.menu-tree__pick')) {
		return;
	}

	const index = rows.value.findIndex((row) => row.node.id === placed.node.id);
	const go    = (to: number): void => {
		event.preventDefault();
		select(rows.value[to]!.node.id);
	};

	if (event.key === 'ArrowDown' && index < rows.value.length - 1) {
		go(index + 1);
	} else if (event.key === 'ArrowUp' && index > 0) {
		go(index - 1);
	} else if (event.key === 'Home') {
		go(0);
	} else if (event.key === 'End') {
		go(rows.value.length - 1);
	} else if (event.key === 'ArrowRight' && placed.node.children.length) {
		event.preventDefault();

		if (placed.node.open) {
			go(index + 1);
		} else {
			placed.node.open = true;
		}
	} else if (event.key === 'ArrowLeft') {
		event.preventDefault();

		if (placed.node.children.length && placed.node.open) {
			placed.node.open = false;
		} else if (placed.parent) {
			select(placed.parent.id);
		}
	} else if (event.key === 'F2') {
		event.preventDefault();
		void edit(placed.node.id);
	}
}

function dragStart(event: DragEvent, placed: Placed): void {
	if ((event.target as Element).closest('input, textarea, .menu-fields')) {
		event.preventDefault();

		return;
	}

	dragging.value = placed.node.id;

	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', placed.node.id);
	}
}

function dragOver(event: DragEvent, placed: Placed): void {
	const id = dragging.value;

	if (id === null || id === placed.node.id || within(find(props.nodes, id)!.node, placed.node.id)) {
		drop.value = null;

		return;
	}

	event.preventDefault();

	const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
	const y   = (event.clientY - box.top) / box.height;
	const at  = y < .3 ? 'before' : y > .7 ? 'after' : 'inside';

	drop.value = { id: placed.node.id, where: at, depth: placed.depth + (at === 'inside' ? 1 : 0) };
}

function dropped(event: DragEvent): void {
	event.preventDefault();

	const id = dragging.value;
	const at = drop.value;

	dragging.value = null;
	drop.value     = null;

	if (id === null || at === null) {
		return;
	}

	moveTo(props.nodes, id, at.id, at.where);
	emit('change');
	selected.value = id;
	say(`Moved ${name(find(props.nodes, id)!.node)}, ${where(id)}.`);
	void focus(id);
}

function dragEnd(): void {
	dragging.value = null;
	drop.value     = null;
}
</script>

<template>
	<section ref="root" class="panel menu-tree-panel" aria-labelledby="menu-items-heading">
		<header class="panel__header">
			<h2 id="menu-items-heading">Items</h2>
			<p class="panel__hint">{{ plural(total, 'item') }}</p>
			<div class="panel__actions">
				<button v-if="parents" type="button" class="button button--small" @click="toggleAll"><AdminIcon :name="closed ? 'chevron-down' : 'chevron-up'" />{{ closed ? 'Expand All' : 'Collapse All' }}</button>
				<button type="button" class="button button--small" @click="add()"><AdminIcon name="plus" />Add Item</button>
			</div>
		</header>

		<EmptyState v-if="!nodes.length" icon="menu" heading="No Items Yet" text="Add the first link. Items can be entries, terms, a type's listing, a named route, any address, or plain text that groups others.">
			<template #actions>
				<button type="button" class="button button--primary" @click="add()"><AdminIcon name="plus" />Add Item</button>
			</template>
		</EmptyState>

		<ul v-else class="menu-tree" aria-labelledby="menu-items-heading">
			<li
				v-for="placed in rows"
				:key="placed.node.id"
				class="menu-tree__item"
				:class="{
					'is-selected': current === placed.node.id,
					'is-editing': editing === placed.node.id,
					'is-dragging': dragging === placed.node.id,
					[`is-drop-${drop?.where}`]: drop?.id === placed.node.id
				}"
				:style="{ '--depth': placed.depth, '--drop-depth': drop?.id === placed.node.id ? drop.depth : 0 }"
				:data-id="placed.node.id"
			>
				<div
					class="menu-tree__row"
					:draggable="editing !== placed.node.id"
					@keydown="key($event, placed)"
					@dragstart="dragStart($event, placed)"
					@dragover="dragOver($event, placed)"
					@drop="dropped"
					@dragend="dragEnd"
				>
					<span class="menu-tree__grip" aria-hidden="true"><AdminIcon name="grip-vertical" /></span>
					<span class="menu-tree__indent" />
					<button v-if="placed.node.children.length" type="button" class="menu-tree__twist" tabindex="-1" :aria-expanded="placed.node.open" @click="twist(placed)">
						<AdminIcon name="chevron-right" /><span class="visually-hidden">{{ placed.node.open ? 'Hide' : 'Show' }} the items under {{ name(placed.node) }}</span>
					</button>
					<span v-else class="menu-tree__twist" />
					<button
						type="button"
						class="menu-tree__pick"
						:tabindex="current === placed.node.id ? 0 : -1"
						:aria-current="current === placed.node.id ? 'true' : undefined"
						aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown Alt+ArrowLeft Alt+ArrowRight F2"
						@click="select(placed.node.id)"
						@dblclick="edit(placed.node.id)"
					>
						<span class="menu-tree__kind" :title="kindLabel(placed.node.link?.kind ?? null)"><SiteIcon :name="textOf(placed.node.item.icon, locale) || null" :fallback="linkIcon(placed.node.link)" /><span class="visually-hidden">{{ kindLabel(placed.node.link?.kind ?? null) }}: </span></span>
						<span class="menu-tree__title" :class="{ 'is-plain': placed.node.link === null }">{{ name(placed.node) }}</span>
						<span v-if="empty(placed.node, locale)" class="pill">Empty</span>
						<span v-else-if="linkPill(placed.node.link)" class="pill" :class="`pill--${linkPill(placed.node.link)!.kind}`" :title="placed.node.link?.message ?? undefined">{{ linkPill(placed.node.link)!.label }}</span>
						<span v-if="placed.node.children.length && !placed.node.open" class="menu-tree__under">{{ plural(under(placed.node), 'sub-item') }}</span>
						<span class="visually-hidden">, {{ where(placed.node.id) }}</span>
					</button>
					<span class="menu-tree__controls">
						<button
							v-for="item in MOVES"
							:key="item.move"
							type="button"
							class="menu-tree__button menu-tree__move"
							:data-move="item.move"
							:tabindex="current === placed.node.id ? 0 : -1"
							:title="item.tip"
							:disabled="!canMove(nodes, placed.node.id, item.move)"
							@click="act(placed.node.id, item.move, `.menu-tree__move[data-move='${item.move}']`)"
						>
							<AdminIcon :name="item.icon" /><span class="visually-hidden">{{ item.label }} {{ name(placed.node) }}</span>
						</button>
						<span class="menu-tree__rule" aria-hidden="true" />
						<button
							type="button"
							class="menu-tree__button menu-tree__edit"
							:tabindex="current === placed.node.id || editing === placed.node.id ? 0 : -1"
							:title="editing === placed.node.id ? 'Close' : 'Edit (F2)'"
							:aria-expanded="editing === placed.node.id"
							:aria-controls="`menu-item-${placed.node.id}`"
							@click="edit(placed.node.id)"
						>
							<AdminIcon name="pen-line" /><span class="visually-hidden">Edit {{ name(placed.node) }}</span>
						</button>
						<MenuButton button-class="menu-tree__button" :label="`More actions for ${name(placed.node)}`" floating @open="selected = placed.node.id">
							<template #button><AdminIcon name="ellipsis-vertical" /></template>
							<button type="button" class="menu-item" @click="add('above', placed.node.id)"><AdminIcon name="arrow-up" />Add item above</button>
							<button type="button" class="menu-item" @click="add('below', placed.node.id)"><AdminIcon name="arrow-down" />Add item below</button>
							<div class="menu-divider" />
							<button type="button" class="menu-item" @click="duplicate(placed.node.id)"><AdminIcon name="copy" />Duplicate</button>
							<div class="menu-divider" />
							<button type="button" class="menu-item menu-item--danger" @click="remove(placed.node.id)"><AdminIcon name="trash-2" />Remove</button>
						</MenuButton>
					</span>
				</div>
				<MenuItemFields v-if="editing === placed.node.id" :node="placed.node" :locale="locale" :kinds="kinds" :style="{ '--menu-indent': `calc(${placed.depth} * var(--menu-step))` }" @change="emit('change')" @done="closeFields(placed.node.id)" />
			</li>
		</ul>
		<span class="visually-hidden" aria-live="polite">{{ said }}</span>
	</section>
</template>

<style scoped>
.menu-tree {
	--menu-step: 22px;

	margin: 0;
	padding: 0;
	list-style: none;
}

.menu-tree__item + .menu-tree__item {
	border-top: 1px solid var(--border);
}

.menu-tree__row {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-height: 50px;
	padding: 8px var(--pad-x) 8px calc(var(--pad-x) - 8px);
}

.menu-tree__item:hover > .menu-tree__row {
	background: var(--surface-2);
}

.menu-tree__item.is-selected > .menu-tree__row,
.menu-tree__item.is-editing > .menu-tree__row {
	background: var(--accent-soft);
}

.menu-tree__item:has(> .menu-tree__row .menu-tree__pick:focus-visible) > .menu-tree__row {
	box-shadow: inset 0 0 0 2px var(--accent);
}

.menu-tree__item.is-dragging {
	opacity: .4;
}

/* Where a dragged row lands: a line before or after a row, at the depth
   it would have, or the row itself, to go in under it. */
.menu-tree__item.is-drop-before > .menu-tree__row::before,
.menu-tree__item.is-drop-after > .menu-tree__row::after {
	position: absolute;
	right: var(--pad-x);
	left: calc(var(--pad-x) + 18px + var(--drop-depth) * var(--menu-step));
	height: 2px;
	border-radius: 2px;
	background: var(--accent);
	content: "";
}

.menu-tree__item.is-drop-before > .menu-tree__row::before {
	top: -1px;
}

.menu-tree__item.is-drop-after > .menu-tree__row::after {
	bottom: -1px;
}

.menu-tree__item.is-drop-inside > .menu-tree__row {
	background: var(--accent-soft);
	box-shadow: inset 0 0 0 2px var(--accent);
}

.menu-tree__grip {
	display: grid;
	flex: none;
	place-items: center;
	width: 18px;
	height: 28px;
	color: var(--fg-3);
	cursor: grab;
	opacity: .55;
}

.menu-tree__item:hover .menu-tree__grip,
.menu-tree__item.is-selected .menu-tree__grip {
	opacity: 1;
}

.menu-tree__grip .icon {
	width: 14px;
	height: 14px;
}

.menu-tree__indent {
	flex: none;
	width: calc(var(--depth) * var(--menu-step));
}

.menu-tree__twist {
	display: grid;
	flex: none;
	place-items: center;
	width: 18px;
	height: 18px;
	padding: 0;
	border: 0;
	border-radius: 4px;
	background: none;
	color: var(--fg-3);
}

button.menu-tree__twist:hover {
	background: var(--surface-3);
	color: var(--fg);
}

.menu-tree__twist .icon {
	width: 12px;
	height: 12px;
	transition: transform .12s;
}

.menu-tree__twist[aria-expanded="true"] .icon {
	transform: rotate(90deg);
}

.menu-tree__pick {
	display: flex;
	flex: 1;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: 2px 0;
	border: 0;
	background: none;
	color: inherit;
	text-align: left;
	cursor: pointer;
}

.menu-tree__pick:focus-visible {
	outline: none;
}

.menu-tree__kind {
	display: grid;
	flex: none;
	place-items: center;
	width: 26px;
	height: 26px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	color: var(--fg-2);
}

.menu-tree__item.is-selected .menu-tree__kind {
	border-color: var(--accent-line);
	background: var(--surface);
	color: var(--accent);
}

.menu-tree__kind .icon {
	width: 14px;
	height: 14px;
}

.menu-tree__title {
	overflow: hidden;
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: var(--title-weight);
	letter-spacing: var(--title-track);
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Plain text, often a heading over the items under it. */
.menu-tree__title.is-plain {
	color: var(--fg-2);
	font-family: var(--font-ui);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.menu-tree__pick .pill {
	flex: none;
}

.menu-tree__under {
	flex: none;
	color: var(--fg-3);
	font-size: var(--text-xs);
	white-space: nowrap;
}

.menu-tree__controls {
	display: flex;
	flex: none;
	align-items: center;
	gap: 2px;
}

/* A row's buttons show on its hover or focus, and always on the selected
   row, where there's a pointer to hover with. */
@media (hover: hover) {
	.menu-tree__item:not(.is-selected, .is-editing, :hover, :focus-within) .menu-tree__controls > * {
		visibility: hidden;
	}
}

.menu-tree__rule {
	width: 1px;
	height: 18px;
	margin: 0 4px;
	background: var(--border);
}

.menu-tree__controls :deep(.menu-tree__button) {
	display: grid;
	place-items: center;
	width: 30px;
	height: 30px;
	padding: 0;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
}

.menu-tree__controls :deep(.menu-tree__button:hover) {
	background: var(--surface-3);
	color: var(--fg);
}

.menu-tree__controls :deep(.menu-tree__button[disabled]) {
	opacity: .32;
	pointer-events: none;
}

.menu-tree__controls :deep(.menu-tree__button[aria-expanded="true"]) {
	background: var(--accent);
	color: var(--accent-fg);
}

.menu-tree__controls :deep(.menu-tree__button .icon) {
	width: 15px;
	height: 15px;
}

@media (width <= 640px) {
	.menu-tree {
		--menu-step: 16px;
	}

	.menu-tree__row {
		flex-wrap: wrap;
		row-gap: 4px;
	}

	.menu-tree__controls {
		flex-basis: 100%;
		justify-content: flex-end;
	}

	.menu-tree__item:not(.is-selected, .is-editing) .menu-tree__controls {
		display: none;
	}
}
</style>
