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
import { errorMessage } from '../api';
import { t } from '../messages';
import { grants, loadRoles, MEMBER, originOf, type RoleInfo, type RoleList } from '../people';
import { can } from '../session';

const list  = ref<RoleList | null>(null);
const error = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = errorMessage(caught, t('roles_view.load_failed', 'The roles couldn\'t be loaded.'));
});

const site = computed(() => list.value?.capabilities.filter((capability) => capability.type === undefined) ?? []);

// The types a role can do anything to: every one (a `content.*.…`
// capability, which covers types added later too), some, or none.
function reach(role: RoleInfo): string {
	const all   = list.value?.all ?? '*';
	const types = list.value?.types ?? [];

	if (role.capabilities.includes(all) || role.capabilities.some((name) => name.startsWith('content.*.'))) {
		return t('roles_view.every_type', 'Every type');
	}

	const count = types.filter((type) => role.capabilities.some((name) => name.startsWith(`content.${type.name}.`))).length;

	return count === 0 ? t('roles_view.no_types', 'No types') : t('roles_view.types_of', '{count} of {total} types', { count, total: types.length });
}

// How many of the site's capabilities it has.
function siteCount(role: RoleInfo): string {
	const count = site.value.filter((capability) => grants(role, capability.name, list.value?.all ?? '*')).length;

	return t('roles_view.site_of', '{count} of {total} site', { count, total: site.value.length });
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('roles_view.title', 'Roles') }}</h1>
			<p class="page-header__hint">{{ t('roles_view.hint', 'Named sets of capabilities, given to accounts') }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="can('roles.manage')" class="button button--primary" :to="{ name: 'role-new' }"><AdminIcon name="plus" />{{ t('roles_view.new_role', 'New Role') }}</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="roles-heading" :aria-busy="list === null">
		<header class="panel__header">
			<h2 id="roles-heading">{{ t('roles_view.all_roles', 'All Roles') }}</h2>
			<p v-if="list" class="panel__hint">{{ t('roles_view.roles_count', '{count, plural, one {# role} other {# roles} }', { count: list.roles.length }) }}</p>
		</header>
		<SkeletonTable v-if="list === null" :columns="[t('roles_view.role', 'Role'), t('roles_view.description', 'Description'), t('roles_view.capabilities', 'Capabilities'), t('roles_view.accounts', 'Accounts')]" :rows="4" :label="t('roles_view.loading', 'Loading the roles…')" />
		<div v-else class="table-wrap">
			<table class="table" aria-labelledby="roles-heading">
				<thead>
					<tr>
						<th scope="col">{{ t('roles_view.role', 'Role') }}</th>
						<th scope="col" class="roles__description">{{ t('roles_view.description', 'Description') }}</th>
						<th scope="col">{{ t('roles_view.capabilities', 'Capabilities') }}</th>
						<th scope="col" class="table__count">{{ t('roles_view.accounts', 'Accounts') }}</th>
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
							<template v-if="role.capabilities.includes(list.all)">{{ t('roles_view.everything', 'Everything') }}</template>
							<template v-else-if="role.name === MEMBER">{{ t('roles_view.own_account', 'Their own account') }}</template>
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
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.roles__capabilities {
	white-space: nowrap;
}

.roles__site {
	display: block;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

/* On a phone, the role's name and readouts need the room. */
@media (width <= 640px) {
	.roles__description {
		display: none;
	}
}
</style>
