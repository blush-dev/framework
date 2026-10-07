<script setup lang="ts">
/**
 * A new content type (D-311; admin.md §8, List, then detail: the type
 * wizard is its own screen): Basics (a collection, terms, or a tree,
 * D-386; names, key, folder, description, icon), Behavior
 * (`TypeBehaviorFields`, and for terms the types they file), and Fields
 * (`FieldListEditor`), with What Gets Created beside them, updating as
 * the steps are filled in. **Create type** writes
 * `user/data/types/{key}.json` (`POST types`), adds the index page when
 * asked, and opens the type's screen. Terms are a collection ordered by
 * position, with no authors and out of `llms.txt`, and a classify relation
 * named after it that files the chosen types under them (`POST
 * relations`, D-593).
 *
 * The key follows the plural name made singular, and the folder the
 * plural name, until either is typed. Leaving with anything filled in
 * asks first.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { RouterLink, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldListEditor from '../components/FieldListEditor.vue';
import TypeBasicsFields from '../components/TypeBasicsFields.vue';
import TypeBehaviorFields from '../components/TypeBehaviorFields.vue';
import { request, type ContentTypeDetail } from '../api';
import { useAction } from '../action';
import { label as fieldLabel } from '../fields';
import { changesOf, DATE_ARCHIVES, emptyForm, FEATURED, folderOf, hasFeatured, singularOf, typeKeyOf, type TypeForm, type TypeKind } from '../type-form';
import { toast } from '../toast';
import { profileType, canCreateTypes, loadTypes, refreshTypes, typeUrls, types } from '../types';

const router = useRouter();

loadTypes().catch(() => undefined);

const STEPS = ['Basics', 'Behavior', 'Fields'];

const step          = ref(0);
// What it is: terms are a collection a classify relation files entries
// under (D-593).
const choice        = ref<TypeKind | 'terms'>('collection');
const kind          = computed<TypeKind>(() => choice.value === 'terms' ? 'collection' : choice.value);
// The types a new type of terms files; none for every type.
const files         = ref<string[]>([]);
const form          = ref<TypeForm>({ ...emptyForm(), fields: [{ ...FEATURED }] });
const key           = ref('');
const folder        = ref('');
const keyTouched    = ref(false);
const folderTouched = ref(false);
const index         = ref(true);
const authorsPage   = ref(false);
const created       = ref(false);
const previous      = ref('');

const { busy: creating, error: failure, run } = useAction();

// The key and folder follow the names until they're typed.
watch(() => form.value.plural, (plural) => {
	if (!keyTouched.value) {
		key.value = typeKeyOf(plural);
	}

	if (!folderTouched.value) {
		folder.value = folderOf(plural);
	}

	if (form.value.singular === '' || form.value.singular === singularOf(previous.value)) {
		form.value.singular = singularOf(plural);
	}

	previous.value = plural;
});

// Terms and trees have no featured image; a collection starts with one.
watch(choice, (value) => {
	if (value !== 'collection' && hasFeatured(form.value)) {
		form.value.fields = form.value.fields.filter((field) => !(field.name === FEATURED.name && field.type === FEATURED.type));
	}

	// Collections credit authors by default; terms don't (D-329).
	form.value.authors = value === 'collection';

	// Terms are left out of llms.txt unless they ask (D-401), and are
	// ordered by position (D-412).
	form.value.llms  = value !== 'terms';
	form.value.order = value === 'terms' ? 'position' : 'published';
});

// The types new terms can file: any but profiles.
const fileable = computed(() => types.value.filter((type) => type.kind !== 'profiles'));

function filed(name: string, on: boolean): void {
	files.value = on ? [...files.value, name] : files.value.filter((item) => item !== name);
}

const authorsLabel = computed(() => types.value.find((item) => item.name === profileType.value)?.labels.plural ?? null);

const keyError = computed(() => {
	if (key.value === '') {
		return 'Give it a key.';
	}

	if (!/^[a-z][a-z0-9_]*$/.test(key.value)) {
		return 'A key starts with a lowercase letter and uses lowercase letters, digits, and underscores.';
	}

	return types.value.some((type) => type.name === key.value) ? `“${key.value}” is already a type. Pick another key.` : '';
});

const folderClean = computed(() => folder.value.trim().replace(/^\/+|\/+$/g, ''));
const folderError = computed(() => {
	if (folderClean.value === '') {
		return 'Give it a folder.';
	}

	const used = types.value.find((type) => type.folder === folderClean.value);

	return used ? `${used.labels.plural} already use this folder.` : '';
});

const basicsDone = computed(() => form.value.plural.trim() !== '' && keyError.value === '' && folderError.value === '');
const prefix     = computed(() => (form.value.prefix || folderClean.value.split('/').map((part) => part.replace(/^_+/, '')).join('/')).replace(/^\/+|\/+$/g, ''));
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

	await run('The type couldn\'t be created.', async () => {
		const type = await request<ContentTypeDetail>('POST', '/types', {
			name: key.value,
			kind: kind.value,
			folder: folderClean.value,
			index: index.value,
			authorsPage: kind.value !== 'tree' && authorsPage.value && form.value.authors && form.value.authorArchives && authorsLabel.value !== null,
			set: changesOf(form.value, null, kind.value)
		});

		// Terms file other entries through a relation named after them.
		if (choice.value === 'terms') {
			await request('POST', '/relations', { name: type.name, kind: 'classify', from: files.value, to: [type.name], create: true });
		}

		created.value = true;
		refreshTypes();
		toast(`Created ${type.labels.plural}`);
		await router.push({ name: 'content-type', params: { name: type.name } });
	});
}

guardLeave(() => dirty.value, () => confirmAction({ title: 'Leave Without Creating the Type?', body: 'What you\'ve filled in will be lost.', confirm: 'Leave', cancel: 'Stay', danger: true }));

const archiveLabel = computed(() => DATE_ARCHIVES.find((option) => option.value === form.value.dateArchives)?.label ?? 'None');
const fileLabels   = computed(() => files.value.map((name) => types.value.find((type) => type.name === name)?.labels.plural ?? name));
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">New Content Type</h1>
			<p class="page-header__hint">A type defines what an entry of that kind is, where it lives, and what fields it carries.</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'types' }">Cancel</RouterLink>
		</div>
	</header>

	<p v-if="!canCreateTypes" class="notice notice--warn"><span><code>config/content.php</code> turns off types in <code>user/data/types</code> (<code>dataTypes</code>), so types can't be created here.</span></p>

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
					<legend>Kind</legend>
					<label class="kind" :class="{ 'kind--on': choice === 'collection' }">
						<input v-model="choice" type="radio" value="collection" name="kind" class="visually-hidden">
						<span class="kind__name">Collection</span>
						<span class="kind__text">Entries people write: posts, recipes, anything with a body.</span>
					</label>
					<label class="kind" :class="{ 'kind--on': choice === 'terms' }">
						<input v-model="choice" type="radio" value="terms" name="kind" class="visually-hidden">
						<span class="kind__name">Terms</span>
						<span class="kind__text">Entries that file other entries, like topics or tags.</span>
					</label>
					<label class="kind" :class="{ 'kind--on': choice === 'tree' }">
						<input v-model="choice" type="radio" value="tree" name="kind" class="visually-hidden">
						<span class="kind__name">Tree</span>
						<span class="kind__text">Pages that nest by folder, like docs or a handbook.</span>
					</label>
				</fieldset>
				<TypeBasicsFields v-model="form" id-prefix="new-" :kind="kind" />
				<div class="field-pair">
					<div class="field">
						<label for="new-key">Key</label>
						<input id="new-key" v-model="key" class="mono" placeholder="recipe" autocomplete="off" spellcheck="false" :aria-invalid="form.plural && keyError ? 'true' : undefined" aria-describedby="new-key-help" @input="keyTouched = true">
						<p v-if="form.plural && keyError" id="new-key-help" class="field__error">{{ keyError }}</p>
						<p v-else id="new-key-help" class="field__help">Names the type in files and the API. Fixed once it's created.</p>
					</div>
					<div class="field">
						<label for="new-folder">Folder</label>
						<input id="new-folder" v-model="folder" class="mono" placeholder="recipes" autocomplete="off" spellcheck="false" :aria-invalid="form.plural && folderError ? 'true' : undefined" aria-describedby="new-folder-help" @input="folderTouched = true">
						<p v-if="form.plural && folderError" id="new-folder-help" class="field__error">{{ folderError }}</p>
						<p v-else id="new-folder-help" class="field__help">Where its entries live, in <code>user/content</code>. Fixed once it's created.</p>
					</div>
				</div>
			</div>

			<div v-else-if="step === 1" class="panel__body form-stack">
				<fieldset v-if="choice === 'terms'" class="fieldset">
					<legend>Files</legend>
					<label v-for="type in fileable" :key="type.name" class="checkbox"><input type="checkbox" :checked="files.includes(type.name)" @change="filed(type.name, ($event.target as HTMLInputElement).checked)"> {{ type.labels.plural }}</label>
					<p class="field__help">{{ files.length === 0 ? 'None chosen, so its terms file every type.' : 'Entries of these types can be filed under its terms. Change it later under Relationships.' }}</p>
				</fieldset>
				<TypeBehaviorFields v-model="form" v-model:index="index" v-model:page-wanted="authorsPage" id-prefix="new-" :kind="kind" :folder-prefix="prefix" :urls="typeUrls" :index-page="null" :authors-label="authorsLabel" :authors-page="null" />
			</div>

			<div v-else>
				<p class="panel__body field__help">Every entry already has a title, a slug, a status, dates, and a Markdown body. Fields add structured data beside them; you can add more later.</p>
				<FieldListEditor v-model="form.fields" :types="types" id-prefix="new-field-" />
			</div>

			<p v-if="failure" class="panel__body field__error" role="alert">{{ failure }}</p>

			<footer class="wizard__foot">
				<button type="button" class="button button--small" :disabled="step === 0" @click="step -= 1"><AdminIcon name="chevron-left" />Back</button>
				<button type="submit" class="button button--primary button--small" :disabled="(step === 0 && !basicsDone) || creating">
					<template v-if="step < STEPS.length - 1">Next<AdminIcon name="chevron-right" /></template>
					<template v-else><AdminIcon name="check" />{{ creating ? 'Creating…' : 'Create Type' }}</template>
				</button>
			</footer>
		</form>

		<section class="panel" aria-labelledby="summary-heading">
			<header class="panel__header">
				<h2 id="summary-heading">What Gets Created</h2>
				<p class="panel__hint">Updates as you go</p>
			</header>
			<dl class="panel__body facts">
				<div><dt>Kind</dt><dd>{{ { collection: 'Collection', terms: 'Terms', tree: 'Tree' }[choice] }}</dd></div>
				<div><dt>Name</dt><dd>{{ form.plural || 'Not set' }}<template v-if="form.singular"> / {{ form.singular }}</template></dd></div>
				<div><dt>File</dt><dd class="mono">user/data/types/{{ key || '…' }}.json</dd></div>
				<div v-if="choice === 'terms'"><dt>Relationship</dt><dd class="mono">user/data/relations/{{ key || '…' }}.json</dd></div>
				<div><dt>Entries in</dt><dd class="mono">user/content/{{ folderClean || '…' }}</dd></div>
				<div><dt>Addresses</dt><dd class="mono">/{{ prefix || '…' }}/{{ kind === 'tree' ? '{path}' : '{slug}' }}</dd></div>
				<div v-if="choice === 'collection'"><dt>Dated</dt><dd>{{ form.dateArchives === 'none' ? 'No' : `Yes, ${archiveLabel.toLowerCase()}` }}</dd></div>
				<div v-if="kind === 'tree'"><dt>Nesting</dt><dd>By folder</dd></div>
				<div v-else><dt>Nesting</dt><dd>{{ form.hierarchical ? 'Entries nest by parent' : 'Flat' }}</dd></div>
				<div v-if="choice === 'terms'"><dt>Files</dt><dd>{{ fileLabels.length ? fileLabels.join(', ') : 'Every type' }}</dd></div>
				<div><dt>Index page</dt><dd>{{ index ? 'Created and pinned' : 'None' }}</dd></div>
				<div v-if="authorsLabel !== null"><dt>{{ authorsLabel }}</dt><dd>{{ !form.authors ? 'Not credited' : (form.authorArchives && typeUrls && kind !== 'tree' ? `Credited, with archives at /${prefix || '…'}/${form.authorsWord.trim() || 'authors'}` : 'Credited') }}</dd></div>
				<div v-if="kind !== 'tree'"><dt>Feed</dt><dd>{{ form.feed ? 'Yes' : 'No' }}</dd></div>
				<div><dt>Fields</dt><dd>{{ form.fields.length ? form.fields.map(fieldLabel).join(', ') : 'None yet' }}</dd></div>
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
	padding: var(--s-4) var(--pad-x);
	border-bottom: 1px solid var(--border);
	list-style: none;
}

.step {
	display: flex;
	align-items: center;
	gap: 7px;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.step[aria-current="step"] {
	color: var(--fg);
	font-weight: 500;
}

.step--done {
	color: var(--fg-2);
}

.step__number {
	display: grid;
	place-items: center;
	width: 23px;
	height: 23px;
	border: 1px solid var(--border-strong);
	border-radius: 99px;
	font-family: var(--font-mono);
	font-size: var(--text-xs);
}

.step__number :deep(svg) {
	width: 12px;
	height: 12px;
}

.step[aria-current="step"] .step__number {
	border-color: var(--accent);
	background: var(--accent);
	color: var(--accent-fg);
}

.step--done .step__number {
	border-color: var(--good-dot);
	background: var(--good-soft);
	color: var(--good);
}

.kinds {
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: var(--s-3);
}

.kind {
	display: grid;
	gap: 4px;
	padding: var(--s-3) var(--s-4);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	cursor: pointer;
}

.kind:hover {
	border-color: var(--border-strong);
}

.kind:has(input:focus-visible) {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.kind--on {
	border-color: var(--accent);
	background: var(--accent-soft);
}

.kind__name {
	font-weight: 600;
}

.kind__text {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.wizard__foot {
	display: flex;
	justify-content: space-between;
	gap: var(--s-2);
	padding: var(--s-4) var(--pad-x);
	border-top: 1px solid var(--border);
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
