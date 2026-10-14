/**
 * A tree of nodes that move: a menu's items (D-700) and a field set's
 * fields (D-772's stage 3), drawn by `ItemTree`. Each node has a page id,
 * its children, and whether it's open. What may go under what is the
 * tree's own rule (`accepts`): a menu takes anything anywhere; a field
 * set takes fields under a group, one level deep (D-770).
 */

export interface TreeNode<T> {
	id: string;
	children: T[];
	open: boolean;
}

// A node's place in the tree.
export interface Placed<T> {
	node: T;
	depth: number;
	parent: T | null;
	list: T[];
	index: number;
}

export type Move = 'up' | 'down' | 'in' | 'out';

export type Where = 'before' | 'after' | 'inside';

/**
 * Whether a node may go under a parent (`null` for the top level).
 */
export type Accepts<T> = (node: T, parent: T | null) => boolean;

const anywhere = (): boolean => true;

/**
 * Every node in order, at every level, or only those shown (under open
 * nodes) with `shown`.
 */
export function flatten<T extends TreeNode<T>>(nodes: T[], shown = false, depth = 0, parent: T | null = null, out: Placed<T>[] = []): Placed<T>[] {
	nodes.forEach((node, index) => {
		out.push({ node, depth, parent, list: nodes, index });

		if (!shown || node.open) {
			flatten(node.children, shown, depth + 1, node, out);
		}
	});

	return out;
}

export function find<T extends TreeNode<T>>(nodes: T[], id: string): Placed<T> | undefined {
	return flatten(nodes).find((placed) => placed.node.id === id);
}

/**
 * Whether a node is below another, at any depth.
 */
export function within<T extends TreeNode<T>>(node: T, id: string): boolean {
	return node.children.some((child) => child.id === id || within(child, id));
}

/**
 * Whether a node can move: up or down among its siblings, in under the
 * one above it, or out beside its parent, where the tree allows it.
 */
export function canMove<T extends TreeNode<T>>(nodes: T[], id: string, move: Move, accepts: Accepts<T> = anywhere): boolean {
	const placed = find(nodes, id);

	if (placed === undefined) {
		return false;
	}

	switch (move) {
		case 'up':
			return placed.index > 0;
		case 'down':
			return placed.index < placed.list.length - 1;
		case 'in':
			return placed.index > 0 && accepts(placed.node, placed.list[placed.index - 1]!);
		case 'out': {
			const outer = placed.parent === null ? undefined : find(nodes, placed.parent.id);

			return outer !== undefined && accepts(placed.node, outer.parent);
		}
	}
}

/**
 * Moves a node a step; answers whether it moved.
 */
export function moveNode<T extends TreeNode<T>>(nodes: T[], id: string, move: Move, accepts: Accepts<T> = anywhere): boolean {
	const placed = find(nodes, id);

	if (placed === undefined || !canMove(nodes, id, move, accepts)) {
		return false;
	}

	const { node, list, index, parent } = placed;

	list.splice(index, 1);

	if (move === 'up') {
		list.splice(index - 1, 0, node);
	} else if (move === 'down') {
		list.splice(index + 1, 0, node);
	} else if (move === 'in') {
		const above = list[index - 1]!;

		above.children.push(node);
		above.open = true;
	} else {
		const outer = find(nodes, parent!.id)!;

		outer.list.splice(outer.index + 1, 0, node);
	}

	return true;
}

/**
 * Whether a node can be dropped before or after another, or in as its
 * first child.
 */
export function canDrop<T extends TreeNode<T>>(nodes: T[], id: string, target: string, where: Where, accepts: Accepts<T> = anywhere): boolean {
	const placed = find(nodes, id);
	const there  = find(nodes, target);

	if (placed === undefined || there === undefined || id === target || within(placed.node, target)) {
		return false;
	}

	return accepts(placed.node, where === 'inside' ? there.node : there.parent);
}

/**
 * Moves a node before or after another, or in as its first child;
 * answers whether it moved.
 */
export function moveTo<T extends TreeNode<T>>(nodes: T[], id: string, target: string, where: Where, accepts: Accepts<T> = anywhere): boolean {
	if (!canDrop(nodes, id, target, where, accepts)) {
		return false;
	}

	const placed = find(nodes, id)!;

	placed.list.splice(placed.index, 1);

	const there = find(nodes, target)!;

	if (where === 'inside') {
		there.node.children.unshift(placed.node);
		there.node.open = true;
	} else {
		there.list.splice(there.index + (where === 'after' ? 1 : 0), 0, placed.node);
	}

	return true;
}

/**
 * What `ItemTree` lets its screen do: add a node (after the selected one,
 * or above, below, or inside a row), open or close every node, open a
 * row's fields, and select one; and say whether any node has children,
 * whether any is closed, and how many there are.
 */
/**
 * What an item tree says about its nodes, worded by the screen that
 * holds it (a menu's items, a form's fields), so each sentence is one
 * message with its noun in it.
 */
export interface ItemTreeWords {
	// What's under a closed row, counted: "3 fields".
	under: (count: number) => string;
	// Read out when a node is added, beside another (`name`) or alone.
	added: (place: 'above' | 'below' | 'inside' | null, name: string) => string;
	// The row menu's buttons.
	addAbove: string;
	addBelow: string;
	// The toast when a node goes with what's under it.
	removedWith: (name: string, count: number) => string;
	// The open and close button of a row with nodes under it, read out.
	showUnder: (name: string) => string;
	hideUnder: (name: string) => string;
}

export interface ItemTreeApi<T> {
	add(place?: 'above' | 'below' | 'inside', id?: string | null, node?: T): Promise<void>;
	remove(id: string): Promise<void>;
	toggleAll(): void;
	edit(id: string): Promise<void>;
	select(id: string, then?: string): void;
	closed: boolean;
	parents: boolean;
	total: number;
}
