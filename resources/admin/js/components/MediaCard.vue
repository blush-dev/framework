<script setup lang="ts">
/**
 * A file in the library as a card (D-509): every card the same height, a
 * 4:3 thumbnail (an image cropped to fill it; a sound's or video's
 * artwork, with its kind on it, D-583; else its kind's glyph and name),
 * its name on one line, and its details on a second. With `to`
 * it's a link to the file; else a button that's chosen (`selected`, a
 * ring and a tick: a tint alone is lost on an image), the tick a number
 * where `order` says which of several it is (D-526).
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, type RouteLocationRaw } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import type { MediaItem } from '../api';
import { mediaIcon, mediaName } from '../media';

const props = defineProps<{
	file: MediaItem;
	details: string;
	to?: RouteLocationRaw;
	selected?: boolean;
	order?: number;
}>();

// The picture it shows: an image itself, else its artwork, until it
// fails to load.
const failed  = ref(false);
const picture = computed(() => failed.value ? null : (props.file.kind === 'image' ? props.file.url : props.file.artworkUrl));

watch(() => props.file.reference, () => {
	failed.value = false;
});
</script>

<template>
	<component :is="to ? RouterLink : 'button'" class="media-card" :to="to" :type="to ? undefined : 'button'" :aria-pressed="to ? undefined : selected === true">
		<span class="media-card__thumb">
			<template v-if="picture">
				<img :src="picture" alt="" loading="lazy" @error="failed = true">
				<span v-if="file.kind !== 'image'" class="media-card__kind mono">{{ file.kind }}</span>
			</template>
			<template v-else><AdminIcon :name="mediaIcon(file)" /><span class="media-card__kind mono">{{ file.kind }}</span></template>
			<span v-if="!to" class="media-card__tick" aria-hidden="true"><b v-if="order" class="media-card__order">{{ order }}</b><AdminIcon v-else name="check" /></span>
		</span>
		<span class="media-card__text">
			<span class="media-card__name" :title="file.name">{{ mediaName(file) }}</span>
			<span class="media-card__meta mono">{{ details }}</span>
		</span>
	</component>
</template>

<style scoped>
.media-card {
	display: flex;
	flex-direction: column;
	min-width: 0;
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg);
	text-align: left;
	text-decoration: none;
	cursor: pointer;
}

.media-card:hover {
	border-color: var(--border-strong);
	color: var(--fg);
}

.media-card[aria-pressed="true"] {
	border-color: var(--accent);
	box-shadow: 0 0 0 2px var(--accent-soft);
}

.media-card__thumb {
	position: relative;
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	overflow: hidden;
	background: var(--surface-2);
	color: var(--fg-3);
}

.media-card__thumb img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.media-card__thumb > :deep(svg) {
	width: 28px;
	height: 28px;
	stroke-width: 1.5;
}

/* A kind where the thumbnail isn't the file itself: a placeholder, or a
   sound's or video's artwork. */
.media-card__kind {
	position: absolute;
	top: 9px;
	left: 9px;
	padding: 1px 4px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg-2);
	font-size: var(--text-2xs);
	letter-spacing: .04em;
	text-transform: uppercase;
}

.media-card__tick {
	position: absolute;
	top: 9px;
	right: 9px;
	display: grid;
	place-items: center;
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: var(--accent);
	color: var(--accent-fg);
	opacity: 0;
	transform: scale(.7);
	transition: opacity 120ms, transform 120ms;
}

.media-card__tick :deep(svg) {
	width: 13px;
	height: 13px;
	stroke-width: 2.6;
}

.media-card__order {
	font-size: var(--text-xs);
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}

.media-card[aria-pressed="true"] .media-card__tick {
	opacity: 1;
	transform: none;
}

.media-card__text {
	display: grid;
	gap: 4px;
	min-width: 0;
	padding: var(--s-3) var(--s-4);
	border-top: 1px solid var(--border);
}

.media-card__name,
.media-card__meta {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.media-card__name {
	font-size: var(--text-sm);
}

.media-card__meta {
	color: var(--fg-3);
	font-size: var(--text-xs);
}
</style>
