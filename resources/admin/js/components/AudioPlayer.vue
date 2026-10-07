<script setup lang="ts">
/**
 * The audio player (D-553) in the admin: the shared, framework-free
 * `<blush-audio-player>` (`resources/player`), which the site uses
 * too, around a native `<audio>`. Given a title, it's a card (D-575),
 * as the site's `::audio{variant=card}` draws one: the artwork (or a
 * music glyph, when it's `null` or can't be shown) beside the title and
 * who made it, over the player. Without `artwork` at all, the card has
 * no picture: the words over the player, as a media file's preview has
 * it, since its Artwork panel shows the picture (D-582).
 */

import { ref, watch } from 'vue';
import { defineAudioPlayer } from '../../../player/audio-player';
import AdminIcon from './AdminIcon.vue';

defineAudioPlayer();

const props = defineProps<{ src: string; title?: string; by?: string; artwork?: string | null }>();

// Whether the artwork couldn't be shown, until it changes.
const failed = ref(false);

watch(() => props.artwork, () => {
	failed.value = false;
});
</script>

<template>
	<figure v-if="title" class="audio-card">
		<figcaption class="audio-card__meta">
			<span class="audio-card__title">{{ title }}</span>
			<span v-if="by" class="audio-card__by">{{ by }}</span>
		</figcaption>
		<img v-if="artwork && !failed" class="audio-card__art" :src="artwork" alt="Its artwork" @error="failed = true">
		<span v-else-if="artwork !== undefined" class="audio-card__art audio-card__art--none"><AdminIcon name="music" /></span>
		<blush-audio-player>
			<audio :src="src" controls preload="metadata" />
		</blush-audio-player>
	</figure>
	<blush-audio-player v-else>
		<audio :src="src" controls preload="metadata" />
	</blush-audio-player>
</template>

<style scoped>
.audio-card__art--none {
	display: grid;
	place-items: center;
	background: var(--surface-2);
	color: var(--fg-3);
}

.audio-card__art--none :deep(svg) {
	width: 36px;
	height: 36px;
	stroke-width: 1.25;
}
</style>
