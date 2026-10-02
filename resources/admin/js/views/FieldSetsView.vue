<script setup lang="ts">
/**
 * Structure → Fields (D-337): every field set, as a list screen, then a
 * screen for each (admin.md §8, List, then detail). A set adds its fields
 * to the content types it names. Sets in `user/data/fields` are created
 * and edited here; the rest are defined in code, so they're shown.
 */

import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { request, type FieldSetList, type FieldSetSummary } from '../api';
import { plural } from '../format';

const list   = ref<FieldSetList | null>(null);
const failed = ref(false);

request<FieldSetList>('GET', '/fields/sets').then((answer) => {
	list.value = answer;
}, () => {
	failed.value = true;
});

/**
 * Where a set comes from, for people.
 */
function origin(set: FieldSetSummary): string {
	return { extension: 'A plugin', config: 'config/fields.php', data: 'user/data/fields' }[set.origin];
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Fields</h1>
			<p class="page-header__hint">Groups of fields added to content types and media files, beside their own.</p>
		</div>
		<div v-if="list?.create" class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'field-set-new' }"><AdminIcon name="plus" />New Field Set</RouterLink>
		</div>
	</header>

	<p class="notice"><span>Sets made here live in <code>user/data/fields</code>, and their screens edit them. Sets from <code>config/fields.php</code> and plugins are defined in code, so their screens show them.</span></p>
	<p v-if="failed" class="notice notice--error" role="alert">The field sets couldn't be loaded.</p>

	<section v-if="!failed" class="panel" aria-labelledby="sets-heading" :aria-busy="list === null">
		<header class="panel__header">
			<h2 id="sets-heading">Field Sets</h2>
			<p v-if="list" class="panel__hint">{{ plural(list.sets.length, 'set') }}</p>
		</header>

		<SkeletonTable v-if="list === null" :columns="['Name', 'Added to', 'Source', 'Fields']" :rows="3" label="Loading the field sets…" />
		<div v-else-if="list.sets.length" class="table-wrap">
			<table class="table" aria-labelledby="sets-heading">
				<thead>
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Added to</th>
						<th scope="col">Source</th>
						<th scope="col" class="table__count">Fields</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="set in list.sets" :key="set.name">
						<th scope="row">
							<span class="entry-title">
								<span class="entry-title__text">
									<RouterLink class="entry-title__link" :to="{ name: 'field-set', params: { name: set.name } }">{{ set.label }}</RouterLink>
								</span>
								<span class="entry-title__path">{{ set.name }}</span>
							</span>
						</th>
						<td>
							<template v-for="(target, index) in set.targets" :key="target.key">{{ index > 0 ? ', ' : '' }}<span :class="{ 'set-target--missing': !target.found }" :title="target.found ? undefined : 'The site doesn\'t have this'">{{ target.label }}<span v-if="!target.found" class="visually-hidden"> (the site doesn't have this)</span></span></template>
							<span v-if="!set.targets.length" class="set-target--missing">Nothing yet</span>
						</td>
						<td :class="{ mono: set.origin !== 'extension' }">{{ origin(set) }}</td>
						<td class="table__count mono">{{ set.fields }}</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div v-else class="empty">
			<AdminIcon name="group" />
			<p class="empty__heading">No Field Sets Yet</p>
			<p class="empty__text">A set adds the same fields to several content types: search engine details for posts and pages, say.</p>
			<RouterLink v-if="list.create" class="button" :to="{ name: 'field-set-new' }"><AdminIcon name="plus" />New Field Set</RouterLink>
		</div>
	</section>
</template>

<style scoped>
.set-target--missing {
	color: var(--fg-3);
}
</style>
