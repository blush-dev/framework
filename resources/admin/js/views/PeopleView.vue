<script setup lang="ts">
/**
 * People (D-329): accounts and authors as one list, since an account's
 * public side is its author page. Each row is a person, by name: one
 * with an account (and usually an author page), a guest author with no
 * account, or an author credited without a page yet. A person's name
 * opens their account, for whoever manages accounts, or else their
 * author page in the editor (admin.md §8, List, then detail).
 *
 * Tabs split those with accounts from guests when you can see accounts,
 * and a search narrows by name, username, or slug. **New Account** and
 * **New Author** are here, for whoever may make them.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import StatusPill from '../components/StatusPill.vue';
import { ApiError, entryRoute } from '../api';
import { plural } from '../format';
import { loadPeople, loadRoles, statusPill, when, type PersonInfo } from '../people';
import { can, session } from '../session';
import { authorType, loadTypes } from '../types';

const people = ref<PersonInfo[] | null>(null);
const labels = ref<Record<string, string>>({});
const error  = ref('');
const tab    = ref<'all' | 'accounts' | 'guests'>('all');
const search = ref('');

const manages = computed(() => can('accounts.manage'));

loadTypes().catch(() => undefined);

Promise.all([loadPeople(), manages.value ? loadRoles() : Promise.resolve(null)]).then(([list, roles]) => {
	labels.value = Object.fromEntries((roles?.roles ?? []).map((role) => [role.name, role.label]));
	people.value = list;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The people couldn\'t be loaded.';
});

const tabs = computed(() => {
	const all = people.value ?? [];

	return [
		{ key: 'all' as const, label: 'All', count: all.length },
		{ key: 'accounts' as const, label: 'Accounts', count: all.filter((person) => person.account !== null).length },
		{ key: 'guests' as const, label: 'Guests', count: all.filter((person) => person.account === null).length }
	];
});

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return (people.value ?? []).filter((person) => (tab.value === 'all' || (tab.value === 'accounts') === (person.account !== null))
		&& (words === '' || `${person.name} ${person.account?.username ?? ''} ${person.author ?? ''}`.toLowerCase().includes(words)));
});

// Where a person's name goes: their account, else their author page.
function routeOf(person: PersonInfo): ReturnType<typeof entryRoute> | { name: string; params: Record<string, string> } | null {
	if (person.account !== null && manages.value) {
		return { name: 'account', params: { username: person.account.username } };
	}

	return person.entry === null ? null : entryRoute(person.entry);
}

function isYou(person: PersonInfo): boolean {
	const account = session.account;

	if (account === null) {
		return false;
	}

	// A row with an account is yours when it's your account; a guest row
	// when it's your author.
	return person.account !== null ? person.account.username === account.username : person.author !== null && person.author === account.author;
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">People</h1>
			<p class="page-header__hint">Who signs in and who's credited: an account's public side is its author page.</p>
		</div>
		<div v-if="manages || (authorType && can('content.create'))" class="page-header__actions">
			<RouterLink v-if="authorType && can('content.create')" class="button" :to="{ name: 'entry-new', query: { type: authorType } }"><AdminIcon name="user-round" />New Author</RouterLink>
			<RouterLink v-if="manages" class="button button--primary" :to="{ name: 'account-new' }"><AdminIcon name="plus" />New Account</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!error">
		<section class="panel" aria-labelledby="people-heading" :aria-busy="people === null">
			<header class="panel__header">
				<h2 id="people-heading" class="visually-hidden">People</h2>
				<nav v-if="manages" class="tabs" aria-label="Who">
					<button v-for="item in tabs" :key="item.key" type="button" class="tabs__tab" :aria-pressed="tab === item.key" @click="tab = item.key">
						{{ item.label }} <span class="tabs__count">{{ item.count }}</span>
					</button>
				</nav>
				<p v-else-if="people" class="panel__hint">{{ plural(people.length, 'person', 'people') }}</p>
				<label class="search">
					<AdminIcon name="search" />
					<span class="visually-hidden">Search people</span>
					<input v-model="search" type="search" placeholder="Search names, usernames, and slugs…" autocomplete="off">
				</label>
			</header>
			<SkeletonTable v-if="people === null" :columns="['Name', 'Account', 'Roles', 'Credited', 'Last signed in']" :rows="3" label="Loading the people…" />
			<div v-else-if="shown.length === 0" class="empty">
				<AdminIcon name="users" />
				<p class="empty__text">{{ search || tab !== 'all' ? 'No one matches the search and tab.' : 'No one here yet.' }}</p>
				<button v-if="search || tab !== 'all'" type="button" class="button" @click="search = ''; tab = 'all'">Clear filters</button>
			</div>
			<div v-else class="table-wrap">
				<table class="table" aria-labelledby="people-heading">
					<thead>
						<tr>
							<th scope="col">Name</th>
							<th scope="col">Account</th>
							<th v-if="manages" scope="col">Roles</th>
							<th scope="col">Credited</th>
							<th v-if="manages" scope="col">Last signed in</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="person in shown" :key="person.account?.username ?? `author:${person.author}`">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink v-if="routeOf(person)" class="entry-title__link" :to="routeOf(person)!">{{ person.name }}</RouterLink>
										<template v-else>{{ person.name }}</template>
										{{ ' ' }}<span v-if="isYou(person)" class="tag">You</span>
										{{ ' ' }}<span v-if="person.virtual" class="tag" title="Entries credit them, but they have no author page">No page yet</span>
										{{ ' ' }}<StatusPill v-if="person.entry && person.entry.status !== 'published'" :status="person.entry.status" />
									</span>
									<span v-if="person.author" class="entry-title__path">{{ person.author }}</span>
								</span>
							</th>
							<td>
								<template v-if="person.account">
									<span class="mono">{{ person.account.username }}</span>
									{{ ' ' }}<span v-if="person.account.status !== 'active'" class="pill" :class="statusPill(person.account.status).kind">{{ statusPill(person.account.status).label }}</span>
								</template>
								<template v-else>{{ manages ? 'Guest' : '—' }}</template>
							</td>
							<td v-if="manages">{{ person.account ? (person.account.roles.map((name) => labels[name] ?? name).join(', ') || '—') : '—' }}</td>
							<td class="table__meta table__count">{{ person.uses.toLocaleString() }}</td>
							<td v-if="manages">{{ person.account ? when(person.account.lastLogin) : '—' }}</td>
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
