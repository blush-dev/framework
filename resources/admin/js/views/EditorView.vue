<script setup lang="ts">
/**
 * Edits an entry (D-229, D-233): the title and the Markdown body, and in
 * the sidebar its publishing (status, date, preview), its schema fields
 * as a form (`fields.ts`), other front matter, and its problems.
 *
 * A save sends only what changed, so untouched keys stay exactly as the
 * file has them, with the revision it was loaded at. Ctrl+S (⌘S) saves.
 *
 * Nothing typed is lost (D-240):
 *
 * - Unsaved changes are kept in this browser (`kept.ts`) as they're
 *   made; opening the entry again offers them back.
 * - Offline, a save waits for the connection and then goes ahead; the
 *   save state says **Waiting for a connection**.
 * - A save that fails says so where the save state is, and offers to
 *   try again.
 * - If the file changed since it was loaded, saving stops, says when,
 *   and offers **Keep theirs**, **Compare**, and **Keep mine**. Nothing
 *   is resolved without asking.
 *
 * Publishing (and updating a live entry) needs the type's required
 * fields; a draft saves without them.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import { ApiError, entryPath, request, type EntryDetail, type EntryStatus, type FieldDescription } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import FieldControl from '../components/FieldControl.vue';
import PreviewLinkControl from '../components/PreviewLinkControl.vue';
import StatusPill from '../components/StatusPill.vue';
import { online } from '../connection';
import { diffLines, type DiffLine } from '../diff';
import { fromForm, humanize, inSentence, label, splitDate, toForm, type FormValue } from '../fields';
import { formatDate, plural } from '../format';
import { forget, keep, kept, type EditorState, type KeptChanges } from '../kept';
import { screenTitle } from '../screen';
import { currentType, findType, loadTypes } from '../types';

// Fields the editor shows in their own places rather than the form.
const PLACED = ['title', 'status', 'published'];

// Why a required field stops publishing.
const REQUIRED = 'Required to publish.';

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
const initial  = ref<EditorState | null>(null);
const error    = ref('');
const saving   = ref(false);
const savedAt  = ref<Date | null>(null);
const notices  = ref(false);
const created  = ref(route.query.created === '1');

// A save waiting for the connection, one that failed, and a conflict:
// each remembers the status the save was moving the entry to.
const waiting   = ref<{ status?: EntryStatus } | null>(null);
const failure   = ref<{ message: string; status?: EntryStatus } | null>(null);
const conflict  = ref<{ status?: EntryStatus; theirs: EntryDetail | null; loading: boolean } | null>(null);
const comparing = ref(false);

// Changes kept in this browser from an earlier visit, until restored or
// thrown away; and whether the latest changes are kept.
const offer    = ref<KeptChanges | null>(null);
const keptHere = ref(false);

// Whether publishing was tried with required fields empty; their errors
// show from then on, clearing as each is filled.
const attempted = ref(false);

function fieldsOf(detail: EntryDetail | null): FieldDescription[] {
	return detail?.type.fields.filter((field) => !PLACED.includes(field.name)) ?? [];
}

const fields    = computed(() => fieldsOf(entry.value));
const dateField = computed(() => entry.value?.type.fields.find((field) => field.name === 'published'));
const typeInfo  = computed(() => entry.value === null ? undefined : findType(entry.value.type.name));
const noun      = computed(() => inSentence(typeInfo.value?.singular ?? (entry.value === null ? 'entry' : humanize(entry.value.type.name))));

// The navigation marks the entry's type; the top bar names what's edited.
loadTypes().catch(() => undefined);

watch(entry, (value) => {
	currentType.value = value?.type.name ?? null;
});

watch(noun, (value) => {
	screenTitle.value = `Edit ${value}`;
}, { immediate: true });

/**
 * The form state an entry the server sent starts from.
 */
function stateOf(detail: EntryDetail): EditorState {
	const published = detail.type.fields.find((field) => field.name === 'published');

	return {
		title: typeof detail.values.title === 'string' ? detail.values.title : detail.title,
		body: detail.body,
		date: published === undefined ? '' : String(toForm(published, detail.values.published)),
		form: Object.fromEntries(fieldsOf(detail).map((field) => [field.name, toForm(field, detail.values[field.name])]))
	};
}

function current(): EditorState {
	return { title: title.value, body: body.value, date: date.value, form: { ...form.value } };
}

function apply(state: EditorState): void {
	title.value = state.title;
	body.value  = state.body;
	date.value  = state.date;
	form.value  = Object.fromEntries(fields.value.map((field) => [field.name, state.form[field.name] ?? toForm(field, undefined)]));
}

function same(a: EditorState, b: EditorState): boolean {
	return a.title === b.title && a.body === b.body && a.date === b.date
		&& fields.value.every((field) => a.form[field.name] === b.form[field.name]);
}

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
	apply(stateOf(detail));

	initial.value   = current();
	conflict.value  = null;
	comparing.value = false;
	failure.value   = null;
	waiting.value   = null;
}

async function load(): Promise<void> {
	error.value = '';
	offer.value = null;

	try {
		const detail = await request<EntryDetail>('GET', entryPath(id.value));

		fill(detail);

		// Changes kept from before are offered back, unless they're what
		// the file already says.
		const earlier = kept(detail.id);

		if (earlier !== null && same(earlier.state, current())) {
			forget(detail.id);
		} else {
			offer.value = earlier;
		}
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be loaded.`;
	}
}

/**
 * Puts kept changes back. Kept from an older version of the file, they
 * meet the file's changes as a conflict, so nothing is overwritten
 * without asking.
 */
function restore(): void {
	const earlier = entry.value === null ? null : offer.value;

	if (earlier === null || entry.value === null) {
		return;
	}

	offer.value = null;
	apply(earlier.state);

	if (earlier.revision !== entry.value.revision) {
		conflict.value = { theirs: entry.value, loading: false };
	}
}

function discard(): void {
	offer.value = null;
	forget(id.value);
}

// Changes are kept in the browser a moment after typing stops; a clean
// form keeps nothing. While an offer is open, what's kept stays as it is.
let keeping: ReturnType<typeof setTimeout> | undefined;

watch([title, body, date, form], () => {
	clearTimeout(keeping);
	keeping = setTimeout(() => {
		const detail = entry.value;

		if (detail === null || offer.value !== null) {
			return;
		}

		if (dirty.value) {
			keptHere.value = keep(detail.id, detail.revision, current());
		} else {
			forget(detail.id);
			keptHere.value = false;
		}
	}, 400);
}, { deep: true });

// Required fields left empty, with where each is shown.
const missing = computed(() => {
	const detail = entry.value;

	if (detail === null) {
		return [];
	}

	const empty = (value: FormValue | undefined): boolean => value === undefined || (typeof value === 'string' && value.trim() === '');
	const found: { name: string; label: string; control: string }[] = [];

	for (const field of detail.type.fields) {
		if (field.required !== true) {
			continue;
		}

		if (field.name === 'title' && empty(title.value)) {
			found.push({ name: field.name, label: 'Title', control: 'editor-title' });
		} else if (field.name === 'published' && empty(date.value)) {
			found.push({ name: field.name, label: 'Publish date', control: 'editor-date' });
		} else if (!PLACED.includes(field.name) && typeof form.value[field.name] !== 'boolean' && empty(form.value[field.name])) {
			found.push({ name: field.name, label: label(field), control: `field-${field.name}` });
		}
	}

	return found;
});

function errorFor(name: string): string | undefined {
	return attempted.value && missing.value.some((item) => item.name === name) ? REQUIRED : undefined;
}

/**
 * Whether a save with this status makes (or keeps) the entry live.
 */
function publishes(status: EntryStatus | undefined): boolean {
	return status === 'published' || status === 'scheduled' || (status === undefined && entry.value !== null && entry.value.status !== 'draft');
}

/**
 * Saves what changed, and moves the entry to a status if one is given.
 */
async function save(status?: EntryStatus): Promise<void> {
	const detail = entry.value;
	const start  = initial.value;

	if (detail === null || start === null || saving.value || conflict.value !== null || offer.value !== null) {
		return;
	}

	if (publishes(status) && missing.value.length > 0) {
		attempted.value = true;
		await nextTick();
		document.getElementById(missing.value[0]?.control ?? '')?.focus();

		return;
	}

	failure.value = null;

	if (!online.value) {
		waiting.value = { status };

		return;
	}

	const set: Record<string, unknown> = {};
	const remove: string[] = [];
	const change: Record<string, unknown> = { revision: detail.revision };

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
		put('published', fromForm(dateField.value, date.value, detail.values.published));
	}

	for (const field of changedFields.value) {
		put(field.name, fromForm(field, form.value[field.name] ?? '', detail.values[field.name]));
	}

	if (body.value !== start.body) {
		change.body = body.value;
	}

	if (status !== undefined) {
		change.status = status;

		if (status === 'scheduled' && dateField.value !== undefined) {
			change.published = fromForm(dateField.value, date.value, detail.values.published);
		}
	}

	change.set    = set;
	change.remove = remove;

	saving.value  = true;
	waiting.value = null;
	created.value = false;

	try {
		const saved = await request<EntryDetail>('PATCH', entryPath(detail.id), change);

		fill(saved);
		forget(saved.id);
		keptHere.value  = false;
		attempted.value = false;
		savedAt.value   = new Date();
	} catch (caught) {
		if (caught instanceof ApiError && caught.status === 409) {
			void openConflict(status);
		} else if (caught instanceof ApiError && caught.status === 0 && !online.value) {
			waiting.value = { status };
		} else {
			failure.value = { message: caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be saved.`, status };
		}
	} finally {
		saving.value = false;
	}
}

// A save that was waiting goes ahead once the connection is back.
watch(online, (value) => {
	const pending = waiting.value;

	if (value && pending !== null) {
		waiting.value = null;
		void save(pending.status);
	}
});

function retry(): void {
	const failed = failure.value;

	failure.value = null;
	void save(failed?.status);
}

/**
 * Stops saving and fetches the file as it is now, to show when it
 * changed and to compare.
 */
async function openConflict(status?: EntryStatus): Promise<void> {
	conflict.value = { status, theirs: null, loading: true };
	await fetchTheirs();
}

async function fetchTheirs(): Promise<void> {
	const open = conflict.value;

	if (open === null || entry.value === null) {
		return;
	}

	open.loading = true;

	try {
		open.theirs = await request<EntryDetail>('GET', entryPath(entry.value.id));
	} catch {
		open.theirs = null;
	} finally {
		open.loading = false;
	}
}

/**
 * Throws away the changes here for the file as it is now.
 */
function keepTheirs(): void {
	const theirs = conflict.value?.theirs;

	if (theirs === null || theirs === undefined) {
		return;
	}

	fill(theirs);
	forget(theirs.id);
	keptHere.value = false;
}

/**
 * Saves the changes here over the file as it is now: every field the
 * editor shows ends up as it is here.
 */
function keepMine(): void {
	const open   = conflict.value;
	const theirs = open?.theirs;

	if (open === null || theirs === null || theirs === undefined) {
		return;
	}

	const mine = current();

	entry.value     = theirs;
	initial.value   = stateOf(theirs);
	conflict.value  = null;
	comparing.value = false;
	apply(mine);

	void save(open.status);
}

// What differs between the file as it is now and the changes here.
const differences = computed(() => {
	const theirs = conflict.value?.theirs;

	if (theirs === null || theirs === undefined) {
		return null;
	}

	const their   = stateOf(theirs);
	const mine    = current();
	const display = (value: FormValue | undefined): string => typeof value === 'boolean' ? (value ? 'Yes' : 'No') : (value === undefined || value === '' ? '—' : value);
	const rows: { label: string; theirs: string; mine: string }[] = [];

	if (their.title !== mine.title) {
		rows.push({ label: 'Title', theirs: display(their.title), mine: display(mine.title) });
	}

	if (their.date !== mine.date) {
		rows.push({ label: 'Publish date', theirs: display(their.date), mine: display(mine.date) });
	}

	for (const field of fields.value) {
		if (their.form[field.name] !== mine.form[field.name]) {
			rows.push({ label: label(field), theirs: display(their.form[field.name]), mine: display(mine.form[field.name]) });
		}
	}

	return { rows, body: their.body === mine.body ? null : hunks(diffLines(their.body, mine.body)) };
});

/**
 * Leaves out long runs of unchanged lines, keeping two on each side of a
 * change.
 */
function hunks(lines: DiffLine[]): (DiffLine | { kind: 'skip'; count: number })[] {
	const near = lines.map((_, index) => lines.slice(Math.max(0, index - 2), index + 3).some((line) => line.kind !== 'same'));
	const out: (DiffLine | { kind: 'skip'; count: number })[] = [];

	lines.forEach((line, index) => {
		if (near[index] === true) {
			out.push(line);

			return;
		}

		const last = out.at(-1);

		if (last !== undefined && last.kind === 'skip') {
			last.count++;
		} else {
			out.push({ kind: 'skip', count: 1 });
		}
	});

	return out;
}

async function trash(): Promise<void> {
	const detail = entry.value;

	if (detail === null || !window.confirm(`Move “${detail.title || detail.id}” to the trash? You can restore it from the Trash tab.`)) {
		return;
	}

	try {
		await request<void>('DELETE', `${entryPath(detail.id)}?revision=${encodeURIComponent(detail.revision)}`);
		forget(detail.id);
		initial.value = null;
		await router.push({ name: 'type', params: { type: detail.type.name } });
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be moved to the trash.`;
	}
}

// What the primary and secondary buttons do, from the status and date.
const primary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const detail = entry.value;

	if (detail === null) {
		return null;
	}

	if (!detail.can.publish) {
		return detail.status === 'draft' ? { label: 'Save draft' } : { label: 'Update' };
	}

	if (future.value) {
		return detail.status === 'scheduled' && date.value === initial.value?.date ? { label: 'Update' } : { label: 'Schedule', status: 'scheduled' };
	}

	return detail.status === 'published' ? { label: 'Update' } : { label: 'Publish', status: 'published' };
});

const secondary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const detail = entry.value;

	if (detail === null || (!detail.can.publish && detail.status === 'draft')) {
		return null;
	}

	return detail.status === 'draft' ? { label: 'Save draft' } : { label: 'Switch to draft', status: 'draft' };
});

// Saving stops while a conflict or an offer of kept changes is open.
const blocked = computed(() => saving.value || conflict.value !== null || offer.value !== null);

const saveState = computed<{ text: string; tone?: 'warn' | 'danger' }>(() => {
	if (conflict.value !== null) {
		return { text: 'Not saved: changed elsewhere', tone: 'danger' };
	}

	if (saving.value) {
		return { text: 'Saving…' };
	}

	if (waiting.value !== null) {
		return { text: 'Waiting for a connection', tone: 'warn' };
	}

	if (failure.value !== null) {
		return { text: 'Not saved', tone: 'danger' };
	}

	if (dirty.value) {
		return { text: 'Unsaved changes' };
	}

	return { text: savedAt.value === null ? '' : `Saved ${savedAt.value.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}` };
});

// Where the changes are while they aren't saved.
const safe = computed(() => keptHere.value ? 'Your changes are kept in this browser' : 'Your changes are still here');

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

onBeforeRouteLeave(() => {
	if (!dirty.value) {
		return true;
	}

	if (!window.confirm('Leave without saving? Your changes will be lost.')) {
		return false;
	}

	clearTimeout(keeping);
	forget(id.value);

	return true;
});

onMounted(() => {
	document.addEventListener('keydown', keydown);
	window.addEventListener('beforeunload', beforeUnload);

	// The "just created" notice shows once.
	if (created.value) {
		void router.replace({ query: {} });
	}
});

onBeforeUnmount(() => {
	clearTimeout(keeping);
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
			<p class="editor__crumb">
				<RouterLink v-if="entry" :to="{ name: 'type', params: { type: entry.type.name } }">{{ typeInfo?.label ?? humanize(entry.type.name) }}</RouterLink>
				<span v-else class="skeleton" />
			</p>
			<h1 tabindex="-1">Edit {{ noun }}</h1>
			<p v-if="entry" class="page-header__hint mono">{{ entry.id }}</p>
		</div>
		<div v-if="entry" class="page-header__actions editor__actions">
			<span class="editor__state" :class="saveState.tone ? `editor__state--${saveState.tone}` : undefined" aria-live="polite">{{ saveState.text }}</span>
			<StatusPill :status="entry.status" />
			<button v-if="secondary" type="button" class="button" :disabled="blocked" @click="save(secondary.status)">{{ secondary.label }}</button>
			<button v-if="primary" type="button" class="button button--primary" :disabled="blocked" @click="save(primary.status)">{{ primary.label }}</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="created" class="notice notice--success" role="status">Created. It's a draft until you publish it.</p>

	<div v-if="offer" class="notice notice--warn notice--actions" role="status">
		<p>This browser kept changes to this {{ noun }} that weren't saved, from {{ formatDate(offer.kept) }}.</p>
		<p class="notice__buttons">
			<button type="button" class="button button--small button--primary" @click="restore">Restore them</button>
			<button type="button" class="button button--small" @click="discard">Throw them away</button>
		</p>
	</div>

	<div v-if="failure" class="notice notice--error notice--actions" role="alert">
		<p>{{ safe }}, but they weren't saved. {{ failure.message }}</p>
		<p class="notice__buttons">
			<button type="button" class="button button--small" @click="retry">Try again</button>
		</p>
	</div>

	<div v-if="attempted && missing.length" class="notice notice--error" role="alert">
		<p>{{ plural(missing.length, 'required field is', 'required fields are') }} empty, so it wasn't published: {{ missing.map((item) => item.label).join(', ') }}. Fill {{ missing.length === 1 ? 'it' : 'them' }} in, or save it as a draft.</p>
	</div>

	<section v-if="conflict" class="panel conflict" aria-labelledby="conflict-heading">
		<header class="panel__header">
			<h2 id="conflict-heading">This {{ noun }} changed while you were editing</h2>
		</header>
		<div class="panel__body">
			<p>
				<template v-if="conflict.theirs?.modified">Its file was saved at {{ formatDate(conflict.theirs.modified) }}, </template>
				<template v-else>Its file was saved again, </template>
				from the admin or by editing the file itself. {{ safe }}; nothing has been saved over.
			</p>
			<p v-if="!conflict.loading && conflict.theirs === null" class="field__error">The saved version couldn't be loaded. Check your connection, then try again.</p>
			<div class="conflict__buttons">
				<template v-if="conflict.theirs">
					<button type="button" class="button" @click="keepTheirs">Keep theirs</button>
					<button type="button" class="button" :aria-expanded="comparing" aria-controls="conflict-compare" @click="comparing = !comparing">Compare</button>
					<button type="button" class="button button--primary" @click="keepMine">Keep mine</button>
				</template>
				<button v-else type="button" class="button" :disabled="conflict.loading" @click="fetchTheirs">{{ conflict.loading ? 'Loading the saved version…' : 'Try again' }}</button>
			</div>
			<p class="field__help">Keep theirs throws away your changes here. Keep mine saves your version of every field shown here over theirs.</p>
		</div>

		<div v-if="comparing && differences" id="conflict-compare" class="compare">
			<table v-if="differences.rows.length" class="table compare__fields">
				<thead>
					<tr>
						<th scope="col">Field</th>
						<th scope="col">Theirs</th>
						<th scope="col">Mine</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in differences.rows" :key="row.label">
						<th scope="row">{{ row.label }}</th>
						<td>{{ row.theirs }}</td>
						<td>{{ row.mine }}</td>
					</tr>
				</tbody>
			</table>
			<div v-if="differences.body" class="compare__body">
				<p class="compare__label">Body <span class="compare__key"><span class="compare__mark compare__mark--theirs">−</span> theirs <span class="compare__mark compare__mark--mine">+</span> mine</span></p>
				<pre class="compare__lines"><template v-for="(line, index) in differences.body" :key="index"><span v-if="line.kind === 'skip'" class="compare__skip">{{ plural(line.count, 'unchanged line') }}</span><span v-else class="compare__line" :class="`compare__line--${line.kind}`"><span class="compare__sign" aria-hidden="true">{{ line.kind === 'theirs' ? '−' : (line.kind === 'mine' ? '+' : ' ') }}</span><span v-if="line.kind !== 'same'" class="visually-hidden">{{ line.kind === 'theirs' ? 'Theirs: ' : 'Mine: ' }}</span>{{ line.text }}</span></template></pre>
			</div>
			<p v-if="!differences.rows.length && !differences.body" class="panel__body field__help">Your changes and theirs match in everything the editor shows.</p>
		</div>
	</section>

	<div v-if="entry" class="editor">
		<div class="editor__main">
			<div class="editor__title-field">
				<label class="visually-hidden" for="editor-title">Title</label>
				<input id="editor-title" v-model="title" class="editor__title" placeholder="Title" autocomplete="off" :aria-invalid="errorFor('title') ? 'true' : undefined" :aria-describedby="errorFor('title') ? 'editor-title-error' : undefined">
				<p v-if="errorFor('title')" id="editor-title-error" class="field__error">{{ errorFor('title') }}</p>
			</div>

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
						<input id="editor-date" v-model="date" type="datetime-local" :aria-invalid="errorFor('published') ? 'true' : undefined" :aria-describedby="errorFor('published') ? 'editor-date-help editor-date-error' : 'editor-date-help'">
						<p id="editor-date-help" class="field__help">A date in the future schedules it.</p>
						<p v-if="errorFor('published')" id="editor-date-error" class="field__error">{{ errorFor('published') }}</p>
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
					<FieldControl v-for="field in fields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" @update:model-value="form[field.name] = $event" />
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

	<div v-else-if="!error" class="editor" aria-hidden="true">
		<div class="editor__main">
			<span class="skeleton editor__title-skeleton" />
			<div class="editor__body editor__body-skeleton">
				<span v-for="line in 8" :key="line" class="skeleton" :style="{ width: `${55 + (line * 23) % 40}%` }" />
			</div>
		</div>
		<div class="editor__side">
			<div v-for="panel in 2" :key="panel" class="panel">
				<div class="panel__body">
					<span class="skeleton" style="width: 40%" />
					<span class="skeleton skeleton--field" />
					<span class="skeleton skeleton--small" style="width: 70%" />
				</div>
			</div>
		</div>
		<p class="visually-hidden" role="status">Loading the {{ noun }}…</p>
	</div>
</template>

<style scoped>
.editor__crumb {
	font-size: var(--text-sm);
}

.editor__crumb a {
	color: var(--fg-2);
}

.editor__crumb .skeleton {
	width: 4rem;
	margin-block: 4px;
}

.editor__actions {
	align-items: center;
}

.editor__state {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.editor__state--warn {
	color: var(--warn);
	font-weight: 500;
}

.editor__state--danger {
	color: var(--danger);
	font-weight: 500;
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

.editor__title-field {
	display: grid;
	gap: 6px;
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

.editor__title[aria-invalid="true"] {
	border-color: var(--danger-dot);
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

.editor__title-skeleton {
	height: 46px;
	border-radius: var(--r-2);
}

.editor__body-skeleton {
	display: grid;
	align-content: start;
	gap: 14px;
}

.editor__body-skeleton:hover {
	border-color: var(--border);
}

.skeleton--field {
	height: 32px;
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

/* The conflict: what happened, three ways out, and the comparison. */

.conflict {
	border-color: var(--danger-dot);
}

.conflict__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.compare {
	border-top: 1px solid var(--border);
}

.compare__fields td {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__body {
	display: grid;
	gap: 8px;
	padding: 12px var(--pad-x) 16px;
	border-top: 1px solid var(--border);
}

.compare__label {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	justify-content: space-between;
	gap: 8px;
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 500;
	letter-spacing: .06em;
	text-transform: uppercase;
}

.compare__key {
	letter-spacing: normal;
	text-transform: none;
}

.compare__mark {
	font-family: var(--font-mono);
	font-weight: 600;
}

.compare__mark--theirs {
	color: var(--danger);
}

.compare__mark--mine {
	color: var(--good);
}

.compare__lines {
	display: grid;
	max-height: 28rem;
	margin: 0;
	overflow: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface-2);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
	line-height: 1.6;
}

.compare__line {
	display: block;
	padding: 0 10px;
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__line--theirs {
	background: var(--danger-soft);
	color: var(--danger);
}

.compare__line--mine {
	background: var(--good-soft);
	color: var(--good);
}

.compare__sign {
	display: inline-block;
	width: 1.5ch;
	user-select: none;
}

.compare__skip {
	display: block;
	padding: 2px 10px;
	border-block: 1px dashed var(--border);
	color: var(--fg-3);
	font-family: var(--font-ui);
	font-size: var(--text-xs);
}

@media (width <= 1100px) {
	.editor {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
