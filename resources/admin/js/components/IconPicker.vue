<script setup lang="ts">
/**
 * The editor's icon picker (admin.md §8, The inserters; D-247): one
 * decision and no browsing, so a popover under its button rather than a
 * panel. A search field (names, labels, and keywords), a dense grid, and
 * a foot showing the directive the highlighted icon would write. The
 * keyboard moves through the grid (`grid.ts`); Enter inserts, Escape
 * closes.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { gridMove } from '../grid';
import { iconMask, iconMatches, loadIcons, type SiteIcon } from '../site-icons';

const COLUMNS = 6;

const props = defineProps<{
	anchor: { left: number; top: number; bottom: number };
	// The directive an icon would write, for the foot.
	preview: (icon: SiteIcon) => string;
}>();

const emit = defineEmits<{
	choose: [icon: SiteIcon];
	close: [];
}>();

const root   = ref<HTMLElement | null>(null);
const input  = ref<HTMLInputElement | null>(null);
const grid   = ref<HTMLElement | null>(null);
const icons  = ref<SiteIcon[]>([]);
const failed = ref(false);
const loaded = ref(false);
const query  = ref('');
const active = ref(0);
const place  = ref({ left: 0, top: 0 });

loadIcons().then((list) => {
	icons.value = list;
}, () => {
	failed.value = true;
}).finally(() => {
	loaded.value = true;
	void nextTick(position);
});

const shown   = computed(() => icons.value.filter((icon) => iconMatches(icon, query.value)));
const current = computed(() => shown.value[active.value]);

watch(query, () => {
	active.value = 0;
});

watch(active, async () => {
	await nextTick();
	grid.value?.querySelector('.icon-picker__cell.is-active')?.scrollIntoView({ block: 'nearest' });
});

function keydown(event: KeyboardEvent): void {
	const next = gridMove(event.key, active.value, shown.value.length, COLUMNS, query.value !== '');

	if (next !== null) {
		event.preventDefault();
		active.value = next;
	} else if (event.key === 'Enter') {
		event.preventDefault();

		if (current.value !== undefined) {
			emit('choose', current.value);
		}
	} else if (event.key === 'Escape') {
		event.preventDefault();
		emit('close');
	}
}

// Under its button, lined up with its left edge; above it when there's
// no room below.
function position(): void {
	const element = root.value;

	if (element === null) {
		return;
	}

	const below = props.anchor.bottom + 6;

	place.value = {
		left: Math.max(8, Math.min(props.anchor.left, window.innerWidth - element.offsetWidth - 8)),
		top: below + element.offsetHeight > window.innerHeight - 8 ? Math.max(8, props.anchor.top - element.offsetHeight - 6) : below
	};
}

function outside(event: PointerEvent): void {
	const target = event.target as Element | null;

	if (target !== null && root.value?.contains(target) !== true && target.closest('[data-icon-toggle]') === null) {
		emit('close');
	}
}

onMounted(() => {
	position();
	input.value?.focus();
	document.addEventListener('pointerdown', outside);
	window.addEventListener('resize', position);
});

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', outside);
	window.removeEventListener('resize', position);
});
</script>

<template>
	<div ref="root" class="icon-picker" role="dialog" aria-label="Insert an icon" :style="{ left: `${place.left}px`, top: `${place.top}px` }">
		<label class="icon-picker__search">
			<AdminIcon name="search" />
			<input
				ref="input"
				v-model="query"
				type="text"
				placeholder="Search icons…"
				autocomplete="off"
				spellcheck="false"
				role="combobox"
				aria-label="Search icons"
				aria-expanded="true"
				aria-controls="icon-picker-grid"
				:aria-activedescendant="current ? `icon-${current.name}` : undefined"
				@keydown="keydown"
			>
		</label>

		<div id="icon-picker-grid" ref="grid" class="icon-picker__grid" role="listbox" aria-label="Icons">
			<div
				v-for="(icon, index) in shown"
				:id="`icon-${icon.name}`"
				:key="icon.name"
				class="icon-picker__cell"
				:class="{ 'is-active': index === active }"
				role="option"
				:aria-selected="index === active"
				:aria-label="icon.label"
				:title="icon.label"
				@pointermove="active = index"
				@mousedown.prevent
				@click="emit('choose', icon)"
			>
				<span v-if="icon.svg" class="icon-picker__glyph" :style="{ maskImage: iconMask(icon) }" />
				<span v-else class="icon-picker__name mono">{{ icon.name }}</span>
			</div>
		</div>

		<p v-if="loaded && !shown.length" class="icon-picker__empty">
			<template v-if="failed">The icons couldn't be loaded.</template>
			<template v-else-if="query">No icon matches “{{ query }}”.</template>
			<template v-else>This site has no icons.</template>
		</p>

		<p class="icon-picker__foot">
			<template v-if="current">
				<span class="icon-picker__glyph icon-picker__glyph--small" :style="{ maskImage: iconMask(current) }" aria-hidden="true" />
				<span>{{ current.label }}</span>
				<code class="icon-picker__code">{{ preview(current) }}</code>
			</template>
			<template v-else>Inserted where the cursor is</template>
		</p>
	</div>
</template>

<style scoped>
.icon-picker {
	position: fixed;
	z-index: 60;
	display: flex;
	flex-direction: column;
	width: min(320px, calc(100vw - 16px));
	overflow: hidden;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-3);
}

.icon-picker__search {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 9px 12px;
	border-bottom: 1px solid var(--border);
	color: var(--fg-3);
}

.icon-picker__search input {
	flex: 1;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}

.icon-picker__grid {
	display: grid;
	grid-template-columns: repeat(6, 1fr);
	gap: 3px;
	max-height: 252px;
	padding: 9px;
	overflow-y: auto;
}

.icon-picker__cell {
	display: grid;
	place-items: center;
	aspect-ratio: 1;
	border-radius: var(--r-1);
	color: var(--fg-2);
	cursor: pointer;
}

.icon-picker__cell.is-active {
	background: var(--accent-soft);
	box-shadow: inset 0 0 0 1px var(--accent-line);
	color: var(--accent);
}

.icon-picker__glyph {
	display: block;
	width: 20px;
	height: 20px;
	background: currentColor;
	mask-position: center;
	mask-repeat: no-repeat;
	mask-size: contain;
}

.icon-picker__glyph--small {
	flex: none;
	width: 14px;
	height: 14px;
}

.icon-picker__name {
	overflow: hidden;
	font-size: var(--text-2xs);
	text-overflow: ellipsis;
}

.icon-picker__empty {
	padding: 26px 10px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-align: center;
}

.icon-picker__foot {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 32px;
	padding: 7px 12px;
	border-top: 1px solid var(--border);
	background: var(--bg);
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.icon-picker__code {
	margin-left: auto;
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
