<script setup lang="ts">
/**
 * Roles (D-249): a list screen, then a screen for each role (admin.md §8,
 * List, then detail). Four or so fixed rows need no search or tabs.
 * Roles are set in `config/auth.php`, so this shows them.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import SkeletonTable from '../components/SkeletonTable.vue';
import { ApiError } from '../api';
import { plural } from '../format';
import { loadRoles, type RoleList } from '../people';

const list  = ref<RoleList | null>(null);
const error = ref('');

loadRoles().then((answer) => {
	list.value = answer;
}, (caught: unknown) => {
	error.value = caught instanceof ApiError ? caught.message : 'The roles couldn\'t be loaded.';
});

const total = computed(() => list.value?.capabilities.length ?? 0);

function granted(capabilities: string[]): string {
	return capabilities.includes(list.value?.all ?? '*') ? 'Everything' : `${capabilities.length} of ${total.value}`;
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Roles</h1>
			<p class="page-header__hint">Named sets of capabilities, given to accounts</p>
		</div>
	</header>

	<p class="notice notice--warn"><span>Roles and what they can do are set in <code>config/auth.php</code>, so these screens show them without changing them.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section v-if="!error" class="panel" aria-labelledby="roles-heading" :aria-busy="list === null">
		<header class="panel__header">
			<h2 id="roles-heading">All Roles</h2>
			<p v-if="list" class="panel__hint">{{ plural(list.roles.length, 'role') }} · {{ plural(total, 'capability', 'capabilities') }} in all</p>
		</header>
		<SkeletonTable v-if="list === null" :columns="['Role', 'Capabilities', 'Accounts']" :rows="4" label="Loading the roles…" />
		<div v-else class="table-wrap">
			<table class="table" aria-labelledby="roles-heading">
				<thead>
					<tr>
						<th scope="col">Role</th>
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
								<span class="entry-title__path">{{ role.name }}{{ role.builtIn ? '' : ' · from config/auth.php' }}</span>
							</span>
						</th>
						<td>{{ granted(role.capabilities) }}</td>
						<td class="table__count mono">{{ role.accounts.length }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>
