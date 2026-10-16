<script setup lang="ts">
/**
 * Accounts (D-353; the profiles sketch's list, D-369): the people who
 * can sign in. A public presence is a separate thing, a profile, so the
 * Profile column shows the link from this side: the profile's name (with
 * its status when it isn't live, since nothing shows on the site until
 * it is), the slug of one credited without a file, or none. Guests
 * (profiles with no account) aren't here; they're under Profiles.
 *
 * It has the entries list's shape (§7): status tabs under the page
 * header (`?status=`), then one row of filters (a search over names,
 * usernames, emails, and profiles; a role; whether there's a profile) with the
 * count, and a line saying which filters are in force. An account's name
 * is its profile's title, so one without a profile shows its username,
 * in a dashed avatar. Each row's ⋮ opens it, its profile, or makes a
 * password link; **New account** is here. Your own account, when the
 * filters leave it, is pinned above the rest, as a type's index page is
 * on its entries list (D-604).
 */

import { computed, ref } from 'vue';
import { confirmAction } from '../confirm';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminAvatar from '../components/AdminAvatar.vue';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import MenuButton from '../components/MenuButton.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import StatusPill from '../components/StatusPill.vue';
import { errorMessage } from '../api';
import { locale, t } from '../messages';
import { freshLink, loadAccounts, loadRoles, makePasswordLink, statusPill, when, type AccountInfo, type AccountStatus } from '../people';
import { can, canType, session } from '../session';
import { copyText, toast } from '../toast';
import { profileType } from '../types';

const route    = useRoute();
const router   = useRouter();
const accounts = ref<AccountInfo[] | null>(null);
const labels   = ref<Record<string, string>>({});
const error    = ref('');
const search   = ref('');
const role     = ref('');
const profile  = ref<'' | 'linked' | 'none'>('');

Promise.all([loadAccounts(), loadRoles()]).then(([list, roles]) => {
	labels.value   = Object.fromEntries(roles.roles.map((item) => [item.name, item.label]));
	accounts.value = [...list].sort((a, b) => a.displayName.localeCompare(b.displayName, locale(), { sensitivity: 'base' }));
}, (caught: unknown) => {
	error.value = errorMessage(caught, t('accounts_view.load_failed', 'The accounts couldn\'t be loaded.'));
});

const STATUSES: AccountStatus[] = ['active', 'invited', 'suspended'];

const tab = computed<'all' | AccountStatus>(() => STATUSES.find((status) => status === route.query.status) ?? 'all');

const tabs = computed(() => {
	const all = accounts.value ?? [];

	return [
		{ key: 'all' as const, label: t('accounts_view.tab_all', 'All'), count: all.length },
		...STATUSES.map((status) => ({ key: status, label: statusPill(status).label, count: all.filter((account) => account.status === status).length }))
	];
});

const roleOptions = computed<SelectOption[]>(() => [
	{ value: '', label: t('accounts_view.any_role', 'Any role') },
	...Object.entries(labels.value).sort(([, a], [, b]) => a.localeCompare(b, locale())).map(([value, label]) => ({ value, label }))
]);

const profileOptions: SelectOption[] = [
	{ value: '', label: t('accounts_view.any_profile', 'Any profile') },
	{ value: 'linked', label: t('accounts_view.has_profile', 'Has a profile') },
	{ value: 'none', label: t('accounts_view.no_profile', 'No profile') }
];

const filtered = computed(() => search.value.trim() !== '' || role.value !== '' || profile.value !== '');

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return (accounts.value ?? []).filter((account) => (tab.value === 'all' || account.status === tab.value)
		&& (role.value === '' || account.roles.includes(role.value))
		&& (profile.value === '' || (profile.value === 'linked') === (account.author !== null))
		&& (words === '' || `${account.displayName} ${account.username} ${account.email ?? ''} ${account.author ?? ''} ${account.profile?.title ?? ''}`.toLowerCase().includes(words)));
});

// Your own account pinned above the rest (D-604), each group its own body.
const groups = computed(() => [
	{ key: 'pinned', accounts: shown.value.filter((account) => mine(account)) },
	{ key: 'rest', accounts: shown.value.filter((account) => !mine(account)) }
].filter((group) => group.accounts.length > 0));

// What the filters in force narrow the list to, in words.
const report = computed(() => {
	const parts = [
		search.value.trim() === '' ? '' : t('accounts_view.report_matching', 'matching “{search}”', { search: search.value.trim() }),
		role.value === '' ? '' : t('accounts_view.report_role', 'with the {role} role', { role: labels.value[role.value] ?? role.value }),
		profile.value === 'linked' ? t('accounts_view.report_profile', 'that have a profile') : (profile.value === 'none' ? t('accounts_view.report_no_profile', 'with no profile') : '')
	].filter((part) => part !== '');

	return t('accounts_view.report', 'Showing accounts {filters}.', { filters: parts.join(', ') });
});

function clear(): void {
	search.value  = '';
	role.value    = '';
	profile.value = '';
}

const hasName = (account: AccountInfo): boolean => account.displayName !== account.username;

async function copyEmail(email: string): Promise<void> {
	await copyText(email, t('accounts_view.copied_email', 'Copied {email}', { email }));
}
const mine    = (account: AccountInfo): boolean => account.username === session.account?.username;

// Makes a password link, then shows it on the account's screen.
async function makeLink(account: AccountInfo): Promise<void> {
	if (account.link !== null && !account.link.expired && !await confirmAction({ title: t('accounts_view.new_link_title', 'Make a New Link for {name}?', { name: account.displayName }), body: t('accounts_view.new_link_body', 'The one they have stops working.'), confirm: t('accounts_view.new_link_confirm', 'Make a New Link') })) {
		return;
	}

	try {
		const answer = await makePasswordLink(account.username);

		freshLink.value = { username: account.username, link: answer.link };
		await router.push({ name: 'account', params: { username: account.username } });
	} catch (caught) {
		toast(errorMessage(caught, t('accounts_view.link_failed', 'The link couldn\'t be made.')), { kind: 'warn' });
	}
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<div class="page-header__text">
				<h1 tabindex="-1">{{ t('accounts_view.title', 'Accounts') }}</h1>
				<p class="page-header__hint">{{ t('accounts_view.hint', 'Users who can sign in. A public presence is a separate, optional thing: a profile.') }}</p>
			</div>
			<div class="page-header__actions">
				<RouterLink v-if="can('accounts.create')" class="button button--primary" :to="{ name: 'account-new' }"><AdminIcon name="plus" />{{ t('accounts_view.new', 'New Account') }}</RouterLink>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<template v-if="!error">
			<nav class="status-tabs" :aria-label="t('accounts_view.standing', 'Standing')">
				<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'all' ? {} : { status: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">
					{{ item.label }}
					<span v-if="accounts" class="status-tabs__count">{{ item.count }}</span>
				</RouterLink>
			</nav>

			<div class="toolbar" role="search">
				<label class="search-field">
					<AdminIcon name="search" />
					<span class="visually-hidden">{{ t('accounts_view.search', 'Search accounts') }}</span>
					<input v-model="search" type="search" :placeholder="t('accounts_view.search', 'Search accounts')" autocomplete="off">
				</label>
				<div class="toolbar__filter accounts-filter">
					<label class="visually-hidden" for="accounts-role">{{ t('accounts_view.role', 'Role') }}</label>
					<AdminSelect id="accounts-role" v-model="role" :options="roleOptions" />
				</div>
				<div v-if="profileType" class="toolbar__filter accounts-filter">
					<label class="visually-hidden" for="accounts-profile">{{ t('accounts_view.profile', 'Profile') }}</label>
					<AdminSelect id="accounts-profile" v-model="profile" :options="profileOptions" />
				</div>
				<span class="toolbar__count" aria-live="polite">{{ accounts ? t('accounts_view.count', '{count, plural, one {# account} other {# accounts} }', { count: shown.length }) : t('common.loading', 'Loading…') }}</span>
			</div>

			<p v-if="filtered" class="filter-report">
				{{ report }}
				<button type="button" class="button button--ghost button--small" @click="clear">{{ t('accounts_view.clear_filters', 'Clear Filters') }}</button>
			</p>

			<section class="panel" aria-labelledby="accounts-heading" :aria-busy="accounts === null">
				<h2 id="accounts-heading" class="visually-hidden">{{ t('accounts_view.tab_heading', '{tab} accounts', { tab: tabs.find((item) => item.key === tab)?.label }) }}</h2>
				<SkeletonTable v-if="accounts === null" :columns="[t('accounts_view.col_account', 'Account'), t('accounts_view.col_email', 'Email'), t('accounts_view.col_roles', 'Roles'), t('accounts_view.profile', 'Profile'), t('accounts_view.col_last_login', 'Last signed in')]" :rows="3" :label="t('accounts_view.loading', 'Loading the accounts…')" />
				<EmptyState v-else-if="shown.length === 0" :icon="filtered ? 'search' : 'key-round'" :heading="filtered ? t('accounts_view.no_match', 'No Account Matches') : t('accounts_view.none', '{status, select, active {No Active Accounts} invited {No Invited Accounts} suspended {No Suspended Accounts} other {No Accounts} }', { status: tab })" :text="filtered ? t('accounts_view.no_match_text', 'Nothing here fits the filters in force. Clearing them brings the other accounts back.') : t('accounts_view.none_text', 'Nobody is in that state right now. The All tab shows every account, whatever its standing.')">
					<template #actions>
						<button v-if="filtered" type="button" class="button" @click="clear">{{ t('accounts_view.clear_filters', 'Clear Filters') }}</button>
						<RouterLink v-else-if="tab !== 'all'" class="button" :to="{ query: {} }">{{ t('accounts_view.show_all', 'Show All Accounts') }}</RouterLink>
					</template>
				</EmptyState>
				<div v-else class="table-wrap">
					<table class="table accounts-table" aria-labelledby="accounts-heading">
						<thead>
							<tr>
								<th scope="col">{{ t('accounts_view.col_account', 'Account') }}</th>
								<th scope="col">{{ t('accounts_view.col_email', 'Email') }}</th>
								<th scope="col">{{ t('accounts_view.col_roles', 'Roles') }}</th>
								<th v-if="profileType" scope="col">{{ t('accounts_view.profile', 'Profile') }}</th>
								<th scope="col">{{ t('accounts_view.col_last_login', 'Last signed in') }}</th>
								<th scope="col" class="table__actions"><span class="visually-hidden">{{ t('accounts_view.col_actions', 'Actions') }}</span></th>
							</tr>
						</thead>
						<tbody v-for="group in groups" :key="group.key" :class="{ 'table__pinned': group.key === 'pinned' }">
							<tr v-for="account in group.accounts" :key="account.username">
								<th scope="row">
									<span class="who">
										<AdminAvatar :class="{ 'avatar--guest': !account.profile }" :name="hasName(account) ? account.displayName : '—'" :image="account.avatar" />
										<span class="who__text">
											<span class="account-cell__name">
												<RouterLink class="account-cell__link" :to="{ name: 'account', params: { username: account.username } }">{{ account.displayName }}</RouterLink>
												<span v-if="mine(account)" class="tag--you">{{ t('accounts_view.you', 'You') }}</span>
												<span v-if="account.status !== 'active'" class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
												<span v-if="account.tokenOnly" class="pill">{{ t('accounts_view.token_only', 'Token-only') }}</span>
											</span>
											<span v-if="hasName(account)" class="who__meta">{{ account.username }}</span>
											<span v-else class="account-cell__none">{{ t('accounts_view.no_name', 'No display name or profile') }}</span>
										</span>
									</span>
								</th>
								<td class="muted">
									<template v-if="account.email">{{ account.email }}</template>
									<span v-else class="pill pill--warn" :title="t('accounts_view.needs_email', 'Every account needs an email address')">{{ t('accounts_view.no_email', 'No email') }}</span>
								</td>
								<td class="muted">{{ account.roles.map((name) => labels[name] ?? name).join(', ') || '—' }}</td>
								<td v-if="profileType">
									<template v-if="account.profile">
										<RouterLink v-if="canType(profileType, 'edit')" class="lnk" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
										<template v-else>{{ account.profile.title || account.profile.slug }}</template>
										{{ ' ' }}<StatusPill v-if="account.profile.status !== 'published'" :status="account.profile.status" />
									</template>
									<span v-else class="muted">{{ t('accounts_view.none_profile', 'None') }}</span>
								</td>
								<td class="muted">{{ when(account.lastLogin, true) }}</td>
								<td class="table__actions">
									<MenuButton button-class="row-more" :label="t('accounts_view.actions_for', 'Actions for {name}', { name: account.displayName })" floating>
										<template #button>
											<AdminIcon name="ellipsis" />
										</template>
										<RouterLink class="menu-item" :to="{ name: 'account', params: { username: account.username } }"><AdminIcon name="key-round" />{{ t('accounts_view.open_account', 'Open account') }}</RouterLink>
										<RouterLink v-if="account.profile && profileType && canType(profileType, 'edit')" class="menu-item" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }"><AdminIcon name="user-round" />{{ t('accounts_view.open_profile', 'Open profile') }}</RouterLink>
										<RouterLink v-else-if="!account.author && account.manages && can('accounts.edit')" class="menu-item" :to="{ name: 'account', params: { username: account.username } }"><AdminIcon name="plus" />{{ t('accounts_view.create_profile', 'Create a profile') }}</RouterLink>
										<button v-if="account.email" type="button" class="menu-item" @click="copyEmail(account.email)"><AdminIcon name="copy" />{{ t('accounts_view.copy_email', 'Copy email address') }}</button>
										<button v-if="account.manages && can('accounts.edit') && account.status !== 'suspended' && !account.tokenOnly" type="button" class="menu-item" @click="makeLink(account)"><AdminIcon name="key-round" />{{ account.link ? t('accounts_view.make_new_link', 'Make a new password link') : t('accounts_view.make_link', 'Make a password link') }}</button>
									</MenuButton>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p class="panel__note">{{ t('accounts_view.display_name_note', 'An account\'s display name is what the admin calls it; without one, it\'s the profile\'s title, then the username.') }}</p>
			</section>
		</template>
	</div>
</template>

<style scoped>
/* A filter's select is as wide as it needs, not the row (§7, Selects),
   from a little narrower than the global's floor. */
.accounts-filter {
	min-width: 8rem;
}

.accounts-table {
	min-width: 760px;
}

.account-cell__name {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	min-width: 0;
}

.account-cell__link {
	overflow: hidden;
	color: var(--color-text);
	font-family: var(--font-family-title);
	font-size: var(--font-size-entry-title);
	font-weight: var(--font-weight-entry-title);
	letter-spacing: var(--letter-spacing-entry-title);
	text-decoration: none;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.account-cell__link:hover {
	color: var(--color-accent);
}

.account-cell__none {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}
</style>
