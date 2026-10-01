<script setup lang="ts">
/**
 * A content type from `user/data/types`, edited (D-311): General (names,
 * description, icon, with its key and folder fixed), Behavior
 * (`TypeBehaviorFields`, with the authors settings, D-329), and Fields (`FieldListEditor`, with the
 * field sets added to it below, D-337), saved together
 * with **Save** (`PATCH types/{name}`, only what changed) or put back
 * with **Revert**; leaving with changes unsaved asks first. A Danger
 * Zone deletes the type's file; its entries stay where they are.
 *
 * After a save or a delete, it asks the server to compile the routes and
 * reindex with the new types (`POST types/refresh`) and loads the types
 * again, so the navigation shows the change.
 */

import { computed, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import FieldListEditor from './FieldListEditor.vue';
import TypeBasicsFields from './TypeBasicsFields.vue';
import TypeBehaviorFields from './TypeBehaviorFields.vue';
import TypeFieldSets from './TypeFieldSets.vue';
import { ApiError, request, type ContentTypeDetail } from '../api';
import { changesOf, formOf, type TypeForm } from '../type-form';
import { toast } from '../toast';
import { authorType, reloadTypes, typeUrls, types } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ saved: [type: ContentTypeDetail] }>();

const router  = useRouter();
const kind    = computed(() => props.type.kind === 'taxonomy' ? 'taxonomy' as const : 'collection' as const);
const form    = ref<TypeForm>(formOf(props.type));
const initial = ref<TypeForm>(formOf(props.type));
const index   = ref(false);
const page    = ref(false);
const saving  = ref(false);
const failure = ref('');
const removal = ref('');

watch(() => props.type, (type) => {
	form.value    = formOf(type);
	initial.value = formOf(type);
	index.value   = false;
	page.value    = false;
});

const changes = computed(() => changesOf(form.value, initial.value, kind.value));
const changed = computed(() => Object.keys(changes.value).length > 0 || index.value || page.value);

// The site's authors type, which the Behavior panel names.
const authorsLabel = computed(() => types.value.find((item) => item.name === authorType.value)?.labels.plural ?? null);

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
		const saved = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(props.type.name)}`, { set: changes.value, index: index.value, authorsPage: page.value && form.value.authors && form.value.authorArchives });

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
	page.value    = false;
	failure.value = '';
}

async function remove(): Promise<void> {
	removal.value = '';

	if (!window.confirm(`Delete the ${props.type.labels.plural} type? Its file in user/data/types is removed; its entries stay in user/content/${props.type.folder}, but nothing lists them until a type claims the folder again.`)) {
		return;
	}

	try {
		await request('DELETE', `/types/${encodeURIComponent(props.type.name)}`);
		initial.value = form.value;
		index.value   = false;
		page.value    = false;
		refresh();
		toast(`Deleted the ${props.type.labels.plural} type`);
		await router.push({ name: 'types' });
	} catch (caught) {
		removal.value = caught instanceof ApiError ? caught.message : 'The type couldn\'t be deleted.';
	}
}

onBeforeRouteLeave(() => !changed.value || window.confirm('Leave without saving? Your changes will be lost.'));
</script>

<template>
	<form class="type-editor" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">General</h2>
				<p class="panel__hint">In <code>{{ type.file }}</code></p>
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
				<TypeBehaviorFields v-model="form" v-model:index="index" v-model:page-wanted="page" id-prefix="type-" :kind="kind" :folder-prefix="type.folderPrefix" :urls="typeUrls && type.prefix !== null" :types="types.filter((item) => item.name !== type.name)" :index-page="type.index" :authors-label="authorsLabel" :authors-page="type.authorsPage" />
			</div>
		</section>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
				<p class="panel__hint">Beside the title, slug, status, dates, and body every entry has</p>
			</header>
			<FieldListEditor v-model="form.fields" :types="types" id-prefix="field-" />
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
			<div class="panel__body type-editor__danger">
				<button type="button" class="button button--danger button--small" @click="remove"><AdminIcon name="x" />Delete this type</button>
				<p v-if="removal" class="field__error" role="alert">{{ removal }}</p>
				<p class="field__help">Removes its file. Its entries stay on disk, unlisted until a type claims <code>user/content/{{ type.folder }}</code> again. A taxonomy that groups it must stop first.</p>
			</div>
		</section>
	</form>
</template>

<style scoped>
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

.type-editor__danger {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
}
</style>
