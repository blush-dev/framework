<script setup lang="ts">
/**
 * Accounts (D-249): a list screen, then a screen for each account (admin.md
 * §8, List, then detail). Read-only for now: accounts are added and
 * changed with `account:*` commands.
 */

import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import SkeletonTable from '../components/SkeletonTable.vue';
import { ApiError } from '../api';
import { plural } from '../format';
import { loadAccounts, loadRoles, when, type AccountInfo } from '../people';

const accounts = ref<AccountInfo[] | null>(null);
const labels   = ref<Record<string, string>>({});
const error    = ref('');

Promise.all([loadAccounts(), loadRoles()]).then(([list, roles]) => {
	labels.value   = Object.fromEntries(roles.roles.map((role) => [role.name, role.label]));
	accounts.value = list;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The accounts couldn\'t be loaded.';
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Accounts</h1>
			<p class="page-header__hint">People who can sign in. An account can hold more than one role.</p>
		</div>
	</header>

	<p class="notice notice--warn"><span>Add accounts and change their roles with <code>bin/blush account:add</code> and <code>account:roles</code> for now; these screens show them.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="accounts-heading" :aria-busy="accounts === null">
		<header class="panel__header">
			<h2 id="accounts-heading">All accounts</h2>
			<p v-if="accounts" class="panel__hint">{{ plural(accounts.length, 'account') }}</p>
		</header>
		<SkeletonTable v-if="accounts === null" :columns="['Account', 'Roles', 'Author', 'Last signed in']" :rows="3" label="Loading the accounts…" />
		<div v-else class="table-wrap">
			<table class="table" aria-labelledby="accounts-heading">
				<thead>
					<tr>
						<th scope="col">Account</th>
						<th scope="col">Roles</th>
						<th scope="col">Author</th>
						<th scope="col">Last signed in</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="account in accounts" :key="account.username">
						<th scope="row">
							<span class="entry-title">
								<span class="entry-title__text">
									<RouterLink class="entry-title__link" :to="{ name: 'account', params: { username: account.username } }">{{ account.username }}</RouterLink>
								</span>
							</span>
						</th>
						<td>{{ account.roles.map((name) => labels[name] ?? name).join(', ') || '—' }}</td>
						<td :class="{ mono: account.author }">{{ account.author ?? '—' }}</td>
						<td>{{ when(account.lastLogin) }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>
