<script setup lang="ts">
/**
 * The signed-in layout: a skip link, the site's name, the admin's
 * navigation, the account with a sign-out button, and the screen.
 */

import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ApiError } from '../api';
import { config } from '../config';
import { session, signOut } from '../session';

const router  = useRouter();
const leaving = ref(false);
const error   = ref('');

async function leave(): Promise<void> {
	leaving.value = true;
	error.value   = '';

	try {
		await signOut();
		await router.push({ name: 'sign-in' });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Signing out failed.';
	} finally {
		leaving.value = false;
	}
}
</script>

<template>
	<a class="skip-link" href="#main">Skip to content</a>
	<header class="masthead">
		<p class="masthead__site">
			<a :href="config.site.url">{{ config.site.name }}</a>
		</p>
		<nav class="masthead__nav" aria-label="Admin">
			<ul>
				<li><RouterLink :to="{ name: 'dashboard' }">Dashboard</RouterLink></li>
			</ul>
		</nav>
		<div class="masthead__account">
			<span>{{ session.account?.username }}</span>
			<button type="button" class="button button--quiet" :disabled="leaving" @click="leave">Sign out</button>
		</div>
	</header>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<main id="main" class="content">
		<slot />
	</main>
</template>
