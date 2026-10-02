<script setup lang="ts">
/**
 * Roles (D-249): a list screen, then a screen for each role (admin.md §8,
 * List, then detail). A handful of rows needs no search or tabs. **New
 * Role** makes a custom role (D-312); each role's screen changes it.
 * Each row has the role's description, and its capabilities as two
 * short readouts (D-361): the types it reaches, and how many site
 * capabilities it has. A plain count grows with every type, so it says
 * little.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { ApiError } from '../api';
import { plural } from '../format';
import { grants, loadRoles, MEMBER, originOf, type RoleInfo, type RoleList } from '../people';
import { can } from '../session';

const list  = ref<RoleList | null>(null);
const error = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The roles couldn\'t be loaded.';
});

const site = computed(() => list.value?.capabilities.filter((capability) => capability.type === undefined) ?? []);

// The types a role can do anything to: every one (a `content.*.…`
// capability, which covers types added later too), some, or none.
function reach(role: RoleInfo): string {
	const all   = list.value?.all ?? '*';
	const types = list.value?.types ?? [];

	if (role.capabilities.includes(all) || role.capabilities.some((name) => name.startsWith('content.*.'))) {
		return 'Every type';
	}

	const count = types.filter((type) => role.capabilities.some((name) => name.startsWith(`content.${type.name}.`))).length;

	return count === 0 ? 'No types' : `${count} of ${types.length} types`;
}

// How many of the site's capabilities it has.
function siteCount(role: RoleInfo): string {
	const count = site.value.filter((capability) => grants(role, capability.name, list.value?.all ?? '*')).length;

	return `${count} of ${site.value.length} site`;
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Roles</h1>
			<p class="page-header__hint">Named sets of capabilities, given to accounts</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="can('roles.manage')" class="button button--primary" :to="{ name: 'role-new' }"><AdminIcon name="plus" />New Role</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="roles-heading" :aria-busy="list === null">
		<header class="panel__header">
			<h2 id="roles-heading">All Roles</h2>
			<p v-if="list" class="panel__hint">{{ plural(list.roles.length, 'role') }}</p>
		</header>
		<SkeletonTable v-if="list === null" :columns="['Role', 'Description', 'Capabilities', 'Accounts']" :rows="4" label="Loading the roles…" />
		<div v-else class="table-wrap">
			<table class="table" aria-labelledby="roles-heading">
				<thead>
					<tr>
						<th scope="col">Role</th>
						<th scope="col" class="roles__description">Description</th>
						<th scope="col">Capabilities</th>
						<th scope="col" class="table__count">Accounts</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="role in list.roles" :key="role.name">
						<th scope="row">
							<span class="entry-title">
								<span class="entry-title__text">
									<RouterLink class="entry-title__link" :to="{ name: 'role', params: { name: role.name } }">{{ role.label }}</RouterLink>
								</span>
								<span class="entry-title__path">{{ role.name }} · {{ originOf(role) }}</span>
							</span>
						</th>
						<td class="roles__description">{{ role.description }}</td>
						<td class="roles__capabilities">
							<template v-if="role.capabilities.includes(list.all)">Everything</template>
							<template v-else-if="role.name === MEMBER">Their own account</template>
							<template v-else>
								{{ reach(role) }}
								<span class="roles__site">{{ siteCount(role) }}</span>
							</template>
						</td>
						<td class="table__count mono">{{ role.accounts.length }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>

<style scoped>
.roles__description {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.roles__capabilities {
	white-space: nowrap;
}

.roles__site {
	display: block;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* On a phone, the role's name and readouts need the room. */
@media (width <= 640px) {
	.roles__description {
		display: none;
	}
}
</style>
