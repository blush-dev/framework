<script setup lang="ts">
/**
 * The sites whose links play on the page (the Writing sketch's embeds,
 * D-695), as a wrapping row of chips: each a toggle button with the
 * site's mark (`brands.ts`), its name, and a check or a dash, so the
 * state never rests on color alone. The sites its links are on are its
 * tooltip and are read with it. Turned on, a chip takes the accent; a
 * provider without a mark has the globe.
 */

import { useId } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { brandMark } from '../brands';
import { series } from '../format';

defineProps<{
	providers: { name: string; label: string; hosts: string[] }[];
	// The names of the providers turned on.
	on: string[];
	locked?: boolean;
}>();

const emit = defineEmits<{
	change: [name: string, on: boolean];
}>();

const id = useId();
</script>

<template>
	<div class="embed-chips" role="group" :aria-label="t('embed_chips.label', 'Embeds')">
		<button
			v-for="provider in providers"
			:key="provider.name"
			type="button"
			class="embed-chip"
			:aria-pressed="on.includes(provider.name)"
			:aria-describedby="`${id}-${provider.name}`"
			:title="series(provider.hosts)"
			:disabled="locked"
			@click="emit('change', provider.name, !on.includes(provider.name))"
		>
			<span class="embed-chip__mark" aria-hidden="true">
				<svg v-if="brandMark(provider.name)" class="embed-chip__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" focusable="false" v-html="brandMark(provider.name)" />
				<AdminIcon v-else name="globe" />
			</span>
			{{ provider.label }}
			<AdminIcon :name="on.includes(provider.name) ? 'check' : 'minus'" />
			<span :id="`${id}-${provider.name}`" class="visually-hidden">{{ t('embed_chips.embeds_links', 'Embeds links to {hosts}.', { hosts: series(provider.hosts) }) }}</span>
		</button>
	</div>
</template>

<style scoped>
.embed-chips {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
	padding: var(--s-4) var(--padding-inline);
}

.embed-chip {
	display: inline-flex;
	align-items: center;
	gap: 9px;
	height: var(--ctl);
	padding: 0 12px 0 11px;
	border: 1px solid var(--border-color);
	border-radius: 999px;
	background: var(--color-surface);
	color: var(--color-text-secondary);
	font: inherit;
	font-size: var(--font-size-small);
	cursor: pointer;
}

.embed-chip:not(:disabled):hover {
	border-color: var(--border-color-strong);
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.embed-chip[aria-pressed="true"] {
	border-color: var(--color-accent-border);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
	font-weight: 500;
}

.embed-chip[aria-pressed="true"]:not(:disabled):hover {
	border-color: var(--color-accent);
}

.embed-chip:not(:disabled):hover .embed-chip__mark {
	color: var(--color-text-secondary);
}

.embed-chip:disabled {
	cursor: not-allowed;
	opacity: .55;
}

/* The mark is the pill's left cap, with a short rule after it in the
   pill's own line color. */
.embed-chip__mark {
	display: flex;
	flex: none;
	align-items: center;
	height: 16px;
	padding-right: 9px;
	border-right: 1px solid var(--border-color);
	color: var(--color-text-muted);
}

.embed-chip[aria-pressed="true"] .embed-chip__mark {
	border-right-color: var(--color-accent-border);
	color: var(--color-accent);
}

.embed-chip__logo {
	width: 15px;
	height: 15px;
	fill: currentColor;
}

.embed-chip .icon {
	flex: none;
	width: 13px;
	height: 13px;
}

.embed-chip__mark .icon {
	width: 15px;
	height: 15px;
}

.embed-chip[aria-pressed="false"] > .icon {
	color: var(--color-text-muted);
}
</style>
