/**
 * A type's `fields` as a tree to edit (D-858, D-863): the library fields it
 * carries, each a node with what the type changes (its label, help, and
 * whether it's required), and its sections, each a node with its fields
 * under it, one level deep. Sections are only presentation: removing one keeps
 * its fields.
 */

import type { TypeFieldItem, TypeFieldSection, PlacedField } from './api';
import type { Accepts, TreeNode } from './tree';

export interface TypeFieldNode extends TreeNode<TypeFieldNode> {
	// A placed field's library name, or `null` for a section.
	field: string | null;
	// What the type changes about a field, or a section's heading and
	// help (`label`, `description`).
	label?: string;
	description?: string;
	required?: boolean;
}

let next = 0;

function nodeId(): string {
	return `type-field-${++next}`;
}

/**
 * A node for a placed field.
 */
export function fieldNode(placed: PlacedField): TypeFieldNode {
	const item = typeof placed === 'string' ? { field: placed } : placed;

	return { id: nodeId(), field: item.field, label: item.label, description: item.description, required: item.required, children: [], open: true };
}

/**
 * A node for a section, with its fields under it.
 */
export function sectionNode(section: TypeFieldSection): TypeFieldNode {
	return { id: nodeId(), field: null, label: section.section, description: section.description, children: section.fields.map(fieldNode), open: true };
}

/**
 * The nodes for a type's fields.
 */
export function toTypeFieldNodes(items: TypeFieldItem[]): TypeFieldNode[] {
	return items.map((item) => typeof item === 'object' && 'section' in item ? sectionNode(item) : fieldNode(item));
}

/**
 * A placed field as a type's `fields` write it: its name alone when the type
 * changes nothing.
 */
function placedOf(node: TypeFieldNode): PlacedField {
	const out: Exclude<PlacedField, string> = { field: node.field ?? '' };

	if (node.label !== undefined && node.label !== '') {
		out.label = node.label;
	}

	if (node.description !== undefined && node.description !== '') {
		out.description = node.description;
	}

	if (node.required !== undefined) {
		out.required = node.required;
	}

	return Object.keys(out).length === 1 ? out.field : out;
}

/**
 * The type's fields to save.
 */
export function toTypeFields(nodes: TypeFieldNode[]): TypeFieldItem[] {
	return nodes.map((node) => node.field === null
		? { section: node.label ?? '', ...(node.description ? { description: node.description } : {}), fields: node.children.map(placedOf) }
		: placedOf(node));
}

/**
 * The rule for what goes where: a section at the top, and a
 * field at the top or in a section.
 */
export const typeFieldsAccept: Accepts<TypeFieldNode> = (node, parent) => parent === null || (parent.field === null && node.field !== null);

/**
 * The keys of the library fields placed.
 */
export function placedNames(nodes: TypeFieldNode[]): string[] {
	return nodes.flatMap((node) => node.field === null ? placedNames(node.children) : [node.field]);
}
