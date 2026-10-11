<script setup lang="ts">
/**
 * The admin's frame: the layout once signed in, the bare screen before,
 * and for a screen drawn outside the shell (`meta.bare`, Connect an App).
 * After each navigation (not the first load, nor one that keeps the
 * screen), focus moves to the new screen's heading, so keyboard and
 * screen reader users land on it.
 */

import { nextTick } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import AdminLayout from './components/AdminLayout.vue';
import { sameScreen } from './router';
import { session } from './session';

const router = useRouter();
const route  = useRoute();
let first = true;

router.afterEach(async (to, from) => {
	if (first) {
		first = false;
		return;
	}

	if (sameScreen(to, from)) {
		return;
	}

	await nextTick();
	document.querySelector<HTMLElement>('main h1')?.focus();
});
</script>

<template>
	<AdminLayout v-if="session.account && route.meta.bare !== true">
		<RouterView />
	</AdminLayout>
	<main v-else class="bare">
		<RouterView />
	</main>
</template>

<style scoped>
.bare {
	display: grid;
	min-height: 100%;
	place-items: center;
	padding: 24px 16px;
}
</style>
