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
 * values.
 */

import { computed, ref } from 'vue';
import type { FieldDescription } from '../api';
import { confirmAction } from '../confirm';
import { blankField, controlIcon, controlLabel, holdsFields } from '../controls';
import { copyField, fieldNode, fieldsAccept, isContainer, keysBeside, type FieldNode } from '../field-tree';
import { label as labelOf } from '../fields';
import { plural } from '../format';
import { toast } from '../toast';
import type { ItemTreeApi, Placed } from '../tree';
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
	// How many entries have a value under each saved key (the top level's).
	counts?: Record<string, number>;
	// Keys this level can't take, with where each is used.
	taken?: Record<string, string>;
	// The group this level is inside, for what's said.
	group?: string;
}>(), { depth: 0, locked: false, counts: () => ({}), taken: () => ({}), group: '' });

const emit = defineEmits<{ change: []; out: [node: FieldNode] }>();

const tree    = ref<ItemTreeApi<FieldNode> | null>(null);
const picking = ref<{ flat: boolean; resolve: (key: string | null) => void } | null>(null);

// The top level takes a field into a group above it; a group's fields
// stay where they are (D-770).
const accepts = computed(() => props.depth === 0 ? fieldsAccept(props.nodes) : (_node: FieldNode, parent: FieldNode | null) => parent === null);

const name = (node: FieldNode): string => node.field.label || (node.field.name ? labelOf(node.field) : 'Untitled field');

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

async function confirmRemove(node: FieldNode): Promise<boolean> {
	const count = node.saved === null || props.depth > 0 ? 0 : (props.counts[node.saved] ?? 0);

	if (count === 0) {
		return true;
	}

	return confirmAction({
		title: `Remove ${name(node)}?`,
		body: `${plural(count, 'entry has', 'entries have')} a value for it. Their files keep it, listed as other front matter once the field is gone.`,
		confirm: 'Remove Field',
		danger: true
	});
}

// Keys a field can't take: the others' at its level, and at the top, the
// ones the set's types already use.
function takenFor(placed: Placed<FieldNode>): Record<string, string> {
	const beside = Object.fromEntries(keysBeside(placed.list, placed.node).map((key) => [key, props.group ? `The ${props.group} group` : 'This set']));

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
	toast(`Moved ${name(placed.node)} into ${name(group)}`);
}

// A group's field moved out goes after the group.
function moveOut(placed: Placed<FieldNode>, node: FieldNode): void {
	const children = placed.node.children;

	children.splice(children.indexOf(node), 1);
	placed.list.splice(placed.index + 1, 0, node);
	emit('change');
	toast(`Moved ${name(node)} out of ${name(placed.node)}`);
}

async function add(): Promise<void> {
	await tree.value?.add('below', null);
}

defineExpose({ add });
</script>

<template>
	<div class="field-nodes" :class="{ 'nest field-nodes--inside': depth > 0 }">
		<p v-if="depth > 0 && !nodes.length" class="nest__none">No fields yet.</p>
		<ItemTree
			v-show="nodes.length"
			ref="tree"
			:nodes="nodes"
			:name="name"
			under="field"
			noun="field"
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
				<span v-if="!placed.node.field.name" class="pill pill--warn">No Key</span>
				<span v-else-if="placed.node.field.required" class="pill">Required</span>
			</template>
			<template #menu="{ placed }">
				<button v-if="depth === 0 && isContainer(placed.node)" type="button" class="menu-item" @click="tree?.add('inside', placed.node.id)"><AdminIcon name="corner-down-right" />Add field inside</button>
				<template v-if="depth === 0 && !isContainer(placed.node)">
					<button v-for="group in nodes.filter((node) => isContainer(node))" :key="group.id" type="button" class="menu-item" @click="moveInto(placed, group)"><AdminIcon name="indent-increase" />Move into {{ name(group) }}</button>
				</template>
				<button v-if="depth > 0" type="button" class="menu-item" @click="emit('out', placed.node)"><AdminIcon name="indent-decrease" />Move out of {{ group || 'the group' }}</button>
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
							@change="emit('change')"
							@out="moveOut(placed, $event)"
						/>
					</template>
				</FieldForm>
			</template>
		</ItemTree>
		<div v-if="depth > 0 && !locked" class="nest__foot">
			<button type="button" class="button button--ghost button--small" @click="add"><AdminIcon name="plus" />Add Field</button>
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
