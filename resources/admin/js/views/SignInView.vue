<script setup lang="ts">
/**
 * Signs in. On success, it goes where the visitor was headed (a path
 * inside the admin only), or to the dashboard.
 */

import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import { useAction } from '../action';
import { config } from '../config';
import { signIn } from '../session';

const route    = useRoute();
const router   = useRouter();
const username = ref('');
const password = ref('');

const { busy, error, run } = useAction();

async function submit(): Promise<void> {
	await run('Signing in failed.', async () => {
		await signIn(username.value, password.value);

		const next = route.query.next;

		await router.replace(typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : { name: 'dashboard' });
	}, () => {
		password.value = '';
	});
}
</script>

<template>
	<AuthCard :title="`Sign In to ${config.site.name}`">
		<form class="auth-card__form" :aria-busy="busy" @submit.prevent="submit">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<p class="field">
				<label for="username">Username</label>
				<input id="username" v-model="username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
			</p>
			<p class="field">
				<label for="password">Password</label>
				<input id="password" v-model="password" name="password" type="password" autocomplete="current-password" required>
			</p>
			<button type="submit" class="button button--primary" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign In' }}</button>
		</form>
	</AuthCard>
</template>
