<script setup lang="ts">
/**
 * An avatar (D-536, D-810): an account's or a profile's picture, as the
 * server's `Avatars` finds it, else up to two initials of its name, in
 * the `.avatar` circle. It's always hidden from assistive technology:
 * the name is printed beside it, or the control holding it is labeled.
 */

import { initials } from '../people';

defineProps<{
	name: string;
	// The picture's URL, or `null` for initials.
	image?: string | null;
}>();
</script>

<template>
	<span class="avatar" aria-hidden="true">
		<img v-if="image" class="avatar__image" :src="image" alt="">
		<template v-else>{{ initials(name) }}</template>
	</span>
</template>
