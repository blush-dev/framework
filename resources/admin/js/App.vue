<script setup lang="ts">
/**
 * The admin's frame: the layout once signed in, the bare screen before.
 * After each navigation (not the first load), focus moves to the new
 * screen's heading, so keyboard and screen reader users land on it.
 */

import { nextTick } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import AdminLayout from './components/AdminLayout.vue';
import { session } from './session';

const router = useRouter();
let first = true;

router.afterEach(async () => {
	if (first) {
		first = false;
		return;
	}

	await nextTick();
	document.querySelector<HTMLElement>('main h1')?.focus();
});
</script>

<template>
	<AdminLayout v-if="session.account">
		<RouterView />
	</AdminLayout>
	<main v-else class="bare">
		<RouterView />
	</main>
</template>
