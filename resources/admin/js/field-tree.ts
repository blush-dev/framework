/**
 * A field set's fields as a tree to edit (the fields sketch, D-772's
 * stage 3): one node a field, the fields of a group or a repeating
 * group under it. A group's own definition is kept without its fields
 * (a repeating group's without its item's), which go back in from the
 * nodes when the set is saved. Fields nest one level here (D-770): a
 * group's fields can't be groups; a set written by hand may nest deeper,
 * and its deeper fields are kept as they are.
 */

import type { FieldDescription } from './api';
import { holdsFields } from './controls';
import { t } from './messages';
import type { Accepts, TreeNode } from './tree';

export interface FieldNode extends TreeNode<FieldNode> {
	field: FieldDescription;
	// The key it was saved with, or `null` for a field not saved yet.
	saved: string | null;
}

let next = 0;

function nodeId(): string {
	return `field-${++next}`;
}

/**
 * Whether a node holds other fields: a group, or a repeating group of
 * groups (one of plain values holds a value an item, D-777).
 */
export function isContainer(node: FieldNode): boolean {
	return holdsFields(node.field.control ?? '') && !(node.field.control === 'repeater' && node.field.item !== undefined && node.field.item.type !== 'object');
}

/**
 * Nodes for a set's definitions (as forms take them, each with its
 * control).
 */
export function toFieldNodes(definitions: FieldDescription[], depth = 0): FieldNode[] {
	return definitions.map((definition) => {
		const control = definition.control ?? '';
		const inside  = control === 'group' ? definition.fields : (control === 'repeater' ? definition.item?.fields : undefined);
		const field   = { ...definition };

		// Deeper than a level, a group's fields stay in its definition.
		if (depth === 0 && control === 'group') {
			delete field.fields;
		} else if (depth === 0 && control === 'repeater' && field.item !== undefined) {
			field.item = { ...field.item };
			delete field.item.fields;
		}

		return { id: nodeId(), field, saved: definition.name, children: depth === 0 ? toFieldNodes(inside ?? [], 1) : [], open: true };
	});
}

/**
 * The definitions to save, with each group's fields back in it.
 */
export function toDefinitions(nodes: FieldNode[]): FieldDescription[] {
	return nodes.map((node) => {
		const field = clean(node.field);
		const kids  = node.children.map((child) => clean(child.field));

		if (field.control === 'group' && (node.children.length > 0 || field.fields === undefined)) {
			return { ...field, fields: node.children.length ? toDefinitions(node.children) : kids };
		}

		if (field.control === 'repeater' && isContainer(node) && (node.children.length > 0 || field.item?.fields === undefined)) {
			return { ...field, item: { ...(field.item ?? { name: '' }), type: 'object', fields: toDefinitions(node.children) } };
		}

		return field;
	});
}

/**
 * A definition as it's saved: without what forms add (option labels as
 * `choices`), and without empty settings.
 */
function clean(field: FieldDescription): FieldDescription {
	const out: Record<string, unknown> = {};

	for (const [key, value] of Object.entries(field)) {
		if (key === 'choices' || key === 'relation' || value === undefined || value === '' || (Array.isArray(value) && value.length === 0 && key === 'aliases')) {
			continue;
		}

		out[key] = key === 'item' && typeof value === 'object' && value !== null ? clean(value as FieldDescription) : value;
	}

	return out as unknown as FieldDescription;
}

/**
 * A new node for a field.
 */
export function fieldNode(field: FieldDescription): FieldNode {
	return { id: nodeId(), field, saved: null, children: [], open: true };
}

/**
 * A copy of a node and the fields under it, not saved.
 */
export function copyField(node: FieldNode): FieldNode {
	return { id: nodeId(), field: structuredClone({ ...node.field, name: `${node.field.name}_copy`, label: node.field.label ? t('field_tree.copy_label', '{label} copy', { label: node.field.label }) : undefined }), saved: null, children: node.children.map(copyField), open: node.open };
}

/**
 * The fields' rule for what goes where (D-770): anything at the top; a
 * field that isn't a group inside a group at the top.
 */
export function fieldsAccept(nodes: FieldNode[]): Accepts<FieldNode> {
	return (node, parent) => parent === null || (isContainer(parent) && !isContainer(node) && nodes.includes(parent));
}

/**
 * Every key used by the fields at one level, but a node's own.
 */
export function keysBeside(list: FieldNode[], except: FieldNode): string[] {
	return list.filter((node) => node !== except).map((node) => node.field.name).filter((name) => name !== '');
}
