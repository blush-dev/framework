<script setup lang="ts">
/**
 * Connect an App (D-752, the MCP design brief): where an AI app sends a
 * person to approve it, outside the shell like Sign In. It says which
 * site, which app (marked unverified when it only says where it's from),
 * and which account it would act as, offers what it may do (the app's
 * request chosen, wider choices unavailable, and Choose for picking
 * capabilities one by one), and sends the person back to the app with
 * Allow or Deny. An app approved before with as much goes back at once;
 * a request that can't be granted says why, and goes back only to an
 * address known to be the app's.
 */

import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthCard from '../components/AuthCard.vue';
import CapabilitySections from '../components/CapabilitySections.vue';
import MessageText from '../components/MessageText.vue';
import { useAction } from '../action';
import { ApiError } from '../api';
import { t } from '../messages';
import { answerConnect, describeConnect, type ConnectRequest, type ConnectScope } from '../oauth';
import { signOut } from '../session';

const route   = useRoute();
const router  = useRouter();
const asked   = ref<ConnectRequest | null>(null);
const failed  = ref<string | null>(null);
// Where to tell the app no, for a request that can't be granted.
const back    = ref<string | null>(null);
const scope   = ref<ConnectScope | 'custom'>('drafts');
const chosen  = ref<string[]>([]);
const leaving = ref(false);

const { busy, error, run } = useAction();

const params = computed<Record<string, string>>(() => Object.fromEntries(
	Object.entries(route.query).filter((entry): entry is [string, string] => typeof entry[1] === 'string')
));

const SCOPE_TEXT: Record<ConnectScope, string> = {
	read: t('authorize_view.scopes.read', 'Read what you can see, drafts included. Change nothing.'),
	drafts: t('authorize_view.scopes.drafts', "Read, and create and edit drafts. Never publish, trash, or change what's live."),
	account: t('authorize_view.scopes.account', 'Everything you can do here, publishing included.')
};

onMounted(async () => {
	try {
		const found = await describeConnect(new URLSearchParams(params.value).toString());

		if (found.redirect !== null) {
			go(found.redirect);

			return;
		}

		asked.value = found;
		scope.value = found.asked;
	} catch (caught) {
		failed.value = caught instanceof ApiError ? caught.message : t('authorize_view.errors.unreadable', "This request to connect can't be read.");
		back.value   = caught instanceof ApiError && typeof (caught.data as { redirect?: unknown } | null)?.redirect === 'string'
			? (caught.data as { redirect: string }).redirect
			: null;
	}
});

function go(address: string): void {
	leaving.value = true;
	window.location.assign(address);
}

async function answer(decision: 'allow' | 'deny'): Promise<void> {
	await run(t('authorize_view.errors.not_connected', "The app couldn't be connected."), async () => {
		go(await answerConnect(params.value, decision, scope.value, chosen.value));
	});
}

async function switchAccount(): Promise<void> {
	await signOut();
	await router.replace({ name: 'sign-in', query: { next: route.fullPath } });
}
</script>

<template>
	<AuthCard :title="t('authorize_view.title', 'Connect an App')" wide>
		<p v-if="leaving" class="connect__lead"><span class="spin" aria-hidden="true" /> {{ t('authorize_view.going_back', 'Going back to the app…') }}</p>

		<template v-else-if="failed">
			<p class="notice notice--error" role="alert">{{ failed }}</p>
			<button v-if="back" type="button" class="button" @click="go(back)">{{ t('authorize_view.back_to_app', 'Back to the App') }}</button>
		</template>

		<p v-else-if="!asked" class="connect__lead"><span class="spin" aria-hidden="true" /> {{ t('authorize_view.reading', 'Reading the request…') }}</p>

		<template v-else>
			<p class="connect__lead">
				<MessageText :text="t('authorize_view.wants_to_act', '{app} {host} wants to act as you on {site} {url}.')">
					<template #app><strong>{{ asked.client.name }}</strong></template>
					<template #host><span v-if="asked.client.host" class="connect__host">{{ asked.client.verified ? t('authorize_view.from_host', 'from {host}', { host: asked.client.host }) : t('authorize_view.says_from_host', "says it's from {host}", { host: asked.client.host }) }}</span></template>
					<template #site><strong>{{ asked.site.name }}</strong></template>
					<template #url><span class="mono connect__host">{{ asked.site.url }}</span></template>
				</MessageText>
			</p>

			<p class="connect__account">
				<MessageText :text="t('authorize_view.signed_in_as', 'Signed in as {name}.')"><template #name><strong>{{ asked.account.name }}</strong></template></MessageText>
				<button type="button" class="link-button" @click="switchAccount">{{ t('authorize_view.use_another_account', 'Use Another Account') }}</button>
			</p>

			<template v-if="!asked.mayConnect">
				<p class="notice notice--warn"><span>{{ t('authorize_view.cant_connect', "Your account can't connect apps. An administrator can give your role Connect apps.") }}</span></p>
				<button type="button" class="button" :disabled="busy" @click="answer('deny')">{{ t('authorize_view.back_to_app', 'Back to the App') }}</button>
			</template>

			<form v-else class="auth-card__form" :aria-busy="busy" @submit.prevent="answer('allow')">
				<fieldset class="fieldset">
					<legend>{{ t('authorize_view.it_can', 'It can') }}</legend>
					<div class="choices choices--detailed">
						<label v-for="choice in asked.scopes" :key="choice.key" class="checkbox">
							<input v-model="scope" type="radio" name="connect-scope" :value="choice.key" :disabled="!choice.available">
							<span class="choice">
								<span class="choice__name">{{ choice.label }}</span>
								<span class="choice__text">{{ choice.available ? SCOPE_TEXT[choice.key] : t('authorize_view.more_than_asked', 'More than the app asked for.') }}</span>
							</span>
						</label>
						<label class="checkbox">
							<input v-model="scope" type="radio" name="connect-scope" value="custom">
							<span class="choice">
								<span class="choice__name">{{ t('authorize_view.choose', 'Choose') }}</span>
								<span class="choice__text">{{ t('authorize_view.choose_text', 'Some of what you may do, picked one by one.') }}</span>
							</span>
						</label>
					</div>
				</fieldset>

				<CapabilitySections v-if="scope === 'custom'" v-model="chosen" :capabilities="asked.capabilities" :types="asked.types" />

				<p class="connect__expect">{{ t('authorize_view.expect', "It works only through this site's MCP server. Everything it changes is listed under Tools → Activity, where it can be undone, and you can disconnect it any time from Your Account → API Tokens.") }}</p>

				<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

				<div class="connect__actions">
					<button type="submit" class="button button--primary" :disabled="busy || (scope === 'custom' && chosen.length === 0)">{{ busy ? t('authorize_view.connecting', 'Connecting…') : t('authorize_view.allow', 'Allow') }}</button>
					<button type="button" class="button" :disabled="busy" @click="answer('deny')">{{ t('authorize_view.deny', 'Deny') }}</button>
				</div>
			</form>
		</template>
	</AuthCard>
</template>

<style scoped>
.connect__lead,
.connect__account,
.connect__expect {
	margin: 0 0 var(--s-3);
}

.connect__host,
.connect__account,
.connect__expect {
	color: var(--color-text-secondary);
}

.connect__expect {
	font-size: var(--font-size-small);
}

.connect__actions {
	display: flex;
	gap: var(--s-2);
}
</style>
