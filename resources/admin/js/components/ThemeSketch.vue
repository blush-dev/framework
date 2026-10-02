<script setup lang="ts">
/**
 * A theme's preview (D-381): a sketch of a page drawn from what the theme
 * declares in `theme.json`'s `preview`, its six palette colors and a
 * layout, not a screenshot, so it can't go stale and no webfont is
 * fetched for it.
 *
 * Each color is a `light-dark()` pair, so the sketch shows the theme's
 * light palette in a light admin and its dark one in a dark admin; a
 * `scheme` pins one half. A theme without a palette is drawn in the
 * admin's own quiet colors, and a broken theme gets no picture, since
 * there's nothing to draw.
 *
 * The page is laid out on a 1200-unit-wide canvas whose unit is a share of
 * the frame's width (container units), so its proportions hold at any
 * width without measuring it. The theme's colors are the only literal
 * colors, set as custom properties on the frame.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { PALETTE_ROLES, type ThemePreview } from '../api';

const props = defineProps<{
	preview: ThemePreview | null;
	// Whether the theme is broken, with nothing to draw.
	broken?: boolean;
	scheme?: 'light' | 'dark';
}>();

const layout = computed(() => props.preview?.layout ?? 'centered');

const colors = computed(() => {
	const palette = props.preview?.palette;
	const style: Record<string, string> = props.scheme ? { colorScheme: props.scheme } : {};

	if (palette) {
		for (const role of PALETTE_ROLES) {
			style[`--t-${role}`] = `light-dark(${palette[role][0]}, ${palette[role][1]})`;
		}
	}

	return style;
});
</script>

<template>
	<div class="sketch" :class="{ 'sketch--plain': !preview?.palette && !broken }" :style="broken ? undefined : colors" aria-hidden="true">
		<div v-if="broken" class="sketch__none">
			<AdminIcon name="image-off" />
			<span>No preview</span>
		</div>
		<div v-else class="sketch__page">
			<div class="sketch__bar"><i class="sketch__mark" /><i class="sketch__link" /><i class="sketch__link" /><i class="sketch__link" /></div>
			<div v-if="layout === 'wide'" class="sketch__hero"><i /></div>
			<div v-if="layout === 'wide'" class="sketch__row">
				<div v-for="card in 3" :key="card" class="sketch__card"><i class="sketch__photo" /><i class="sketch__line sketch__line--a" /><i class="sketch__line sketch__line--c" /></div>
			</div>
			<div v-else class="sketch__body" :class="`sketch__body--${layout}`">
				<div class="sketch__column">
					<i class="sketch__heading" /><i class="sketch__heading sketch__heading--short" /><i class="sketch__accent" />
					<i class="sketch__line sketch__line--a" /><i class="sketch__line sketch__line--b" /><i class="sketch__line sketch__line--a" /><i class="sketch__line sketch__line--c" />
					<div class="sketch__card"><i class="sketch__photo" /><i class="sketch__line sketch__line--a" /><i class="sketch__line sketch__line--c" /></div>
				</div>
				<div v-if="layout === 'sidebar'" class="sketch__side">
					<i class="sketch__line sketch__line--a" /><i class="sketch__line sketch__line--b" /><i class="sketch__line sketch__line--a" />
					<i class="sketch__line sketch__line--c" /><i class="sketch__line sketch__line--b" /><i class="sketch__line sketch__line--a" />
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.sketch {
	/* One unit of the 1200-wide canvas. */
	--u: calc(100cqw / 1200);

	position: relative;
	aspect-ratio: 16 / 10;
	overflow: hidden;
	container-type: inline-size;
	background: var(--surface-2);
}

/* No palette: the admin's own quiet colors. */
.sketch--plain {
	--t-background: var(--surface);
	--t-surface: var(--surface-2);
	--t-text: var(--fg-2);
	--t-muted: var(--fg-3);
	--t-accent: var(--border-strong);
	--t-border: var(--border);
}

.sketch i {
	display: block;
}

.sketch__page {
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	background: var(--t-background);
}

.sketch__bar {
	display: flex;
	flex: none;
	align-items: center;
	gap: calc(30 * var(--u));
	height: calc(86 * var(--u));
	padding: 0 calc(64 * var(--u));
	border-bottom: calc(2 * var(--u)) solid var(--t-border);
	background: var(--t-surface);
}

.sketch__mark {
	width: calc(120 * var(--u));
	height: calc(19 * var(--u));
	border-radius: calc(4 * var(--u));
	background: var(--t-text);
}

.sketch__link {
	width: calc(58 * var(--u));
	height: calc(12 * var(--u));
	border-radius: calc(3 * var(--u));
	background: var(--t-muted);
	opacity: .55;
}

.sketch__mark + .sketch__link {
	background: var(--t-accent);
	opacity: 1;
}

.sketch__body {
	display: flex;
	flex: 1;
	gap: calc(44 * var(--u));
	min-height: 0;
	padding: calc(54 * var(--u)) calc(64 * var(--u)) 0;
}

.sketch__body--centered {
	padding-inline: calc(170 * var(--u));
}

.sketch__column {
	display: flex;
	flex: 1;
	flex-direction: column;
	gap: calc(22 * var(--u));
	min-width: 0;
}

.sketch__heading {
	height: calc(46 * var(--u));
	border-radius: calc(7 * var(--u));
	background: var(--t-text);
}

.sketch__heading--short {
	width: 62%;
}

.sketch__accent {
	width: calc(140 * var(--u));
	height: calc(14 * var(--u));
	border-radius: calc(4 * var(--u));
	background: var(--t-accent);
}

.sketch__line {
	height: calc(14 * var(--u));
	border-radius: calc(4 * var(--u));
	background: var(--t-muted);
	opacity: .42;
}

.sketch__line--a {
	width: 96%;
}

.sketch__line--b {
	width: 88%;
}

.sketch__line--c {
	width: 60%;
}

.sketch__card {
	display: flex;
	flex: 1;
	flex-direction: column;
	gap: calc(16 * var(--u));
	min-height: 0;
	padding: calc(22 * var(--u));
	border: calc(2 * var(--u)) solid var(--t-border);
	border-radius: calc(12 * var(--u));
	background: var(--t-surface);
}

.sketch__photo {
	height: 52%;
	border-radius: calc(8 * var(--u));
	background: var(--t-accent);
	opacity: .22;
}

.sketch__side {
	display: flex;
	flex: none;
	flex-direction: column;
	gap: calc(18 * var(--u));
	width: calc(270 * var(--u));
	padding: calc(24 * var(--u));
	border: calc(2 * var(--u)) solid var(--t-border);
	border-radius: calc(12 * var(--u));
	background: var(--t-surface);
}

.sketch__hero {
	display: flex;
	flex: none;
	align-items: center;
	height: calc(168 * var(--u));
	margin: calc(54 * var(--u)) calc(64 * var(--u)) calc(34 * var(--u));
	padding: 0 calc(34 * var(--u));
	border-radius: calc(12 * var(--u));
	background: var(--t-accent);
}

.sketch__hero i {
	width: 46%;
	height: calc(22 * var(--u));
	border-radius: calc(5 * var(--u));
	background: var(--t-background);
	opacity: .85;
}

.sketch__row {
	display: flex;
	flex: 1;
	gap: calc(26 * var(--u));
	min-height: 0;
	padding: 0 calc(64 * var(--u)) calc(40 * var(--u));
}

.sketch__none {
	position: absolute;
	inset: 0;
	display: grid;
	place-content: center;
	justify-items: center;
	gap: var(--s-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.sketch__none .icon {
	width: 26px;
	height: 26px;
}
</style>
