<script setup lang="ts">
/**
 * Signs in. On success, it goes where the visitor was headed (a path
 * inside the admin only), or to the dashboard.
 */

import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ApiError } from '../api';
import { config } from '../config';
import { signIn } from '../session';

const route    = useRoute();
const router   = useRouter();
const username = ref('');
const password = ref('');
const busy     = ref(false);
const error    = ref('');

async function submit(): Promise<void> {
	busy.value  = true;
	error.value = '';

	try {
		await signIn(username.value, password.value);

		const next = route.query.next;

		await router.replace(typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : { name: 'dashboard' });
	} catch (caught) {
		error.value    = caught instanceof ApiError ? caught.message : 'Signing in failed.';
		password.value = '';
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<div class="sign-in">
		<h1 tabindex="-1">Sign in to {{ config.site.name }}</h1>
		<form class="form" :aria-busy="busy" @submit.prevent="submit">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<p class="field">
				<label for="username">Username</label>
				<input id="username" v-model="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
			</p>
			<p class="field">
				<label for="password">Password</label>
				<input id="password" v-model="password" name="password" type="password" autocomplete="current-password" required>
			</p>
			<p>
				<button type="submit" class="button" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
			</p>
		</form>
	</div>
</template>
