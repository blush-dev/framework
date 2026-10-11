<script setup lang="ts">
/**
 * An account's API tokens (D-749), a panel on its screen: yours on Your
 * Account, and another's for someone who may edit accounts.
 *
 * - **The list:** each token's name, what it can do (a preset's name, or
 *   how many capabilities it's narrowed to), who made it and when, when
 *   it was last used, and when it expires; expired ones stay listed,
 *   marked, until they're revoked or pruned (D-748). Revoke takes one;
 *   **Revoke All** takes every one, for a leak.
 * - **New Token** (your own with `api.tokens`; a token-only account's
 *   with `accounts.edit`): `TokenFields`, and, for capabilities chosen
 *   one by one from the account's (those you have too), the role
 *   screen's `CapabilitySections`.
 * - A token just made is shown once, above the list, with a copy button
 *   (`ShownOnce`); only a hash of it is kept.
 * - **Connected Apps** (D-752): one row an app connected through OAuth,
 *   with what it was given, when it was approved and last connected, a
 *   link to its changes under Tools → Activity, and Disconnect, which
 *   takes its approval and every token it holds. Revoke All takes them
 *   too.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import CapabilitySections from './CapabilitySections.vue';
import ShownOnce from './ShownOnce.vue';
import TokenFields from './TokenFields.vue';
import { useAction } from '../action';
import { ApiError, errorMessage } from '../api';
import { confirmAction } from '../confirm';
import { plural } from '../format';
import { disconnectApp, freshToken, loadTokens, makeToken, revokeAllTokens, revokeToken, presetCapabilities, tokenScope, when, type AccountInfo, type ConnectedApp, type NewToken, type TokenInfo, type TokenList, type TokenPreset } from '../people';
import { can } from '../session';
import { toast } from '../toast';

const props = defineProps<{
	account: AccountInfo;
	mine: boolean;
}>();

const list  = ref<TokenList | null>(null);
const error = ref('');
const fresh = ref<NewToken | null>(null);

async function load(): Promise<void> {
	error.value = '';

	try {
		list.value = await loadTokens(props.account.username);
	} catch (caught) {
		error.value = errorMessage(caught, 'The tokens couldn\'t be loaded.');
	}
}

watch(() => props.account.username, (username) => {
	const made = freshToken.value;

	fresh.value      = made !== null && made.username === username ? made.token : null;
	freshToken.value = null;
	list.value       = null;
	void load();
}, { immediate: true });

const whose = computed(() => props.mine ? 'you' : props.account.displayName);

// Your own panel is left out when you have none and can't make one.
const shown = computed(() => !(props.mine && list.value !== null && !list.value.make && list.value.tokens.length === 0 && list.value.apps.length === 0 && fresh.value === null));

const APP_SCOPES: Record<ConnectedApp['scope'], string> = { read: 'Read', drafts: 'Write drafts', account: 'Same as the account', custom: 'Chosen' };

// A new token: its name, lifetime, and what it can do.
const making = ref(false);
const name   = ref('');
const days   = ref<number | null>(90);
const scope  = ref<TokenPreset | 'chosen'>('drafts');
const chosen = ref<string[]>([]);
const field  = ref<'name' | 'expires' | 'capabilities' | null>(null);

const { busy, error: makeError, run } = useAction();

function startNew(): void {
	name.value      = '';
	days.value      = 90;
	scope.value     = 'drafts';
	chosen.value    = [];
	field.value     = null;
	makeError.value = '';
	making.value    = true;
}

async function make(): Promise<void> {
	field.value = null;

	const capabilities = scope.value === 'chosen' ? chosen.value : presetCapabilities(scope.value);

	await run('The token couldn\'t be made.', async () => {
		const made = await makeToken(props.account.username, { name: name.value.trim(), expires: days.value, capabilities });

		fresh.value  = made;
		making.value = false;
		list.value   = list.value ? { ...list.value, tokens: [made.token, ...list.value.tokens] } : list.value;
		toast(`Made ${made.token.name}`);
	}, (caught) => {
		field.value = caught instanceof ApiError && (caught.field === 'name' || caught.field === 'expires' || caught.field === 'capabilities') ? caught.field : null;
	});
}

// Revoking: one, or every one.
const { busy: revoking, error: revokeError, run: runRevoke } = useAction();

async function revoke(token: TokenInfo): Promise<void> {
	if (!await confirmAction({
		title: `Revoke ${token.name}?`,
		body: token.expired ? 'It has expired already; this takes it off the list.' : 'Whatever uses it stops working at once. This can\'t be undone: a new token is a new secret.',
		confirm: 'Revoke the Token',
		danger: true
	})) {
		return;
	}

	await runRevoke('The token couldn\'t be revoked.', async () => {
		await revokeToken(props.account.username, token.id);

		list.value = list.value ? { ...list.value, tokens: list.value.tokens.filter((item) => item.id !== token.id) } : list.value;

		if (fresh.value?.token.id === token.id) {
			fresh.value = null;
		}

		toast(`Revoked ${token.name}`, { kind: 'danger' });
	});
}

async function disconnect(app: ConnectedApp): Promise<void> {
	if (!await confirmAction({
		title: `Disconnect ${app.name}?`,
		body: 'It stops working at once, and connecting it again asks for approval again.',
		confirm: 'Disconnect',
		danger: true
	})) {
		return;
	}

	await runRevoke('The app couldn\'t be disconnected.', async () => {
		await disconnectApp(props.account.username, app.id);

		list.value = list.value ? { ...list.value, apps: list.value.apps.filter((item) => item.id !== app.id) } : list.value;
		toast(`Disconnected ${app.name}`, { kind: 'danger' });
	});
}

async function revokeAll(): Promise<void> {
	const count = (list.value?.tokens.length ?? 0) + (list.value?.apps.length ?? 0);

	if (!await confirmAction({
		title: `Revoke ${plural(count, 'Token', 'Tokens')}?`,
		body: [`Every app, script, and agent using ${props.mine ? 'your' : 'this account\'s'} tokens stops working at once.`, 'For a token that may have leaked: a new password doesn\'t revoke tokens.'],
		confirm: 'Revoke All',
		danger: true
	})) {
		return;
	}

	await runRevoke('The tokens couldn\'t be revoked.', async () => {
		const revoked = await revokeAllTokens(props.account.username);

		list.value  = list.value ? { ...list.value, tokens: [], apps: [] } : list.value;
		fresh.value = null;
		toast(`Revoked ${plural(revoked, 'token')}`, { kind: 'danger' });
	});
}
</script>

<template>
	<section v-if="shown" class="panel" aria-labelledby="tokens-heading">
		<header class="panel__header">
			<h2 id="tokens-heading">API Tokens</h2>
			<p class="panel__hint">For apps, scripts, and agents that use the API</p>
			<div v-if="list && (list.make || list.tokens.length || list.apps.length)" class="panel__actions">
				<button v-if="list.tokens.length || list.apps.length" type="button" class="button button--small" :disabled="revoking" @click="revokeAll">Revoke All</button>
				<button v-if="list.make" type="button" class="button button--primary button--small" @click="startNew"><AdminIcon name="plus" />New Token</button>
			</div>
		</header>

		<div v-if="fresh" class="panel__body">
			<ShownOnce :value="fresh.secret" label="API token" noun="the token" copy="Copy Token">
				<strong>Copy {{ fresh.token.name }} now.</strong> It's shown once, and works {{ fresh.token.expires ? `until ${when(fresh.token.expires)}` : 'until it\'s revoked' }}. Send it as <code>Authorization: Bearer</code> and the token.
			</ShownOnce>
		</div>

		<p v-if="error" class="panel__body field__error" role="alert">{{ error }}</p>
		<p v-if="revokeError" class="panel__body field__error" role="alert">{{ revokeError }}</p>

		<div v-if="list && list.tokens.length" class="table-wrap">
			<table class="table" aria-labelledby="tokens-heading">
				<thead>
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Can</th>
						<th scope="col">Last used</th>
						<th scope="col">Expires</th>
						<th scope="col"><span class="visually-hidden">Actions</span></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="token in list.tokens" :key="token.id">
						<th scope="row">
							<span class="entry-title">
								<span class="entry-title__text">{{ token.name }}</span>
								<span class="entry-title__path">Made {{ when(token.created, true) }}{{ token.createdBy ? ` by ${token.createdBy.displayName}` : ' on the command line' }}</span>
							</span>
						</th>
						<td>{{ tokenScope(token.capabilities) }}</td>
						<td>{{ when(token.lastUsed, true) }}</td>
						<td>
							<span v-if="token.expired" class="pill pill--danger">Expired</span>
							<template v-else>{{ token.expires ? when(token.expires, true) : 'Never' }}</template>
						</td>
						<td class="table__actions"><button type="button" class="button button--ghost button--small" :disabled="revoking" @click="revoke(token)">Revoke<span class="visually-hidden"> {{ token.name }}</span></button></td>
					</tr>
				</tbody>
			</table>
		</div>
		<p v-else-if="list && !list.apps.length" class="panel__body muted">No tokens yet.</p>

		<template v-if="list && list.apps.length">
			<div class="panel__section panel__section--turn">
				<div class="panel__section-head"><h3 id="apps-heading" class="eyebrow">Connected Apps</h3><span>Apps approved to act as {{ whose }} through the MCP server</span></div>
			</div>
			<ul class="rows" aria-labelledby="apps-heading">
				<li v-for="app in list.apps" :key="app.id" class="rows__item">
					<div class="rows__main">
						<span class="rows__title">{{ app.name }}<span v-if="app.host" class="rows__sub"> · {{ app.host }}</span></span>
						<span class="rows__sub">
							{{ APP_SCOPES[app.scope] }} · approved {{ when(app.approved, true) }} · {{ app.connected ? `last connected ${when(app.connected, true)}` : 'not connected yet' }}
						</span>
					</div>
					<div class="rows__side">
						<RouterLink v-if="can('site.activity')" class="button button--ghost button--small" :to="{ name: 'tools', query: { tab: 'activity', client: app.name, account: account.username } }">Activity</RouterLink>
						<button type="button" class="button button--ghost button--small" :disabled="revoking" @click="disconnect(app)">Disconnect<span class="visually-hidden"> {{ app.name }}</span></button>
					</div>
				</li>
			</ul>
		</template>

		<p class="panel__note">
			A token acts as {{ whose }}, with {{ mine ? 'your' : 'its' }} roles as they are on each request<template v-if="account.status === 'suspended'">, so this account's don't work while it's suspended</template>. Revoking stops one at once; a new password doesn't.
		</p>
	</section>

	<AdminModal :open="making" title="New API Token" :wide="scope === 'chosen'" @close="making = false">
		<form id="new-token" class="form-stack" @submit.prevent="make">
			<TokenFields v-model:name="name" v-model:days="days" v-model:scope="scope" :choose="list?.capabilities.length ? (mine ? 'you' : 'the account') : undefined" :invalid="field" />
			<CapabilitySections v-if="scope === 'chosen' && list" v-model="chosen" :capabilities="list.capabilities" :types="list.types" />
		</form>
		<p v-if="makeError" class="field__error" role="alert">{{ makeError }}</p>
		<template #footer>
			<button type="button" class="button" @click="making = false">Cancel</button>
			<button type="submit" form="new-token" class="button button--primary" :disabled="busy || name.trim() === ''">{{ busy ? 'Making…' : 'Make the Token' }}</button>
		</template>
	</AdminModal>
</template>
