<script setup lang="ts">
/**
 * Edits an entry (D-229, D-233): the title and the Markdown body, and in
 * the sidebar its publishing (status, date, preview), its schema fields
 * as a form (`fields.ts`), other front matter, and its problems.
 *
 * A save sends only what changed, so untouched keys stay exactly as the
 * file has them, with the revision it was loaded at; if someone else
 * saved first, the server refuses and nothing is lost. Leaving with
 * unsaved changes asks first. Ctrl+S (⌘S) saves.
 */

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import { ApiError, entryPath, request, type EntryDetail, type EntryStatus, type FieldDescription } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import FieldControl from '../components/FieldControl.vue';
import PreviewLinkControl from '../components/PreviewLinkControl.vue';
import StatusPill from '../components/StatusPill.vue';
import { fromForm, humanize, inSentence, splitDate, toForm, type FormValue } from '../fields';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { currentType, findType, loadTypes } from '../types';

// Fields the editor shows in their own places rather than the form.
const PLACED = ['title', 'status', 'published'];

const route  = useRoute();
const router = useRouter();

const id = computed(() => {
	const segments = route.params.id;

	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
});

const entry    = ref<EntryDetail | null>(null);
const title    = ref('');
const body     = ref('');
const date     = ref('');
const form     = ref<Record<string, FormValue>>({});
const initial  = ref<{ title: string; body: string; date: string; form: Record<string, FormValue> } | null>(null);
const error    = ref('');
const conflict = ref(false);
const saving   = ref(false);
const savedAt  = ref<Date | null>(null);
const notices  = ref(false);
const created  = ref(route.query.created === '1');

const fields = computed(() => entry.value?.type.fields.filter((field) => !PLACED.includes(field.name)) ?? []);
const dateField = computed(() => entry.value?.type.fields.find((field) => field.name === 'published'));
const typeInfo = computed(() => entry.value === null ? undefined : findType(entry.value.type.name));
const noun     = computed(() => inSentence(typeInfo.value?.singular ?? (entry.value === null ? 'entry' : humanize(entry.value.type.name))));

// The navigation marks the entry's type; the top bar names what's edited.
loadTypes().catch(() => undefined);

watch(entry, (value) => {
	currentType.value = value?.type.name ?? null;
});

watch(noun, (value) => {
	screenTitle.value = `Edit ${value}`;
}, { immediate: true });

const changedFields = computed(() => {
	const start = initial.value;

	return start === null ? [] : fields.value.filter((field) => form.value[field.name] !== start.form[field.name]);
});

const dirty = computed(() => {
	const start = initial.value;

	return start !== null && (title.value !== start.title || body.value !== start.body || date.value !== start.date || changedFields.value.length > 0);
});

// Whether the date in the form is still to come.
const future = computed(() => {
	if (date.value === '') {
		return false;
	}

	const offset = splitDate(entry.value?.values.published)?.offset ?? '';
	const when   = Date.parse(`${date.value}:00${offset === '' ? '' : offset.replace(/^([+-]\d{2})(\d{2})$/, '$1:$2')}`);

	return !Number.isNaN(when) && when > Date.now();
});

const shown = computed(() => (entry.value?.violations ?? []).filter((violation) => notices.value || violation.severity !== 'notice'));
const noticeCount = computed(() => (entry.value?.violations ?? []).filter((violation) => violation.severity === 'notice').length);

/**
 * Sets the form from an entry the server sent.
 */
function fill(detail: EntryDetail): void {
	entry.value = detail;
	title.value = typeof detail.values.title === 'string' ? detail.values.title : detail.title;
	body.value  = detail.body;
	date.value  = dateField.value === undefined ? '' : String(toForm(dateField.value, detail.values.published));
	form.value  = Object.fromEntries(fields.value.map((field) => [field.name, toForm(field, detail.values[field.name])]));

	initial.value = { title: title.value, body: body.value, date: date.value, form: { ...form.value } };
	conflict.value = false;
}

async function load(): Promise<void> {
	error.value = '';

	try {
		fill(await request<EntryDetail>('GET', entryPath(id.value)));
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be loaded.`;
	}
}

/**
 * Saves what changed, and moves the entry to a status if one is given.
 */
async function save(status?: EntryStatus): Promise<void> {
	const current = entry.value;
	const start   = initial.value;

	if (current === null || start === null || saving.value) {
		return;
	}

	const set: Record<string, unknown> = {};
	const remove: string[] = [];
	const change: Record<string, unknown> = { revision: current.revision };

	const put = (name: string, value: unknown): void => {
		if (value === null) {
			remove.push(name);
		} else {
			set[name] = value;
		}
	};

	if (title.value !== start.title) {
		put('title', title.value.trim() === '' ? null : title.value.trim());
	}

	if (date.value !== start.date && dateField.value !== undefined) {
		put('published', fromForm(dateField.value, date.value, current.values.published));
	}

	for (const field of changedFields.value) {
		put(field.name, fromForm(field, form.value[field.name] ?? '', current.values[field.name]));
	}

	if (body.value !== start.body) {
		change.body = body.value;
	}

	if (status !== undefined) {
		change.status = status;

		if (status === 'scheduled' && dateField.value !== undefined) {
			change.published = fromForm(dateField.value, date.value, current.values.published);
		}
	}

	change.set    = set;
	change.remove = remove;

	saving.value  = true;
	error.value   = '';
	created.value = false;

	try {
		fill(await request<EntryDetail>('PATCH', entryPath(current.id), change));
		savedAt.value = new Date();
	} catch (caught) {
		conflict.value = caught instanceof ApiError && caught.status === 409;
		error.value    = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be saved.`;
	} finally {
		saving.value = false;
	}
}

async function reload(): Promise<void> {
	if (window.confirm('Load the saved version? Your unsaved changes here will be lost.')) {
		await load();
	}
}

async function trash(): Promise<void> {
	const current = entry.value;

	if (current === null || !window.confirm(`Move “${current.title || current.id}” to the trash? You can restore it from the Trash tab.`)) {
		return;
	}

	try {
		await request<void>('DELETE', `${entryPath(current.id)}?revision=${encodeURIComponent(current.revision)}`);
		initial.value = null;
		await router.push({ name: 'type', params: { type: current.type.name } });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be moved to the trash.`;
	}
}

// What the primary and secondary buttons do, from the status and date.
const primary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const current = entry.value;

	if (current === null) {
		return null;
	}

	if (!current.can.publish) {
		return current.status === 'draft' ? { label: 'Save draft' } : { label: 'Update' };
	}

	if (future.value) {
		return current.status === 'scheduled' && date.value === initial.value?.date ? { label: 'Update' } : { label: 'Schedule', status: 'scheduled' };
	}

	return current.status === 'published' ? { label: 'Update' } : { label: 'Publish', status: 'published' };
});

const secondary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const current = entry.value;

	if (current === null || (!current.can.publish && current.status === 'draft')) {
		return null;
	}

	return current.status === 'draft' ? { label: 'Save draft' } : { label: 'Switch to draft', status: 'draft' };
});

const saveState = computed(() => {
	if (saving.value) {
		return 'Saving…';
	}

	if (dirty.value) {
		return 'Unsaved changes';
	}

	return savedAt.value === null ? '' : `Saved ${savedAt.value.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`;
});

function keydown(event: KeyboardEvent): void {
	if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
		event.preventDefault();
		void save();
	}
}

function beforeUnload(event: BeforeUnloadEvent): void {
	if (dirty.value) {
		event.preventDefault();
	}
}

onBeforeRouteLeave(() => !dirty.value || window.confirm('Leave without saving? Your changes will be lost.'));

onMounted(() => {
	document.addEventListener('keydown', keydown);
	window.addEventListener('beforeunload', beforeUnload);

	// The "just created" notice shows once.
	if (created.value) {
		void router.replace({ query: {} });
	}
});

onBeforeUnmount(() => {
	document.removeEventListener('keydown', keydown);
	window.removeEventListener('beforeunload', beforeUnload);
});

watch(id, load, { immediate: true });

function fieldKey(field: FieldDescription): string {
	return `${entry.value?.revision ?? ''}-${field.name}`;
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<p class="editor__crumb"><RouterLink :to="entry ? { name: 'type', params: { type: entry.type.name } } : { name: 'entries' }">{{ typeInfo?.label ?? 'Entries' }}</RouterLink></p>
			<h1 tabindex="-1">Edit {{ noun }}</h1>
			<p v-if="entry" class="page-header__hint mono">{{ entry.id }}</p>
		</div>
		<div v-if="entry" class="page-header__actions editor__actions">
			<span class="editor__state" aria-live="polite">{{ saveState }}</span>
			<StatusPill :status="entry.status" />
			<button v-if="secondary" type="button" class="button" :disabled="saving" @click="save(secondary.status)">{{ secondary.label }}</button>
			<button v-if="primary" type="button" class="button button--primary" :disabled="saving" @click="save(primary.status)">{{ primary.label }}</button>
		</div>
	</header>

	<div v-if="error" class="notice notice--error" role="alert">
		<p>{{ error }}</p>
		<p v-if="conflict"><button type="button" class="button button--small" @click="reload">Load the saved version</button></p>
	</div>
	<p v-if="created" class="notice notice--success" role="status">Created. It's a draft until you publish it.</p>

	<div v-if="entry" class="editor">
		<div class="editor__main">
			<label class="visually-hidden" for="editor-title">Title</label>
			<input id="editor-title" v-model="title" class="editor__title" placeholder="Title" autocomplete="off">

			<label class="visually-hidden" for="editor-body">Body (Markdown)</label>
			<textarea id="editor-body" v-model="body" class="editor__body" spellcheck="true" placeholder="Write in Markdown…" />
		</div>

		<aside class="editor__side" aria-label="Entry settings">
			<section class="panel" aria-labelledby="publish-heading">
				<header class="panel__header">
					<h2 id="publish-heading">Publishing</h2>
				</header>
				<div class="panel__body">
					<div v-if="dateField" class="field">
						<label for="editor-date">Publish date</label>
						<input id="editor-date" v-model="date" type="datetime-local" aria-describedby="editor-date-help">
						<p id="editor-date-help" class="field__help">A date in the future schedules it.</p>
					</div>
					<p v-if="entry.url" class="editor__link">
						<a class="button button--small" :href="entry.url" target="_blank" rel="noopener">
							<AdminIcon name="external-link" />View<span class="visually-hidden"> the live {{ noun }} (new tab)</span>
						</a>
					</p>
					<div v-else class="editor__preview">
						<span class="field__help">Preview</span>
						<PreviewLinkControl :entry="{ id: entry.id, title: entry.title, path: entry.id }" />
					</div>
					<p v-if="entry.can.delete" class="editor__trash">
						<button type="button" class="button button--danger button--small" @click="trash">Move to trash</button>
					</p>
				</div>
			</section>

			<section v-if="fields.length" class="panel" aria-labelledby="fields-heading">
				<header class="panel__header">
					<h2 id="fields-heading">Fields</h2>
				</header>
				<div class="panel__body">
					<FieldControl v-for="field in fields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" @update:model-value="form[field.name] = $event" />
				</div>
			</section>

			<section v-if="Object.keys(entry.extra).length" class="panel" aria-labelledby="extra-heading">
				<header class="panel__header">
					<h2 id="extra-heading">Other front matter</h2>
					<p class="panel__hint">Kept as it is</p>
				</header>
				<dl class="panel__body editor__extra">
					<div v-for="(value, key) in entry.extra" :key="key">
						<dt class="mono">{{ key }}</dt>
						<dd class="mono">{{ typeof value === 'string' ? value : JSON.stringify(value) }}</dd>
					</div>
				</dl>
			</section>

			<section v-if="entry.violations.length" class="panel" aria-labelledby="problems-heading">
				<header class="panel__header">
					<h2 id="problems-heading">Problems</h2>
					<p class="panel__hint">As last saved</p>
				</header>
				<div class="panel__body">
					<ul v-if="shown.length" class="editor__problems">
						<li v-for="(violation, index) in shown" :key="index">
							<span class="pill" :class="{ 'pill--danger': violation.severity === 'error', 'pill--warn': violation.severity === 'warning' }">{{ humanize(violation.severity) }}</span>
							<span><code>{{ violation.field }}</code>: {{ violation.message }}</span>
						</li>
					</ul>
					<label v-if="noticeCount" class="checkbox">
						<input v-model="notices" type="checkbox">
						Show {{ plural(noticeCount, 'notice') }}
					</label>
				</div>
			</section>
		</aside>
	</div>

	<p v-else-if="!error" class="loading" aria-live="polite">Loading…</p>
</template>

<style scoped>
.editor__crumb {
	font-size: var(--text-sm);
}

.editor__crumb a {
	color: var(--fg-2);
}

.editor__actions {
	align-items: center;
}

.editor__state {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.editor {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 20rem;
	align-items: start;
	gap: 20px;
}

.editor__main {
	display: grid;
	gap: 12px;
}

.editor__title {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg);
	font-family: var(--font-display);
	font-size: var(--h1);
	font-weight: 600;
	letter-spacing: var(--h1-track);
}

.editor__body {
	width: 100%;
	min-height: 60vh;
	padding: 14px 16px;
	border: 1px solid var(--border);
	border-radius: var(--r-3);
	background: var(--surface);
	box-shadow: var(--shadow-1);
	color: var(--fg);
	font-family: var(--font-mono);
	font-size: var(--base);
	line-height: 1.65;
	resize: vertical;
	tab-size: 4;
}

.editor__title:hover,
.editor__body:hover {
	border-color: var(--border-strong);
}

.editor__title:focus-visible,
.editor__body:focus-visible {
	border-color: var(--accent);
	outline-offset: 0;
}

.editor__side {
	display: grid;
	gap: 16px;
}

.editor__preview {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.editor__trash {
	padding-top: 12px;
	border-top: 1px solid var(--border);
}

.editor__extra {
	margin: 0;
}

.editor__extra div {
	display: grid;
	gap: 2px;
}

.editor__extra dt {
	color: var(--fg-2);
	font-size: var(--text-xs);
}

.editor__extra dd {
	margin: 0;
	font-size: var(--text-xs);
	overflow-wrap: anywhere;
}

.editor__problems {
	display: grid;
	gap: 8px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.editor__problems li {
	display: grid;
	justify-items: start;
	gap: 4px;
}

.editor__problems code {
	overflow-wrap: anywhere;
}

@media (width <= 1100px) {
	.editor {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
