<script setup lang="ts">
/**
 * A content type from `user/data/types`, edited (D-311), or a collection
 * or taxonomy from code, changed through a file there (D-349): General
 * (names, description, icon, with its key and folder fixed), Behavior
 * (`TypeBehaviorFields`), Profiles (`TypePeopleFields`, its profile fields,
 * D-353, D-369), Addresses
 * (`TypeRoutesFields`, each route key's path, D-350), and Fields
 * (`FieldListEditor`, with the field sets added to it below, D-337;
 * read-only when the code's fields are classes of its own), saved
 * together with **Save** (`PATCH types/{name}`, only what changed) or
 * put back with **Revert**; leaving with changes unsaved asks first. A
 * Danger Zone deletes a data type's file (its entries stay where they
 * are), or resets a code type to the code's definition.
 *
 * After a save or a delete, it asks the server to compile the routes and
 * reindex with the new types (`POST types/refresh`) and loads the types
 * again, so the navigation shows the change.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, confirmLeave } from '../confirm';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import FieldListEditor from './FieldListEditor.vue';
import TypeBasicsFields from './TypeBasicsFields.vue';
import TypeBehaviorFields from './TypeBehaviorFields.vue';
import TypeFieldSets from './TypeFieldSets.vue';
import TypePeopleFields from './TypePeopleFields.vue';
import TypeRoutesFields from './TypeRoutesFields.vue';
import { ApiError, request, type ContentTypeDetail } from '../api';
import { label } from '../fields';
import { changesOf, formOf, type TypeForm } from '../type-form';
import { toast } from '../toast';
import { profileType, reloadTypes, typeUrls, types } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ saved: [type: ContentTypeDetail] }>();

const router  = useRouter();
const kind    = computed(() => props.type.kind === 'taxonomy' ? 'taxonomy' as const : 'collection' as const);
const form    = ref<TypeForm>(formOf(props.type));
const initial = ref<TypeForm>(formOf(props.type));
const index   = ref(false);
// The people fields to give list pages when saved (D-353).
const pages   = ref<string[]>([]);
const saving  = ref(false);
const failure = ref('');
const removal = ref('');

watch(() => props.type, (type) => {
	form.value    = formOf(type);
	initial.value = formOf(type);
	index.value   = false;
	pages.value   = [];
});

const changes = computed(() => changesOf(form.value, initial.value, kind.value));
const changed = computed(() => Object.keys(changes.value).length > 0 || index.value || pages.value.length > 0);

// A type from code, changed through a file in user/data/types (D-349).
const code   = computed(() => props.type.origin !== 'data');
const source = computed(() => props.type.origin === 'config' ? 'config/content.php' : 'an extension');
const file   = computed(() => props.type.file ?? `user/data/types/${props.type.name}.yaml`);

// The prefix the addresses sit under, as the form has it.
const prefix = computed(() => (form.value.prefix || props.type.folderPrefix).replace(/^\/+|\/+$/g, ''));

// The site's profiles type, which the People panel credits.
const profilesLabel = computed(() => types.value.find((item) => item.name === profileType.value)?.labels.plural ?? null);

// After a change: the routes and index, then the navigation.
function refresh(): void {
	request('POST', '/types/refresh').catch(() => undefined).finally(() => {
		reloadTypes().catch(() => undefined);
	});
}

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const saved = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(props.type.name)}`, { set: changes.value, index: index.value, listPages: pages.value });

		emit('saved', saved);
		refresh();
		toast(`Saved ${saved.labels.plural}`);
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The type couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	form.value    = formOf(props.type);
	index.value   = false;
	pages.value   = [];
	failure.value = '';
}

async function reset(): Promise<void> {
	removal.value = '';

	if (!await confirmAction({ title: `Reset ${props.type.labels.plural}?`, body: `It goes back to how ${source.value} defines it: **${file.value}** is removed, and every change made here with it.`, confirm: 'Reset the type', danger: true })) {
		return;
	}

	try {
		const saved = await request<ContentTypeDetail>('POST', `/types/${encodeURIComponent(props.type.name)}/reset`);

		emit('saved', saved);
		refresh();
		toast(`Reset ${saved.labels.plural}`);
	} catch (caught) {
		removal.value = caught instanceof ApiError ? caught.message : 'The type couldn\'t be reset.';
	}
}

async function remove(): Promise<void> {
	removal.value = '';

	if (!await confirmAction({ title: `Delete the ${props.type.labels.plural} Type?`, body: [`Its file in user/data/types is removed.`, `Its entries stay in **user/content/${props.type.folder}**, but nothing lists them until a type claims the folder again.`], confirm: 'Delete the type', danger: true })) {
		return;
	}

	try {
		await request('DELETE', `/types/${encodeURIComponent(props.type.name)}`);
		initial.value = form.value;
		index.value   = false;
		pages.value   = [];
		refresh();
		toast(`Deleted the ${props.type.labels.plural} type`);
		await router.push({ name: 'types' });
	} catch (caught) {
		removal.value = caught instanceof ApiError ? caught.message : 'The type couldn\'t be deleted.';
	}
}

onBeforeRouteLeave(() => !changed.value || confirmLeave());
</script>

<template>
	<form class="type-editor" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">General</h2>
				<p v-if="code" class="panel__hint">From {{ source }}; changes are saved in <code>{{ file }}</code></p>
				<p v-else class="panel__hint">In <code>{{ type.file }}</code></p>
			</header>
			<div class="panel__body type-editor__body">
				<TypeBasicsFields v-model="form" id-prefix="type-" :kind="kind" />
				<dl class="type-editor__facts">
					<div><dt>Key</dt><dd class="mono">{{ type.name }}</dd></div>
					<div><dt>Folder</dt><dd class="mono">user/content/{{ type.folder }}</dd></div>
				</dl>
				<p class="field__help">The key and folder are fixed: entries are filed by them.</p>
			</div>
		</section>

		<section class="panel" aria-labelledby="behavior-heading">
			<header class="panel__header">
				<h2 id="behavior-heading">Behavior</h2>
			</header>
			<div class="panel__body">
				<TypeBehaviorFields v-model="form" v-model:index="index" id-prefix="type-" :kind="kind" :folder-prefix="type.folderPrefix" :urls="typeUrls && type.prefix !== null" :types="types.filter((item) => item.name !== type.name)" :index-page="type.index" :authors-label="null" :authors-page="null" />
			</div>
		</section>

		<section v-if="profilesLabel !== null && form.people !== null" class="panel" aria-labelledby="people-heading">
			<header class="panel__header type-people-header">
				<h2 id="people-heading">{{ profilesLabel }}</h2>
				<p class="panel__hint">Each field credits a profile, under this type's own word for it</p>
				<div class="panel__actions">
					<TypePeopleFields v-model="form.people" v-model:list-pages="pages" part="add" id-prefix="add-" :prefix="prefix" :urls="typeUrls && type.prefix !== null" :saved="type.people" :profiles-label="profilesLabel" />
				</div>
			</header>
			<div class="type-people-rows">
				<TypePeopleFields v-model="form.people" v-model:list-pages="pages" part="fields" id-prefix="people-" :prefix="prefix" :urls="typeUrls && type.prefix !== null" :saved="type.people" :profiles-label="profilesLabel" />
			</div>
			<p class="panel__note">Every field points at the one <strong>{{ profilesLabel }}</strong> collection. A person is one profile with one slug; these are this type's words for how they're credited.</p>
		</section>

		<section v-if="profilesLabel !== null && form.people !== null && form.people.length" class="panel" aria-labelledby="archives-heading">
			<header class="panel__header">
				<h2 id="archives-heading">Archives</h2>
				<p class="panel__hint">Whether a field's addresses route at all</p>
			</header>
			<div class="panel__body">
				<TypePeopleFields v-model="form.people" v-model:list-pages="pages" part="archives" id-prefix="archives-" :prefix="prefix" :urls="typeUrls && type.prefix !== null" :saved="type.people" :profiles-label="profilesLabel" />
			</div>
			<p class="panel__note">Same switch as the type's own index page, and the same rule: turning it off stops the routing and deletes nothing that was written. Pages written for that field's archives are kept and marked unreachable on each profile.</p>
		</section>

		<section v-if="type.routes.length" class="panel" aria-labelledby="addresses-heading">
			<header class="panel__header">
				<h2 id="addresses-heading">Addresses</h2>
				<p class="panel__hint">Under <code>/{{ prefix }}</code></p>
			</header>
			<div class="panel__body">
				<TypeRoutesFields v-model="form" id-prefix="route-" :routes="type.routes" :prefix="prefix" :taxonomy="kind === 'taxonomy'" :people="type.people" :editable="typeUrls" />
			</div>
		</section>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
				<p class="panel__hint">Beside the title, slug, status, dates, and body every entry has</p>
			</header>
			<FieldListEditor v-if="type.fieldsEditable" v-model="form.fields" :types="types" id-prefix="field-" />
			<div v-else class="panel__body">
				<ul class="type-editor__fields">
					<li v-for="field in type.fields" :key="field.name">{{ label(field) }} <span class="mono">{{ field.type }}</span></li>
				</ul>
				<p class="field__help">Some of its fields are field classes {{ source }} defines, so they're changed there.</p>
			</div>
		</section>

		<TypeFieldSets :type="type" />

		<div class="type-editor__save">
			<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
			<button type="submit" class="button button--primary" :disabled="!changed || saving">{{ saving ? 'Saving…' : 'Save' }}</button>
			<button v-if="changed" type="button" class="button button--ghost" :disabled="saving" @click="revert">Revert</button>
		</div>

		<section class="panel" aria-labelledby="danger-heading">
			<header class="panel__header">
				<h2 id="danger-heading">Danger Zone</h2>
			</header>
			<div v-if="code" class="panel__body type-editor__danger">
				<button type="button" class="button button--danger button--small" :disabled="!type.overridden" @click="reset"><AdminIcon name="refresh-cw" />Reset to {{ source }}</button>
				<p v-if="removal" class="field__error" role="alert">{{ removal }}</p>
				<p class="field__help">{{ type.overridden ? `Removes ${file}, so every setting is as ${source} has it.` : `Nothing has changed it here yet.` }} It's defined in code, so it can't be deleted here.</p>
			</div>
			<div v-else class="panel__body type-editor__danger">
				<button type="button" class="button button--danger button--small" @click="remove"><AdminIcon name="x" />Delete this type</button>
				<p v-if="removal" class="field__error" role="alert">{{ removal }}</p>
				<p class="field__help">Removes its file. Its entries stay on disk, unlisted until a type claims <code>user/content/{{ type.folder }}</code> again. A taxonomy that groups it must stop first.</p>
			</div>
		</section>
	</form>
</template>

<style scoped>
/* The profile fields' rows run edge to edge under a ruled header, as the
   profiles sketch has them. */
.type-people-header {
	border-bottom: 0;
}

.type-people-rows {
	border-top: 1px solid var(--border);
}

.type-editor {
	display: grid;
	gap: var(--s-4);
}

.type-editor__body {
	display: grid;
	gap: var(--s-4);
}

.type-editor__body > * + * {
	margin-top: 0;
}

.type-editor__facts {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2) var(--s-6);
	margin: 0;
}

.type-editor__facts div {
	display: flex;
	gap: 8px;
}

.type-editor__facts dt {
	color: var(--fg-2);
}

.type-editor__facts dd {
	margin: 0;
}

.type-editor__save {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.type-editor__save .field__error {
	flex-basis: 100%;
	margin: 0;
}

.type-editor__fields {
	margin: 0;
	padding-left: 1.2em;
}

.type-editor__danger {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
}
</style>
