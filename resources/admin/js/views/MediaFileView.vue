<script setup lang="ts">
/**
 * One file in the media library (D-251): a preview, its metadata, its
 * facts, and what to write to use it.
 *
 * The metadata is the library's own, kept in `user/data` (`PATCH
 * media/{path}`): the fields its kind has (D-287), as a form built from
 * their definitions, as an entry's are (`FieldControl`), with keys the
 * fields don't declare shown as they are and what doesn't fit under its
 * field. What the file says about itself (D-289: its EXIF, IPTC, and
 * XMP) is shown From the File, each value with **Use** to copy it into
 * the field it fits (a title into the title, a creator or credit into
 * the credit; captions only ever come from the library, D-290), and a file that carries its location says so, without
 * where. Alt text and a caption are filled in when the file is inserted
 * as an image (D-269), and a page's image without alt text uses the
 * library's (D-270); what an entry writes is its own. Changes are saved
 * when asked, only the fields that changed, and leaving with changes
 * unsaved asks first.
 *
 * Who may change a file's details, or delete it, is by whose it is
 * (D-407): the file says who uploaded it, and its details are read-only
 * to someone who may not change them. It lists the entries that use it,
 * and deleting it asks first, naming them.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, confirmLeave } from '../confirm';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldControl from '../components/FieldControl.vue';
import { ApiError, request, type FieldDescription, type MediaDetail } from '../api';
import { fromForm, toForm, type FormValue } from '../fields';
import { attributeText, imageText } from '../markdown';
import { formatDate, formatSize } from '../format';
import { forgetFile, formatDuration, mediaFacts, mediaName } from '../media';
import { screenTitle } from '../screen';
import { toast } from '../toast';

const route  = useRoute();
const router = useRouter();
const file  = ref<MediaDetail | null>(null);
const error = ref('');

const path = computed(() => {
	const segments = route.params.path;

	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
});

const address = computed(() => `/media/${path.value.split('/').map(encodeURIComponent).join('/')}`);

// The fields, as typed, and as loaded.
const form    = ref<Record<string, FormValue>>({});
const initial = ref<Record<string, FormValue>>({});
const saving  = ref(false);
const failure = ref('');
const invalid = ref<{ field: string; message: string } | null>(null);

const fields  = computed<FieldDescription[]>(() => file.value?.fields ?? []);

// The field sets attached to the kind (D-341), each under its label after
// the built-in fields.
const inSets    = computed(() => new Set((file.value?.sets ?? []).flatMap((set) => set.fields)));
const ownFields = computed(() => fields.value.filter((field) => !inSets.value.has(field.name)));
const setGroups = computed(() => (file.value?.sets ?? []).map((set) => ({
	...set,
	fields: set.fields.flatMap((name) => fields.value.filter((field) => field.name === name))
})).filter((set) => set.fields.length > 0));
const changes = computed(() => fields.value.filter((field) => form.value[field.name] !== initial.value[field.name]));
const changed = computed(() => changes.value.length > 0);
const altText = computed(() => typeof form.value.alt === 'string' ? form.value.alt.trim() : '');
const extra   = computed(() => Object.entries(file.value?.extra ?? {}));

// What the file says about itself, labeled, with the field each value
// can fill, if the file's kind has it.
const EMBEDDED: Record<string, { label: string; field?: string }> = {
	title: { label: 'Title', field: 'title' },
	description: { label: 'Description', field: 'description' },
	creator: { label: 'Creator', field: 'credit' },
	album: { label: 'Album' },
	track: { label: 'Track' },
	genre: { label: 'Genre' },
	credit: { label: 'Credit', field: 'credit' },
	copyright: { label: 'Copyright' },
	keywords: { label: 'Keywords' },
	created: { label: 'Made' },
	camera: { label: 'Camera' },
	lens: { label: 'Lens' },
	focalLength: { label: 'Focal length' },
	aperture: { label: 'Aperture' },
	exposure: { label: 'Exposure' },
	iso: { label: 'ISO' },
	orientation: { label: 'Orientation' },
	artwork: { label: 'Artwork' },
	software: { label: 'Software' }
};

const embedded = computed(() => Object.entries(file.value?.embedded.values ?? {}).filter(([key]) => EMBEDDED[key] !== undefined).map(([key, value]) => {
	const text  = Array.isArray(value) ? value.join(', ') : String(value);
	const field = EMBEDDED[key]?.field;

	return { key, label: EMBEDDED[key]?.label ?? key, text, field: field !== undefined && fields.value.some((item) => item.name === field) && form.value[field] !== text ? field : undefined };
}));

// Copies a value into a field, as typing it would; saving is still asked.
function use(field: string, text: string): void {
	form.value[field] = text;
	document.getElementById(`media-${field}`)?.focus();
}

function fieldLabel(name: string): string {
	const found = fields.value.find((field) => field.name === name);

	return found?.label ?? name;
}

function fill(item: MediaDetail): void {
	file.value    = item;
	form.value    = Object.fromEntries(item.fields.map((field) => [field.name, toForm(field, item.values[field.name])]));
	initial.value = { ...form.value };
	invalid.value = null;
}

// What's wrong with a field: what the last save refused, or what the
// file holds that doesn't fit it.
function errorFor(field: FieldDescription): string | undefined {
	if (invalid.value?.field === field.name) {
		return invalid.value.message;
	}

	const found = file.value?.violations.find((violation) => violation.field === field.name && violation.severity === 'error');

	return found === undefined ? undefined : `${found.message.charAt(0).toUpperCase()}${found.message.slice(1)}`;
}

watch(path, async () => {
	file.value    = null;
	error.value   = '';
	failure.value = '';

	try {
		fill(await request<MediaDetail>('GET', address.value));
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The file couldn\'t be loaded.';
	}
}, { immediate: true });

watch(file, (value) => {
	screenTitle.value = value === null ? null : mediaName(value);
});

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';
	invalid.value = null;

	const set: Record<string, unknown> = {};
	const remove: string[] = [];

	for (const field of changes.value) {
		const value = fromForm(field, form.value[field.name] ?? '', file.value?.values[field.name]);

		if (value === null) {
			remove.push(field.name);
		} else {
			set[field.name] = value;
		}
	}

	try {
		fill(await request<MediaDetail>('PATCH', address.value, { set, remove }));
		forgetFile(file.value?.reference ?? '');
		toast('Saved');
	} catch (caught) {
		if (caught instanceof ApiError && caught.field !== null) {
			invalid.value = { field: caught.field, message: caught.message };
		} else {
			failure.value = caught instanceof ApiError ? caught.message : 'It couldn\'t be saved.';
		}
	} finally {
		saving.value = false;
	}
}

onBeforeRouteLeave(() => !changed.value || confirmLeave());

const deleting = ref(false);

// Deletes the file, saying first which entries use it.
async function remove(): Promise<void> {
	const item = file.value;

	if (item === null || deleting.value) {
		return;
	}

	const used = item.usedIn.length;
	const body = used === 0
		? ['It isn\'t used in any entry. This can\'t be undone.']
		: [
			`It's used in **${used === 1 ? '1 entry' : `${used} entries`}**: ${item.usedIn.slice(0, 5).map((entry) => entry.title).join(', ')}${used > 5 ? `, and ${used - 5} more` : ''}. They'll show a broken image or link until they're changed.`,
			'This can\'t be undone.'
		];

	if (!await confirmAction({ title: `Delete ${mediaName(item)}?`, body, confirm: 'Delete the file', danger: true })) {
		return;
	}

	deleting.value = true;

	try {
		await request('DELETE', address.value);
		forgetFile(item.reference);
		initial.value = { ...form.value };
		toast(`Deleted ${mediaName(item)}`);
		await router.push({ name: 'media' });
	} catch (caught) {
		toast(caught instanceof ApiError ? caught.message : 'The file couldn\'t be deleted.', { kind: 'warn' });
	} finally {
		deleting.value = false;
	}
}

// What an entry would write to show it: an image is Markdown, with the
// library's alt text and caption (D-267, D-268); the rest are components.
const snippet = computed(() => {
	const item = file.value;

	if (item === null) {
		return '';
	}

	return written(item.reference);
});

// What an entry writes to show it, by a reference.
function written(reference: string): string {
	const item = file.value;

	if (item === null) {
		return '';
	}

	if (item.kind === 'image') {
		return imageText(reference, item.alt, item.caption).text;
	}

	const name = item.kind === 'video' ? 'video' : (item.kind === 'audio' ? 'audio' : 'file');

	return `::blush/${name}{${attributeText('src', reference)}}`;
}

async function copy(text: string, what: string): Promise<void> {
	try {
		await navigator.clipboard.writeText(text);
		toast(`Copied the ${what}`);
	} catch {
		toast(`The ${what} couldn't be copied`, { kind: 'warn' });
	}
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'media' }"><AdminIcon name="chevron-left" />All media</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ file ? mediaName(file) : 'File' }}</h1>
			<p v-if="file" class="page-header__hint">
				<template v-if="file.title"><span class="mono">{{ file.name }}</span> · </template>{{ file.mime }} · {{ mediaFacts(file) }}
			</p>
		</div>
		<div class="page-header__actions">
			<a v-if="file" class="button" :href="file.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />Open<span class="visually-hidden"> the file (new tab)</span></a>
			<button v-if="file?.may.delete" type="button" class="button button--danger" :disabled="deleting" @click="remove"><AdminIcon name="trash-2" />Delete</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="file" class="detail">
		<section class="panel preview" aria-label="Preview">
			<img v-if="file.kind === 'image'" :src="file.url" :alt="`Preview of ${mediaName(file)}`">
			<video v-else-if="file.kind === 'video'" :src="file.url" controls preload="metadata" />
			<audio v-else-if="file.kind === 'audio'" :src="file.url" controls preload="metadata" />
			<AdminIcon v-else :name="file.kind === 'document' ? 'file-text' : 'file'" />
		</section>

		<div class="detail__side">
			<form class="panel" aria-labelledby="text-heading" @submit.prevent="save">
				<header class="panel__header">
					<h2 id="text-heading">Details</h2>
				</header>
				<fieldset class="panel__body text" :disabled="!file.may.edit">
					<p v-if="!file.may.edit" class="field__help text__note"><AdminIcon name="info" />{{ file.uploader === null ? 'No one\'s recorded as uploading this file, so only someone who may change anyone\'s files can change its details.' : `Only ${file.uploader.name}, or someone who may change anyone's files, can change its details.` }}</p>
					<p v-if="file.kind === 'image' && fields.some((field) => field.name === 'alt') && altText === ''" class="field__help text__warn"><AdminIcon name="triangle-alert" />No alt text. It's what the image shows, for anyone who can't see it; images inserted from the library start with it, and pages use it where they have none.</p>
					<FieldControl
						v-for="field in ownFields"
						:key="`${file.reference}-${field.name}`"
						:field="field"
						id-prefix="media-"
						:model-value="form[field.name] ?? ''"
						:error="errorFor(field)"
						@update:model-value="form[field.name] = $event"
					/>
					<div v-for="set in setGroups" :key="`${file.reference}-set-${set.name}`" class="text__set">
						<p class="text__set-heading">{{ set.label }}</p>
						<p v-if="set.description" class="field__help">{{ set.description }}</p>
						<FieldControl
							v-for="field in set.fields"
							:key="`${file.reference}-${field.name}`"
							:field="field"
							id-prefix="media-"
							:model-value="form[field.name] ?? ''"
							:error="errorFor(field)"
							@update:model-value="form[field.name] = $event"
						/>
					</div>
					<div v-if="extra.length" class="text__extra">
						<p class="field__help">Also in its metadata file, kept as they are:</p>
						<dl>
							<div v-for="[key, value] in extra" :key="key">
								<dt class="mono">{{ key }}</dt>
								<dd class="mono">{{ typeof value === 'string' ? value : JSON.stringify(value) }}</dd>
							</div>
						</dl>
					</div>
					<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
					<div v-if="file.may.edit" class="text__actions">
						<button type="submit" class="button button--primary button--small" :disabled="!changed || saving">{{ saving ? 'Saving…' : 'Save' }}</button>
						<span class="field__help">Kept in <code>user/data/media</code>, not in the file.</span>
					</div>
				</fieldset>
			</form>

			<section v-if="embedded.length || file.embedded.location" class="panel" aria-labelledby="embedded-heading">
				<header class="panel__header">
					<h2 id="embedded-heading">From the File</h2>
					<span class="panel__hint">What it says about itself</span>
				</header>
				<div class="panel__body embedded">
					<p v-if="file.embedded.location" class="field__help text__warn"><AdminIcon name="triangle-alert" />This file carries where it was taken (GPS). The site never shows it, but anyone who downloads the file can read it.</p>
					<dl v-if="embedded.length" class="facts">
						<div v-for="item in embedded" :key="item.key">
							<dt>{{ item.label }}</dt>
							<dd>
								<span>{{ item.text }}</span>
								<button v-if="item.field" type="button" class="button button--ghost button--small" @click="use(item.field, item.text)">Use<span class="visually-hidden"> as the {{ fieldLabel(item.field).toLowerCase() }}</span></button>
							</dd>
						</div>
					</dl>
				</div>
			</section>

			<section class="panel" aria-labelledby="details-heading">
				<header class="panel__header">
					<h2 id="details-heading">File</h2>
				</header>
				<dl class="panel__body facts">
					<div><dt>Folder</dt><dd class="mono">user/media/{{ file.folder }}</dd></div>
					<div><dt>Type</dt><dd class="mono">{{ file.mime }}</dd></div>
					<div><dt>Size</dt><dd>{{ formatSize(file.size) }}</dd></div>
					<div v-if="file.width !== null && file.height !== null"><dt>Dimensions</dt><dd>{{ file.width }} × {{ file.height }}</dd></div>
					<div v-if="file.duration !== null"><dt>Length</dt><dd>{{ formatDuration(file.duration) }}</dd></div>
					<div><dt>Changed</dt><dd>{{ formatDate(file.modified) }}</dd></div>
					<div><dt>Uploaded by</dt><dd :class="{ 'facts__none': file.uploader === null }">{{ file.uploader?.name ?? 'Not recorded' }}</dd></div>
				</dl>
			</section>

			<section class="panel" aria-labelledby="used-heading">
				<header class="panel__header">
					<h2 id="used-heading">Used In</h2>
					<span class="panel__hint">{{ file.usedIn.length === 0 ? 'No entries' : (file.usedIn.length === 1 ? '1 entry' : `${file.usedIn.length} entries`) }}</span>
				</header>
				<div class="panel__body">
					<p v-if="file.usedIn.length === 0" class="field__help">No entry uses it, by any of its addresses.</p>
					<ul v-else class="used">
						<li v-for="entry in file.usedIn" :key="entry.id">
							<RouterLink :to="{ name: 'entry-file', params: { id: entry.id.split('/') } }">{{ entry.title }}</RouterLink>
							<span v-if="entry.type" class="used__type">{{ entry.type }}</span>
						</li>
					</ul>
				</div>
			</section>

			<section class="panel" aria-labelledby="use-heading">
				<header class="panel__header">
					<h2 id="use-heading">Use It</h2>
				</header>
				<div class="panel__body use">
					<p class="field__help">In the editor, the media button inserts it. In Markdown or front matter, it's:</p>
					<div class="use__row">
						<code>{{ file.reference }}</code>
						<button type="button" class="button button--small" @click="copy(file.reference, 'address')"><AdminIcon name="copy" />Copy</button>
					</div>
					<div class="use__row">
						<code>{{ snippet }}</code>
						<button type="button" class="button button--small" @click="copy(snippet, file.kind === 'image' ? 'Markdown' : 'component')"><AdminIcon name="copy" />Copy</button>
					</div>
				</div>
			</section>
		</div>
	</div>
</template>

<style scoped>
/* The form's controls, read-only as one when they can't be changed. */
fieldset.text {
	min-width: 0;
	margin: 0;
	border: 0;
}

.text__note {
	display: flex;
	gap: var(--s-2);
}

.text__note :deep(.icon) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 2px;
}

.facts__none {
	color: var(--fg-3);
}

.used {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	list-style: none;
}

.used li {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	gap: var(--s-1) var(--s-2);
}

.used__type {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.detail {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
	align-items: start;
	gap: 16px;
}

/* One column at the side's own width, so a long snippet scrolls inside
   its box rather than widening the column. */
.detail__side {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 16px;
	min-width: 0;
}

.preview {
	display: grid;
	place-items: center;
	min-height: 240px;
	padding: 16px;
	background: var(--surface-2);
	color: var(--fg-3);
}

.preview img,
.preview video {
	max-width: 100%;
	max-height: 60vh;
	border-radius: var(--r-1);
}

.preview audio {
	width: 100%;
}

.preview > :deep(svg) {
	width: 48px;
	height: 48px;
	stroke-width: 1.25;
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

.text {
	display: grid;
	gap: var(--s-4);
}

/* A field set's fields, under its label (D-341), as the editor's panel
   groups them. */
.text__set {
	display: grid;
	gap: var(--s-4);
	padding-top: var(--s-4);
	border-top: 1px solid var(--border);
}

.text__set > .field__help {
	margin-top: calc(var(--s-4) * -1 + 4px);
}

.text__set-heading {
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.text__warn {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	color: var(--warn);
}

.text__warn :deep(svg) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.embedded {
	display: grid;
	gap: var(--s-3);
}

.embedded .facts dd {
	display: flex;
	align-items: baseline;
	justify-content: flex-end;
	gap: var(--s-2);
	min-width: 0;
}

.embedded .facts dd .button {
	flex: none;
}

.text__extra dl {
	display: grid;
	gap: 6px;
	margin: 6px 0 0;
}

.text__extra dt {
	color: var(--fg-2);
	font-size: var(--text-xs);
}

.text__extra dd {
	margin: 0;
	font-size: var(--text-xs);
	overflow-wrap: anywhere;
}

.text__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-3);
}

.use__row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.use__row code {
	flex: 1;
	min-width: 0;
	padding: 5px 8px;
	overflow-x: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	font-size: var(--text-sm);
	white-space: nowrap;
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
