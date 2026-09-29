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
		<header class="sign-in__header">
			<span class="sign-in__mark" aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
			<h1 tabindex="-1">Sign in to {{ config.site.name }}</h1>
		</header>
		<form class="sign-in__form" :aria-busy="busy" @submit.prevent="submit">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<p class="field">
				<label for="username">Username</label>
				<input id="username" v-model="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
			</p>
			<p class="field">
				<label for="password">Password</label>
				<input id="password" v-model="password" name="password" type="password" autocomplete="current-password" required>
			</p>
			<button type="submit" class="button button--primary" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>
		</form>
	</div>
</template>

<style scoped>
.sign-in {
	display: grid;
	gap: 20px;
	width: min(100%, 22rem);
	padding: 28px 24px;
	background: var(--surface);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	box-shadow: var(--shadow-2);
}

.sign-in__header {
	display: grid;
	justify-items: start;
	gap: 14px;
}

.sign-in__mark {
	display: grid;
	place-items: center;
	width: 32px;
	height: 32px;
	border-radius: var(--r-1);
	background: var(--accent);
	color: var(--accent-fg);
	font-family: var(--font-display);
	font-weight: 600;
	text-transform: uppercase;
}

.sign-in__form {
	display: grid;
	gap: 14px;
}

.sign-in__form .button {
	height: 34px;
	margin-top: 4px;
}
</style>
