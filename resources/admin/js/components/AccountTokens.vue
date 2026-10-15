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
import MessageText from './MessageText.vue';
import ShownOnce from './ShownOnce.vue';
import TokenFields from './TokenFields.vue';
import { useAction } from '../action';
import { ApiError, errorMessage } from '../api';
import { confirmAction } from '../confirm';
import { t } from '../messages';
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
		error.value = errorMessage(caught, t('account_tokens.load_failed', 'The tokens couldn\'t be loaded.'));
	}
}

watch(() => props.account.username, (username) => {
	const made = freshToken.value;

	fresh.value      = made !== null && made.username === username ? made.token : null;
	freshToken.value = null;
	list.value       = null;
	void load();
}, { immediate: true });

const whose = computed(() => props.mine ? t('account_tokens.you', 'you') : props.account.displayName);

// Your own panel is left out when you have none and can't make one.
const shown = computed(() => !(props.mine && list.value !== null && !list.value.make && list.value.tokens.length === 0 && list.value.apps.length === 0 && fresh.value === null));

const APP_SCOPES: Record<ConnectedApp['scope'], string> = { read: t('account_tokens.scope_read', 'Read'), drafts: t('account_tokens.scope_drafts', 'Write drafts'), account: t('account_tokens.scope_account', 'Same as the account'), custom: t('account_tokens.scope_custom', 'Chosen') };

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

	await run(t('account_tokens.make_failed', 'The token couldn\'t be made.'), async () => {
		const made = await makeToken(props.account.username, { name: name.value.trim(), expires: days.value, capabilities });

		fresh.value  = made;
		making.value = false;
		list.value   = list.value ? { ...list.value, tokens: [made.token, ...list.value.tokens] } : list.value;
		toast(t('account_tokens.made', 'Made {name}', { name: made.token.name }));
	}, (caught) => {
		field.value = caught instanceof ApiError && (caught.field === 'name' || caught.field === 'expires' || caught.field === 'capabilities') ? caught.field : null;
	});
}

// Revoking: one, or every one.
const { busy: revoking, error: revokeError, run: runRevoke } = useAction();

async function revoke(token: TokenInfo): Promise<void> {
	if (!await confirmAction({
		title: t('account_tokens.revoke_title', 'Revoke {name}?', { name: token.name }),
		body: token.expired ? t('account_tokens.revoke_expired', 'It has expired already; this takes it off the list.') : t('account_tokens.revoke_body', 'Whatever uses it stops working at once. This can\'t be undone: a new token is a new secret.'),
		confirm: t('account_tokens.revoke_confirm', 'Revoke the Token'),
		danger: true
	})) {
		return;
	}

	await runRevoke(t('account_tokens.revoke_failed', 'The token couldn\'t be revoked.'), async () => {
		await revokeToken(props.account.username, token.id);

		list.value = list.value ? { ...list.value, tokens: list.value.tokens.filter((item) => item.id !== token.id) } : list.value;

		if (fresh.value?.token.id === token.id) {
			fresh.value = null;
		}

		toast(t('account_tokens.revoked', 'Revoked {name}', { name: token.name }), { kind: 'danger' });
	});
}

async function disconnect(app: ConnectedApp): Promise<void> {
	if (!await confirmAction({
		title: t('account_tokens.disconnect_title', 'Disconnect {name}?', { name: app.name }),
		body: t('account_tokens.disconnect_body', 'It stops working at once, and connecting it again asks for approval again.'),
		confirm: t('account_tokens.disconnect', 'Disconnect'),
		danger: true
	})) {
		return;
	}

	await runRevoke(t('account_tokens.disconnect_failed', 'The app couldn\'t be disconnected.'), async () => {
		await disconnectApp(props.account.username, app.id);

		list.value = list.value ? { ...list.value, apps: list.value.apps.filter((item) => item.id !== app.id) } : list.value;
		toast(t('account_tokens.disconnected', 'Disconnected {name}', { name: app.name }), { kind: 'danger' });
	});
}

async function revokeAll(): Promise<void> {
	const count = (list.value?.tokens.length ?? 0) + (list.value?.apps.length ?? 0);

	if (!await confirmAction({
		title: t('account_tokens.revoke_all_title', 'Revoke {count, plural, one {# Token} other {# Tokens}}?', { count }),
		body: [t('account_tokens.revoke_all_body', 'Every app, script, and agent using {mine, select, yes {your} other {this account\'s}} tokens stops working at once.', { mine: props.mine ? 'yes' : 'no' }), t('account_tokens.revoke_all_leak', 'For a token that may have leaked: a new password doesn\'t revoke tokens.')],
		confirm: t('account_tokens.revoke_all', 'Revoke All'),
		danger: true
	})) {
		return;
	}

	await runRevoke(t('account_tokens.revoke_all_failed', 'The tokens couldn\'t be revoked.'), async () => {
		const revoked = await revokeAllTokens(props.account.username);

		list.value  = list.value ? { ...list.value, tokens: [], apps: [] } : list.value;
		fresh.value = null;
		toast(t('account_tokens.revoked_count', 'Revoked {count, plural, one {# token} other {# tokens}}', { count: revoked }), { kind: 'danger' });
	});
}
</script>

<template>
	<section v-if="shown" class="panel" aria-labelledby="tokens-heading">
		<header class="panel__header">
			<h2 id="tokens-heading">{{ t('account_tokens.title', 'API Tokens') }}</h2>
			<p class="panel__hint">{{ t('account_tokens.hint', 'For apps, scripts, and agents that use the API') }}</p>
			<div v-if="list && (list.make || list.tokens.length || list.apps.length)" class="panel__actions">
				<button v-if="list.tokens.length || list.apps.length" type="button" class="button button--small" :disabled="revoking" @click="revokeAll">{{ t('account_tokens.revoke_all', 'Revoke All') }}</button>
				<button v-if="list.make" type="button" class="button button--primary button--small" @click="startNew"><AdminIcon name="plus" />{{ t('account_tokens.new_token', 'New Token') }}</button>
			</div>
		</header>

		<div v-if="fresh" class="panel__body">
			<ShownOnce :value="fresh.secret" :label="t('account_tokens.shown_label', 'API token')" :noun="t('account_tokens.shown_noun', 'the token')" :copy="t('account_tokens.copy_token', 'Copy Token')">
				<MessageText :text="fresh.token.expires ? t('account_tokens.shown_once_until', '{copy} It\'s shown once, and works until {date}. Send it as {header} and the token.', { date: when(fresh.token.expires) }) : t('account_tokens.shown_once_revoked', '{copy} It\'s shown once, and works until it\'s revoked. Send it as {header} and the token.')">
					<template #copy><strong>{{ t('account_tokens.copy_now', 'Copy {name} now.', { name: fresh.token.name }) }}</strong></template>
					<template #header><code>Authorization: Bearer</code></template>
				</MessageText>
			</ShownOnce>
		</div>

		<p v-if="error" class="panel__body field__error" role="alert">{{ error }}</p>
		<p v-if="revokeError" class="panel__body field__error" role="alert">{{ revokeError }}</p>

		<div v-if="list && list.tokens.length" class="table-wrap">
			<table class="table" aria-labelledby="tokens-heading">
				<thead>
					<tr>
						<th scope="col">{{ t('account_tokens.name', 'Name') }}</th>
						<th scope="col">{{ t('account_tokens.can', 'Can') }}</th>
						<th scope="col">{{ t('account_tokens.last_used', 'Last used') }}</th>
						<th scope="col">{{ t('account_tokens.expires', 'Expires') }}</th>
						<th scope="col"><span class="visually-hidden">{{ t('account_tokens.actions', 'Actions') }}</span></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="token in list.tokens" :key="token.id">
						<th scope="row">
							<span class="entry-title">
								<span class="entry-title__text">{{ token.name }}</span>
								<span class="entry-title__path">{{ token.createdBy ? t('account_tokens.made_by', 'Made {date} by {name}', { date: when(token.created, true), name: token.createdBy.displayName }) : t('account_tokens.made_cli', 'Made {date} on the command line', { date: when(token.created, true) }) }}</span>
							</span>
						</th>
						<td>{{ tokenScope(token.capabilities) }}</td>
						<td>{{ when(token.lastUsed, true) }}</td>
						<td>
							<span v-if="token.expired" class="pill pill--danger">{{ t('account_tokens.expired', 'Expired') }}</span>
							<template v-else>{{ token.expires ? when(token.expires, true) : t('account_tokens.never', 'Never') }}</template>
						</td>
						<td class="table__buttons"><button type="button" class="button button--ghost button--small" :disabled="revoking" @click="revoke(token)">{{ t('account_tokens.revoke', 'Revoke') }}<span class="visually-hidden"> {{ token.name }}</span></button></td>
					</tr>
				</tbody>
			</table>
		</div>
		<p v-else-if="list && !list.apps.length" class="panel__body muted">{{ t('account_tokens.no_tokens', 'No tokens yet.') }}</p>

		<template v-if="list && list.apps.length">
			<div class="panel__section panel__section--turn">
				<div class="panel__section-head"><h3 id="apps-heading" class="eyebrow">{{ t('account_tokens.connected_apps', 'Connected Apps') }}</h3><span>{{ t('account_tokens.apps_hint', 'Apps approved to act as {whose} through the MCP server', { whose }) }}</span></div>
			</div>
			<ul class="rows" aria-labelledby="apps-heading">
				<li v-for="app in list.apps" :key="app.id" class="rows__item">
					<div class="rows__main">
						<span class="rows__title">{{ app.name }}<span v-if="app.host" class="rows__sub"> · {{ app.host }}</span></span>
						<span class="rows__sub">
							{{ APP_SCOPES[app.scope] }} · {{ t('account_tokens.approved', 'approved {date}', { date: when(app.approved, true) }) }} · {{ app.connected ? t('account_tokens.last_connected', 'last connected {date}', { date: when(app.connected, true) }) : t('account_tokens.not_connected', 'not connected yet') }}
						</span>
					</div>
					<div class="rows__side">
						<RouterLink v-if="can('site.activity')" class="button button--ghost button--small" :to="{ name: 'tools', query: { tab: 'activity', client: app.name, account: account.username } }">{{ t('account_tokens.activity', 'Activity') }}</RouterLink>
						<button type="button" class="button button--ghost button--small" :disabled="revoking" @click="disconnect(app)">{{ t('account_tokens.disconnect', 'Disconnect') }}<span class="visually-hidden"> {{ app.name }}</span></button>
					</div>
				</li>
			</ul>
		</template>

		<p class="panel__note">
			{{ t('account_tokens.note', 'A token acts as {whose}, with {mine, select, yes {your} other {its} } roles as they are on each request{suspended, select, yes {, so this account\'s don\'t work while it\'s suspended} other {} }. Revoking stops one at once; a new password doesn\'t.', { whose, mine: mine ? 'yes' : 'no', suspended: account.status === 'suspended' ? 'yes' : 'no' }) }}
		</p>
	</section>

	<AdminModal :open="making" :title="t('account_tokens.new_title', 'New API Token')" :wide="scope === 'chosen'" @close="making = false">
		<form id="new-token" class="form-stack" @submit.prevent="make">
			<TokenFields v-model:name="name" v-model:days="days" v-model:scope="scope" :choose="list?.capabilities.length ? (mine ? t('account_tokens.choose_you', 'you') : t('account_tokens.choose_account', 'the account')) : undefined" :invalid="field" />
			<CapabilitySections v-if="scope === 'chosen' && list" v-model="chosen" :capabilities="list.capabilities" :types="list.types" />
		</form>
		<p v-if="makeError" class="field__error" role="alert">{{ makeError }}</p>
		<template #footer>
			<button type="button" class="button" @click="making = false">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="submit" form="new-token" class="button button--primary" :disabled="busy || name.trim() === ''">{{ busy ? t('account_tokens.making', 'Making…') : t('account_tokens.make', 'Make the Token') }}</button>
		</template>
	</AdminModal>
</template>
