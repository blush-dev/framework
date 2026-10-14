<script setup lang="ts">
/**
 * Confirming a new email address with the link sent to it (D-849):
 * `{admin}/confirm-email#for={site|id}&token={token}`, the site's or an
 * account's. The token is in the fragment, so it never reaches a
 * server's logs, and it's taken out of the address bar as soon as it's
 * read. Nothing changes until Confirm is pressed, so a mail app that
 * opens links to check them confirms nothing.
 */

import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import { useAction } from '../action';
import { ApiError, confirmEmail } from '../api';
import { config } from '../config';
import { t } from '../messages';
import { loadSession, session } from '../session';

const params    = new URLSearchParams(window.location.hash.slice(1));
const changeFor = params.get('for') ?? '';
const token     = params.get('token') ?? '';
// A link that can't be used any more: expired, used, or replaced.
const dead      = ref(changeFor === '' || token === '');
const done      = ref<{ for: 'site' | 'account'; email: string } | null>(null);

const { busy, error, run } = useAction();

onMounted(() => {
	// Keep the token out of the address bar and history.
	if (window.location.hash !== '') {
		window.history.replaceState(window.history.state, '', window.location.pathname);
	}
});

async function submit(): Promise<void> {
	await run(t('confirm_email_view.errors.not_confirmed', 'The address couldn\'t be confirmed.'), async () => {
		done.value = await confirmEmail(changeFor, token);

		if (session.account !== null) {
			await loadSession(true);
		}
	}, (caught) => {
		dead.value = caught instanceof ApiError && caught.status === 410;
	});
}
</script>

<template>
	<AuthCard :title="t('confirm_email_view.title', 'Confirm Email Address')">
		<template v-if="done">
			<p class="notice notice--success" role="status">{{ done.for === 'site' ? t('confirm_email_view.site_done', 'The email address of {site} is {email} now.', { site: config.site.name, email: done.email }) : t('confirm_email_view.account_done', 'Your email address is {email} now.', { email: done.email }) }}</p>
			<RouterLink class="button button--primary" :to="session.account ? { name: 'dashboard' } : { name: 'sign-in' }">{{ session.account ? t('confirm_email_view.to_dashboard', 'Go to the Dashboard') : t('confirm_email_view.sign_in', 'Sign In') }}</RouterLink>
		</template>

		<template v-else-if="dead">
			<p class="notice notice--error" role="alert">{{ error || t('confirm_email_view.errors.incomplete_link', 'This link is missing what it confirms. Copy the whole link and try again.') }}</p>
			<RouterLink class="button" :to="{ name: 'sign-in' }">{{ t('confirm_email_view.sign_in', 'Sign In') }}</RouterLink>
		</template>

		<form v-else class="auth-card__form" :aria-busy="busy" @submit.prevent="submit">
			<p class="confirm-email__intro">{{ changeFor === 'site' ? t('confirm_email_view.site_intro', 'Make this address the email address of {site}, where it sends notices for its owner.', { site: config.site.name }) : t('confirm_email_view.account_intro', 'Make this address your account\'s email address on {site}.', { site: config.site.name }) }}</p>
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<button type="submit" class="button button--primary" :disabled="busy">{{ busy ? t('confirm_email_view.confirming', 'Confirming…') : t('confirm_email_view.submit', 'Confirm Email Address') }}</button>
		</form>
	</AuthCard>
</template>

<style scoped>
.confirm-email__intro {
	color: var(--color-text-secondary);
}
</style>
