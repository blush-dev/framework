<script setup lang="ts" generic="T extends TreeNode<T>">
/**
 * A tree of items that move, edited in place (D-509): a menu's items
 * (`MenuTree`, from the menus sketch) and a field set's fields
 * (`FieldTree`, from the fields sketch). Each row shows what the screen
 * puts in it (the `pick` slot: a kind tile, a name, pills) and, when it's
 * closed, how many are under it; its own buttons move it (up, down, out
 * beside its parent, in under the item above), open its fields under it
 * (the `fields` slot), and hold more (add above or below, the `menu`
 * slot's items, duplicate, remove). What may go under what is the
 * screen's rule (`accepts`).
 *
 * The keys, on a row: ↑ and ↓ go to the next row shown, Home and End to
 * the ends, → opens an item or goes to its first child, ← closes it or
 * goes to its parent, F2 opens its fields, and ⌥ with an arrow moves it,
 * as the editor moves elements (D-314). Rows can be dragged too: onto the
 * top or bottom of a row to go before or after it, onto its middle to go
 * in as its first child. Only the selected row's buttons are in the tab
 * order. Removing and duplicating offer Undo. What moved, and where to,
 * is read out. A `locked` tree only opens rows, to read them. A `flat`
 * tree shows only its top level, with no room for a twist: a node's
 * children are counted on its row and edited elsewhere (a field group's
 * own tree, in its form), and nodes only move up and down, by button,
 * key, or drag, so nothing moves out of sight (D-778).
 */

import { computed, nextTick, ref } from 'vue';
import { toast } from '../toast';
import { t } from '../messages';
import { canDrop, canMove, find, flatten, moveNode, moveTo, within, type Accepts, type ItemTreeWords, type Move, type Placed, type TreeNode, type Where } from '../tree';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';

const props = withDefaults(defineProps<{
	nodes: T[];
	// A node's name, for buttons and what's read out.
	name: (node: T) => string;
	// What the tree says about its nodes, in the screen's words.
	words: ItemTreeWords;
	// What labels the tree.
	labelledBy: string;
	// A node to add, and a copy of one, with new page ids.
	blank: () => T;
	copy: (node: T) => T;
	accepts?: Accepts<T>;
	locked?: boolean;
	// What to focus when a row's fields open, by the node.
	focusOnEdit?: (node: T) => string | null;
	// Asked before a row is removed; `false` keeps it.
	confirmRemove?: (node: T) => Promise<boolean>;
	// Makes the node to add, given the node it'll be under (`null` at the
	// top), when the screen asks first (a field's control); `null` adds
	// nothing.
	create?: (parent: T | null) => Promise<T | null>;
	flat?: boolean;
	// Whether a node added opens its fields, or is only selected.
	openAdded?: boolean;
}>(), { openAdded: true, accepts: () => true, locked: false, focusOnEdit: () => null, confirmRemove: () => Promise.resolve(true) });

const emit = defineEmits<{ change: [] }>();

defineSlots<{
	pick(props: { placed: Placed<T>; selected: boolean }): unknown;
	fields(props: { placed: Placed<T>; done: () => void }): unknown;
	menu(props: { placed: Placed<T> }): unknown;
}>();

const root     = ref<HTMLElement | null>(null);
const selected = ref<string | null>(null);
const editing  = ref<string | null>(null);
const dragging = ref<string | null>(null);
const drop     = ref<{ id: string; where: Where; depth: number } | null>(null);
const said     = ref('');

const rows    = computed(() => props.flat ? props.nodes.map((node, index) => ({ node, depth: 0, parent: null, list: props.nodes, index })) : flatten(props.nodes, true));
const total   = computed(() => flatten(props.nodes).length);
const parents = computed(() => !props.flat && flatten(props.nodes).some((placed) => placed.node.children.length > 0));
const closed  = computed(() => !props.flat && flatten(props.nodes).some((placed) => placed.node.children.length > 0 && !placed.node.open));
const current = computed(() => selected.value !== null && rows.value.some((placed) => placed.node.id === selected.value) ? selected.value : rows.value[0]?.node.id ?? null);

const ALL_MOVES: { move: Move; icon: 'arrow-up' | 'arrow-down' | 'indent-decrease' | 'indent-increase'; tip: string; label: (name: string) => string; said: string }[] = [
	{ move: 'up', icon: 'arrow-up', tip: t('item_tree.move_up_tip', 'Move Up (⌥↑)'), label: (name) => t('item_tree.move_up', 'Move up {name}', { name }), said: t('item_tree.moved_up', 'Moved up') },
	{ move: 'down', icon: 'arrow-down', tip: t('item_tree.move_down_tip', 'Move Down (⌥↓)'), label: (name) => t('item_tree.move_down', 'Move down {name}', { name }), said: t('item_tree.moved_down', 'Moved down') },
	{ move: 'out', icon: 'indent-decrease', tip: t('item_tree.outdent_tip', 'Outdent (⌥←)'), label: (name) => t('item_tree.outdent', 'Outdent {name}', { name }), said: t('item_tree.outdented', 'Outdented') },
	{ move: 'in', icon: 'indent-increase', tip: t('item_tree.indent_tip', 'Indent (⌥→)'), label: (name) => t('item_tree.indent', 'Indent {name}', { name }), said: t('item_tree.indented', 'Indented') }
];

// A flat tree moves only up and down.
const MOVES = computed(() => props.flat ? ALL_MOVES.filter((item) => item.move === 'up' || item.move === 'down') : ALL_MOVES);

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

	const at = { level: placed.depth + 1, position: placed.index + 1, count: placed.list.length };

	return placed.parent
		? t('item_tree.where_inside', 'level {level}, {position} of {count} inside {parent}', { ...at, parent: props.name(placed.parent) })
		: t('item_tree.where_top', 'level {level}, {position} of {count} at the top', at);
}

function underCount(node: T): number {
	return flatten(node.children).length;
}

function movable(id: string, move: Move): boolean {
	return !props.locked && (!props.flat || move === 'up' || move === 'down') && canMove(props.nodes, id, move, props.accepts);
}

async function focus(id: string, selector = '.item-tree__pick'): Promise<void> {
	await nextTick();

	const row = root.value?.querySelector<HTMLElement>(`[data-id="${id}"] > .item-tree__row`);
	const to  = row?.querySelector<HTMLElement>(`${selector}:not([disabled])`) ?? row?.querySelector<HTMLElement>('.item-tree__pick');

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
	if (props.locked || !moveNode(props.nodes, id, move, props.accepts)) {
		say(t('item_tree.cannot_move', 'Can\'t move it that way from here.'));

		return;
	}

	emit('change');
	selected.value = id;
	say(t('item_tree.moved_where', '{said}. {name} is {where}.', { said: ALL_MOVES.find((item) => item.move === move)!.said, name: props.name(find(props.nodes, id)!.node), where: where(id) }));
	void focus(id, then ?? '.item-tree__pick');
}

function twist(placed: Placed<T>): void {
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
		void focus(id, '.item-tree__edit');

		return;
	}

	const node = find(props.nodes, id)?.node;

	await nextTick();

	const first = node === undefined ? null : props.focusOnEdit(node);

	if (first !== null) {
		document.getElementById(first)?.focus();
	}

	if (node !== undefined) {
		say(t('item_tree.editing', 'Editing {name}', { name: props.name(node) }));
	}
}

function closeFields(id: string): void {
	editing.value = null;
	void focus(id, '.item-tree__edit');
}

/**
 * Adds a node: after the selected one, or above, below, or inside a row
 * (its menu's), with its fields open (or only selected, when the
 * screen says not to open them, `openAdded`). A screen that asks first (a
 * field's control) gives the node to add.
 */
async function add(place: 'above' | 'below' | 'inside' = 'below', id: string | null = current.value, given?: T): Promise<void> {
	const placed = id === null ? undefined : find(props.nodes, id);
	const parent = placed === undefined ? null : (place === 'inside' ? placed.node : placed.parent);
	const node   = given ?? (props.create === undefined ? props.blank() : await props.create(parent));

	if (node === null) {
		return;
	}

	if (placed === undefined) {
		props.nodes.push(node);
	} else if (place === 'inside') {
		placed.node.children.push(node);
		placed.node.open = true;
	} else {
		placed.list.splice(placed.index + (place === 'above' ? 0 : 1), 0, node);
	}

	selected.value = node.id;
	editing.value  = props.openAdded ? node.id : null;
	emit('change');

	if (!props.openAdded) {
		void focus(node.id);
	}

	say(placed ? props.words.added(place, props.name(placed.node)) : props.words.added(null, ''));

	await nextTick();

	const first = props.openAdded ? props.focusOnEdit(node) : null;

	if (first !== null) {
		document.getElementById(first)?.focus();
	}

	root.value?.querySelector(`[data-id="${node.id}"]`)?.scrollIntoView({ block: 'nearest' });
}

async function remove(id: string): Promise<void> {
	const placed = find(props.nodes, id);

	if (placed === undefined || !await props.confirmRemove(placed.node)) {
		return;
	}

	const { node, list, index, parent } = placed;
	const count = underCount(node);

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

	toast(count ? props.words.removedWith(props.name(node), count) : t('item_tree.removed', 'Removed {name}', { name: props.name(node) }), {
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

	const copy = props.copy(placed.node);

	placed.list.splice(placed.index + 1, 0, copy);
	emit('change');
	select(copy.id);

	toast(t('item_tree.duplicated', 'Duplicated {name}', { name: props.name(placed.node) }), {
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

function key(event: KeyboardEvent, placed: Placed<T>): void {
	const target = event.target as HTMLElement;
	const move   = ARROWS[event.key];

	if (event.altKey && move !== undefined) {
		event.preventDefault();

		if (!movable(placed.node.id, move)) {
			say(t('item_tree.cannot_move', 'Can\'t move it that way from here.'));

			return;
		}

		act(placed.node.id, move, target.matches('.item-tree__move') ? `.item-tree__move[data-move="${move}"]` : undefined);

		return;
	}

	if (!target.matches('.item-tree__pick')) {
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
	} else if (event.key === 'ArrowRight' && placed.node.children.length && !props.flat) {
		event.preventDefault();

		if (placed.node.open) {
			go(index + 1);
		} else {
			placed.node.open = true;
		}
	} else if (event.key === 'ArrowLeft') {
		event.preventDefault();

		if (placed.node.children.length && placed.node.open && !props.flat) {
			placed.node.open = false;
		} else if (placed.parent) {
			select(placed.parent.id);
		}
	} else if (event.key === 'F2') {
		event.preventDefault();
		void edit(placed.node.id);
	}
}

function dragStart(event: DragEvent, placed: Placed<T>): void {
	if (props.locked || (event.target as Element).closest('input, textarea, .item-tree__fields')) {
		event.preventDefault();

		return;
	}

	dragging.value = placed.node.id;

	if (event.dataTransfer) {
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData('text/plain', placed.node.id);
	}
}

function dragOver(event: DragEvent, placed: Placed<T>): void {
	const id = dragging.value;

	if (id === null) {
		return;
	}

	const box    = (event.currentTarget as HTMLElement).getBoundingClientRect();
	const y      = (event.clientY - box.top) / box.height;
	const inside = !props.flat && canDrop(props.nodes, id, placed.node.id, 'inside', props.accepts);
	const at     = y < .3 ? 'before' : y > .7 ? 'after' : (inside ? 'inside' : (y < .5 ? 'before' : 'after'));

	if (!canDrop(props.nodes, id, placed.node.id, at, props.accepts)) {
		drop.value = null;

		return;
	}

	event.preventDefault();
	drop.value = { id: placed.node.id, where: at, depth: placed.depth + (at === 'inside' ? 1 : 0) };
}

function dropped(event: DragEvent): void {
	event.preventDefault();

	const id = dragging.value;
	const at = drop.value;

	dragging.value = null;
	drop.value     = null;

	if (id === null || at === null || !moveTo(props.nodes, id, at.id, at.where, props.accepts)) {
		return;
	}

	emit('change');
	selected.value = id;
	say(t('item_tree.moved', 'Moved {name}, {where}.', { name: props.name(find(props.nodes, id)!.node), where: where(id) }));
	void focus(id);
}

function dragEnd(): void {
	dragging.value = null;
	drop.value     = null;
}

defineExpose({ add, remove, toggleAll, edit, select, closed, parents, total });
</script>

<template>
	<ul ref="root" class="item-tree" :aria-labelledby="labelledBy">
		<li
			v-for="placed in rows"
			:key="placed.node.id"
			class="item-tree__item"
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
				class="item-tree__row"
				:draggable="!locked && editing !== placed.node.id"
				@keydown="key($event, placed)"
				@dragstart="dragStart($event, placed)"
				@dragover="dragOver($event, placed)"
				@drop="dropped"
				@dragend="dragEnd"
			>
				<span class="item-tree__grip" aria-hidden="true"><AdminIcon v-if="!locked" name="grip-vertical" /></span>
				<span class="item-tree__indent" />
				<button v-if="placed.node.children.length && !flat" type="button" class="item-tree__twist" tabindex="-1" :aria-expanded="placed.node.open" @click="twist(placed)">
					<AdminIcon name="chevron-right" /><span class="visually-hidden">{{ placed.node.open ? words.hideUnder(name(placed.node)) : words.showUnder(name(placed.node)) }}</span>
				</button>
				<span v-else-if="!flat" class="item-tree__twist" />
				<button
					type="button"
					class="item-tree__pick"
					:tabindex="current === placed.node.id ? 0 : -1"
					:aria-current="current === placed.node.id ? 'true' : undefined"
					aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown Alt+ArrowLeft Alt+ArrowRight F2"
					@click="select(placed.node.id)"
					@dblclick="edit(placed.node.id)"
				>
					<slot name="pick" :placed="placed" :selected="current === placed.node.id" />
					<span v-if="placed.node.children.length && (flat || !placed.node.open)" class="item-tree__under">{{ words.under(underCount(placed.node)) }}</span>
					<span class="visually-hidden">, {{ where(placed.node.id) }}</span>
				</button>
				<span class="item-tree__controls">
					<template v-if="!locked">
						<button
							v-for="item in MOVES"
							:key="item.move"
							type="button"
							class="item-tree__button item-tree__move"
							:data-move="item.move"
							:tabindex="current === placed.node.id ? 0 : -1"
							:title="item.tip"
							:disabled="!movable(placed.node.id, item.move)"
							@click="act(placed.node.id, item.move, `.item-tree__move[data-move='${item.move}']`)"
						>
							<AdminIcon :name="item.icon" /><span class="visually-hidden">{{ item.label(name(placed.node)) }}</span>
						</button>
						<span class="item-tree__rule" aria-hidden="true" />
					</template>
					<button
						type="button"
						class="item-tree__button item-tree__edit"
						:tabindex="current === placed.node.id || editing === placed.node.id ? 0 : -1"
						:title="editing === placed.node.id ? t('common.close', 'Close') : (locked ? t('item_tree.open_f2', 'Open (F2)') : t('item_tree.edit_f2', 'Edit (F2)'))"
						:aria-expanded="editing === placed.node.id"
						:aria-controls="`item-tree-${placed.node.id}`"
						@click="edit(placed.node.id)"
					>
						<AdminIcon :name="locked ? 'eye' : 'pen-line'" /><span class="visually-hidden">{{ locked ? t('item_tree.open_name', 'Open {name}', { name: name(placed.node) }) : t('item_tree.edit_name', 'Edit {name}', { name: name(placed.node) }) }}</span>
					</button>
					<MenuButton v-if="!locked" button-class="item-tree__button" :label="t('item_tree.more_actions', 'More actions for {name}', { name: name(placed.node) })" floating @open="selected = placed.node.id">
						<template #button><AdminIcon name="ellipsis-vertical" /></template>
						<button type="button" class="menu-item" @click="add('above', placed.node.id)"><AdminIcon name="arrow-up" />{{ words.addAbove }}</button>
						<button type="button" class="menu-item" @click="add('below', placed.node.id)"><AdminIcon name="arrow-down" />{{ words.addBelow }}</button>
						<slot name="menu" :placed="placed" />
						<div class="menu-divider" />
						<button type="button" class="menu-item" @click="duplicate(placed.node.id)"><AdminIcon name="copy" />{{ t('item_tree.duplicate', 'Duplicate') }}</button>
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" @click="remove(placed.node.id)"><AdminIcon name="trash-2" />{{ t('common.remove', 'Remove') }}</button>
					</MenuButton>
				</span>
			</div>
			<div v-if="editing === placed.node.id" :id="`item-tree-${placed.node.id}`" class="item-tree__fields" :style="{ '--tree-indent': `calc(${placed.depth} * var(--tree-step))` }">
				<slot name="fields" :placed="placed" :done="() => closeFields(placed.node.id)" />
			</div>
		</li>
	</ul>
	<span class="visually-hidden" aria-live="polite">{{ said }}</span>
</template>

<style scoped>
.item-tree {
	--tree-step: 22px;

	margin: 0;
	padding: 0;
	list-style: none;
}

.item-tree__item + .item-tree__item {
	border-top: 1px solid var(--border-color);
}

.item-tree__row {
	position: relative;
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-height: 50px;
	padding: 8px var(--padding-inline) 8px calc(var(--padding-inline) - 8px);
}

.item-tree__item:hover > .item-tree__row {
	background: var(--color-surface-subtle);
}

.item-tree__item.is-selected > .item-tree__row,
.item-tree__item.is-editing > .item-tree__row {
	background: var(--color-accent-subtle);
}

.item-tree__item:has(> .item-tree__row .item-tree__pick:focus-visible) > .item-tree__row {
	box-shadow: inset 0 0 0 2px var(--color-accent);
}

.item-tree__item.is-dragging {
	opacity: .4;
}

/* Where a dragged row lands: a line before or after a row, at the depth
   it would have, or the row itself, to go in under it. */
.item-tree__item.is-drop-before > .item-tree__row::before,
.item-tree__item.is-drop-after > .item-tree__row::after {
	position: absolute;
	right: var(--padding-inline);
	left: calc(var(--padding-inline) + 18px + var(--drop-depth) * var(--tree-step));
	height: 2px;
	border-radius: 2px;
	background: var(--color-accent);
	content: "";
}

.item-tree__item.is-drop-before > .item-tree__row::before {
	top: -1px;
}

.item-tree__item.is-drop-after > .item-tree__row::after {
	bottom: -1px;
}

.item-tree__item.is-drop-inside > .item-tree__row {
	background: var(--color-accent-subtle);
	box-shadow: inset 0 0 0 2px var(--color-accent);
}

.item-tree__grip {
	display: grid;
	flex: none;
	place-items: center;
	width: 18px;
	height: 28px;
	color: var(--color-text-muted);
	cursor: grab;
	opacity: .55;
}

.item-tree__item:hover .item-tree__grip,
.item-tree__item.is-selected .item-tree__grip {
	opacity: 1;
}

.item-tree__grip .icon {
	width: 14px;
	height: 14px;
}

.item-tree__indent {
	flex: none;
	width: calc(var(--depth) * var(--tree-step));
}

.item-tree__twist {
	display: grid;
	flex: none;
	place-items: center;
	width: 18px;
	height: 18px;
	padding: 0;
	border: 0;
	border-radius: 4px;
	background: none;
	color: var(--color-text-muted);
}

button.item-tree__twist:hover {
	background: var(--color-surface-strong);
	color: var(--color-text);
}

.item-tree__twist .icon {
	width: 12px;
	height: 12px;
	transition: transform .12s;
}

.item-tree__twist[aria-expanded="true"] .icon {
	transform: rotate(90deg);
}

.item-tree__pick {
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

.item-tree__pick:focus-visible {
	outline: none;
}

.item-tree__under {
	flex: none;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	white-space: nowrap;
}

.item-tree__controls {
	display: flex;
	flex: none;
	align-items: center;
	gap: 2px;
}

/* A row's buttons show on its hover or focus, and always on the selected
   row, where there's a pointer to hover with. */
@media (hover: hover) {
	.item-tree__item:not(.is-selected, .is-editing, :hover, :focus-within) .item-tree__controls > * {
		visibility: hidden;
	}
}

.item-tree__rule {
	width: 1px;
	height: 18px;
	margin: 0 4px;
	background: var(--border-color);
}

.item-tree__controls :deep(.item-tree__button) {
	display: grid;
	place-items: center;
	width: 30px;
	height: 30px;
	padding: 0;
	border: 0;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-text-secondary);
}

.item-tree__controls :deep(.item-tree__button:hover) {
	background: var(--color-surface-strong);
	color: var(--color-text);
}

.item-tree__controls :deep(.item-tree__button[disabled]) {
	opacity: .32;
	pointer-events: none;
}

.item-tree__controls :deep(.item-tree__button[aria-expanded="true"]) {
	background: var(--color-accent);
	color: var(--color-accent-text);
}

.item-tree__controls :deep(.item-tree__button .icon) {
	width: 15px;
	height: 15px;
}

@media (width <= 640px) {
	.item-tree {
		--tree-step: 16px;
	}

	.item-tree__row {
		flex-wrap: wrap;
		row-gap: 4px;
	}

	.item-tree__controls {
		flex-basis: 100%;
		justify-content: flex-end;
	}

	.item-tree__item:not(.is-selected, .is-editing) .item-tree__controls {
		display: none;
	}
}
</style>
