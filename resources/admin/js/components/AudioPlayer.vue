<script setup lang="ts">
/**
 * The audio player (D-553) in the admin: the shared, framework-free
 * `<blush-audio-player>` (`resources/player`), which the site can use
 * too, around a native `<audio>`.
 */

import { defineAudioPlayer } from '../../../player/audio-player';

defineAudioPlayer();

defineProps<{ src: string }>();
</script>

<template>
	<blush-audio-player>
		<audio :src="src" controls preload="metadata" />
	</blush-audio-player>
</template>
