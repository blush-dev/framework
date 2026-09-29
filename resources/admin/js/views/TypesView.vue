<script setup lang="ts">
/**
 * Content types (D-250): every type, taxonomies too, as a list screen
 * with tabs by kind and a search, then a screen for each type (admin.md
 * §8, List, then detail). Types are defined in code and files for now, so
 * this shows them.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { request, type ContentTypeSummary, type EntryList } from '../api';
import { humanize } from '../fields';
import { plural } from '../format';
import { loadTypes, typeIcon, types } from '../types';

const loaded = ref(false);
const failed = ref(false);
const kind   = ref<'all' | 'content' | 'taxonomy'>('all');
const search = ref('');
const counts = ref<Record<string, number>>({});

loadTypes().then(() => {
	loaded.value = true;

	// How many entries each has, as the account may edit them.
	for (const type of types.value) {
		request<EntryList>('GET', `/entries?type=${encodeURIComponent(type.name)}&per=1`).then((answer) => {
			counts.value = { ...counts.value, [type.name]: answer.total };
		}, () => undefined);
	}
}, () => {
	failed.value = true;
});

const isTaxonomy = (type: ContentTypeSummary): boolean => type.kind === 'taxonomy';

const tabs = computed(() => [
	{ key: 'all' as const, label: 'All', count: types.value.length },
	{ key: 'content' as const, label: 'Content', count: types.value.filter((type) => !isTaxonomy(type)).length },
	{ key: 'taxonomy' as const, label: 'Taxonomies', count: types.value.filter(isTaxonomy).length }
]);

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return types.value.filter((type) => (kind.value === 'all' || (kind.value === 'taxonomy') === isTaxonomy(type))
		&& (words === '' || `${type.label} ${type.singular} ${type.name}`.toLowerCase().includes(words)));
});

/**
 * Where a type comes from, for people.
 */
function origin(type: ContentTypeSummary): string {
	return { 'built-in': 'Built in', extension: 'An extension', config: 'config/content.php', data: 'user/data/types' }[type.origin];
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Content types</h1>
			<p class="page-header__hint">The kinds of entries the site has. Taxonomies are content types too.</p>
		</div>
	</header>

	<p class="notice notice--warn"><span>Types are defined in <code>config/content.php</code>, <code>user/data/types</code>, and extensions, so these screens show them. Creating and changing types here comes later.</span></p>
	<p v-if="failed" class="notice notice--error" role="alert">The content types couldn't be loaded.</p>

	<section v-if="!failed" class="panel" aria-labelledby="types-heading" :aria-busy="!loaded">
		<header class="panel__header">
			<h2 id="types-heading" class="visually-hidden">Content types</h2>
			<nav class="tabs" aria-label="Kinds">
				<button v-for="tab in tabs" :key="tab.key" type="button" class="tabs__tab" :aria-pressed="kind === tab.key" @click="kind = tab.key">
					{{ tab.label }} <span class="tabs__count">{{ tab.count }}</span>
				</button>
			</nav>
			<label class="search">
				<AdminIcon name="search" />
				<span class="visually-hidden">Search content types</span>
				<input v-model="search" type="search" placeholder="Search names and keys…" autocomplete="off">
			</label>
		</header>

		<SkeletonTable v-if="!loaded" :columns="['Name', 'Kind', 'Source', 'Fields', 'Entries']" :rows="4" label="Loading the content types…" />
		<div v-else-if="shown.length" class="table-wrap">
			<table class="table" aria-labelledby="types-heading">
				<thead>
					<tr>
						<th scope="col">Name</th>
						<th scope="col">Kind</th>
						<th scope="col">Source</th>
						<th scope="col" class="table__count">Fields</th>
						<th scope="col" class="table__count">Entries</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="type in shown" :key="type.name">
						<th scope="row">
							<span class="type-name">
								<AdminIcon :name="typeIcon(type)" />
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink class="entry-title__link" :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink>
									</span>
									<span class="entry-title__path">{{ type.name }}{{ type.prefix ? ` · ${type.prefix}` : '' }}</span>
								</span>
							</span>
						</th>
						<td>{{ humanize(type.kind) }}</td>
						<td :class="{ mono: type.origin === 'config' || type.origin === 'data' }">{{ origin(type) }}</td>
						<td class="table__count mono">{{ type.fields }}</td>
						<td class="table__count mono">{{ counts[type.name] ?? '—' }}</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div v-else class="empty">
			<AdminIcon name="search" />
			<p class="empty__heading">No types match</p>
			<p class="empty__text">Nothing matches the search and kind.</p>
			<button type="button" class="button" @click="search = ''; kind = 'all'">Clear filters</button>
		</div>
		<p v-if="loaded" class="visually-hidden" role="status">{{ plural(shown.length, 'type') }} shown</p>
	</section>
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
	padding: 4px 10px;
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
	width: 12rem;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}

.type-name {
	display: flex;
	gap: 8px;
}

.type-name > svg {
	flex: none;
	margin-top: 2px;
	color: var(--fg-3);
}
</style>
