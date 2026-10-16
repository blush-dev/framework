<script setup lang="ts">
/**
 * An admin theme in miniature (D-827): its rail (in its chrome, D-878), a panel, an accent, and
 * two lines of ink, in the theme's own colors (`preview`, each a
 * `light-dark()` value, so it follows the admin's color scheme), on its
 * background. Drawn in shares of its box, so it's the same picture as a
 * swatch on Your Account and as a card's media on Admin Themes; whatever
 * holds it gives its size and shape (`aspect-ratio`). A broken theme has
 * nothing to draw.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import type { AdminThemePreview } from '../api';

const props = defineProps<{
	preview: AdminThemePreview | null;
}>();

const colors = computed(() => props.preview === null ? undefined : {
	'--sw-bg': props.preview.background,
	'--sw-surface': props.preview.surface,
	'--sw-chrome': props.preview.chrome,
	'--sw-accent': props.preview.accent,
	'--sw-fg': props.preview.text
});
</script>

<template>
	<span class="admin-sketch" :class="{ 'admin-sketch--none': !preview }" :style="colors" aria-hidden="true">
		<template v-if="preview">
			<span class="admin-sketch__rail" />
			<span class="admin-sketch__panel" />
			<span class="admin-sketch__accent" />
			<span class="admin-sketch__line" />
			<span class="admin-sketch__line admin-sketch__line--short" />
		</template>
		<template v-else>
			<AdminIcon name="image-off" />
			<span>{{ t('admin_theme_sketch.no_preview', 'No preview') }}</span>
		</template>
	</span>
</template>

<style scoped>
.admin-sketch {
	position: relative;
	display: block;
	overflow: hidden;
	aspect-ratio: 46 / 34;
	container-type: inline-size;
	background: var(--sw-bg);
}

.admin-sketch > span {
	position: absolute;
	display: block;
}

/* One unit of the 46-wide swatch it was drawn as. */
.admin-sketch__rail,
.admin-sketch__panel,
.admin-sketch__accent,
.admin-sketch__line {
	--u: calc(100cqw / 46);
}

.admin-sketch__rail {
	top: 0;
	bottom: 0;
	left: 0;
	width: 24%;
	border-right: max(1px, calc(var(--u) / 2)) solid color-mix(in srgb, var(--sw-fg) 14%, transparent);
	background: var(--sw-chrome);
}

.admin-sketch__panel {
	top: 15%;
	right: 9%;
	bottom: 15%;
	left: 33%;
	border-radius: calc(var(--u) * 3);
	background: var(--sw-surface);
}

.admin-sketch__accent {
	top: 26%;
	left: 41%;
	width: 28%;
	height: 9%;
	border-radius: calc(var(--u) * 2);
	background: var(--sw-accent);
}

.admin-sketch__line {
	top: 47%;
	left: 41%;
	width: 37%;
	height: 6%;
	border-radius: var(--u);
	background: var(--sw-fg);
	opacity: .5;
}

.admin-sketch__line--short {
	top: 62%;
	width: 24%;
	opacity: .28;
}

/* Nothing to draw: the admin's own quiet colors. */
.admin-sketch--none {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
	background: var(--color-surface-subtle);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.admin-sketch--none > span {
	position: static;
}
</style>
