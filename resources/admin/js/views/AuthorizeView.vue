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
import { useAction } from '../action';
import { ApiError } from '../api';
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
	read: 'Read what you can see, drafts included. Change nothing.',
	drafts: 'Read, and create and edit drafts. Never publish, trash, or change what\'s live.',
	account: 'Everything you can do here, publishing included.'
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
		failed.value = caught instanceof ApiError ? caught.message : 'This request to connect can\'t be read.';
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
	await run('The app couldn\'t be connected.', async () => {
		go(await answerConnect(params.value, decision, scope.value, chosen.value));
	});
}

async function switchAccount(): Promise<void> {
	await signOut();
	await router.replace({ name: 'sign-in', query: { next: route.fullPath } });
}
</script>

<template>
	<AuthCard title="Connect an App" wide>
		<p v-if="leaving" class="connect__lead"><span class="spin" aria-hidden="true" /> Going back to the app…</p>

		<template v-else-if="failed">
			<p class="notice notice--error" role="alert">{{ failed }}</p>
			<button v-if="back" type="button" class="button" @click="go(back)">Back to the App</button>
		</template>

		<p v-else-if="!asked" class="connect__lead"><span class="spin" aria-hidden="true" /> Reading the request…</p>

		<template v-else>
			<p class="connect__lead">
				<strong>{{ asked.client.name }}</strong>
				<span v-if="asked.client.host" class="connect__host">{{ asked.client.verified ? `from ${asked.client.host}` : `says it's from ${asked.client.host}` }}</span>
				wants to act as you on <strong>{{ asked.site.name }}</strong> <span class="mono connect__host">{{ asked.site.url }}</span>.
			</p>

			<p class="connect__account">
				Signed in as <strong>{{ asked.account.name }}</strong>.
				<button type="button" class="link-button" @click="switchAccount">Use Another Account</button>
			</p>

			<template v-if="!asked.mayConnect">
				<p class="notice notice--warn"><span>Your account can't connect apps. An administrator can give your role Connect apps.</span></p>
				<button type="button" class="button" :disabled="busy" @click="answer('deny')">Back to the App</button>
			</template>

			<form v-else class="auth-card__form" :aria-busy="busy" @submit.prevent="answer('allow')">
				<fieldset class="fieldset">
					<legend>It can</legend>
					<div class="choices choices--detailed">
						<label v-for="choice in asked.scopes" :key="choice.key" class="checkbox">
							<input v-model="scope" type="radio" name="connect-scope" :value="choice.key" :disabled="!choice.available">
							<span class="choice">
								<span class="choice__name">{{ choice.label }}</span>
								<span class="choice__text">{{ choice.available ? SCOPE_TEXT[choice.key] : 'More than the app asked for.' }}</span>
							</span>
						</label>
						<label class="checkbox">
							<input v-model="scope" type="radio" name="connect-scope" value="custom">
							<span class="choice">
								<span class="choice__name">Choose</span>
								<span class="choice__text">Some of what you may do, picked one by one.</span>
							</span>
						</label>
					</div>
				</fieldset>

				<CapabilitySections v-if="scope === 'custom'" v-model="chosen" :capabilities="asked.capabilities" :types="asked.types" />

				<p class="connect__expect">It works only through this site's MCP server. Everything it changes is listed under Tools → Activity, where it can be undone, and you can disconnect it any time from Your Account → API Tokens.</p>

				<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

				<div class="connect__actions">
					<button type="submit" class="button button--primary" :disabled="busy || (scope === 'custom' && chosen.length === 0)">{{ busy ? 'Connecting…' : 'Allow' }}</button>
					<button type="button" class="button" :disabled="busy" @click="answer('deny')">Deny</button>
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
	color: var(--fg-2);
}

.connect__expect {
	font-size: var(--text-sm);
}

.connect__actions {
	display: flex;
	gap: var(--s-2);
}
</style>
