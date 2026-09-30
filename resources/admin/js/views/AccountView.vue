<script setup lang="ts">
/**
 * One account (D-249): who it is, its roles (with what each is), and how
 * to change it until the admin can. Read-only.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError } from '../api';
import { loadAccounts, loadRoles, when, type AccountInfo, type RoleList } from '../people';
import { screenTitle } from '../screen';
import { session } from '../session';

const route    = useRoute();
const accounts = ref<AccountInfo[] | null>(null);
const roles    = ref<RoleList | null>(null);
const error    = ref('');

Promise.all([loadAccounts(), loadRoles()]).then(([list, answer]) => {
	accounts.value = list;
	roles.value    = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The account couldn\'t be loaded.';
});

const account = computed(() => accounts.value?.find((item) => item.username === route.params.username));
const yours   = computed(() => account.value?.username === session.account?.username);

watch(account, (value) => {
	screenTitle.value = value?.username ?? null;
}, { immediate: true });
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ account?.username ?? 'Account' }}</h1>
			<p v-if="account" class="page-header__hint">
				{{ account.roles.map((name) => roles?.roles.find((role) => role.name === name)?.label ?? name).join(', ') || 'No roles' }} · last signed in {{ account.lastLogin ? when(account.lastLogin) : 'never' }}
			</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="yours" class="button" :to="{ name: 'profile' }"><AdminIcon name="users" />Your Profile</RouterLink>
			<RouterLink class="button" :to="{ name: 'accounts' }"><AdminIcon name="arrow-left" />All accounts</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="accounts && !account" class="notice notice--error" role="alert">There's no “{{ route.params.username }}” account.</p>

	<div v-if="account" class="detail">
		<section class="panel" aria-labelledby="account-heading">
			<header class="panel__header">
				<h2 id="account-heading">Account</h2>
				<p class="panel__hint">Read-only</p>
			</header>
			<dl class="panel__body facts">
				<div><dt>Username</dt><dd class="mono">{{ account.username }}</dd></div>
				<div><dt>Author</dt><dd :class="{ mono: account.author }">{{ account.author ?? 'Not linked' }}</dd></div>
				<div><dt>Created</dt><dd>{{ when(account.created) }}</dd></div>
				<div><dt>Last signed in</dt><dd>{{ when(account.lastLogin) }}</dd></div>
			</dl>
			<p class="panel__body field__help">
				Change it with <code>bin/blush account:roles</code>, <code>account:author</code>, or <code>account:password</code>, and remove it with <code>account:remove</code>.
			</p>
		</section>

		<section class="panel" aria-labelledby="roles-heading">
			<header class="panel__header">
				<h2 id="roles-heading">Roles</h2>
				<p class="panel__hint">An account can hold more than one</p>
			</header>
			<ul class="panel__body held">
				<li v-for="role in roles?.roles ?? []" :key="role.name" :class="account.roles.includes(role.name) ? 'is-on' : 'is-off'">
					<AdminIcon :name="account.roles.includes(role.name) ? 'circle-check' : 'x'" />
					<RouterLink :to="{ name: 'role', params: { name: role.name } }">{{ role.label }}</RouterLink>
					<span class="visually-hidden">{{ account.roles.includes(role.name) ? ': held' : ': not held' }}</span>
				</li>
			</ul>
		</section>
	</div>
</template>

<style scoped>
.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: 16px;
}

.facts {
	display: grid;
	gap: 8px;
	margin: 0;
}

.facts > * + * {
	margin-top: 0;
}

.facts div {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}

.facts dt {
	color: var(--fg-2);
}

.facts dd {
	margin: 0;
}

.held {
	display: grid;
	gap: 6px;
	margin: 0;
	list-style: none;
}

.held li {
	display: flex;
	align-items: center;
	gap: 8px;
}

.held svg {
	width: 15px;
	height: 15px;
}

.held .is-on svg {
	color: var(--good);
}

.held .is-off,
.held .is-off a {
	color: var(--fg-3);
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
