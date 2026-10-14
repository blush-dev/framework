<script setup lang="ts">
/**
 * The tools a connected app can be asked to do (the AI sketch's Tools,
 * D-765): one list, grouped by who registered them, Core or the site or
 * a named plugin, each tool a row of its title, its MCP name, and the
 * capability it needs. A row opens its description; `open` holds the
 * names of the open ones, so the panel's header can open or close them
 * all. Other tools' names in a description read as code.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { IconName } from '../icons';
import type { ToolSource } from '../api';
import { titleCase } from '../format';
import { t } from '../messages';

const props = defineProps<{
	sources: ToolSource[];
	// The names of the tools whose descriptions are open.
	open: string[];
}>();

const emit = defineEmits<{
	toggle: [name: string];
}>();

const KINDS: Record<ToolSource['kind'], { label: string; icon: IconName }> = {
	core: { label: t('offered_tools.kinds.core', 'Built-in'), icon: 'package' },
	site: { label: t('offered_tools.kinds.site', 'This site'), icon: 'globe' },
	plugin: { label: t('offered_tools.kinds.plugin', 'Plugin'), icon: 'plug' },
	other: { label: t('offered_tools.kinds.other', 'Elsewhere'), icon: 'package' }
};

const names = computed(() => new Set(props.sources.flatMap((source) => source.tools.map((tool) => tool.name))));

// A description as parts, the tools' names marked as code.
function parts(text: string): { text: string; code: boolean }[] {
	return text.split(/\b([a-z][a-z0-9]*(?:_[a-z0-9]+)+)\b/).map((part, index) => ({ text: part, code: index % 2 === 1 && names.value.has(part) }));
}
</script>

<template>
	<div class="offered-tools">
		<div class="offered-tools__columns" aria-hidden="true"><span>{{ t('offered_tools.tool', 'Tool') }}</span><span>{{ t('offered_tools.needs', 'Needs') }}</span></div>
		<div v-for="source in sources" :key="`${source.kind}:${source.label}`" class="offered-tools__group">
			<p class="offered-tools__source">
				<strong>{{ source.kind === 'core' ? t('offered_tools.core', 'Core') : source.label }}</strong>
				<span class="tag"><AdminIcon :name="KINDS[source.kind].icon" />{{ KINDS[source.kind].label }}</span>
				<span class="offered-tools__count">{{ t('offered_tools.count', '{count, plural, one {# tool} other {# tools} }', { count: source.tools.length }) }}</span>
			</p>
			<ul class="offered-tools__list">
				<li v-for="tool in source.tools" :key="tool.name">
					<button type="button" class="offered-tools__row" :aria-expanded="open.includes(tool.name)" :aria-controls="`tool-${tool.name}`" @click="emit('toggle', tool.name)">
						<AdminIcon name="chevron-right" class="offered-tools__twisty" />
						<span class="offered-tools__name"><span class="offered-tools__title">{{ titleCase(tool.title) }}</span><code>{{ tool.name }}</code></span>
						<span class="offered-tools__needs" :class="{ 'is-anyone': tool.needs === null }">{{ tool.needs ?? t('offered_tools.anyone_connected', 'Anyone connected') }}</span>
					</button>
					<p v-show="open.includes(tool.name)" :id="`tool-${tool.name}`" class="offered-tools__description">
						<template v-for="(part, index) in parts(tool.description)" :key="index"><code v-if="part.code">{{ part.text }}</code><template v-else>{{ part.text }}</template></template>
						<template v-if="tool.needs === null">{{ ' ' }}{{ t('offered_tools.anyone', 'Anyone who connects an app may use it, for what they may see.') }}</template>
					</p>
				</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
.offered-tools__columns,
.offered-tools__row {
	display: grid;
	grid-template-columns: 16px minmax(0, 1fr) auto;
	gap: var(--s-3);
	align-items: center;
	padding-inline: var(--padding-inline);
}

.offered-tools__columns {
	padding-block: var(--s-2);
	border-bottom: 1px solid var(--border-color);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	font-weight: 600;
	letter-spacing: .06em;
	text-transform: uppercase;
}

/* "Tool" starts at the panel's edge, flush with its other headings. */
.offered-tools__columns span:first-child {
	grid-column: 1 / 3;
}

.offered-tools__group + .offered-tools__group {
	border-top: 1px solid var(--border-color);
}

.offered-tools__source {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	margin: 0;
	padding: var(--s-2) var(--padding-inline);
	border-bottom: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.offered-tools__source strong {
	color: var(--color-text);
	font-weight: 600;
}

.offered-tools__source .tag {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.offered-tools__source .tag .icon {
	width: 11px;
	height: 11px;
}

.offered-tools__count {
	margin-left: auto;
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	font-variant-numeric: tabular-nums;
}

.offered-tools__list {
	margin: 0;
	padding: 0;
	list-style: none;
}

.offered-tools__list li + li {
	border-top: 1px solid var(--border-color);
}

.offered-tools__row {
	width: 100%;
	padding-block: var(--s-3);
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	text-align: left;
	cursor: pointer;
}

.offered-tools__row:hover {
	background: var(--color-surface-subtle);
}

/* An open tool is gray as a whole, its row and its description, so it
   stands apart from the rows around it and the space above its
   description matches the space below, hovered or not. */
.offered-tools__list li:has(> [aria-expanded="true"]) {
	background: var(--color-surface-subtle);
}

/* A line between an open row and its description, with the same space
   on each side of the description. */
.offered-tools__row[aria-expanded="true"] {
	border-bottom: 1px solid var(--border-color);
}

.offered-tools__twisty {
	width: 14px;
	height: 14px;
	color: var(--color-text-muted);
	transition: transform .12s;
}

.offered-tools__row[aria-expanded="true"] .offered-tools__twisty {
	transform: rotate(90deg);
}

.offered-tools__name {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: var(--s-1) var(--s-3);
	min-width: 0;
}

.offered-tools__title {
	color: var(--color-text);
	font-weight: 500;
}

.offered-tools__name code {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.offered-tools__needs {
	color: var(--color-text-secondary);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	white-space: nowrap;
}

.offered-tools__needs.is-anyone {
	color: var(--color-text-muted);
	font-family: var(--font-family-ui);
}

.offered-tools__description {
	max-width: calc(90ch + 60px);
	margin: 0;
	padding: var(--s-3) var(--padding-inline) var(--s-3) calc(var(--padding-inline) + 16px + var(--s-3));
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
	line-height: 1.6;
}

@container (width < 640px) {
	.offered-tools__columns,
	.offered-tools__row {
		grid-template-columns: 16px minmax(0, 1fr);
	}

	.offered-tools__columns span:last-child {
		display: none;
	}

	.offered-tools__needs {
		grid-column: 2;
	}

	.offered-tools__description {
		padding-left: var(--padding-inline);
	}
}

@media (prefers-reduced-motion: reduce) {
	.offered-tools__twisty {
		transition: none;
	}
}
</style>
