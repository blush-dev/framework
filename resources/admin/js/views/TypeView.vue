<script setup lang="ts">
/**
 * One content type (D-250): its names and settings, its relationships
 * (`TypeRelations`, D-593: the terms that file it, or for terms the types
 * they file), the fields it defines and the field sets added to it
 * (D-337), with a way to its entries. A type from `user/data/types` is
 * edited here (`TypeEditor`, D-311), and so is a collection from code,
 * through a file there (D-349); the pages and authors types from code are
 * shown read-only (D-042), but their relationships are edited here too,
 * and so is where they sit in the Content menu (`TypeMenuFields`, D-726),
 * saved from the save bar.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import TypeEditor from '../components/TypeEditor.vue';
import TypeFieldSets from '../components/TypeFieldSets.vue';
import SaveBar from '../components/SaveBar.vue';
import TypeIcon from '../components/TypeIcon.vue';
import TypeMenuFields from '../components/TypeMenuFields.vue';
import TypeRelations from '../components/TypeRelations.vue';
import { errorMessage, request, type ContentTypeDetail } from '../api';
import { useAction } from '../action';
import { guardLeave } from '../confirm';
import { humanize, label } from '../fields';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { changesOf, formOf, type TypeForm } from '../type-form';
import { findType, loadTypes, refreshTypes } from '../types';

const route = useRoute();
const type  = ref<ContentTypeDetail | null>(null);
const error = ref('');

// A type that isn't otherwise edited here is still placed in the menu
// (D-726): only its place changes.
const menu = ref<TypeForm | null>(null);

loadTypes().catch(() => undefined);

async function load(name: string): Promise<void> {
	error.value = '';

	try {
		type.value = await request<ContentTypeDetail>('GET', `/types/${encodeURIComponent(name)}`);
	} catch (caught) {
		error.value = errorMessage(caught, t('type_view.load_failed', 'The content type couldn\'t be loaded.'));
	}
}

watch(() => route.params.name, (name) => {
	type.value = null;
	void load(String(name));
}, { immediate: true });

watch(type, (value) => {
	screenTitle.value = value?.labels.plural ?? null;
	menu.value        = value ? formOf(value) : null;
});


const { busy: placing, error: placeFailure, run: runPlace } = useAction();

const placeChanges = computed(() => type.value && menu.value ? changesOf(menu.value, formOf(type.value), type.value.kind === 'tree' ? 'tree' : 'collection') : {});
const placeCount   = computed(() => Object.keys(placeChanges.value).length);

async function place(): Promise<void> {
	const current = type.value;

	if (current === null || placeCount.value === 0 || placing.value) {
		return;
	}

	await runPlace(t('type_view.place_failed', 'Its place in the menu couldn\'t be saved.'), async () => {
		type.value = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(current.name)}`, { set: placeChanges.value });
		refreshTypes();
		toast(t('type_view.saved', 'Saved {label}', { label: current.labels.plural }));
	});
}

function revertPlace(): void {
	menu.value         = type.value ? formOf(type.value) : null;
	placeFailure.value = '';
}

guardLeave(() => !type.value?.editable && placeCount.value > 0);

const people   = computed(() => type.value?.kind === 'profiles');

const origin = computed(() => ({
	'built-in': t('type_view.origin_built_in', 'Built in'),
	extension: t('type_view.origin_plugin', 'A plugin'),
	data: 'user/data/types'
})[type.value?.origin ?? 'extension']);

// The types that credit people, on the profiles type's screen.
const related = computed(() => (people.value ? (type.value?.types ?? []) : []).map((name) => ({ name, label: findType(name)?.labels.plural ?? humanize(name) })));
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'types' }"><AdminIcon name="chevron-left" />{{ t('type_view.all_types', 'All types') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ type?.labels.plural ?? t('type_view.title', 'Content Type') }}</h1>
			<p v-if="type" class="page-header__hint">
				{{ type.terms ? t('type_view.terms', 'Terms') : humanize(type.kind) }} · <span class="mono">{{ type.name }}</span> · {{ t('type_view.from_origin', 'from {origin}', { origin }) }}<template v-if="type.overridden"> · <MessageText :text="t('type_view.changed_in', 'changed in {file}')"><template #file><span class="mono">{{ type.file }}</span></template></MessageText></template>
			</p>
			<p v-if="type?.description" class="page-header__hint">{{ type.description }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink v-if="type" class="button button--primary" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon name="files" />{{ t('type_view.view_items', 'View {items}', { items: type.labels.items }) }}</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<TypeEditor v-if="type?.editable" :type="type" @saved="type = $event" @relations="load(type.name)" />

	<div v-else-if="type" class="detail">
		<div class="detail__side">
			<section class="panel" aria-labelledby="general-heading">
				<header class="panel__header">
					<h2 id="general-heading">{{ t('type_view.general', 'General') }}</h2>
					<p class="panel__hint">{{ t('type_view.defined_in', 'Defined in {origin}, so it\'s shown here', { origin }) }}</p>
				</header>
				<dl class="panel__body facts">
					<div><dt>{{ t('type_view.name_plural', 'Name (plural)') }}</dt><dd>{{ type.labels.plural }}</dd></div>
					<div><dt>{{ t('type_view.name_singular', 'Name (singular)') }}</dt><dd>{{ type.labels.singular }}</dd></div>
					<div v-if="type.labels.menu !== type.labels.plural"><dt>{{ t('type_view.in_the_menu', 'In the menu') }}</dt><dd>{{ type.labels.menu }}</dd></div>
					<div><dt>{{ t('type_view.mid_sentence', 'Mid-sentence') }}</dt><dd>{{ type.labels.item }}, {{ type.labels.items }}</dd></div>
					<div><dt>{{ t('type_view.actions', 'Actions') }}</dt><dd>{{ type.labels.newItem }} · {{ type.labels.editItem }} · {{ type.labels.searchItems }}</dd></div>
					<div><dt>{{ t('type_view.key', 'Key') }}</dt><dd class="mono">{{ type.name }}</dd></div>
					<div><dt>{{ t('type_view.icon', 'Icon') }}</dt><dd class="type-facts__icon"><TypeIcon :type="type" /><span :class="{ mono: type.icon }">{{ type.icon ?? t('type_view.kind_icon', 'The {kind} icon', { kind: type.kind }) }}</span></dd></div>
					<div><dt>{{ t('type_view.folder', 'Folder') }}</dt><dd class="mono">user/content/{{ type.folder }}</dd></div>
					<div><dt>{{ t('type_view.address', 'Address') }}</dt><dd :class="{ mono: type.prefix }">{{ type.prefix ?? t('type_view.no_pages', 'No pages of its own') }}</dd></div>
					<div v-if="type.kind === 'collection'"><dt>{{ t('type_view.nests', 'Nests') }}</dt><dd>{{ type.hierarchical ? t('type_view.nests_yes', 'Yes: an entry can name a parent') : t('common.no', 'No') }}</dd></div>
					<div v-if="type.kind === 'collection'"><dt>{{ t('type_view.order', 'Order') }}</dt><dd>{{ type.order === 'position' ? t('type_view.order_position', 'By position, then title') : t('type_view.order_newest', 'Newest first, by date') }}</dd></div>
					<div v-if="!people"><dt>{{ t('type_view.dated', 'Dated') }}</dt><dd>{{ type.dated ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
					<div v-if="!people"><dt>{{ t('type_view.credits_authors', 'Credits authors') }}</dt><dd>{{ type.authors ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
					<div v-for="archive in type.archivePages" :key="archive.relation"><dt>{{ t('type_view.archives', '{label} archives', { label: archive.label }) }}</dt><dd class="mono">{{ `${(type.prefix ?? '').replace(/\/+$/, '')}/${archive.word}` }}</dd></div>
					<div><dt>{{ t('type_view.public', 'Public') }}</dt><dd>{{ type.public ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
					<div><dt>{{ t('type_view.feed', 'Feed') }}</dt><dd>{{ type.feed ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
					<div><dt>{{ t('type_view.in_sitemap', 'In the sitemap') }}</dt><dd>{{ type.sitemap ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
					<div><dt><MessageText :text="t('type_view.in_llms', 'In {file}')"><template #file><code>llms.txt</code></template></MessageText></dt><dd>{{ type.llms ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
				</dl>
			</section>

			<form v-if="type.placeable && menu" class="panel" aria-labelledby="menu-heading" @submit.prevent="place">
				<header class="panel__header">
					<h2 id="menu-heading">{{ t('type_view.menu_heading', 'In the Menu') }}</h2>
					<p class="panel__hint">{{ t('type_view.menu_hint', 'Where it sits in the Content menu, for everyone') }}</p>
				</header>
				<div class="panel__body">
					<TypeMenuFields v-model="menu" id-prefix="type-" :name="type.name" />
				</div>
				<SaveBar :count="placeCount" :failure="placeFailure" :saving="placing" @revert="revertPlace" />
			</form>

			<section v-if="people" class="panel" aria-labelledby="related-heading">
				<header class="panel__header">
					<h2 id="related-heading">{{ t('type_view.credited_by', 'Credited By') }}</h2>
				</header>
				<div class="panel__body">
					<ul v-if="related.length" class="chips">
						<li v-for="item in related" :key="item.name">
							<RouterLink :to="{ name: 'content-type', params: { name: item.name } }">{{ item.label }}</RouterLink>
						</li>
					</ul>
					<p v-else class="field__help">{{ t('type_view.no_credits', 'No type credits authors yet.') }}</p>
					<p class="field__help">{{ t('type_view.profiles_note', 'Profiles are the public side of accounts, so they\'re under Users in the navigation.') }}</p>
				</div>
			</section>

			<TypeFieldSets :type="type" />
		</div>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">{{ t('type_view.fields', 'Fields') }}</h2>
				<p class="panel__hint">{{ t('type_view.field_count', '{count, plural, one {# field} other {# fields} } of its own', { count: type.fields.length }) }}</p>
			</header>
			<div v-if="type.fields.length" class="table-wrap">
				<table class="table" aria-labelledby="fields-heading">
					<thead>
						<tr>
							<th scope="col">{{ t('type_view.col_field', 'Field') }}</th>
							<th scope="col">{{ t('type_view.col_type', 'Type') }}</th>
							<th scope="col">{{ t('type_view.col_required', 'Required') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="field in type.fields" :key="field.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">{{ label(field) }}</span>
									<span class="entry-title__path">{{ field.name }}</span>
								</span>
							</th>
							<td class="mono">{{ field.type }}{{ field.type === 'list' && field.item ? ` ${t('type_view.list_of', 'of {type}', { type: field.item.type })}` : '' }}{{ field.to ? ` → ${field.to}` : '' }}</td>
							<td>{{ field.required ? t('common.yes', 'Yes') : '' }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p v-else class="panel__body field__help">{{ t('type_view.no_fields', 'None of its own.') }}</p>
			<p class="panel__body field__help">{{ t('type_view.every_entry', 'Every entry also has a title, a status, dates, a slug, and authors, and the fields of its relationships.') }}</p>
		</section>
	</div>

	<div v-else-if="!error" class="detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--label" /><span class="skeleton" /></div></div>
	</div>

	<TypeRelations v-if="type && !type.editable && !people" class="type-relations-panel" :type="type" @changed="load(type.name)" />
</template>

<style scoped>
.type-facts__icon {
	display: flex;
	align-items: center;
	gap: 6px;
}

.detail {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
	align-items: start;
	gap: 16px;
}

/* Relationships are a table of sentences, so they take the width. */
.type-relations-panel {
	margin-top: 16px;
}

.detail__side {
	display: grid;
	gap: 16px;
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
