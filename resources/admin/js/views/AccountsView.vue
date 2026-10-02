<script setup lang="ts">
/**
 * Accounts (D-353): the people who can sign in, by name. A public
 * presence is a separate thing, a profile, so the Profile column shows
 * the link from this side: the profile's name (with its status when it
 * isn't live, since nothing shows on the site until it is), the slug of
 * one credited without a file, or none. Guests (profiles with no
 * account) aren't here; they're under Profiles.
 *
 * Tabs split accounts by status, and a search narrows by name,
 * username, or profile. **New Account** is here.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import StatusPill from '../components/StatusPill.vue';
import { ApiError } from '../api';
import { loadAccounts, loadRoles, statusPill, when, type AccountInfo, type AccountStatus } from '../people';
import { session } from '../session';

const accounts = ref<AccountInfo[] | null>(null);
const labels   = ref<Record<string, string>>({});
const error    = ref('');
const tab      = ref<'all' | AccountStatus>('all');
const search   = ref('');

Promise.all([loadAccounts(), loadRoles()]).then(([list, roles]) => {
	labels.value   = Object.fromEntries(roles.roles.map((role) => [role.name, role.label]));
	accounts.value = [...list].sort((a, b) => a.displayName.localeCompare(b.displayName, undefined, { sensitivity: 'base' }));
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The accounts couldn\'t be loaded.';
});

const tabs = computed(() => {
	const all = accounts.value ?? [];

	return [
		{ key: 'all' as const, label: 'All', count: all.length },
		...(['active', 'invited', 'suspended'] as const).map((status) => ({ key: status, label: statusPill(status).label, count: all.filter((account) => account.status === status).length }))
	];
});

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return (accounts.value ?? []).filter((account) => (tab.value === 'all' || account.status === tab.value)
		&& (words === '' || `${account.displayName} ${account.username} ${account.author ?? ''} ${account.profile?.title ?? ''}`.toLowerCase().includes(words)));
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Accounts</h1>
			<p class="page-header__hint">People who can sign in. A public presence is a separate, optional thing: a profile.</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'account-new' }"><AdminIcon name="plus" />New Account</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!error">
		<section class="panel" aria-labelledby="accounts-heading" :aria-busy="accounts === null">
			<header class="panel__header">
				<h2 id="accounts-heading" class="visually-hidden">Accounts</h2>
				<nav class="tabs" aria-label="Status">
					<button v-for="item in tabs" :key="item.key" type="button" class="tabs__tab" :aria-pressed="tab === item.key" @click="tab = item.key">
						{{ item.label }} <span class="tabs__count">{{ item.count }}</span>
					</button>
				</nav>
				<label class="search">
					<AdminIcon name="search" />
					<span class="visually-hidden">Search accounts</span>
					<input v-model="search" type="search" placeholder="Search names, usernames, and profiles…" autocomplete="off">
				</label>
			</header>
			<SkeletonTable v-if="accounts === null" :columns="['Account', 'Roles', 'Profile', 'Last signed in']" :rows="3" label="Loading the accounts…" />
			<div v-else-if="shown.length === 0" class="empty">
				<AdminIcon name="key-round" />
				<p class="empty__text">{{ search || tab !== 'all' ? 'No accounts match the search and tab.' : 'No accounts yet.' }}</p>
				<button v-if="search || tab !== 'all'" type="button" class="button" @click="search = ''; tab = 'all'">Clear filters</button>
			</div>
			<div v-else class="table-wrap">
				<table class="table" aria-labelledby="accounts-heading">
					<thead>
						<tr>
							<th scope="col">Account</th>
							<th scope="col">Roles</th>
							<th scope="col">Profile</th>
							<th scope="col">Last signed in</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="account in shown" :key="account.username">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink class="entry-title__link" :to="{ name: 'account', params: { username: account.username } }">{{ account.displayName }}</RouterLink>
										{{ ' ' }}<span v-if="account.username === session.account?.username" class="tag">You</span>
										{{ ' ' }}<span v-if="account.status !== 'active'" class="pill" :class="statusPill(account.status).kind">{{ statusPill(account.status).label }}</span>
									</span>
									<span class="entry-title__path">{{ account.username }}</span>
								</span>
							</th>
							<td>{{ account.roles.map((name) => labels[name] ?? name).join(', ') || '—' }}</td>
							<td>
								<template v-if="account.profile">
									<RouterLink :to="{ name: 'profile-detail', params: { slug: account.profile.slug } }">{{ account.profile.title || account.profile.slug }}</RouterLink>
									{{ ' ' }}<StatusPill v-if="account.profile.status !== 'published'" :status="account.profile.status" />
								</template>
								<template v-else-if="account.author">
									<span class="mono">{{ account.author }}</span>
									{{ ' ' }}<span class="tag" title="Entries may credit this slug, but there's no profile file yet">No profile yet</span>
								</template>
								<template v-else>None</template>
							</td>
							<td>{{ when(account.lastLogin) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</template>
</template>

<style scoped>
.panel__header {
	flex-wrap: wrap;
}

.tabs {
	display: flex;
	gap: 4px;
}

.tabs__tab {
	padding: 5px 12px;
	border: 1px solid transparent;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font-weight: 500;
	cursor: pointer;
}

.tabs__tab:hover {
	color: var(--fg);
}

.tabs__tab[aria-pressed="true"] {
	border-color: var(--border);
	background: var(--surface-2);
	color: var(--fg);
}

.tabs__count {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.search {
	display: flex;
	align-items: center;
	gap: 7px;
	height: 30px;
	margin-left: auto;
	padding: 0 9px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	color: var(--fg-3);
}

.search:focus-within {
	border-color: var(--accent);
}

.search input {
	width: 14rem;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}
</style>
