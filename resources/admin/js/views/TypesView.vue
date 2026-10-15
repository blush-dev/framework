<script setup lang="ts">
/**
 * Content types (D-250): every type, taxonomies too, as a list screen with a
 * tab for each kind the site has (D-400, D-856: Collections, Taxonomies (collections a
 * classify relation files entries under, D-593), Trees, Profiles) and a
 * search, then a screen for each type (admin.md §8, List, then detail).
 * Types in `user/data/types` are created and edited here (D-311), and
 * collections from code are
 * edited through a file there (D-349); the pages and authors types from
 * code are shown.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import TypeIcon from '../components/TypeIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { request, type ContentTypeSummary, type EntryList } from '../api';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { canCreateTypes, loadTypes, types } from '../types';

const loaded = ref(false);
const failed = ref(false);
const kind   = ref<'all' | Kind>('all');
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

// A type's tab: its kind, with taxonomies apart from other collections.
type Kind = ContentTypeSummary['kind'] | 'taxonomy';

function kindOf(type: ContentTypeSummary): Kind {
	return type.taxonomy ? 'taxonomy' : type.kind;
}

// Each kind's tab, shown when the site has a type of that kind.
const kinds: [Kind, string][] = [['collection', t('types_view.tab_collections', 'Collections')], ['taxonomy', t('types_view.tab_taxonomies', 'Taxonomies')], ['tree', t('types_view.tab_trees', 'Trees')], ['profiles', t('types_view.tab_profiles', 'Profiles')]];

const tabs = computed(() => [
	{ key: 'all' as const, label: t('types_view.tab_all', 'All'), count: types.value.length },
	...kinds
		.map(([key, label]) => ({ key, label, count: types.value.filter((type) => kindOf(type) === key).length }))
		.filter((tab) => tab.count > 0)
]);

const shown = computed(() => {
	const words = search.value.trim().toLowerCase();

	return types.value.filter((type) => (kind.value === 'all' || kindOf(type) === kind.value)
		&& (words === '' || `${type.labels.plural} ${type.labels.singular} ${type.name}`.toLowerCase().includes(words)));
});

/**
 * Where a type comes from, for people.
 */
function origin(type: ContentTypeSummary): string {
	return { 'built-in': t('types_view.origin_built_in', 'Built in'), extension: t('types_view.origin_plugin', 'A plugin'), data: 'user/data/types' }[type.origin];
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('types_view.title', 'Content Types') }}</h1>
			<p class="page-header__hint">{{ t('types_view.hint', 'The kinds of entries the site has. Taxonomies are content types too.') }}</p>
		</div>
		<div v-if="canCreateTypes" class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'type-new' }"><AdminIcon name="plus" />{{ t('types_view.new', 'New Content Type') }}</RouterLink>
		</div>
	</header>

	<p class="notice"><span><MessageText :text="t('types_view.notice', 'Types made here live in {folder}, and their screens edit them. Collections from plugins are edited too, with the changes saved in {folder} over the code\'s. The pages and authors types stay as their code defines them, so their screens show them.')"><template #folder><code>user/data/types</code></template></MessageText></span></p>
	<p v-if="failed" class="notice notice--error" role="alert">{{ t('types_view.load_failed', 'The content types couldn\'t be loaded.') }}</p>

	<section v-if="!failed" class="panel" aria-labelledby="types-heading" :aria-busy="!loaded">
		<header class="panel__header">
			<h2 id="types-heading" class="visually-hidden">{{ t('types_view.title', 'Content Types') }}</h2>
			<div class="segmented" role="group" :aria-label="t('types_view.kinds', 'Kinds')">
				<button v-for="tab in tabs" :key="tab.key" type="button" :aria-pressed="kind === tab.key" @click="kind = tab.key">
					{{ tab.label }} <span class="types-count">{{ tab.count }}</span>
				</button>
			</div>
			<label class="search-field types-search">
				<AdminIcon name="search" />
				<span class="visually-hidden">{{ t('types_view.search_label', 'Search content types') }}</span>
				<input v-model="search" type="search" :placeholder="t('types_view.search_placeholder', 'Search names and keys…')" autocomplete="off">
			</label>
		</header>

		<SkeletonTable v-if="!loaded" :columns="[t('types_view.col_name', 'Name'), t('types_view.col_kind', 'Kind'), t('types_view.col_source', 'Source'), t('types_view.col_fields', 'Fields'), t('types_view.col_entries', 'Entries')]" :rows="4" :label="t('types_view.loading', 'Loading the content types…')" />
		<div v-else-if="shown.length" class="table-wrap">
			<table class="table" aria-labelledby="types-heading">
				<thead>
					<tr>
						<th scope="col">{{ t('types_view.col_name', 'Name') }}</th>
						<th scope="col">{{ t('types_view.col_kind', 'Kind') }}</th>
						<th scope="col">{{ t('types_view.col_source', 'Source') }}</th>
						<th scope="col" class="table__count">{{ t('types_view.col_fields', 'Fields') }}</th>
						<th scope="col" class="table__count">{{ t('types_view.col_entries', 'Entries') }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="type in shown" :key="type.name">
						<th scope="row">
							<span class="type-name">
								<TypeIcon :type="type" />
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink class="entry-title__link" :to="{ name: 'content-type', params: { name: type.name } }">{{ type.labels.plural }}</RouterLink>
									</span>
									<span class="entry-title__path">{{ type.name }}{{ type.prefix ? ` · ${type.prefix}` : '' }}</span>
								</span>
							</span>
						</th>
						<td>{{ t('types_view.kind', '{kind, select, taxonomy {Taxonomy} collection {Collection} tree {Tree} other {Profiles} }', { kind: type.taxonomy ? 'taxonomy' : type.kind }) }}</td>
						<td :class="{ mono: type.origin === 'data' }">{{ origin(type) }}</td>
						<td class="table__count mono">{{ type.fields }}</td>
						<td class="table__count mono">{{ counts[type.name] ?? '—' }}</td>
					</tr>
				</tbody>
			</table>
		</div>
		<EmptyState v-else icon="search" :heading="t('types_view.no_match', 'No Types Match')" :text="t('types_view.no_match_text', 'Nothing matches the search and kind.')">
			<template #actions>
				<button type="button" class="button" @click="search = ''; kind = 'all'">{{ t('types_view.clear_filters', 'Clear Filters') }}</button>
			</template>
		</EmptyState>
		<p v-if="loaded" class="visually-hidden" role="status">{{ t('types_view.shown', '{count, plural, one {# type} other {# types} } shown', { count: shown.length }) }}</p>
	</section>
</template>

<style scoped>
.types-count {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.types-search {
	flex: 0 1 16rem;
	margin-left: auto;
}

.type-name {
	display: flex;
	gap: 8px;
}

.type-name > svg {
	flex: none;
	margin-top: 2px;
	color: var(--color-text-muted);
}
</style>
