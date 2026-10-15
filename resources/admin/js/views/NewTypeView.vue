<script setup lang="ts">
/**
 * A new content type (D-311; admin.md §8, List, then detail: the type
 * wizard is its own screen): Basics (a collection, a taxonomy (D-856), or a tree,
 * D-386; names, key, description, icon), Behavior
 * (`TypeBehaviorFields`, and for a taxonomy the types it files), and Fields
 * (`FieldTree`), with What Gets Created beside them, updating as
 * the steps are filled in. **Create type** writes
 * `user/data/types/{key}.json` (`POST types`), adds the index page when
 * asked, and opens the type's screen. A taxonomy is a collection ordered by
 * position, with no authors and out
 * of `llms.txt`, and a classify relation
 * named after it that files the chosen types under them (`POST
 * relations`, D-593).
 *
 * A type is kept in `_` and its key (D-683). The key follows the plural
 * name made singular, and the URL prefix the plural name, until either
 * is typed. Leaving with anything filled in asks first.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { RouterLink, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldTree from '../components/FieldTree.vue';
import MessageText from '../components/MessageText.vue';
import TypeBasicsFields from '../components/TypeBasicsFields.vue';
import TypeBehaviorFields from '../components/TypeBehaviorFields.vue';
import { request, type ContentTypeDetail } from '../api';
import { useAction } from '../action';
import { label as fieldLabel } from '../fields';
import { t } from '../messages';
import { changesOf, DATE_ARCHIVES, emptyForm, FEATURED, folderOf, hasFeatured, singularOf, typeKeyOf, type TypeForm, type TypeKind } from '../type-form';
import { toast } from '../toast';
import { profileType, canCreateTypes, loadTypes, refreshTypes, typeFiles, typeUrls, types } from '../types';

const router = useRouter();

loadTypes().catch(() => undefined);

const STEPS = [t('new_type_view.step_basics', 'Basics'), t('new_type_view.step_behavior', 'Behavior'), t('new_type_view.step_fields', 'Fields')];

const step          = ref(0);
// What it is: a taxonomy is a collection a classify relation files entries
// under (D-593).
const choice        = ref<TypeKind | 'taxonomy'>('collection');
const kind          = computed<TypeKind>(() => choice.value === 'taxonomy' ? 'collection' : choice.value);
// The types a new taxonomy files; none for every type.
const files         = ref<string[]>([]);
const form          = ref<TypeForm>({ ...emptyForm(), fields: [{ ...FEATURED }] });
const key           = ref('');
const keyTouched    = ref(false);
const index         = ref(true);
const authorsPage   = ref(false);
const created       = ref(false);
const previous      = ref('');

const { busy: creating, error: failure, run } = useAction();

// The key and prefix follow the names until they're typed.
watch(() => form.value.plural, (plural) => {
	if (!keyTouched.value) {
		key.value = typeKeyOf(plural);
	}

	if (typeUrls.value && (form.value.prefix === '' || form.value.prefix === folderOf(previous.value))) {
		form.value.prefix = folderOf(plural);
	}

	if (form.value.singular === '' || form.value.singular === singularOf(previous.value)) {
		form.value.singular = singularOf(plural);
	}

	previous.value = plural;
});

// Taxonomies and trees have no featured image; a collection starts with one.
watch(choice, (value) => {
	if (value !== 'collection' && hasFeatured(form.value)) {
		form.value.fields = form.value.fields.filter((field) => !(field.name === FEATURED.name && field.type === FEATURED.type));
	}

	// Collections credit authors by default; taxonomies don't (D-329).
	form.value.authors = value === 'collection';

	// Taxonomies are left out of llms.txt unless they ask (D-401), and are
	// ordered by position (D-412).
	form.value.llms  = value !== 'taxonomy';
	form.value.order = value === 'taxonomy' ? 'position' : 'date';
});

// The types a new taxonomy can file: any but profiles.
const fileable = computed(() => types.value.filter((type) => type.kind !== 'profiles'));

function filed(name: string, on: boolean): void {
	files.value = on ? [...files.value, name] : files.value.filter((item) => item !== name);
}

const authorsLabel = computed(() => types.value.find((item) => item.name === profileType.value)?.labels.plural ?? null);

const keyError = computed(() => {
	if (key.value === '') {
		return t('new_type_view.key_required', 'Give it a key.');
	}

	if (!/^[a-z][a-z0-9_]*$/.test(key.value)) {
		return t('new_type_view.key_invalid', 'A key starts with a lowercase letter and uses lowercase letters, digits, and underscores.');
	}

	return types.value.some((type) => type.name === key.value) ? t('new_type_view.key_taken', '“{key}” is already a type. Pick another key.', { key: key.value }) : '';
});

const basicsDone = computed(() => form.value.plural.trim() !== '' && keyError.value === '');
const prefix     = computed(() => (form.value.prefix || key.value).replace(/^\/+|\/+$/g, ''));
const dirty      = computed(() => !created.value && (form.value.plural.trim() !== '' || form.value.fields.length > 1));

function next(): void {
	if (step.value === 0 && !basicsDone.value) {
		return;
	}

	if (step.value < STEPS.length - 1) {
		step.value += 1;

		return;
	}

	create();
}

async function create(): Promise<void> {
	if (creating.value) {
		return;
	}

	await run(t('new_type_view.create_failed', 'The type couldn\'t be created.'), async () => {
		const type = await request<ContentTypeDetail>('POST', '/types', {
			name: key.value,
			kind: kind.value,
			index: index.value,
			// It joins the authors credit (D-602), with its list's page.
			authors: form.value.authors && authorsLabel.value !== null,
			listPages: kind.value !== 'tree' && authorsPage.value && form.value.authors && authorsLabel.value !== null ? ['authors'] : [],
			set: changesOf(form.value, null, kind.value)
		});

		// A taxonomy files other entries through a relation named after it.
		if (choice.value === 'taxonomy') {
			await request('POST', '/relations', { name: type.name, kind: 'classify', from: files.value, to: [type.name], create: true });
		}

		created.value = true;
		refreshTypes();
		toast(t('new_type_view.created', 'Created {type}', { type: type.labels.plural }));
		await router.push({ name: 'content-type', params: { name: type.name } });
	});
}

guardLeave(() => dirty.value, () => confirmAction({ title: t('new_type_view.leave_title', 'Leave Without Creating the Type?'), body: t('new_type_view.leave_body', 'What you\'ve filled in will be lost.'), confirm: t('new_type_view.leave', 'Leave'), cancel: t('new_type_view.stay', 'Stay'), danger: true }));

const archiveLabel = computed(() => DATE_ARCHIVES.find((option) => option.value === form.value.dateArchives)?.label ?? t('new_type_view.none', 'None'));
const fileLabels   = computed(() => files.value.map((name) => types.value.find((type) => type.name === name)?.labels.plural ?? name));
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('new_type_view.title', 'New Content Type') }}</h1>
			<p class="page-header__hint">{{ t('new_type_view.hint', 'A type defines what an entry of that kind is, where it lives, and what fields it carries.') }}</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'types' }">{{ t('common.cancel', 'Cancel') }}</RouterLink>
		</div>
	</header>

	<p v-if="!canCreateTypes" class="notice notice--warn"><span><MessageText :text="t('new_type_view.data_types_off', '{config} turns off types in {folder} ({option}), so types can\'t be created here.')"><template #config><code>config/content.php</code></template><template #folder><code>user/data/types</code></template><template #option><code>dataTypes</code></template></MessageText></span></p>

	<div v-else class="wizard">
		<form class="panel" aria-labelledby="wizard-heading" @submit.prevent="next">
			<h2 id="wizard-heading" class="visually-hidden">{{ STEPS[step] }}</h2>
			<ol class="steps">
				<li v-for="(name, at) in STEPS" :key="name" class="step" :class="{ 'step--done': at < step }" :aria-current="at === step ? 'step' : undefined">
					<span class="step__number">
						<AdminIcon v-if="at < step" name="check" />
						<template v-else>{{ at + 1 }}</template>
					</span>
					{{ name }}
				</li>
			</ol>

			<div v-if="step === 0" class="panel__body form-stack">
				<fieldset class="fieldset kinds">
					<legend>{{ t('new_type_view.kind', 'Kind') }}</legend>
					<label class="kind" :class="{ 'kind--on': choice === 'collection' }">
						<input v-model="choice" type="radio" value="collection" name="kind" class="visually-hidden">
						<span class="kind__name">{{ t('new_type_view.collection', 'Collection') }}</span>
						<span class="kind__text">{{ t('new_type_view.collection_text', 'Entries people write: posts, recipes, anything with a body.') }}</span>
					</label>
					<label class="kind" :class="{ 'kind--on': choice === 'taxonomy' }">
						<input v-model="choice" type="radio" value="taxonomy" name="kind" class="visually-hidden">
						<span class="kind__name">{{ t('new_type_view.taxonomy', 'Taxonomy') }}</span>
						<span class="kind__text">{{ t('new_type_view.taxonomy_text', 'Entries that file other entries, like topics or tags.') }}</span>
					</label>
					<label class="kind" :class="{ 'kind--on': choice === 'tree' }">
						<input v-model="choice" type="radio" value="tree" name="kind" class="visually-hidden">
						<span class="kind__name">{{ t('new_type_view.tree', 'Tree') }}</span>
						<span class="kind__text">{{ t('new_type_view.tree_text', 'Pages that nest by folder, like docs or a handbook.') }}</span>
					</label>
				</fieldset>
				<TypeBasicsFields v-model="form" id-prefix="new-" :kind="kind" />
				<div class="field">
					<label for="new-key">{{ t('new_type_view.key', 'Key') }}</label>
					<input id="new-key" v-model="key" class="mono" placeholder="recipe" autocomplete="off" spellcheck="false" :aria-invalid="form.plural && keyError ? 'true' : undefined" aria-describedby="new-key-help" @input="keyTouched = true">
					<p v-if="form.plural && keyError" id="new-key-help" class="field__error">{{ keyError }}</p>
					<p v-else id="new-key-help" class="field__help">{{ t('new_type_view.key_help', 'Names the type in files and the API, and the folder its entries are kept in. Fixed once it\'s created.') }}</p>
				</div>
			</div>

			<div v-else-if="step === 1" class="panel__body form-stack">
				<fieldset v-if="choice === 'taxonomy'" class="fieldset">
					<legend>{{ t('new_type_view.files', 'Files') }}</legend>
					<label v-for="type in fileable" :key="type.name" class="checkbox"><input type="checkbox" :checked="files.includes(type.name)" @change="filed(type.name, ($event.target as HTMLInputElement).checked)"> {{ type.labels.plural }}</label>
					<p class="field__help">{{ files.length === 0 ? t('new_type_view.files_none', 'None chosen, so it files every type.') : t('new_type_view.files_some', 'Entries of these types can be filed under it. Change it later under Relationships.') }}</p>
				</fieldset>
				<TypeBehaviorFields v-model="form" v-model:index="index" v-model:page-wanted="authorsPage" id-prefix="new-" :kind="kind" :default-prefix="key" :urls="typeUrls" :index-page="null" :authors-label="authorsLabel" />
			</div>

			<div v-else>
				<p class="panel__body field__help">{{ t('new_type_view.fields_help', 'Every entry already has a title, a slug, a status, dates, and a Markdown body. Fields add structured data beside them; you can add more later.') }}</p>
				<FieldTree v-model="form.fields" heading-id="new-fields-heading" id-prefix="new-field-" bare />
			</div>

			<p v-if="failure" class="panel__body field__error" role="alert">{{ failure }}</p>

			<footer class="wizard__foot">
				<button type="button" class="button button--small" :disabled="step === 0" @click="step -= 1"><AdminIcon name="chevron-left" />{{ t('common.back', 'Back') }}</button>
				<button type="submit" class="button button--primary button--small" :disabled="(step === 0 && !basicsDone) || creating">
					<template v-if="step < STEPS.length - 1">{{ t('new_type_view.next', 'Next') }}<AdminIcon name="chevron-right" /></template>
					<template v-else><AdminIcon name="check" />{{ creating ? t('new_type_view.creating', 'Creating…') : t('new_type_view.create', 'Create Type') }}</template>
				</button>
			</footer>
		</form>

		<section class="panel" aria-labelledby="summary-heading">
			<header class="panel__header">
				<h2 id="summary-heading">{{ t('new_type_view.summary', 'What Gets Created') }}</h2>
				<p class="panel__hint">{{ t('new_type_view.summary_hint', 'Updates as you go') }}</p>
			</header>
			<dl class="panel__body facts">
				<div><dt>{{ t('new_type_view.kind', 'Kind') }}</dt><dd>{{ { collection: t('new_type_view.collection', 'Collection'), taxonomy: t('new_type_view.taxonomy', 'Taxonomy'), tree: t('new_type_view.tree', 'Tree') }[choice] }}</dd></div>
				<div><dt>{{ t('new_type_view.name', 'Name') }}</dt><dd>{{ form.plural || t('new_type_view.not_set', 'Not set') }}<template v-if="form.singular"> / {{ form.singular }}</template></dd></div>
				<div><dt>{{ t('new_type_view.file', 'File') }}</dt><dd class="mono">user/data/types/{{ key || '…' }}.json</dd></div>
				<div v-if="choice === 'taxonomy'"><dt>{{ t('new_type_view.relationship', 'Relationship') }}</dt><dd class="mono">user/data/relations/{{ key || '…' }}.json</dd></div>
				<div v-if="typeFiles"><dt>{{ t('new_type_view.entries_in', 'Entries in') }}</dt><dd class="mono">user/content/_{{ key || '…' }}</dd></div>
				<div><dt>{{ t('new_type_view.addresses', 'Addresses') }}</dt><dd class="mono">/{{ prefix || '…' }}/{{ kind === 'tree' ? '{path}' : '{slug}' }}</dd></div>
				<div v-if="choice === 'collection'"><dt>{{ t('new_type_view.dated', 'Dated') }}</dt><dd>{{ form.dateArchives === 'none' ? t('common.no', 'No') : t('new_type_view.dated_yes', 'Yes, {archives}', { archives: archiveLabel.toLowerCase() }) }}</dd></div>
				<div v-if="kind === 'tree'"><dt>{{ t('new_type_view.nesting', 'Nesting') }}</dt><dd>{{ t('new_type_view.by_folder', 'By folder') }}</dd></div>
				<div v-else><dt>{{ t('new_type_view.nesting', 'Nesting') }}</dt><dd>{{ form.hierarchical ? t('new_type_view.nest_parent', 'Entries nest by parent') : t('new_type_view.flat', 'Flat') }}</dd></div>
				<div v-if="choice === 'taxonomy'"><dt>{{ t('new_type_view.files', 'Files') }}</dt><dd>{{ fileLabels.length ? fileLabels.join(', ') : t('new_type_view.every_type', 'Every type') }}</dd></div>
				<div><dt>{{ t('new_type_view.index_page', 'Index page') }}</dt><dd>{{ index ? t('new_type_view.index_created', 'Created and pinned') : t('new_type_view.none', 'None') }}</dd></div>
				<div v-if="authorsLabel !== null"><dt>{{ authorsLabel }}</dt><dd>{{ form.authors ? t('new_type_view.credited', 'Credited, through the authors relationship') : t('new_type_view.not_credited', 'Not credited') }}</dd></div>
				<div v-if="kind !== 'tree'"><dt>{{ t('new_type_view.feed', 'Feed') }}</dt><dd>{{ form.feed ? t('common.yes', 'Yes') : t('common.no', 'No') }}</dd></div>
				<div><dt>{{ t('new_type_view.fields', 'Fields') }}</dt><dd>{{ form.fields.length ? form.fields.map(fieldLabel).join(', ') : t('new_type_view.none_yet', 'None yet') }}</dd></div>
			</dl>
		</section>
	</div>
</template>

<style scoped>
.wizard {
	display: grid;
	grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.steps {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-4);
	margin: 0;
	padding: var(--s-4) var(--padding-inline);
	border-bottom: 1px solid var(--border-color);
	list-style: none;
}

.step {
	display: flex;
	align-items: center;
	gap: 7px;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

.step[aria-current="step"] {
	color: var(--color-text);
	font-weight: 500;
}

.step--done {
	color: var(--color-text-secondary);
}

.step__number {
	display: grid;
	place-items: center;
	width: 23px;
	height: 23px;
	border: 1px solid var(--border-color-strong);
	border-radius: 99px;
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
}

.step__number :deep(svg) {
	width: 12px;
	height: 12px;
}

.step[aria-current="step"] .step__number {
	border-color: var(--color-accent);
	background: var(--color-accent);
	color: var(--color-accent-text);
}

.step--done .step__number {
	border-color: var(--color-success-indicator);
	background: var(--color-success-subtle);
	color: var(--color-success);
}

.kinds {
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: var(--s-3);
}

.kind {
	display: grid;
	gap: 4px;
	padding: var(--s-3) var(--s-4);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface);
	cursor: pointer;
}

.kind:hover {
	border-color: var(--border-color-strong);
}

.kind:has(input:focus-visible) {
	outline: 2px solid var(--color-accent);
	outline-offset: 2px;
}

.kind--on {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
}

.kind__name {
	font-weight: 600;
}

.kind__text {
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.wizard__foot {
	display: flex;
	justify-content: space-between;
	gap: var(--s-2);
	padding: var(--s-4) var(--padding-inline);
	border-top: 1px solid var(--border-color);
}

@media (width <= 1100px) {
	.wizard {
		grid-template-columns: minmax(0, 1fr);
	}
}

@media (width <= 760px) {
	.kinds {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
