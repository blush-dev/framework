<script setup lang="ts">
/**
 * A palette as a legend under its preview (D-381, D-827): each of the six
 * roles' colors in one color scheme as a chip (and an admin theme's
 * chrome, when it sets it; D-878), framed so white still has
 * an edge, with its name and value in the admin's own ink. Theme Details
 * and Admin Theme Details list each half of a palette with it.
 */

import { CHROME_ROLES, PALETTE_ROLES, type ChromeRole, type PaletteRole } from '../api';
import { t } from '../messages';

const props = defineProps<{
	palette: Record<PaletteRole, [string, string]> & Partial<Record<ChromeRole, [string, string]>>;
	// 0 for the light half, 1 for the dark.
	half: 0 | 1;
}>();

const LABELS: Record<PaletteRole | ChromeRole, string> = {
	background: t('palette_legend.background', 'Background'),
	surface: t('palette_legend.surface', 'Surface'),
	text: t('palette_legend.text', 'Text'),
	muted: t('palette_legend.muted', 'Muted'),
	accent: t('palette_legend.accent', 'Accent'),
	border: t('palette_legend.border', 'Border'),
	chrome: t('palette_legend.chrome', 'Chrome'),
	chromeAccent: t('palette_legend.chrome_accent', 'Chrome accent')
};

const swatches = () => [...PALETTE_ROLES, ...CHROME_ROLES].flatMap((role) => {
	const colors = props.palette[role];

	return colors === undefined ? [] : [{ role, fill: colors[props.half] }];
});
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
