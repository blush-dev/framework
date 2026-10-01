<script setup lang="ts">
/**
 * One content type (D-250): its names and settings, the taxonomies that
 * group it (or, for a taxonomy, the types it groups), and the fields it
 * defines, with a way to its entries. A type from `user/data/types` is
 * edited here (`TypeEditor`, D-311); the rest are defined in code and
 * shown read-only (D-042).
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import TypeEditor from '../components/TypeEditor.vue';
import TypeIcon from '../components/TypeIcon.vue';
import { ApiError, request, type ContentTypeDetail } from '../api';
import { humanize, label } from '../fields';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { findType, loadTypes } from '../types';

const route = useRoute();
const type  = ref<ContentTypeDetail | null>(null);
const error = ref('');

loadTypes().catch(() => undefined);

watch(() => route.params.name, async (name) => {
	type.value  = null;
	error.value = '';

	try {
		type.value = await request<ContentTypeDetail>('GET', `/types/${encodeURIComponent(String(name))}`);
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The content type couldn\'t be loaded.';
	}
}, { immediate: true });

watch(type, (value) => {
	screenTitle.value = value?.labels.plural ?? null;
});

const taxonomy = computed(() => type.value?.kind === 'taxonomy');
const people   = computed(() => type.value?.kind === 'authors');

const origin = computed(() => ({
	'built-in': 'Built in',
	extension: 'An extension',
	config: 'config/content.php',
	data: 'user/data/types'
})[type.value?.origin ?? 'config']);

// The other side: a taxonomy's types, the types that credit authors, or
// a type's taxonomies.
const related = computed(() => {
	const detail = type.value;

	if (detail === null) {
		return [];
	}

	const names = taxonomy.value || people.value ? (detail.types ?? []) : detail.taxonomies;

	return names.map((name) => ({ name, label: findType(name)?.labels.plural ?? humanize(name) }));
});
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ type?.labels.plural ?? 'Content Type' }}</h1>
			<p v-if="type" class="page-header__hint">
				{{ humanize(type.kind) }} · <span class="mono">{{ type.name }}</span> · from {{ origin }}
			</p>
			<p v-if="type?.description" class="page-header__hint">{{ type.description }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'types' }"><AdminIcon name="arrow-left" />All types</RouterLink>
			<RouterLink v-if="type" class="button button--primary" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon name="files" />View {{ type.labels.items }}</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<TypeEditor v-if="type?.editable" :type="type" @saved="type = $event" />

	<div v-else-if="type" class="detail">
		<div class="detail__side">
			<section class="panel" aria-labelledby="general-heading">
				<header class="panel__header">
					<h2 id="general-heading">General</h2>
					<p class="panel__hint">Defined in {{ origin }}, so it's shown here</p>
				</header>
				<dl class="panel__body facts">
					<div><dt>Name (plural)</dt><dd>{{ type.labels.plural }}</dd></div>
					<div><dt>Name (singular)</dt><dd>{{ type.labels.singular }}</dd></div>
					<div v-if="type.labels.menu !== type.labels.plural"><dt>In the menu</dt><dd>{{ type.labels.menu }}</dd></div>
					<div><dt>Mid-sentence</dt><dd>{{ type.labels.item }}, {{ type.labels.items }}</dd></div>
					<div><dt>Actions</dt><dd>{{ type.labels.newItem }} · {{ type.labels.editItem }} · {{ type.labels.searchItems }}</dd></div>
					<div><dt>Key</dt><dd class="mono">{{ type.name }}</dd></div>
					<div><dt>Icon</dt><dd class="type-facts__icon"><TypeIcon :type="type" /><span :class="{ mono: type.icon }">{{ type.icon ?? `The ${type.kind === 'pages' ? 'pages' : type.kind} icon` }}</span></dd></div>
					<div><dt>Folder</dt><dd class="mono">user/content/{{ type.folder }}</dd></div>
					<div><dt>Address</dt><dd :class="{ mono: type.prefix }">{{ type.prefix ?? 'No pages of its own' }}</dd></div>
					<div v-if="taxonomy"><dt>Hierarchical</dt><dd>{{ type.hierarchical ? 'Yes: a term can name a parent' : 'No' }}</dd></div>
					<div v-else-if="!people"><dt>Dated</dt><dd>{{ type.dated ? 'Yes' : 'No' }}</dd></div>
					<div v-if="!people"><dt>Credits authors</dt><dd>{{ type.authors ? 'Yes' : 'No' }}</dd></div>
					<div v-if="!people && type.authors && type.prefix !== null"><dt>Author archives</dt><dd :class="{ mono: type.authorsWord }">{{ type.authorsWord ? `${type.prefix.replace(/\/+$/, '')}/${type.authorsWord}` : 'None' }}</dd></div>
					<div><dt>Public</dt><dd>{{ type.public ? 'Yes' : 'No' }}</dd></div>
					<div><dt>Feed</dt><dd>{{ type.feed ? 'Yes' : 'No' }}</dd></div>
					<div><dt>In the sitemap</dt><dd>{{ type.sitemap ? 'Yes' : 'No' }}</dd></div>
				</dl>
			</section>

			<section class="panel" aria-labelledby="related-heading">
				<header class="panel__header">
					<h2 id="related-heading">{{ people ? 'Credited by' : (taxonomy ? 'Groups' : 'Taxonomies') }}</h2>
				</header>
				<div class="panel__body">
					<ul v-if="related.length" class="chips">
						<li v-for="item in related" :key="item.name">
							<RouterLink :to="{ name: 'content-type', params: { name: item.name } }">{{ item.label }}</RouterLink>
						</li>
					</ul>
					<p v-else-if="people" class="field__help">No type credits authors yet.</p>
					<p v-else-if="taxonomy" class="field__help">Every type, so it's under Shared taxonomies in the navigation.</p>
					<p v-else class="field__help">No taxonomy groups it.</p>
					<p v-if="people" class="field__help">Authors are the public side of accounts, so they're under People in the navigation.</p>
					<p v-if="taxonomy && related.length === 1" class="field__help">One type, so it sits under {{ related[0]?.label }} in the navigation.</p>
				</div>
			</section>
		</div>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
				<p class="panel__hint">{{ plural(type.fields.length, 'field') }} of its own</p>
			</header>
			<div v-if="type.fields.length" class="table-wrap">
				<table class="table" aria-labelledby="fields-heading">
					<thead>
						<tr>
							<th scope="col">Field</th>
							<th scope="col">Type</th>
							<th scope="col">Required</th>
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
							<td class="mono">{{ field.type }}{{ field.type === 'list' && field.item ? ` of ${field.item.type}` : '' }}{{ field.to ? ` → ${field.to}` : '' }}</td>
							<td>{{ field.required ? 'Yes' : '' }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p v-else class="panel__body field__help">None of its own.</p>
			<p class="panel__body field__help">Every entry also has a title, a status, dates, a slug, and authors, and the fields of the taxonomies that group it.</p>
		</section>
	</div>

	<div v-else-if="!error" class="detail" aria-hidden="true">
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
		<div class="panel"><div class="panel__body"><span class="skeleton skeleton--label" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
/* Widths as classes: the admin's CSP blocks inline style attributes. */
.skeleton--heading {
	width: 40%;
}

.skeleton--label {
	width: 30%;
}

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
	text-align: right;
	overflow-wrap: anywhere;
}

.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.chips a {
	display: inline-block;
	padding: 2px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: var(--surface-2);
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-decoration: none;
}

.chips a:hover {
	border-color: var(--border-strong);
	color: var(--fg);
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
