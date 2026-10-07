<script setup lang="ts">
/**
 * The video player (D-554) in the admin: the shared, framework-free
 * `<blush-video-player>` (`resources/player`), which the site can use
 * too, around a native `<video>`, with a `poster` when it has artwork
 * (D-581).
 */

import { defineVideoPlayer } from '../../../player/video-player';

defineVideoPlayer();

defineProps<{ src: string; poster?: string | null }>();
</script>

<template>
	<blush-video-player>
		<video :src="src" :poster="poster ?? undefined" controls preload="metadata" />
	</blush-video-player>
</template>
