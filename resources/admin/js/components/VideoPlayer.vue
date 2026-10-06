<script setup lang="ts">
/**
 * The video player (D-554) in the admin: the shared, framework-free
 * `<blush-video-player>` (`resources/player`), which the site can use
 * too, around a native `<video>`.
 */

import { defineVideoPlayer } from '../../../player/video-player';

defineVideoPlayer();

defineProps<{ src: string }>();
</script>

<template>
	<blush-video-player>
		<video :src="src" controls preload="metadata" />
	</blush-video-player>
</template>
