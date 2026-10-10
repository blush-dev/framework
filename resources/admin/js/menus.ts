/**
 * The Menus screens' API and the item tree they edit (from the menus
 * sketch). A menu's items come from the server as nodes: each item's
 * stored keys (without `children`), what its link leads to now, and its
 * children. The screen edits them as a tree of `MenuNode`s, each with an
 * id of its own for the page, and saves the stored keys back, so keys
 * the screen doesn't show (an `image`, a route's `params`, a theme's own
 * fields) are kept as they were.
 *
 * A new menu (New Menu, Duplicate) is a draft in this tab until it's
 * published (`draft`): nothing is written before then.
 */

import { ref } from 'vue';
import { request } from './api';
import type { IconName } from './icons';
import type { LinkOption } from './components/LinkPicker.vue';

export type LinkState = 'live' | 'draft' | 'scheduled' | 'hidden' | 'trash' | 'missing';

// What an item's link leads to now.
export interface MenuLink {
	// The item key that names it: `entry`, `term`, `collection`, `route`, `url`, or a plugin's.
	kind: string;
	value: string;
	// The entry's id, for kinds that link one.
	ref: string | null;
	title: string;
	address: string;
	state: LinkState;
	// Why it doesn't lead anywhere now.
	message: string | null;
}

export type StoredItem = Record<string, unknown>;

export interface MenuNodeData {
	item: StoredItem;
	link: MenuLink | null;
	children: MenuNodeData[];
}

export interface MenuLocation {
	name: string;
	label: string;
	// The deepest level it shows, or `null` for any.
	depth: number | null;
	// How many items the theme shows there until it's assigned a menu.
	defaults: number;
	menu: string | null;
}

export interface MenuListed {
	name: string;
	label: string;
	items: number;
	locations: string[];
}

export interface MenuOverview {
	theme: string;
	menus: MenuListed[];
	locations: MenuLocation[];
	kinds: MenuDetail['kinds'];
	locale: string;
}

export interface MenuDetail {
	menu: {
		name: string;
		// Text, or a map of locales to text.
		label: unknown;
		// Where it's kept, for people.
		where: string;
		items: MenuNodeData[];
		editable: boolean;
		problems: string[];
	};
	theme: string;
	locations: MenuLocation[];
	// Every menu, for the names taken and the labels of what locations show.
	menus: { name: string; label: string }[];
	// Each link kind's key, and the other keys it reads (an entry's `ref`).
	kinds: { key: string; keys: string[] }[];
	locale: string;
}

export interface MenuNode {
	id: string;
	item: StoredItem;
	link: MenuLink | null;
	children: MenuNode[];
	open: boolean;
}

// An item's place in the tree.
export interface Placed {
	node: MenuNode;
	depth: number;
	parent: MenuNode | null;
	list: MenuNode[];
	index: number;
}

export type Move = 'up' | 'down' | 'in' | 'out';

export interface MenuDraft {
	name: string;
	label: string;
	items: MenuNodeData[];
}

// A new menu, until it's published.
export const draft = ref<MenuDraft | null>(null);

let next = 0;

export function loadMenus(): Promise<MenuOverview> {
	return request<MenuOverview>('GET', '/menus');
}

export function loadMenu(name: string): Promise<MenuDetail> {
	return request<MenuDetail>('GET', `/menus/${encodeURIComponent(name)}`);
}

export function saveMenu(menu: { was: string | null; name: string; label: unknown; items: StoredItem[]; locations: string[] }): Promise<MenuDetail> {
	return request<MenuDetail>('POST', '/menus', menu);
}

export function deleteMenu(name: string): Promise<{ deleted: { name: string; label: unknown; items: StoredItem[] }; locations: string[] }> {
	return request('DELETE', `/menus/${encodeURIComponent(name)}`);
}

export function assignLocation(location: string, menu: string | null): Promise<MenuOverview> {
	return request<MenuOverview>('PUT', `/menu-locations/${encodeURIComponent(location)}`, { menu });
}

export function searchLinks(text: string): Promise<{ links: MenuLink[]; total: number }> {
	return request<{ links: MenuLink[]; total: number }>('GET', `/menu-links?${new URLSearchParams({ search: text }).toString()}`);
}

/**
 * Nodes for editing, each with a page id; every item starts open.
 */
export function toNodes(data: MenuNodeData[]): MenuNode[] {
	return data.map((node) => ({
		id: `item-${++next}`,
		item: Array.isArray(node.item) ? {} : { ...node.item },
		link: node.link,
		children: toNodes(node.children),
		open: true
	}));
}

/**
 * Nodes as the server sends them, for a copy (Duplicate) or a draft.
 */
export function toData(nodes: MenuNode[]): MenuNodeData[] {
	return nodes.map((node) => ({ item: { ...node.item }, link: node.link, children: toData(node.children) }));
}

/**
 * Items as they're stored: each with its children, when it has any.
 */
export function toItems(nodes: MenuNode[]): StoredItem[] {
	return nodes.map((node) => node.children.length ? { ...node.item, children: toItems(node.children) } : { ...node.item });
}

/**
 * Every item in order, at every level, or only those shown (under open
 * items) with `shown`.
 */
export function flatten(nodes: MenuNode[], shown = false, depth = 0, parent: MenuNode | null = null, out: Placed[] = []): Placed[] {
	nodes.forEach((node, index) => {
		out.push({ node, depth, parent, list: nodes, index });

		if (!shown || node.open) {
			flatten(node.children, shown, depth + 1, node, out);
		}
	});

	return out;
}

export function find(nodes: MenuNode[], id: string): Placed | undefined {
	return flatten(nodes).find((placed) => placed.node.id === id);
}

/**
 * Whether an item can move: up or down among its siblings, in under the
 * one above it, or out beside its parent.
 */
export function canMove(nodes: MenuNode[], id: string, move: Move): boolean {
	const placed = find(nodes, id);

	if (placed === undefined) {
		return false;
	}

	return {
		up: placed.index > 0,
		in: placed.index > 0,
		down: placed.index < placed.list.length - 1,
		out: placed.parent !== null
	}[move];
}

/**
 * Moves an item a step; answers whether it moved.
 */
export function moveNode(nodes: MenuNode[], id: string, move: Move): boolean {
	const placed = find(nodes, id);

	if (placed === undefined || !canMove(nodes, id, move)) {
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
 * Moves an item before or after another, or in as its first child.
 */
export function moveTo(nodes: MenuNode[], id: string, target: string, where: 'before' | 'after' | 'inside'): void {
	const placed = find(nodes, id);

	if (placed === undefined || id === target || within(placed.node, target)) {
		return;
	}

	placed.list.splice(placed.index, 1);

	const there = find(nodes, target)!;

	if (where === 'inside') {
		there.node.children.unshift(placed.node);
		there.node.open = true;
	} else {
		there.list.splice(there.index + (where === 'after' ? 1 : 0), 0, placed.node);
	}
}

/**
 * Whether an item is below another, at any depth.
 */
export function within(node: MenuNode, id: string): boolean {
	return node.children.some((child) => child.id === id || within(child, id));
}

/**
 * A copy of an item and the items under it, with new page ids.
 */
export function copyNode(node: MenuNode): MenuNode {
	return { id: `item-${++next}`, item: structuredClone(node.item), link: node.link, children: node.children.map(copyNode), open: node.open };
}

export function blankNode(): MenuNode {
	return { id: `item-${++next}`, item: {}, link: null, children: [], open: true };
}

/**
 * Text, or a map of locales to text, in a locale.
 */
export function textOf(value: unknown, locale: string): string {
	if (typeof value === 'string') {
		return value;
	}

	if (typeof value === 'object' && value !== null) {
		const map = value as Record<string, unknown>;
		const own = map[locale] ?? Object.values(map)[0];

		return typeof own === 'string' ? own : '';
	}

	return '';
}

/**
 * Text written back where it came from: in its locale's place in a map,
 * else as text.
 */
export function withText(value: unknown, text: string, locale: string): unknown {
	return typeof value === 'object' && value !== null && !Array.isArray(value) ? { ...(value as Record<string, unknown>), [locale]: text } : text;
}

/**
 * What an item is called: its label, else its link's title.
 */
export function shown(node: MenuNode, locale: string): string {
	return textOf(node.item.label, locale).trim() || node.link?.title || 'Untitled';
}

/**
 * Whether an item has neither a link nor a label, so it can't be saved.
 */
export function empty(node: MenuNode, locale: string): boolean {
	return node.link === null && textOf(node.item.label, locale).trim() === '';
}

/**
 * A link's icon: its kind's, or the feed's for a route to a feed.
 */
export function linkIcon(link: MenuLink | null): IconName {
	return link?.kind === 'route' && /(^|\.)feed(\.|$)/.test(link.value) ? 'rss' : kindIcon(link?.kind ?? null);
}

export function kindIcon(kind: string | null): IconName {
	return ({ entry: 'file-text', term: 'tag', collection: 'folder', route: 'signpost', url: 'globe' } as Record<string, IconName>)[kind ?? ''] ?? (kind === null ? 'text' : 'link');
}

export function kindLabel(kind: string | null): string {
	if (kind === null) {
		return 'Plain Text';
	}

	return ({ entry: 'Entry', term: 'Term', collection: 'Collection', route: 'Route', url: 'URL' } as Record<string, string>)[kind] ?? kind.charAt(0).toUpperCase() + kind.slice(1);
}

/**
 * The pill for a link that isn't on the site as it is.
 */
export function linkPill(link: MenuLink | null): LinkOption['pill'] {
	switch (link?.state) {
		case 'draft':
			return { label: 'Draft', kind: 'warn' };
		case 'scheduled':
			return { label: 'Scheduled', kind: 'warn' };
		case 'hidden':
		case 'trash':
		case 'missing':
			return { label: 'Not on the Site', kind: 'danger' };
		default:
			return null;
	}
}

export function linkOption(link: MenuLink): LinkOption {
	return { key: `${link.kind}:${link.value}`, icon: linkIcon(link), title: link.title || link.value, hint: link.address, pill: linkPill(link) };
}

/**
 * A typed address as a link: a path on the site or a whole address.
 */
export function typedLink(text: string): MenuLink | null {
	if (!/^(https?:\/\/\S+|\/\S*)$/i.test(text)) {
		return null;
	}

	let title = text;

	try {
		title = /^https?:/i.test(text) ? new URL(text).hostname.replace(/^www\./, '') : text;
	} catch {
		// Kept as typed.
	}

	return { kind: 'url', value: text, ref: null, title, address: text, state: 'live', message: null };
}

/**
 * Points an item at a link, or at none: every link kind's keys are taken
 * away first, so an item has one link.
 */
export function setLink(node: MenuNode, link: MenuLink | null, kinds: MenuDetail['kinds']): void {
	const item = { ...node.item };

	kinds.forEach((kind) => {
		delete item[kind.key];
		kind.keys.forEach((key) => delete item[key]);
	});

	if (link !== null) {
		item[link.kind] = link.value;

		if (link.ref !== null && kinds.find((kind) => kind.key === link.kind)?.keys.includes('ref')) {
			item.ref = link.ref;
		}
	}

	node.item = item;
	node.link = link;
}

/**
 * A menu's name from its label: lowercase words joined by hyphens.
 */
export function menuSlug(label: string): string {
	return label.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-+|-+$/g, '');
}

/**
 * A free name for a menu: the label's, numbered when it's taken.
 */
export function freeName(label: string, taken: string[]): string {
	const base = menuSlug(label);
	let name   = base;

	for (let count = 2; name !== '' && taken.includes(name); count++) {
		name = `${base}-${count}`;
	}

	return name;
}

export const MENU_NAME = /^[a-z0-9][a-z0-9_-]*$/;
