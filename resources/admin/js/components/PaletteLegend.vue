<script setup lang="ts">
/**
 * A palette as a legend under its preview (D-381, D-827): each of the six
 * roles' colors in one color scheme as a chip, framed so white still has
 * an edge, with its name and value in the admin's own ink. Theme Details
 * and Admin Theme Details list each half of a palette with it.
 */

import { PALETTE_ROLES, type PaletteRole } from '../api';

const props = defineProps<{
	palette: Record<PaletteRole, [string, string]>;
	// 0 for the light half, 1 for the dark.
	half: 0 | 1;
}>();

const LABELS: Record<PaletteRole, string> = {
	background: 'Background',
	surface: 'Surface',
	text: 'Text',
	muted: 'Muted',
	accent: 'Accent',
	border: 'Border'
};

const swatches = () => PALETTE_ROLES.map((role) => ({ role, fill: props.palette[role][props.half] }));
</script>

<template>
	<dl class="palette-legend">
		<div v-for="swatch in swatches()" :key="swatch.role">
			<i :style="{ background: swatch.fill }" aria-hidden="true" />
			<dt>{{ LABELS[swatch.role] }}</dt>
			<dd class="mono">{{ swatch.fill }}</dd>
		</div>
	</dl>
</template>

<style scoped>
.palette-legend {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 1px var(--s-4);
	margin: var(--s-3) 0 0;
}

.palette-legend > div {
	display: grid;
	grid-template-columns: auto 78px auto;
	justify-content: start;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
	padding: 2px 0;
	font-size: var(--font-size-xsmall);
}

.palette-legend i {
	width: 16px;
	height: 16px;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
}

.palette-legend dt {
	overflow: hidden;
	color: var(--color-text-secondary);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.palette-legend dd {
	margin: 0;
	color: var(--color-text-muted);
}

@media (width <= 600px) {
	.palette-legend {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
