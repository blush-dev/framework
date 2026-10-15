<script setup lang="ts">
/**
 * A content type from `user/data/types`, edited (D-311), or a collection
 * from code, changed through a file there (D-349): General (names,
 * description, icon, with its key and folder fixed), In the Menu (its
 * place in the Content menu, `TypeMenuFields`, D-726), Behavior
 * (`TypeBehaviorFields`), Archives (its relation archives' list pages,
 * and which credit is its byline, D-602), Relationships (`TypeRelations`,
 * saved on their own, D-593), Addresses
 * (`TypeRoutesFields`, each route key's path, D-350), and Fields
 * (`FieldTree`, with the field sets added to it below, D-337;
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
import { RouterLink, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import DangerZone from './DangerZone.vue';
import MessageText from './MessageText.vue';
import FieldTree from './FieldTree.vue';
import SaveBar from './SaveBar.vue';
import TypeBasicsFields from './TypeBasicsFields.vue';
import TypeBehaviorFields from './TypeBehaviorFields.vue';
import TypeFieldSets from './TypeFieldSets.vue';
import TypeMenuFields from './TypeMenuFields.vue';
import AdminSelect from './AdminSelect.vue';
import TypeRelations from './TypeRelations.vue';
import TypeRoutesFields from './TypeRoutesFields.vue';
import { entryRoute, request, type ContentTypeDetail } from '../api';
import { useAction } from '../action';
import { label } from '../fields';
import { t } from '../messages';
import { changesOf, formOf, type TypeForm, type TypeKind } from '../type-form';
import { toast } from '../toast';
import { refreshTypes, typeUrls, types } from '../types';

const props = defineProps<{ type: ContentTypeDetail }>();
const emit  = defineEmits<{ saved: [type: ContentTypeDetail]; relations: [] }>();

const router  = useRouter();
const kind    = computed<TypeKind>(() => props.type.kind === 'tree' ? 'tree' : 'collection');
const form    = ref<TypeForm>(formOf(props.type));
const initial = ref<TypeForm>(formOf(props.type));
const index   = ref(false);
// The relation archives to give list pages when saved (D-602).
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
const file   = computed(() => props.type.file ?? `user/data/types/${props.type.name}.json`);

// The prefix the addresses sit under, as the form has it.
const prefix = computed(() => (form.value.prefix || props.type.defaultPrefix).replace(/^\/+|\/+$/g, ''));

// Which credit is the byline, when it has more than one (D-602).
// Its workflow (D-851): core's `standard` is `''`.
const workflowOptions = computed(() => props.type.workflows.map((workflow) => ({ value: workflow.name === 'standard' ? '' : workflow.name, label: workflow.label })));

const bylineOptions = computed(() => [{ value: '', label: t('type_editor.choose_one', 'Choose one') }, ...props.type.credits.map((name) => ({ value: name, label: name }))]);

function wantPage(relation: string, on: boolean): void {
	pages.value = on ? [...pages.value, relation] : pages.value.filter((item) => item !== relation);
}

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	await run(t('type_editor.save_failed', 'The type couldn\'t be saved.'), async () => {
		const saved = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(props.type.name)}`, { set: changes.value, index: index.value, listPages: pages.value });

		emit('saved', saved);
		refreshTypes();
		toast(t('type_editor.saved', 'Saved {name}', { name: saved.labels.plural }));
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

	if (!await confirmAction({ title: t('type_editor.reset_title', 'Reset {name}?', { name: props.type.labels.plural }), body: t('type_editor.reset_body', 'It goes back to how its plugin defines it: **{file}** is removed, and every change made here with it.', { file: file.value }), confirm: t('type_editor.reset_the_type', 'Reset the Type'), danger: true })) {
		return;
	}

	await runRemoval(t('type_editor.reset_failed', 'The type couldn\'t be reset.'), async () => {
		const saved = await request<ContentTypeDetail>('POST', `/types/${encodeURIComponent(props.type.name)}/reset`);

		emit('saved', saved);
		refreshTypes();
		toast(t('type_editor.reset_done', 'Reset {name}', { name: saved.labels.plural }));
	});
}

async function remove(): Promise<void> {
	removal.value = '';

	if (!await confirmAction({ title: t('type_editor.delete_title', 'Delete the {name} Type?', { name: props.type.labels.plural }), body: [t('type_editor.delete_body_file', 'Its file in user/data/types is removed.'), t('type_editor.delete_body_entries', 'Its entries stay in **user/content/{folder}**, but nothing lists them until a type claims the folder again.', { folder: props.type.folder })], confirm: t('type_editor.delete_the_type', 'Delete the Type'), danger: true })) {
		return;
	}

	await runRemoval(t('type_editor.delete_failed', 'The type couldn\'t be deleted.'), async () => {
		await request('DELETE', `/types/${encodeURIComponent(props.type.name)}`);
		initial.value = form.value;
		index.value   = false;
		pages.value   = [];
		refreshTypes();
		toast(t('type_editor.deleted', 'Deleted the {name} type', { name: props.type.labels.plural }), { kind: 'danger' });
		await router.push({ name: 'types' });
	});
}

guardLeave(() => changed.value);
</script>

<template>
	<form class="form-stack" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">{{ t('type_editor.general', 'General') }}</h2>
				<p v-if="code" class="panel__hint"><MessageText :text="t('type_editor.from_plugin', 'From its plugin; changes are saved in {file}')"><template #file><code>{{ file }}</code></template></MessageText></p>
				<p v-else class="panel__hint"><MessageText :text="t('type_editor.in_file', 'In {file}')"><template #file><code>{{ type.file }}</code></template></MessageText></p>
			</header>
			<div class="panel__body form-stack">
				<TypeBasicsFields v-model="form" id-prefix="type-" :kind="kind" />
				<dl class="facts facts--inline">
					<div><dt>{{ t('type_editor.key', 'Key') }}</dt><dd class="mono">{{ type.name }}</dd></div>
					<div><dt>{{ t('type_editor.folder', 'Folder') }}</dt><dd class="mono">user/content/{{ type.folder }}</dd></div>
				</dl>
				<p class="field__help">{{ t('type_editor.key_fixed', 'The key is fixed: entries are filed by it, in a folder named for it.') }}</p>
			</div>
		</section>

		<section v-if="type.placeable" class="panel" aria-labelledby="menu-heading">
			<header class="panel__header">
				<h2 id="menu-heading">{{ t('type_editor.in_the_menu', 'In the Menu') }}</h2>
				<p class="panel__hint">{{ t('type_editor.in_the_menu_hint', 'Where it sits in the Content menu, for everyone') }}</p>
			</header>
			<div class="panel__body">
				<TypeMenuFields v-model="form" id-prefix="type-" :name="type.name" />
			</div>
		</section>

		<section class="panel" aria-labelledby="behavior-heading">
			<header class="panel__header">
				<h2 id="behavior-heading">{{ t('type_editor.behavior', 'Behavior') }}</h2>
			</header>
			<div class="panel__body form-stack">
				<TypeBehaviorFields v-model="form" v-model:index="index" id-prefix="type-" :kind="kind" :default-prefix="type.defaultPrefix" :urls="typeUrls && type.prefix !== null" :index-page="type.index" :authors-label="null" />
				<div v-if="workflowOptions.length > 1" class="field">
					<label for="type-workflow">{{ t('type_editor.workflow', 'Workflow') }}</label>
					<AdminSelect id="type-workflow" v-model="form.workflow" :options="workflowOptions" described-by="type-workflow-help" />
					<p id="type-workflow-help" class="field__help">{{ t('type_editor.workflow_help', 'The statuses its entries move through, and who may move them. Entries keep their status when it changes.') }}</p>
				</div>
				<div class="field">
					<label class="checkbox"><input v-model="form.expires" type="checkbox" aria-describedby="type-expires-help"> {{ t('type_editor.expires', 'Entries can expire') }}</label>
					<p id="type-expires-help" class="field__help">{{ t('type_editor.expires_help', 'Each entry gets an Expires date. Once it passes, the entry is off the site, as a draft is, until the date is moved or cleared.') }}</p>
				</div>
			</div>
		</section>

		<section v-if="type.archivePages.length || type.credits.length > 1" class="panel" aria-labelledby="archives-heading">
			<header class="panel__header">
				<h2 id="archives-heading">{{ t('type_editor.archives', 'Archives') }}</h2>
				<p class="panel__hint">{{ t('type_editor.archives_hint', 'What its entries link to, listed under its address') }}</p>
			</header>
			<div class="panel__body form-stack">
				<div v-if="type.credits.length > 1" class="field">
					<label for="type-byline">{{ t('type_editor.byline', 'Byline') }}</label>
					<AdminSelect id="type-byline" v-model="form.byline" :options="bylineOptions" described-by="type-byline-help" />
					<p id="type-byline-help" class="field__help">{{ t('type_editor.byline_help', 'Which credit names an entry\'s author, shown first and linked from its byline.') }}</p>
				</div>
				<div v-for="archive in type.archivePages" :key="archive.relation" class="field">
					<p class="field__label">{{ archive.label }} <span class="mono">/{{ prefix }}/{{ archive.word }}</span></p>
					<p v-if="archive.page" class="field__help"><MessageText :text="t('type_editor.introduced', 'Introduced by {link}, an entry edited like any other.')"><template #link><RouterLink :to="entryRoute(archive.page)">{{ archive.page.title }}</RouterLink></template></MessageText></p>
					<label v-else class="checkbox"><input type="checkbox" :checked="pages.includes(archive.relation)" @change="wantPage(archive.relation, ($event.target as HTMLInputElement).checked)"> {{ t('type_editor.has_page', 'Has a page introducing the list') }}</label>
				</div>
			</div>
			<p class="panel__note">{{ t('type_editor.archives_note', 'Each relationship\'s archives are set under Relationships, below. A page written for one entry\'s archive is kept on that entry\'s screen (a profile\'s, for credits).') }}</p>
		</section>

		<TypeRelations :type="type" @changed="emit('relations')" />

		<section v-if="type.routes.length" class="panel" aria-labelledby="addresses-heading">
			<header class="panel__header">
				<h2 id="addresses-heading">{{ t('type_editor.addresses', 'Addresses') }}</h2>
				<p class="panel__hint"><MessageText :text="t('type_editor.under', 'Under {prefix}')"><template #prefix><code>/{{ prefix }}</code></template></MessageText></p>
			</header>
			<div class="panel__body">
				<TypeRoutesFields v-model="form" id-prefix="route-" :routes="type.routes" :prefix="prefix" :terms="type.terms" :editable="typeUrls" />
			</div>
		</section>

		<FieldTree v-if="type.fieldsEditable" v-model="form.fields" heading-id="fields-heading" :hint="t('type_editor.fields_hint', 'Beside the title, slug, status, dates, and body every entry has')" id-prefix="field-" :noun="type.labels.plural.toLowerCase()" />
		<section v-else class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">{{ t('type_editor.fields', 'Fields') }}</h2>
				<p class="panel__hint">{{ t('type_editor.fields_hint', 'Beside the title, slug, status, dates, and body every entry has') }}</p>
			</header>
			<div class="panel__body">
				<ul class="type-editor__fields">
					<li v-for="field in type.fields" :key="field.name">{{ label(field) }} <span class="mono">{{ field.type }}</span></li>
				</ul>
				<p class="field__help">{{ t('type_editor.fields_in_code', 'Some of its fields are field classes its plugin defines, so they\'re changed there.') }}</p>
			</div>
		</section>

		<TypeFieldSets :type="type" />

		<DangerZone v-if="code" :error="removal">
			{{ type.overridden ? t('type_editor.removes_file', 'Removes {file}, so every setting is as its plugin has it.', { file }) : t('type_editor.nothing_changed', 'Nothing has changed it here yet.') }} {{ t('type_editor.defined_in_code', 'It\'s defined in code, so it can\'t be deleted here.') }}
			<template #action><button type="button" class="button button--danger" :disabled="!type.overridden" @click="reset"><AdminIcon name="refresh-cw" />{{ t('type_editor.reset_the_type', 'Reset the Type') }}</button></template>
		</DangerZone>
		<DangerZone v-else :error="removal">
			<MessageText :text="t('type_editor.delete_warning', 'Removes its file. Its entries stay on disk, unlisted until a type claims {folder} again. Relationships that name it must be removed first.')"><template #folder><code>user/content/{{ type.folder }}</code></template></MessageText>
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="x" />{{ t('type_editor.delete_this_type', 'Delete This Type') }}</button></template>
		</DangerZone>

		<SaveBar :count="count" :failure="failure" :saving="saving" @revert="revert" />
	</form>
</template>

<style scoped>
.type-editor__fields {
	margin: 0;
	padding-left: 1.2em;
}
</style>
