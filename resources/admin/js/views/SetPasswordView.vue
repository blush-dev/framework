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
const busy     = ref(false);
const error    = ref('');
const field    = ref(false);
// A link that can't be used any more: expired, used, or replaced.
const dead     = ref(account === '' || token === '');
const input    = ref<HTMLInputElement | null>(null);

onMounted(() => {
	// Keep the token out of the address bar and history.
	if (window.location.hash !== '') {
		window.history.replaceState(window.history.state, '', window.location.pathname);
	}
});

async function submit(): Promise<void> {
	busy.value  = true;
	error.value = '';
	field.value = false;

	try {
		await setPassword(account, token, password.value);
		await loadSession(true);
		toast('Password set. Welcome!');
		await router.replace({ name: 'dashboard' });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'Your password couldn\'t be set.';
		field.value = caught instanceof ApiError && caught.field === 'password';
		dead.value  = caught instanceof ApiError && caught.status === 410;

		if (field.value) {
			await nextTick();
			input.value?.focus();
		}
	} finally {
		busy.value = false;
	}
}
</script>

<template>
	<div class="set-password">
		<header class="set-password__header">
			<span class="set-password__mark" aria-hidden="true">{{ config.site.name.charAt(0) }}</span>
			<h1 tabindex="-1">Choose a Password</h1>
		</header>

		<template v-if="dead">
			<p class="notice notice--error" role="alert">{{ error || 'This link is missing its account or token. Copy the whole link and try again.' }}</p>
			<RouterLink class="button" :to="{ name: 'sign-in' }">Sign in</RouterLink>
		</template>

		<form v-else class="set-password__form" :aria-busy="busy" @submit.prevent="submit">
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
	</div>
</template>

<style scoped>
.set-password {
	display: grid;
	gap: 20px;
	width: min(100%, 24rem);
	padding: 28px 24px;
	background: var(--surface);
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	box-shadow: var(--shadow-2);
}

.set-password__header {
	display: grid;
	justify-items: start;
	gap: 14px;
}

.set-password__mark {
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

.set-password__intro {
	color: var(--fg-2);
}

.set-password__form {
	display: grid;
	gap: 14px;
}

.set-password__form .button {
	height: 34px;
	margin-top: 4px;
}
</style>
