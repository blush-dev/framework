<script setup lang="ts">
/**
 * Choosing a password with a link an administrator sent (D-312):
 * `{admin}/set-password#account={username}&token={token}`. The token is
 * in the fragment, so it never reaches a server's logs, and it's taken
 * out of the address bar as soon as it's read. Choosing a password ends
 * the link and signs in.
 */

import { nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import { useAction } from '../action';
import { ApiError } from '../api';
import { config } from '../config';
import { setPassword } from '../people';
import { loadSession, session } from '../session';
import { toast } from '../toast';

const router   = useRouter();
const params   = new URLSearchParams(window.location.hash.slice(1));
const account  = params.get('account') ?? '';
const token    = params.get('token') ?? '';
const password = ref('');
const field    = ref(false);
// A link that can't be used any more: expired, used, or replaced.
const dead     = ref(account === '' || token === '');
const input    = ref<HTMLInputElement | null>(null);

const { busy, error, run } = useAction();

onMounted(() => {
	// Keep the token out of the address bar and history.
	if (window.location.hash !== '') {
		window.history.replaceState(window.history.state, '', window.location.pathname);
	}
});

async function submit(): Promise<void> {
	field.value = false;

	await run('Your password couldn\'t be set.', async () => {
		await setPassword(account, token, password.value);
		await loadSession(true);
		toast('Password set. Welcome!');
		await router.replace({ name: 'dashboard' });
	}, (caught) => {
		field.value = caught instanceof ApiError && caught.field === 'password';
		dead.value  = caught instanceof ApiError && caught.status === 410;

		if (field.value) {
			void nextTick(() => input.value?.focus());
		}
	});
}
</script>

<template>
	<AuthCard title="Choose a Password" wide>
		<template v-if="dead">
			<p class="notice notice--error" role="alert">{{ error || 'This link is missing its account or token. Copy the whole link and try again.' }}</p>
			<RouterLink class="button" :to="{ name: 'sign-in' }">Sign in</RouterLink>
		</template>

		<form v-else class="auth-card__form" :aria-busy="busy" @submit.prevent="submit">
			<p v-if="session.account && session.account.username !== account" class="notice notice--warn"><span>You're signed in as {{ session.account.displayName }}. Choosing this password signs you in as {{ account }} instead.</span></p>
			<p class="set-password__intro">For the <strong class="mono">{{ account }}</strong> account on {{ config.site.name }}. You'll sign in with it from now on.</p>
			<input type="text" name="username" autocomplete="username" :value="account" readonly hidden>
			<p class="field">
				<label for="new-password">New password</label>
				<input id="new-password" ref="input" v-model="password" name="password" type="password" autocomplete="new-password" required :aria-invalid="field ? 'true' : undefined" aria-describedby="new-password-help">
				<span v-if="field" id="new-password-help" class="field__error" role="alert">{{ error }}</span>
				<span v-else id="new-password-help" class="field__help">Long is strong: a few words you'll remember works well.</span>
			</p>
			<p v-if="error && !field" class="notice notice--error" role="alert">{{ error }}</p>
			<button type="submit" class="button button--primary" :disabled="busy || password === ''">{{ busy ? 'Saving…' : 'Set password and sign in' }}</button>
		</form>
	</AuthCard>
</template>

<style scoped>
.set-password__intro {
	color: var(--fg-2);
}
</style>
