<script setup lang="ts">
/**
 * A menu's items, as a tree in a panel (from the menus sketch), drawn by
 * the shared `ItemTree` (D-509). Each row shows the item's icon (its
 * link's kind's, until it's given one), what it's called, and a pill when
 * its link isn't on the site as it is; its fields open under it
 * (`MenuItemFields`). The panel's header counts the items, and has
 * **Expand All** (or **Collapse All**) and **Add Item**.
 */

import { ref } from 'vue';
import { plural } from '../format';
import { blankNode, copyNode, empty, kindLabel, linkIcon, linkPill, shown, textOf, type MenuDetail, type MenuNode } from '../menus';
import AdminIcon from './AdminIcon.vue';
import EmptyState from './EmptyState.vue';
import ItemTree from './ItemTree.vue';
import MenuItemFields from './MenuItemFields.vue';
import SiteIcon from './SiteIcon.vue';
import type { ItemTreeApi } from '../tree';

const props = defineProps<{
	nodes: MenuNode[];
	locale: string;
	kinds: MenuDetail['kinds'];
}>();

const emit = defineEmits<{ change: [] }>();

const tree = ref<ItemTreeApi<MenuNode> | null>(null);

const name = (node: MenuNode): string => shown(node, props.locale);
</script>

<template>
	<section class="panel menu-tree-panel" aria-labelledby="menu-items-heading">
		<header class="panel__header">
			<h2 id="menu-items-heading">Items</h2>
			<p class="panel__hint">{{ plural(tree?.total ?? 0, 'item') }}</p>
			<div class="panel__actions">
				<button v-if="tree?.parents" type="button" class="button button--small" @click="tree?.toggleAll()"><AdminIcon :name="tree?.closed ? 'chevron-down' : 'chevron-up'" />{{ tree?.closed ? 'Expand All' : 'Collapse All' }}</button>
				<button type="button" class="button button--small" @click="tree?.add()"><AdminIcon name="plus" />Add Item</button>
			</div>
		</header>

		<EmptyState v-if="!nodes.length" icon="menu" heading="No Items Yet" text="Add the first link. Items can be entries, terms, a type's listing, a named route, any address, or plain text that groups others.">
			<template #actions>
				<button type="button" class="button button--primary" @click="tree?.add()"><AdminIcon name="plus" />Add Item</button>
			</template>
		</EmptyState>

		<ItemTree
			v-show="nodes.length"
			ref="tree"
			:nodes="nodes"
			:name="name"
			under="sub-item"
			noun="item"
			labelled-by="menu-items-heading"
			:blank="blankNode"
			:copy="copyNode"
			:focus-on-edit="(node) => `menu-item-${node.id}-link`"
			@change="emit('change')"
		>
			<template #pick="{ placed }">
				<span class="item-tree__kind" :title="kindLabel(placed.node.link?.kind ?? null)"><SiteIcon :name="textOf(placed.node.item.icon, locale) || null" :fallback="linkIcon(placed.node.link)" /><span class="visually-hidden">{{ kindLabel(placed.node.link?.kind ?? null) }}: </span></span>
				<span class="item-tree__title" :class="{ 'is-plain': placed.node.link === null }">{{ name(placed.node) }}</span>
				<span v-if="empty(placed.node, locale)" class="pill">Empty</span>
				<span v-else-if="linkPill(placed.node.link)" class="pill" :class="`pill--${linkPill(placed.node.link)!.kind}`" :title="placed.node.link?.message ?? undefined">{{ linkPill(placed.node.link)!.label }}</span>
			</template>
			<template #fields="{ placed, done }">
				<MenuItemFields :node="placed.node" :locale="locale" :kinds="kinds" @change="emit('change')" @done="done" />
			</template>
		</ItemTree>
	</section>
</template>

<style scoped>
/* Plain text, often a heading over the items under it. */
.item-tree__title.is-plain {
	color: var(--fg-2);
	font-family: var(--font-ui);
	font-size: var(--text-2xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}
</style>
