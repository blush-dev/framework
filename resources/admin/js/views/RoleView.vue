<script setup lang="ts">
/**
 * One role (D-249): what it is, who holds it, and each capability it
 * grants or doesn't, in groups. Read-only: roles are set in
 * `config/auth.php`.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError } from '../api';
import { plural } from '../format';
import { capabilityGroups, grants, loadRoles, type RoleList } from '../people';
import { screenTitle } from '../screen';

const route = useRoute();
const list  = ref<RoleList | null>(null);
const error = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The role couldn\'t be loaded.';
});

const role   = computed(() => list.value?.roles.find((item) => item.name === route.params.name));
const all    = computed(() => list.value?.all ?? '*');
const total  = computed(() => list.value?.capabilities.length ?? 0);
const count  = computed(() => role.value === undefined ? 0 : list.value?.capabilities.filter((capability) => grants(role.value!, capability.name, all.value)).length ?? 0);
const groups = computed(() => capabilityGroups(list.value?.capabilities ?? []));

watch(role, (value) => {
	screenTitle.value = value?.label ?? null;
}, { immediate: true });
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ role?.label ?? 'Role' }}</h1>
			<p v-if="role" class="page-header__hint">
				{{ role.capabilities.includes(all) ? 'Every capability' : `${count} of ${total} capabilities` }} · held by {{ plural(role.accounts.length, 'account') }}
			</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'roles' }"><AdminIcon name="arrow-left" />All roles</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="list && !role" class="notice notice--error" role="alert">There's no “{{ route.params.name }}” role.</p>

	<div v-if="role" class="detail">
		<div class="detail__side">
			<section class="panel" aria-labelledby="about-heading">
				<header class="panel__header">
					<h2 id="about-heading">About</h2>
					<p class="panel__hint">Read-only</p>
				</header>
				<dl class="panel__body facts">
					<div><dt>Key</dt><dd class="mono">{{ role.name }}</dd></div>
					<div><dt>Capabilities</dt><dd class="mono">{{ role.capabilities.includes(all) ? 'all' : `${count} / ${total}` }}</dd></div>
					<div><dt>Source</dt><dd>{{ role.builtIn ? 'Built in' : 'config/auth.php' }}</dd></div>
				</dl>
			</section>

			<section class="panel" aria-labelledby="held-heading">
				<header class="panel__header">
					<h2 id="held-heading">Held By</h2>
					<p class="panel__hint">{{ role.accounts.length ? plural(role.accounts.length, 'account') : 'Nobody' }}</p>
				</header>
				<ul v-if="role.accounts.length" class="panel__body people">
					<li v-for="username in role.accounts" :key="username">
						<span class="people__avatar" aria-hidden="true">{{ username.charAt(0) }}</span>
						<RouterLink :to="{ name: 'account', params: { username } }">{{ username }}</RouterLink>
					</li>
				</ul>
				<div v-else class="empty">
					<AdminIcon name="users" />
					<p class="empty__heading">No Accounts Have This Role</p>
					<p class="empty__text">Give it to an account with <code>bin/blush account:roles</code>.</p>
				</div>
			</section>
		</div>

		<section class="panel" aria-labelledby="capabilities-heading">
			<header class="panel__header">
				<h2 id="capabilities-heading">Capabilities</h2>
				<p class="panel__hint">{{ role.capabilities.includes(all) ? 'All granted' : `${count} granted` }}</p>
			</header>
			<div v-for="group in groups" :key="group.name" class="capabilities">
				<h3>{{ group.name }}</h3>
				<ul>
					<li v-for="capability in group.capabilities" :key="capability.name" :class="grants(role, capability.name, all) ? 'is-on' : 'is-off'">
						<AdminIcon :name="grants(role, capability.name, all) ? 'circle-check' : 'x'" />
						<span>{{ capability.label }}<span class="visually-hidden">: {{ grants(role, capability.name, all) ? 'granted' : 'not granted' }}</span></span>
						<code>{{ capability.name }}</code>
					</li>
				</ul>
			</div>
		</section>
	</div>
</template>

<style scoped>
.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
	align-items: start;
	gap: 16px;
}

.detail__side {
	display: grid;
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

.people {
	display: grid;
	gap: 8px;
	margin: 0;
	list-style: none;
}

.people li {
	display: flex;
	align-items: center;
	gap: 10px;
}

.people__avatar {
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--surface-3);
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 600;
	text-transform: uppercase;
}

.capabilities {
	padding: 12px var(--pad-x);
	border-top: 1px solid var(--border);
}

.capabilities h3 {
	margin-bottom: 6px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.capabilities ul {
	display: grid;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.capabilities li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: var(--text-sm);
}

.capabilities li svg {
	flex: none;
	width: 15px;
	height: 15px;
}

.capabilities code {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.capabilities .is-on svg {
	color: var(--good);
}

.capabilities .is-off {
	color: var(--fg-3);
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
