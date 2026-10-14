<script setup lang="ts">
/**
 * Asks for a password link by email (D-846), from Sign In's "Forgot your
 * password?": a username or an email address. The answer is the same
 * whether or not the account exists, so the screen never says which
 * do; it says to check the account's email. A site that can't send
 * email has no link to this screen, and says so here.
 */

import { ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import { useAction } from '../action';
import { config } from '../config';
import { t } from '../messages';
import { forgotPassword } from '../people';

const route = useRoute();
const login = ref(typeof route.query.login === 'string' ? route.query.login : '');
// What the server said once it took the request.
const sent  = ref('');

const { busy, error, run } = useAction();

async function submit(): Promise<void> {
	await run(t('forgot_password_view.errors.failed', 'The link couldn\'t be asked for.'), async () => {
		sent.value = (await forgotPassword(login.value.trim())).message;
	});
}
</script>

<template>
	<AuthCard :title="t('forgot_password_view.title', 'Reset Your Password')">
		<p v-if="!config.mail" class="notice">{{ t('forgot_password_view.no_mail', 'This site can\'t send email, so ask an administrator for a password link.') }}</p>
		<p v-else-if="sent" class="notice notice--success" role="status">{{ sent }}</p>
		<form v-else class="auth-card__form" :aria-busy="busy" @submit.prevent="submit">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<p class="field">
				<label for="login">{{ t('forgot_password_view.login', 'Username or email address') }}</label>
				<input id="login" v-model="login" name="login" autocomplete="username" autocapitalize="none" spellcheck="false" required>
				<span class="field__help">{{ t('forgot_password_view.help', 'The account is emailed a link to choose a new password.') }}</span>
			</p>
			<button type="submit" class="button button--primary" :disabled="busy || login.trim() === ''">{{ busy ? t('forgot_password_view.sending', 'Sending…') : t('forgot_password_view.submit', 'Email Me a Link') }}</button>
		</form>
		<template #foot>
			<RouterLink class="lnk" :to="{ name: 'sign-in' }">{{ t('forgot_password_view.back', 'Back to Sign In') }}</RouterLink>
		</template>
	</AuthCard>
</template>
