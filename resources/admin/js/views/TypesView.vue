<script setup lang="ts">
/**
 * Content types (D-250): every type, taxonomies too, as a list screen with a
 * tab for each kind the site has (D-400, D-856: Collections, Taxonomies (collections a
 * classify relation files entries under, D-593), Trees, Profiles) and a
 * search, then a screen for each type (admin.md §8, List, then detail).
 * From the content types sketch (D-866): each row is the type's name
 * over its key and address, its kind with what a taxonomy files or that
 * a tree nests, where it's defined (the plugin's name, and how much is
 * changed here), and how many fields and entries it has. After a type
 * is deleted (`?deleted=`), a notice says where its entries are.
 */

import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import TypeIcon from '../components/TypeIcon.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import type { ContentTypeSummary } from '../api';
import { formatNumber, series } from '../format';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { canCreateTypes, loadTypes, types } from '../types';

const route  = useRoute();
const router = useRouter();
const loaded = ref(false);
const failed = ref(false);
const kind   = ref<'all' | Kind>('all');
const search = ref('');

loadTypes().then(() => {
	loaded.value = true;
}, () => {
	failed.value = true;
});

// A type just deleted (`?deleted={name}&entries={count}`): where its
// entries are now, until it's dismissed.
const deleted = computed(() => typeof route.query.deleted === 'string' ? { name: route.query.deleted, entries: Number(route.query.entries ?? 0) } : null);

function dismissDeleted(): void {
	void router.replace({ query: {} });
}

// What the kind says beside it: what a taxonomy files, or how it nests.
function kindNote(type: ContentTypeSummary): string {
	if (type.taxonomy) {
		const files = (type.types ?? []).map((name) => types.value.find((other) => other.name === name)?.labels.plural ?? name);

		return files.length ? t('types_view.files', 'Files {types}', { types: series(files) }) : t('types_view.files_any', 'Files any type');
	}

	if (type.kind === 'tree') {
		return t('types_view.nests_folder', 'Nests by folder');
	}

	return type.hierarchical ? t('types_view.nests', 'Entries nest') : '';
}

// What the source says under it.
function sourceNote(type: ContentTypeSummary): string {
	if (type.origin === 'data') {
		return t('types_view.made_here', 'Made here');
	}

	if (type.origin === 'built-in') {
		return type.kind === 'profiles' ? t('types_view.under_users', 'Managed under Users') : t('types_view.shown_as_is', 'Shown as it is');
	}

	return type.changed ? t('types_view.changed_here', '{count, plural, one {# setting changed here} other {# settings changed here} }', { count: type.changed }) : t('types_view.as_code', 'As its code defines it');
}

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
 * Where a type comes from, for people: this site, core, or its plugin by
 * name.
 */
function origin(type: ContentTypeSummary): string {
	return { 'built-in': t('types_view.origin_built_in', 'Built in'), extension: type.plugin ?? t('types_view.origin_plugin', 'A plugin'), data: t('types_view.origin_site', 'This site') }[type.origin];
}

function originIcon(type: ContentTypeSummary): 'file' | 'lock' | 'plug' {
	return type.origin === 'data' ? 'file' : (type.origin === 'built-in' ? 'lock' : 'plug');
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

	<p v-if="!canCreateTypes && loaded" class="notice"><AdminIcon name="lock" /><span><MessageText :text="t('types_view.locked', 'Content types are read only here: the site\'s config turns off {option}. Their relationships can still change.')"><template #option><code>dataTypes</code></template></MessageText></span></p>
	<div v-if="deleted" class="notice">
		<AdminIcon name="file" />
		<span><MessageText :text="t('types_view.deleted', '{count, plural, one {# entry} other {# entries} } of the deleted type stay in {folder}, as files without a type: off the site and out of the Content menu. Site Health lists them under Content Files, and a type named {name} brings them back.', { count: deleted.entries })"><template #folder><code>user/content/_{{ deleted.name }}</code></template><template #name><code>{{ deleted.name }}</code></template></MessageText></span>
		<RouterLink class="button button--small" :to="{ name: 'health' }">{{ t('types_view.open_health', 'Open Site Health') }}</RouterLink>
		<button type="button" class="button button--ghost button--small button--icon" @click="dismissDeleted"><AdminIcon name="x" /><span class="visually-hidden">{{ t('common.dismiss', 'Dismiss') }}</span></button>
	</div>
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
									<span class="entry-title__path">{{ type.name }} · {{ !type.public ? t('types_view.no_pages', 'no pages') : (type.prefix ?? (type.kind === 'tree' ? '/' : t('types_view.no_routes', 'no routes'))) }}</span>
								</span>
							</span>
						</th>
						<td>
							<span class="types-cell">{{ t('types_view.kind', '{kind, select, taxonomy {Taxonomy} collection {Collection} tree {Tree} other {Profiles} }', { kind: type.taxonomy ? 'taxonomy' : type.kind }) }}</span>
							<span v-if="kindNote(type)" class="types-note">{{ kindNote(type) }}</span>
						</td>
						<td>
							<span class="types-cell"><AdminIcon :name="originIcon(type)" />{{ origin(type) }}</span>
							<span class="types-note">{{ sourceNote(type) }}</span>
						</td>
						<td class="table__count mono" :class="{ 'types-zero': !type.fields }">{{ type.fields }}</td>
						<td class="table__count mono" :class="{ 'types-zero': !type.entries }">{{ formatNumber(type.entries) }}</td>
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

.types-cell {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.types-cell .icon {
	width: 14px;
	height: 14px;
	color: var(--color-text-muted);
}

.types-note {
	display: block;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.types-zero {
	color: var(--color-text-muted);
}
</style>
