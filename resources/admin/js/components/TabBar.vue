<script setup lang="ts" generic="Key extends string">
/**
 * Tabs that switch what a panel shows (D-509), as the editor's drawer and
 * the media picker have them: an ARIA tablist whose current tab is the
 * only one in the tab order, with Left and Right moving between them
 * (wrapping) and focusing the one they choose. Each tab is
 * `{prefix}-tab-{key}` and controls `{prefix}-panel-{key}`. The `tab`
 * slot draws a tab's content, else its icon and label.
 */

import { nextTick } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { IconName } from '../icons';

const props = defineProps<{
	tabs: { key: Key; label: string; icon?: IconName }[];
	// What the tabs choose between, for the tablist's name.
	label: string;
	prefix: string;
}>();

const model = defineModel<Key>({ required: true });

async function keydown(event: KeyboardEvent): Promise<void> {
	if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
		return;
	}

	event.preventDefault();

	const at   = props.tabs.findIndex((item) => item.key === model.value);
	const next = props.tabs[(at + (event.key === 'ArrowRight' ? 1 : props.tabs.length - 1)) % props.tabs.length];

	if (next !== undefined) {
		model.value = next.key;
		await nextTick();
		document.getElementById(`${props.prefix}-tab-${next.key}`)?.focus();
	}
}
</script>

<template>
	<div class="tab-bar" role="tablist" :aria-label="label" @keydown="keydown">
		<button
			v-for="item in tabs"
			:id="`${prefix}-tab-${item.key}`"
			:key="item.key"
			type="button"
			class="tab-bar__tab"
			role="tab"
			:aria-controls="`${prefix}-panel-${item.key}`"
			:aria-selected="model === item.key"
			:tabindex="model === item.key ? 0 : -1"
			@click="model = item.key"
		>
			<slot name="tab" :tab="item"><AdminIcon v-if="item.icon" :name="item.icon" />{{ item.label }}</slot>
		</button>
	</div>
</template>

<style scoped>
.tab-bar {
	display: flex;
	gap: var(--s-1);
	min-width: 0;
}

.tab-bar__tab {
	display: flex;
	align-items: center;
	gap: 7px;
	min-width: 0;
	margin-bottom: -1px;
	padding: 12px 12px 11px;
	border: 0;
	border-bottom: 2px solid transparent;
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	white-space: nowrap;
	cursor: pointer;
}

.tab-bar__tab:first-child {
	padding-left: 0;
}

.tab-bar__tab :deep(svg) {
	flex: none;
	width: 13px;
	height: 13px;
}

.tab-bar__tab:hover {
	color: var(--fg);
}

.tab-bar__tab[aria-selected="true"] {
	border-bottom-color: var(--accent);
	color: var(--fg);
	font-weight: 500;
}
</style>
