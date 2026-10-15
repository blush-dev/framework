<script setup lang="ts">
/**
 * One level of a set's fields as a tree (the fields sketch, D-766), over
 * the shared `ItemTree`, flat: a group's fields are counted on its row,
 * and its form holds them in a box (`depth` 1), a tree of their own with
 * the same rows, moves, drag, and menus, and **Add Field** at its foot
 * (D-776). Fields nest one level here (D-770), and move between levels
 * only from a row's menu (D-778): a top-level field into a group beside
 * it, and a group's field out of the group (`out`).
 *
 * Each row opens its form (`FieldForm`), which changes the field as it's
 * edited, so closing the row keeps what was typed; Cancel puts back what
 * it was when it opened. Adding a field asks for its control first
 * (`ControlPicker`); inside a group, the new field is selected, not
 * opened (D-777). A field without a key yet says so on its row. Removing
 * a field entries have values for asks first; their files keep the
 * values. A group keeps its fields' values under its key (D-780), so a
 * field moved into or out of one says how many entries' values move with
 * it when the set is saved.
 */

import { computed, ref } from 'vue';
import type { FieldDescription } from '../api';
import { confirmAction } from '../confirm';
import { blankField, controlIcon, controlLabel, holdsFields } from '../controls';
import { copyField, fieldNode, fieldsAccept, isContainer, keysBeside, type FieldNode } from '../field-tree';
import { label as labelOf } from '../fields';
import { t } from '../messages';
import { toast } from '../toast';
import type { ItemTreeApi, ItemTreeWords, Placed } from '../tree';
import AdminIcon from './AdminIcon.vue';
import ControlPicker from './ControlPicker.vue';
import FieldForm from './FieldForm.vue';
import ItemTree from './ItemTree.vue';

const props = withDefaults(defineProps<{
	nodes: FieldNode[];
	depth?: number;
	labelledBy: string;
	idPrefix: string;
	locked?: boolean;
	// How many entries have a value under each saved key, a group's
	// fields under both keys (`details.servings`).
	counts?: Record<string, number>;
	// The saved key of the group this level is inside, for its counts.
	at?: string | null;
	// Keys this level can't take, with where each is used.
	taken?: Record<string, string>;
	// The group this level is inside, for what's said.
	group?: string;
	// Whether the group has nothing beside it (a library field's own
	// fields, D-858), so its fields can't move out of it.
	alone?: boolean;
}>(), { depth: 0, locked: false, counts: () => ({}), at: null, taken: () => ({}), group: '', alone: false });

const emit = defineEmits<{ change: []; out: [node: FieldNode] }>();

const tree    = ref<ItemTreeApi<FieldNode> | null>(null);
const picking = ref<{ flat: boolean; resolve: (key: string | null) => void } | null>(null);

// The top level takes a field into a group above it; a group's fields
// stay where they are (D-770).
const words: ItemTreeWords = {
	under: (count) => t('field_node_tree.under', '{count, plural, one {# field} other {# fields}}', { count }),
	added: (place, name) => place === null
		? t('field_node_tree.added', 'Added a field.')
		: t('field_node_tree.added_at', 'Added a field {place, select, above {above} below {after} other {inside}} {name}.', { place, name }),
	addAbove: t('field_node_tree.add_above', 'Add field above'),
	addBelow: t('field_node_tree.add_below', 'Add field below'),
	removedWith: (name, count) => t('field_node_tree.removed_with', 'Removed {name} and its {count, plural, one {# field} other {# fields}}', { name, count }),
	showUnder: (name) => t('field_node_tree.show_under', 'Show the fields under {name}', { name }),
	hideUnder: (name) => t('field_node_tree.hide_under', 'Hide the fields under {name}', { name })
};

const accepts = computed(() => props.depth === 0 ? fieldsAccept(props.nodes) : (_node: FieldNode, parent: FieldNode | null) => parent === null);

const name = (node: FieldNode): string => node.field.label || (node.field.name ? labelOf(node.field) : t('field_node_tree.untitled', 'Untitled field'));

function pick(flat: boolean): Promise<string | null> {
	return new Promise((resolve) => {
		picking.value = { flat, resolve };
	});
}

function chose(key: string | null): void {
	picking.value?.resolve(key);
	picking.value = null;
}

async function create(parent: FieldNode | null): Promise<FieldNode | null> {
	const key = await pick(props.depth > 0 || parent !== null);

	return key === null ? null : fieldNode(blankField(key));
}

// How many entries have a value for a saved field, inside a group or not.
function countOf(node: FieldNode, group: string | null = props.at): number {
	if (node.saved === null || (props.depth > 0 && group === null)) {
		return 0;
	}

	return props.counts[group === null ? node.saved : `${group}.${node.saved}`] ?? 0;
}


async function confirmRemove(node: FieldNode): Promise<boolean> {
	const count = countOf(node);

	if (count === 0) {
		return true;
	}

	return confirmAction({
		title: t('field_node_tree.remove_title', 'Remove {name}?', { name: name(node) }),
		body: t('field_node_tree.remove_body', '{count, plural, one {# entry has} other {# entries have}} a value for it. Their files keep it, listed as other front matter once the field is gone.', { count }),
		confirm: t('field_node_tree.remove_field', 'Remove Field'),
		danger: true
	});
}

// Keys a field can't take: the others' at its level, and at the top, the
// ones the set's types already use.
function takenFor(placed: Placed<FieldNode>): Record<string, string> {
	const beside = Object.fromEntries(keysBeside(placed.list, placed.node).map((key) => [key, props.group ? t('field_node_tree.taken_group', 'The {group} group', { group: props.group }) : t('field_node_tree.taken_top', 'These fields')]));

	return { ...props.taken, ...beside };
}

function update(placed: Placed<FieldNode>, field: FieldDescription): void {
	placed.node.field = field;

	// A control that no longer holds fields lets go of the ones inside it.
	if (!holdsFields(field.control ?? '') && placed.node.children.length) {
		placed.list.splice(placed.index + 1, 0, ...placed.node.children);
		placed.node.children = [];
	}

	emit('change');
}

// A field moved into a group goes at the end of its fields.
function moveInto(placed: Placed<FieldNode>, group: FieldNode): void {
	placed.list.splice(placed.index, 1);
	group.children.push(placed.node);
	emit('change');
	const count = countOf(placed.node);
	const names = { field: name(placed.node), group: name(group), count };

	toast(count === 0
		? t('field_node_tree.moved_into', 'Moved {field} into {group}', names)
		: t('field_node_tree.moved_into_values', 'Moved {field} into {group}. Saving moves its values in {count, plural, one {# entry} other {# entries}} into it too', names));
}

// A group's field moved out goes after the group.
function moveOut(placed: Placed<FieldNode>, node: FieldNode): void {
	const children = placed.node.children;

	children.splice(children.indexOf(node), 1);
	placed.list.splice(placed.index + 1, 0, node);
	emit('change');
	const count = countOf(node, placed.node.saved);
	const names = { field: name(node), group: name(placed.node), count };

	toast(count === 0
		? t('field_node_tree.moved_out', 'Moved {field} out of {group}', names)
		: t('field_node_tree.moved_out_values', 'Moved {field} out of {group}. Saving moves its values in {count, plural, one {# entry} other {# entries}} out of it too', names));
}

async function add(): Promise<void> {
	await tree.value?.add('below', null);
}

defineExpose({ add });
</script>

<template>
	<div class="field-nodes" :class="{ 'nest field-nodes--inside': depth > 0 }">
		<p v-if="depth > 0 && !nodes.length" class="nest__none">{{ t('field_node_tree.no_fields', 'No fields yet.') }}</p>
		<ItemTree
			v-show="nodes.length"
			ref="tree"
			:nodes="nodes"
			:name="name"
			:words="words"
			:labelled-by="labelledBy"
			:blank="() => fieldNode(blankField('text'))"
			:copy="copyField"
			:accepts="accepts"
			:locked="locked"
			:create="create"
			:confirm-remove="confirmRemove"
			:focus-on-edit="(node) => `${idPrefix}${node.id}-label`"
			flat
			:open-added="depth === 0"
			@change="emit('change')"
		>
			<template #pick="{ placed }">
				<span class="item-tree__kind" :title="controlLabel(placed.node.field.control ?? 'text')"><AdminIcon :name="controlIcon(placed.node.field.control ?? 'text')" /><span class="visually-hidden">{{ controlLabel(placed.node.field.control ?? 'text') }}: </span></span>
				<span class="item-tree__title">{{ name(placed.node) }}</span>
				<span v-if="!placed.node.field.name" class="pill pill--warn">{{ t('field_node_tree.no_key', 'No Key') }}</span>
				<span v-else-if="placed.node.field.required" class="pill">{{ t('field_node_tree.required', 'Required') }}</span>
			</template>
			<template #menu="{ placed }">
				<button v-if="depth === 0 && isContainer(placed.node)" type="button" class="menu-item" @click="tree?.add('inside', placed.node.id)"><AdminIcon name="corner-down-right" />{{ t('field_node_tree.add_inside', 'Add field inside') }}</button>
				<template v-if="depth === 0 && !isContainer(placed.node)">
					<button v-for="group in nodes.filter((node) => isContainer(node))" :key="group.id" type="button" class="menu-item" @click="moveInto(placed, group)"><AdminIcon name="indent-increase" />{{ t('field_node_tree.move_into', 'Move into {group}', { group: name(group) }) }}</button>
				</template>
				<button v-if="depth > 0 && !alone" type="button" class="menu-item" @click="emit('out', placed.node)"><AdminIcon name="indent-decrease" />{{ t('field_node_tree.move_out', 'Move out of {group}', { group: group || t('field_node_tree.the_group', 'the group') }) }}</button>
			</template>
			<template #fields="{ placed, done: close }">
				<FieldForm
					:field="placed.node.field"
					:saved="placed.node.saved"
					:taken="takenFor(placed)"
					:count="placed.node.saved === null || depth > 0 ? 0 : (counts[placed.node.saved] ?? 0)"
					:inside="placed.node.children.map((child) => ({ name: child.field.name, label: name(child) }))"
					:nested="depth > 0"
					:locked="locked"
					:id-prefix="`${idPrefix}${placed.node.id}-`"
					@update="update(placed, $event)"
					@done="close"
					@remove="tree?.remove(placed.node.id)"
				>
					<template v-if="depth === 0 && isContainer(placed.node)" #inside>
						<FieldNodeTree
							:nodes="placed.node.children"
							:depth="depth + 1"
							:labelled-by="`${idPrefix}${placed.node.id}-inside-label`"
							:id-prefix="`${idPrefix}${placed.node.id}-`"
							:locked="locked"
							:group="name(placed.node)"
							:counts="counts"
							:at="placed.node.saved"
							@change="emit('change')"
							@out="moveOut(placed, $event)"
						/>
					</template>
				</FieldForm>
			</template>
		</ItemTree>
		<div v-if="depth > 0 && !locked" class="nest__foot">
			<button type="button" class="button button--ghost button--small" @click="add"><AdminIcon name="plus" />{{ t('field_node_tree.add_field', 'Add Field') }}</button>
		</div>

		<ControlPicker v-if="picking" :flat="picking.flat" @choose="chose" @close="chose(null)" />
	</div>
</template>

<style scoped>
/* Inside a group's form, rows are a box's rows: closer to its edges. */
.field-nodes--inside :deep(.item-tree__row) {
	min-height: 46px;
	padding: 6px 8px 6px 6px;
}

.field-nodes--inside :deep(.field-form) {
	padding: var(--s-3) var(--s-4) var(--s-4);
}

.field-nodes--inside > :deep(.item-tree) {
	border-bottom: 0;
}
</style>
