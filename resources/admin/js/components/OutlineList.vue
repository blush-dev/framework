<script setup lang="ts">
/**
 * The editor's Outline, and a Content group's list (admin.md §8, D-280,
 * D-509): a row per element, its icon, its name a quiet column (in the
 * accent for a component someone placed), and its excerpt flowing out
 * of it. The Outline draws depth as indent and a hairline per level
 * (`indent`), and marks the element the caret is in (`current`). The
 * editor says what each element is (`describe`), and a row's press
 * selects it.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { sameElement, type ElementRef, type OutlineItem } from '../elements';
import type { IconName } from '../icons';

export interface OutlineRow {
	name: string;
	icon: IconName;
	text: string;
	// Placed, not written: a container or leaf component.
	placed: boolean;
}

const props = defineProps<{
	items: OutlineItem[];
	describe: (item: ElementRef) => OutlineRow;
	// What's said when there's nothing to list.
	empty: string;
	current?: ElementRef | null;
	indent?: boolean;
}>();

const emit = defineEmits<{ select: [item: OutlineItem] }>();

const rows = computed(() => props.items.map((item) => ({ item, ...props.describe(item) })));
</script>

<template>
	<p v-if="!items.length" class="field__help">{{ empty }}</p>
	<ul v-else class="outline">
		<li v-for="row in rows" :key="`${row.item.kind}-${row.item.index}`">
			<button
				type="button"
				class="outline__row"
				:class="{ 'is-current': sameElement(row.item, current), 'is-placed': row.placed }"
				:style="indent ? { '--depth': row.item.depth } : undefined"
				:aria-current="sameElement(row.item, current) ? 'true' : undefined"
				@click="emit('select', row.item)"
			>
				<AdminIcon :name="row.icon" />
				<span class="outline__name">{{ row.name }}</span>
				<span class="outline__text">{{ row.text }}</span>
			</button>
		</li>
	</ul>
</template>

<style scoped>
/* The type name a quiet column, the excerpt flowing out of it, depth
   drawn as indent and a hairline per level. A component's name is in
   the accent: someone placed it. */
.outline {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 2px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.outline li {
	min-width: 0;
}

.outline__row {
	--depth: 0;
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-width: 0;
	padding: 7px 10px 7px calc(10px + var(--depth) * 13px);
	overflow: hidden;
	border: 0;
	border-radius: var(--r-1);
	background: repeating-linear-gradient(to right, var(--border-strong) 0 1px, transparent 1px 13px) 15px 4px / calc(var(--depth) * 13px) calc(100% - 8px) no-repeat;
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.outline__row:hover {
	background-color: var(--surface-2);
	color: var(--fg);
}

.outline__row.is-current {
	background-color: var(--accent-soft);
}

.outline__row svg {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.outline__row.is-current svg {
	color: var(--accent);
}

/* The type name is a quiet column; the excerpt flows out of it, and both
   give way to the panel's width rather than overflowing it. */
.outline__name {
	flex: none;
	min-width: 74px;
	max-width: 50%;
	overflow: hidden;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.outline__row.is-placed .outline__name,
.outline__row.is-current .outline__name {
	color: var(--accent);
}

.outline__text {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.outline__row:hover .outline__text {
	color: var(--fg);
}
</style>
