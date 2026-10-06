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
 * password link; **New account** is here.
 */

import { computed, ref } from 'vue';
import { confirmAction } from '../confirm';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import MenuButton from '../components/MenuButton.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import StatusPill from '../components/StatusPill.vue';
import { errorMessage } from '../api';
import { plural } from '../format';
import { freshLink, initials, loadAccounts, loadRoles, makePasswordLink, statusPill, when, type AccountInfo, type AccountStatus } from '../people';
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
	accounts.value = [...list].sort((a, b) => a.displayName.localeCompare(b.displayName, undefined, { sensitivity: 'base' }));
}, (caught: unknown) => {
	error.value = errorMessage(caught, 'The accounts couldn\'t be loaded.');
});

const STATUSES: AccountStatus[] = ['active', 'invited', 'suspended'];

const tab = computed<'all' | AccountStatus>(() => STATUSES.find((status) => status === route.query.status) ?? 'all');

const tabs = computed(() => {
	const all = accounts.value ?? [];

	return [
		{ key: 'all' as const, label: 'All', count: all.length },
		...STATUSES.map((status) => ({ key: status, label: statusPill(status).label, count: all.filter((account) => account.status === status).length }))
	];
});

const roleOptions = computed<SelectOption[]>(() => [
	{ value: '', label: 'Any role' },
	...Object.entries(labels.value).sort(([, a], [, b]) => a.localeCompare(b)).map(([value, label]) => ({ value, label }))
]);

const profileOptions: SelectOption[] = [
	{ value: '', label: 'Any profile' },
	{ value: 'linked', label: 'Has a profile' },
	{ value: 'none', label: 'No profile' }
];

const filtered = computed(() => search.value.trim() !== '' || role.value !== '' || profile.value !== '');

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return (accounts.value ?? []).filter((account) => (tab.value === 'all' || account.status === tab.value)
		&& (role.value === '' || account.roles.includes(role.value))
		&& (profile.value === '' || (profile.value === 'linked') === (account.author !== null))
		&& (words === '' || `${account.displayName} ${account.username} ${account.email ?? ''} ${account.author ?? ''} ${account.profile?.title ?? ''}`.toLowerCase().includes(words)));
});

// What the filters in force narrow the list to, in words.
const report = computed(() => {
	const parts = [
		search.value.trim() === '' ? '' : `matching “${search.value.trim()}”`,
		role.value === '' ? '' : `with the ${labels.value[role.value] ?? role.value} role`,
		profile.value === 'linked' ? 'that have a profile' : (profile.value === 'none' ? 'with no profile' : '')
	].filter((part) => part !== '');

	return `Showing accounts ${parts.join(', ')}.`;
});

function clear(): void {
	search.value  = '';
	role.value    = '';
	profile.value = '';
}

const hasName = (account: AccountInfo): boolean => account.displayName !== account.username;

async function copyEmail(email: string): Promise<void> {
	await copyText(email, 'the email address', email);
}
const mine    = (account: AccountInfo): boolean => account.username === session.account?.username;

// Makes a password link, then shows it on the account's screen.
async function makeLink(account: AccountInfo): Promise<void> {
	if (account.link !== null && !account.link.expired && !await confirmAction({ title: `Make a New Link for ${account.displayName}?`, body: 'The one they have stops working.', confirm: 'Make a new link' })) {
		return;
	}

	try {
		const answer = await makePasswordLink(account.username);

		freshLink.value = { username: account.username, link: answer.link };
		await router.push({ name: 'account', params: { username: account.username } });
	} catch (caught) {
		toast(errorMessage(caught, 'The link couldn\'t be made.'), { kind: 'warn' });
	}
}
</script>

<template>
	<div class="people">
		<header class="page-header">
			<div class="page-header__text">
				<h1 tabindex="-1">Accounts</h1>
				<p class="page-header__hint">Users who can sign in. A public presence is a separate, optional thing: a profile.</p>
			</div>
			<div class="page-header__actions">
				<RouterLink v-if="can('accounts.create')" class="button button--primary" :to="{ name: 'account-new' }"><AdminIcon name="plus" />New account</RouterLink>
			</div>
		</header>

		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<template v-if="!error">
			<nav class="status-tabs" aria-label="Standing">
				<RouterLink v-for="item in tabs" :key="item.key" class="status-tabs__tab" :to="{ query: item.key === 'all' ? {} : { status: item.key } }" :aria-current="tab === item.key ? 'page' : undefined">
					{{ item.label }}
					<span v-if="accounts" class="status-tabs__count">{{ item.count }}</span>
				</RouterLink>
			</nav>

			<div class="toolbar" role="search">
				<label class="search-field">
					<AdminIcon name="search" />
					<span class="visually-hidden">Search accounts</span>
					<input v-model="search" type="search" placeholder="Search accounts" autocomplete="off">
				</label>
				<div class="toolbar__filter accounts-filter">
					<label class="visually-hidden" for="accounts-role">Role</label>
					<AdminSelect id="accounts-role" v-model="role" :options="roleOptions" />
				</div>
				<div v-if="profileType" class="toolbar__filter accounts-filter">
					<label class="visually-hidden" for="accounts-profile">Profile</label>
					<AdminSelect id="accounts-profile" v-model="profile" :options="profileOptions" />
				</div>
				<span class="toolbar__count" aria-live="polite">{{ accounts ? plural(shown.length, 'account', 'accounts') : 'Loading…' }}</span>
			</div>

			<p v-if="filtered" class="filter-report">
				{{ report }}
				<button type="button" class="button button--ghost button--small" @click="clear">Clear filters</button>
			</p>

			<section class="panel" aria-labelledby="accounts-heading" :aria-busy="accounts === null">
				<h2 id="accounts-heading" class="visually-hidden">{{ tabs.find((item) => item.key === tab)?.label }} accounts</h2>
				<SkeletonTable v-if="accounts === null" :columns="['Account', 'Email', 'Roles', 'Profile', 'Last signed in']" :rows="3" label="Loading the accounts…" />
				<EmptyState v-else-if="shown.length === 0" :icon="filtered ? 'search' : 'key-round'" :heading="filtered ? 'No Account Matches' : `No ${tab === 'all' ? '' : statusPill(tab).label + ' '}Accounts`" :text="filtered ? 'Nothing here fits the filters in force. Clearing them brings the other accounts back.' : 'Nobody is in that state right now. The All tab shows every account, whatever its standing.'">
					<template #actions>
						<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
						<RouterLink v-else-if="tab !== 'all'" class="button" :to="{ query: {} }">Show all accounts</RouterLink>
					</template>
				</EmptyState>
				<div v-else class="table-wrap">
					<table class="table accounts-table" aria-labelledby="accounts-heading">
						<thead>
							<tr>
								<th scope="col">Account</th>
								<th scope="col">Email</th>
								<th scope="col">Roles</th>
								<th v-if="profileType" scope="col">Profile</th>
								<th scope="col">Last signed in</th>
								<th scope="col" class="table__actions"><span class="visually-hidden">Actions</span></th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="account in shown" :key="account.username">
								<th scope="row">
									<span class="who">
										<span class="avatar" :class="{ 'avatar--guest': !account.profile }" aria-hidden="true">{{ hasName(account) ? initials(account.displayName) : '—' }}</span>
										<span class="who__text">
											<span class="account-cell__name">
												<RouterLink class="account-cell__link" :to="{ name: 'account', params: { username: account.username } }">{{ account.displayName }}</RouterLink>
												<span v-if="mine(account)" class="tag--you">You</span>
												<span v-if="account.status !== 'active'" class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
											</span>
											<span v-if="hasName(account)" class="who__meta">{{ account.username }}</span>
											<span v-else class="account-cell__none">No display name or profile</span>
										</span>
									</span>
								</th>
								<td class="muted">
									<template v-if="account.email">{{ account.email }}</template>
									<span v-else class="pill pill--warn" title="Every account needs an email address">No email</span>
								</td>
								<td class="muted">{{ account.roles.map((name) => labels[name] ?? name).join(', ') || '—' }}</td>
								<td v-if="profileType">
									<template v-if="account.profile">
										<RouterLink v-if="canType(profileType, 'edit')" class="lnk" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
										<template v-else>{{ account.profile.title || account.profile.slug }}</template>
										{{ ' ' }}<StatusPill v-if="account.profile.status !== 'published'" :status="account.profile.status" />
									</template>
									<template v-else-if="account.author">
										<span class="mono">{{ account.author }}</span>
										{{ ' ' }}<span class="tag" title="Entries may credit this slug, but there's no profile file yet">No file yet</span>
									</template>
									<span v-else class="muted">None</span>
								</td>
								<td class="muted">{{ when(account.lastLogin) }}</td>
								<td class="table__actions">
									<MenuButton button-class="row-more" :label="`Actions for ${account.displayName}`" floating>
										<template #button>
											<AdminIcon name="ellipsis" />
										</template>
										<RouterLink class="menu-item" :to="{ name: 'account', params: { username: account.username } }"><AdminIcon name="key-round" />Open account</RouterLink>
										<RouterLink v-if="account.profile && profileType && canType(profileType, 'edit')" class="menu-item" :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }"><AdminIcon name="user-round" />Open profile</RouterLink>
										<RouterLink v-else-if="!account.author && account.manages && can('accounts.edit')" class="menu-item" :to="{ name: 'account', params: { username: account.username } }"><AdminIcon name="plus" />Create a profile</RouterLink>
										<button v-if="account.email" type="button" class="menu-item" @click="copyEmail(account.email)"><AdminIcon name="copy" />Copy email address</button>
										<button v-if="account.manages && can('accounts.edit') && account.status !== 'suspended'" type="button" class="menu-item" @click="makeLink(account)"><AdminIcon name="key-round" />{{ account.link ? 'Make a new password link' : 'Make a password link' }}</button>
									</MenuButton>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p class="panel__note">An account's display name is what the admin calls it; without one, it's the profile's title, then the username.</p>
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
	color: var(--fg);
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: var(--title-weight);
	letter-spacing: var(--title-track);
	text-decoration: none;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.account-cell__link:hover {
	color: var(--accent);
}

.account-cell__none {
	color: var(--fg-3);
	font-size: var(--text-xs);
}
</style>
