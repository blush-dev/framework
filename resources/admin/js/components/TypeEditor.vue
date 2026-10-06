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
 * together from the save bar, which counts the changes (D-508), with
 * **Save changes** (`PATCH types/{name}`, only what changed) or put back
 * with **Revert**; leaving with changes unsaved asks first. A
 * Danger Zone deletes a data type's file (its entries stay where they
 * are), or resets a code type to the code's definition.
 *
 * After a save or a delete, it asks the server to compile the routes and
 * reindex with the new types (`POST types/refresh`) and loads the types
 * again, so the navigation shows the change.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import DangerZone from './DangerZone.vue';
import FieldListEditor from './FieldListEditor.vue';
import SaveBar from './SaveBar.vue';
import TypeBasicsFields from './TypeBasicsFields.vue';
import TypeBehaviorFields from './TypeBehaviorFields.vue';
import TypeFieldSets from './TypeFieldSets.vue';
import TypePeopleFields from './TypePeopleFields.vue';
import TypeRoutesFields from './TypeRoutesFields.vue';
import { request, type ContentTypeDetail } from '../api';
import { useAction } from '../action';
import { label } from '../fields';
import { changesOf, formOf, type TypeForm, type TypeKind } from '../type-form';
import { toast } from '../toast';
import { profileType, refreshTypes, typeUrls, types } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ saved: [type: ContentTypeDetail] }>();

const router  = useRouter();
const kind    = computed<TypeKind>(() => props.type.kind === 'taxonomy' || props.type.kind === 'tree' ? props.type.kind : 'collection');
const form    = ref<TypeForm>(formOf(props.type));
const initial = ref<TypeForm>(formOf(props.type));
const index   = ref(false);
// The people fields to give list pages when saved (D-353).
const pages   = ref<string[]>([]);

const { busy: saving, error: failure, run } = useAction();
const { error: removal, run: runRemoval }   = useAction();

watch(() => props.type, (type) => {
	form.value    = formOf(type);
	initial.value = formOf(type);
	index.value   = false;
	pages.value   = [];
});

const changes = computed(() => changesOf(form.value, initial.value, kind.value));
// What the save bar counts: each setting changed, the index page asked
// for, and each list page.
const count   = computed(() => Object.keys(changes.value).length + (index.value ? 1 : 0) + pages.value.length);
const changed = computed(() => count.value > 0);

// A type from code, changed through a file in user/data/types (D-349).
const code   = computed(() => props.type.origin !== 'data');
const source = computed(() => props.type.origin === 'config' ? 'config/content.php' : 'a plugin');
const file   = computed(() => props.type.file ?? `user/data/types/${props.type.name}.yaml`);

// The prefix the addresses sit under, as the form has it.
const prefix = computed(() => (form.value.prefix || props.type.folderPrefix).replace(/^\/+|\/+$/g, ''));

// The site's profiles type, which the People panel credits.
const profilesLabel = computed(() => types.value.find((item) => item.name === profileType.value)?.labels.plural ?? null);

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	await run('The type couldn\'t be saved.', async () => {
		const saved = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(props.type.name)}`, { set: changes.value, index: index.value, listPages: pages.value });

		emit('saved', saved);
		refreshTypes();
		toast(`Saved ${saved.labels.plural}`);
	});
}

function revert(): void {
	form.value    = formOf(props.type);
	index.value   = false;
	pages.value   = [];
	failure.value = '';
}

async function reset(): Promise<void> {
	removal.value = '';

	if (!await confirmAction({ title: `Reset ${props.type.labels.plural}?`, body: `It goes back to how ${source.value} defines it: **${file.value}** is removed, and every change made here with it.`, confirm: 'Reset the Type', danger: true })) {
		return;
	}

	await runRemoval('The type couldn\'t be reset.', async () => {
		const saved = await request<ContentTypeDetail>('POST', `/types/${encodeURIComponent(props.type.name)}/reset`);

		emit('saved', saved);
		refreshTypes();
		toast(`Reset ${saved.labels.plural}`);
	});
}

async function remove(): Promise<void> {
	removal.value = '';

	if (!await confirmAction({ title: `Delete the ${props.type.labels.plural} Type?`, body: [`Its file in user/data/types is removed.`, `Its entries stay in **user/content/${props.type.folder}**, but nothing lists them until a type claims the folder again.`], confirm: 'Delete the Type', danger: true })) {
		return;
	}

	await runRemoval('The type couldn\'t be deleted.', async () => {
		await request('DELETE', `/types/${encodeURIComponent(props.type.name)}`);
		initial.value = form.value;
		index.value   = false;
		pages.value   = [];
		refreshTypes();
		toast(`Deleted the ${props.type.labels.plural} type`, { kind: 'danger' });
		await router.push({ name: 'types' });
	});
}

guardLeave(() => changed.value);
</script>

<template>
	<form class="form-stack" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">General</h2>
				<p v-if="code" class="panel__hint">From {{ source }}; changes are saved in <code>{{ file }}</code></p>
				<p v-else class="panel__hint">In <code>{{ type.file }}</code></p>
			</header>
			<div class="panel__body form-stack">
				<TypeBasicsFields v-model="form" id-prefix="type-" :kind="kind" />
				<dl class="facts facts--inline">
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

		<section v-if="profilesLabel !== null && form.people !== null && form.people.length && kind !== 'tree'" class="panel" aria-labelledby="archives-heading">
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

		<DangerZone v-if="code" :error="removal">
			{{ type.overridden ? `Removes ${file}, so every setting is as ${source} has it.` : `Nothing has changed it here yet.` }} It's defined in code, so it can't be deleted here.
			<template #action><button type="button" class="button button--danger" :disabled="!type.overridden" @click="reset"><AdminIcon name="refresh-cw" />Reset to {{ source }}</button></template>
		</DangerZone>
		<DangerZone v-else :error="removal">
			Removes its file. Its entries stay on disk, unlisted until a type claims <code>user/content/{{ type.folder }}</code> again. A taxonomy that groups it must stop first.
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="x" />Delete This Type</button></template>
		</DangerZone>

		<SaveBar :count="count" :failure="failure" :saving="saving" @revert="revert" />
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

.type-editor__fields {
	margin: 0;
	padding-left: 1.2em;
}
</style>
