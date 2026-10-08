<script setup lang="ts">
/**
 * Relationships (D-610, from the pickers sketch's Relationships board):
 * every relationship on the site, the primary place they're made and
 * edited, since a type's own screen shows only its side.
 *
 * Built like the admin's other lists: tabs by purpose (Credits, Files
 * Under Terms, Links), a search over names and keys, a type filter that
 * matches either end ("everything that points at Profiles, everything
 * Recipes points at"), and a source filter; then a table where the name
 * leads (its label, with its key under it) and opens its screen.
 * **Connects** says the type that stores it, then the one it points at.
 *
 * A relationship from config or a plugin keeps its row, but its name
 * isn't a link; Source says where it's defined. One still written as a
 * taxonomy is flagged, with Migrate going where Site Health migrates it.
 * No checkboxes: nothing done to relationships is safe to do to several
 * at once. The table's header names the tab and counts what's shown,
 * of how many the tab holds when filters are in force, with Clear
 * Filters beside it.
 *
 * The filters are in the address (`?kind=credit&type=recipes`), so a
 * type's panel can link here filtered to it.
 */

import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import EmptyState from '../components/EmptyState.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { errorMessage, request, type RelationListed } from '../api';
import { plural } from '../format';
import { purposeIcon, purposeOf, purposes, relationLabel, sourceOf, type RelationPurpose } from '../relations';
import { can } from '../session';
import { canCreateTypes, labelsOf, loadTypes, types } from '../types';

const route  = useRoute();
const router = useRouter();

const relations = ref<RelationListed[] | null>(null);
const error     = ref('');
const search    = ref('');

loadTypes().catch(() => undefined);

request<{ relations: RelationListed[] }>('GET', '/relations').then((answer) => {
	relations.value = answer.relations;
}, (caught: unknown) => {
	error.value = errorMessage(caught, 'The relationships couldn\'t be loaded.');
});

const text = (key: string): string => typeof route.query[key] === 'string' ? route.query[key] : '';

const kind   = computed(() => text('kind') as RelationPurpose | '');
const type   = computed(() => text('type'));
const source = computed(() => text('source'));

function go(query: Record<string, string | undefined>): void {
	void router.replace({ query: { ...route.query, ...query } });
}

const typeValue   = computed({ get: () => type.value, set: (value: string) => go({ type: value || undefined }) });
const sourceValue = computed({ get: () => source.value, set: (value: string) => go({ source: value || undefined }) });

const typeOptions = computed(() => [
	{ value: '', label: 'Any type' },
	...[...types.value].sort((a, b) => a.labels.plural.localeCompare(b.labels.plural)).map((item) => ({ value: item.name, label: item.labels.plural }))
]);

const sourceOptions = [
	{ value: '', label: 'Any source' },
	{ value: 'data', label: 'Made here' },
	{ value: 'extension', label: 'Plugins' }
];

// Every relationship but the filtered tab, so each tab counts what the
// other filters leave.
const filteredAll = computed(() => {
	const words = search.value.trim().toLowerCase();

	return (relations.value ?? []).filter((relation) => (type.value === '' || relation.from.length === 0 || relation.from.includes(type.value) || relation.to.includes(type.value))
		&& (source.value === '' || relation.origin === source.value)
		&& (words === '' || `${relationLabel(relation)} ${relation.name} ${relation.field}`.toLowerCase().includes(words)));
});

const tabs = computed(() => [
	{ key: '' as const, label: 'All', count: filteredAll.value.length },
	...purposes.map((item) => ({ key: item.key, label: item.label, count: filteredAll.value.filter((relation) => purposeOf(relation) === item.key).length }))
]);

const shown = computed(() => filteredAll.value
	.filter((relation) => kind.value === '' || purposeOf(relation) === kind.value)
	.sort((a, b) => relationLabel(a).localeCompare(relationLabel(b))));

// How many the tab holds before the filters, for "3 relationships of 13".
const total = computed(() => (relations.value ?? []).filter((relation) => kind.value === '' || purposeOf(relation) === kind.value).length);

const filtered = computed(() => search.value !== '' || type.value !== '' || source.value !== '');

function clear(): void {
	search.value = '';
	go({ type: undefined, source: undefined });
}

const stores = (relation: RelationListed): string => relation.from.length === 0 ? 'Every type' : relation.from.map((name) => labelsOf(name).plural).join(', ');
const target = (relation: RelationListed): string => relation.to.map((name) => labelsOf(name).plural).join(', ');
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Relationships</h1>
			<p class="page-header__hint">How entries of one type point at entries of another.</p>
		</div>
		<div v-if="canCreateTypes" class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'relation-new' }"><AdminIcon name="plus" />New Relationship</RouterLink>
		</div>
	</header>

	<p class="notice"><AdminIcon name="info" /><span>Relationships made here live in <code>user/data/relations</code>. Ones from plugins are shown with their source and edited where they're defined.</span></p>
	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-if="!error">
		<nav class="status-tabs" aria-label="Purpose">
			<RouterLink v-for="tab in tabs" :key="tab.key" class="status-tabs__tab" :to="{ query: { ...route.query, kind: tab.key || undefined } }" :aria-current="kind === tab.key ? 'page' : undefined">
				{{ tab.label }}
				<span v-if="relations" class="status-tabs__count">{{ tab.count }}</span>
			</RouterLink>
		</nav>

		<div class="toolbar" role="search">
			<label class="search-field toolbar__search">
				<AdminIcon name="search" />
				<span class="visually-hidden">Search relationships</span>
				<input v-model="search" type="search" placeholder="Search names and keys" autocomplete="off">
			</label>
			<div class="toolbar__filter">
				<label class="visually-hidden" for="relations-type">Type</label>
				<AdminSelect id="relations-type" v-model="typeValue" :options="typeOptions" searchable />
			</div>
			<div class="toolbar__filter">
				<label class="visually-hidden" for="relations-source">Source</label>
				<AdminSelect id="relations-source" v-model="sourceValue" :options="sourceOptions" />
			</div>
		</div>

		<section class="panel" aria-labelledby="relations-heading" :aria-busy="relations === null">
			<header class="panel__header">
				<h2 id="relations-heading">{{ tabs.find((tab) => tab.key === kind)?.label }}</h2>
				<p class="panel__hint" aria-live="polite">
					<template v-if="relations">{{ plural(shown.length, 'relationship') }}<template v-if="filtered"> of {{ total }}</template></template>
					<template v-else>&nbsp;</template>
				</p>
				<div v-if="filtered" class="panel__actions">
					<button type="button" class="button button--ghost button--small" @click="clear">Clear Filters</button>
				</div>
			</header>
			<SkeletonTable v-if="relations === null" :columns="['Name', 'Connects', 'Kind', 'Source', 'Entries']" :rows="4" label="Loading the relationships…" />
			<EmptyState v-else-if="shown.length === 0" :icon="filtered ? 'search' : 'workflow'" :heading="filtered ? 'No Relationship Matches' : 'No Relationships Yet'" :text="filtered ? 'Nothing here fits the filters in force.' : 'A relationship lets entries of one type name entries of another: a recipe its cooks, a post its categories.'">
				<template #actions>
					<button v-if="filtered" type="button" class="button" @click="clear">Clear Filters</button>
					<RouterLink v-else-if="canCreateTypes" class="button button--primary" :to="{ name: 'relation-new' }">New Relationship</RouterLink>
				</template>
			</EmptyState>
			<div v-else class="table-wrap">
				<table class="table relations-table" aria-labelledby="relations-heading">
					<colgroup><col class="relations-table__name"><col><col class="relations-table__kind"><col class="relations-table__source"><col class="relations-table__count"></colgroup>
					<thead>
						<tr>
							<th scope="col">Name</th>
							<th scope="col">Connects</th>
							<th scope="col">Kind</th>
							<th scope="col">Source</th>
							<th scope="col" class="table__count">Entries</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="relation in shown" :key="relation.name">
							<th scope="row">
								<span class="relation-name">
									<AdminIcon :name="purposeIcon(relation)" />
									<span class="entry-title">
										<span class="entry-title__text">
											<RouterLink v-if="relation.editable" class="entry-title__link" :to="{ name: 'relation', params: { name: relation.name } }">{{ relationLabel(relation) }}</RouterLink>
											<template v-else>{{ relationLabel(relation) }}</template>
										</span>
										<span class="entry-title__path">{{ relation.field }}</span>
										<span v-if="relation.legacy" class="relation-name__legacy">
											<AdminIcon name="triangle-alert" />Written the old way, as a taxonomy.
											<RouterLink v-if="can('site.health')" class="lnk" :to="{ name: 'health-check', params: { area: 'content', check: 'taxonomies' } }">Migrate</RouterLink>
										</span>
									</span>
								</span>
							</th>
							<td>{{ stores(relation) }} <AdminIcon class="relations-table__arrow" name="arrow-right" /> {{ target(relation) }}</td>
							<td>{{ purposes.find((item) => item.key === purposeOf(relation))?.label }}</td>
							<td :class="{ mono: relation.origin !== 'extension' }">{{ sourceOf(relation) }}</td>
							<td class="table__count mono">{{ relation.entries.toLocaleString() }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</template>
</template>

<style scoped>
.relations-table {
	min-width: 760px;
	table-layout: fixed;
}

.relations-table__name {
	width: 28%;
}

.relations-table__kind {
	width: 15%;
}

.relations-table__source {
	width: 20%;
}

.relations-table__count {
	width: 10%;
}

.relations-table__arrow {
	width: 13px;
	height: 13px;
	margin-inline: 2px;
	color: var(--fg-3);
	vertical-align: -2px;
}

/* The name beside its kind's icon, as the types list draws a type. */
.relation-name {
	display: flex;
	gap: 8px;
}

.relation-name > .icon {
	flex: none;
	margin-top: 2px;
	color: var(--fg-3);
}

.relation-name__legacy {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px var(--s-2);
	margin-top: 2px;
	color: var(--warn);
	font-size: var(--text-xs);
}

.relation-name__legacy .icon {
	width: 13px;
	height: 13px;
	color: var(--warn-dot);
}
</style>
