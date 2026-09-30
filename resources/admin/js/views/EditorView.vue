<script setup lang="ts">
/**
 * Edits an entry (D-229, D-233), as a writing surface (admin.md §8,
 * D-245): one centered column with the title as part of the document and
 * the Markdown body under it (`MarkdownEditor`, D-241); a header with
 * where you are, the save state, the status, and the actions; and a
 * footer that's the status line. Settings are a drawer that pushes the
 * column aside (⌘/), closed at first, with two tabs, left, and a close
 * button, right: **Document** (its publishing, schema fields as a form,
 * other front matter, and its problems) and **Component** (D-265): the
 * options of the component the caret is in (`ComponentOptions`), and
 * under them every component in the entry, each a way to select it. With
 * nothing selected it says so over that list, so the tab is never a dead
 * end. With the drawer closed, the footer names the component the caret
 * is in instead of opening anything.
 *
 * The header's left half has the four ways to put something in (D-247,
 * D-265): block components, in a panel that slides in from the left and
 * stays open (also opened by typing `/`); media and icons, each a
 * library in a modal; and inline components, a short menu. Its right half
 * says what the entry is and what happens to it.
 *
 * While keys move, the header and footer fade back; any pointer movement
 * brings them back. Focus mode (⌘⇧F) leaves only the column; Escape
 * returns.
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
import { ApiError, entryPath, entryRoute, request, type EntryDetail, type EntryStatus, type FieldDescription, type MediaItem } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import ComponentOptions from '../components/ComponentOptions.vue';
import ComponentPanel from '../components/ComponentPanel.vue';
import FieldControl from '../components/FieldControl.vue';
import IconPicker from '../components/IconPicker.vue';
import MarkdownEditor from '../components/MarkdownEditor.vue';
import MediaPicker from '../components/MediaPicker.vue';
import MenuButton from '../components/MenuButton.vue';
import PreviewLinkControl from '../components/PreviewLinkControl.vue';
import StatusPill from '../components/StatusPill.vue';
import { componentIcon, loadComponents, type ComponentDescription, type ComponentProp } from '../components';
import { online } from '../connection';
import { diffLines, type DiffLine } from '../diff';
import { fromForm, humanize, inSentence, label, splitDate, toForm, type FormValue } from '../fields';
import { formatDate, plural } from '../format';
import { forget, keep, kept, type EditorState, type KeptChanges } from '../kept';
import { attributeText, attributesOf, imageText, directiveAt, directiveHead, outline, wordCount, withAttribute, withoutDirective, type Directive, type Edit } from '../markdown';
import type { SiteIcon } from '../site-icons';
import { focusMode, screenTitle } from '../screen';
import { toast } from '../toast';
import { useCommands, type Command } from '../commands';
import { currentType, findType, loadTypes } from '../types';

// Fields the editor shows in their own places rather than the form.
const PLACED = ['title', 'status', 'published'];

// Why a required field stops publishing.
const REQUIRED = 'Required to publish.';

const route  = useRoute();
const router = useRouter();

function joined(segments: string | string[] | undefined): string {
	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
}

// What the route names: an entry's handle (`post/hello`), or its path for
// one without a handle (D-253).
const address = computed(() => route.name === 'entry'
	? { handle: true, name: `${joined(route.params.type)}/${joined(route.params.key)}` }
	: { handle: false, name: joined(route.params.id) });

function isAt(detail: EntryDetail): boolean {
	const at = address.value;

	return at.name === (at.handle ? detail.handle : detail.id);
}


/**
 * Keeps the address on the entry's handle, as it moves (a rename) or
 * when it was opened by its path.
 */
function follow(detail: EntryDetail): void {
	if (detail.handle !== null && !(address.value.handle && address.value.name === detail.handle)) {
		void router.replace({ ...entryRoute(detail), query: route.query, hash: route.hash });
	}
}

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

	follow(detail);
}

async function load(): Promise<void> {
	error.value = '';
	offer.value = null;

	try {
		const at     = address.value;
		const detail = await request<EntryDetail>('GET', at.handle ? `/content/${at.name.split('/').map(encodeURIComponent).join('/')}` : entryPath(at.name));

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

	if (entry.value !== null) {
		forget(entry.value.id);
	}
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
		await showMissing();

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

		// A plain save shows in the save state; a change of status says so.
		if (status !== undefined && status !== detail.status) {
			toast(status === 'published' ? 'Published' : (status === 'scheduled' ? 'Scheduled' : 'Switched to draft'));
		}
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

	if (detail === null || !window.confirm(`Move “${detail.title || 'Untitled'}” to the trash? You can restore it from the Trash tab.`)) {
		return;
	}

	try {
		await request<void>('DELETE', `${entryPath(detail.id)}?revision=${encodeURIComponent(detail.revision)}`);
		forget(detail.id);
		initial.value = null;
		await router.push({ name: 'type', params: { type: detail.type.name } });
		toast(`Moved “${detail.title || 'Untitled'}” to the trash`);
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

/**
 * Focuses the first required field left empty, opening the settings when
 * it's there rather than the title.
 */
async function showMissing(): Promise<void> {
	const first = missing.value[0];

	if (first === undefined) {
		return;
	}

	if (first.control !== 'editor-title') {
		sideOpen.value = true;
		tab.value      = 'document';
	}

	await nextTick();
	document.getElementById(first.control)?.focus();
}

// The writing surface.

const bodyEditor = ref<InstanceType<typeof MarkdownEditor> | null>(null);
const titleField = ref<HTMLTextAreaElement | null>(null);
const sideOpen   = ref(false);
const tab        = ref<'document' | 'component'>('document');
const caret      = ref(0);
const writing    = ref(false);
const available  = ref<ComponentDescription[]>([]);

const componentsFailed = ref(false);

loadComponents().then((components) => {
	available.value = components;
}, () => {
	componentsFailed.value = true;
});

// The component panel: open from its button, or for a slash typed at the
// start of a line (then the query is in the text, and it closes once a
// component replaces it).
const panel      = ref<InstanceType<typeof ComponentPanel> | null>(null);
const panelOpen  = ref(false);
const panelSlash = ref(false);
const panelQuery = ref('');

async function togglePanel(): Promise<void> {
	if (panelOpen.value) {
		closePanel();

		return;
	}

	panelSlash.value = false;
	panelQuery.value = '';
	panelOpen.value  = true;
	await nextTick();
	panel.value?.focus();
}

function closePanel(refocus = true): void {
	if (!panelOpen.value) {
		return;
	}

	panelOpen.value = false;

	if (panelSlash.value) {
		bodyEditor.value?.dismissSlash();
	}

	panelSlash.value = false;

	if (refocus) {
		bodyEditor.value?.focusAt(caret.value);
	}
}

function slashed(query: string | null): void {
	if (query === null) {
		if (panelSlash.value) {
			panelOpen.value  = false;
			panelSlash.value = false;
		}

		return;
	}

	panelSlash.value = true;
	panelQuery.value = query;
	panelOpen.value  = true;
}

function slashKey(key: 'ArrowUp' | 'ArrowDown' | 'Enter' | 'Escape'): void {
	if (key === 'Escape') {
		panelOpen.value  = false;
		panelSlash.value = false;
	} else if (key === 'Enter') {
		panel.value?.choose();
	} else {
		panel.value?.move(key === 'ArrowDown' ? 1 : -1);
	}
}

// The panel offers block components; inline ones have their own menu,
// less the icon, which has its own picker.
const blockComponents  = computed(() => available.value.filter((component) => component.kind !== 'inline'));
const inlineComponents = computed(() => available.value.filter((component) => component.kind === 'inline' && component !== iconComponent.value));

// "a callout", "an embed".
function article(name: string): string {
	return `${/^[aeiou]/i.test(name) ? 'an' : 'a'} ${inSentence(name)}`;
}

function chooseComponent(component: ComponentDescription): void {
	bodyEditor.value?.insert(component);
	toast(`Inserted ${article(component.label)}`);

	// A slash's panel has done its job; one opened from its button stays.
	if (panelSlash.value) {
		panelOpen.value  = false;
		panelSlash.value = false;
	}
}

function chooseInline(component: ComponentDescription): void {
	bodyEditor.value?.insert(component);
	toast(`Inserted ${article(component.label)}`);
}

// The icon picker's modal.
const iconsOpen = ref(false);

function openIcons(): void {
	iconsOpen.value = true;
}

const iconComponent = computed(() => componentFor('icon'));

function iconPreview(icon: SiteIcon): string {
	return `:${iconComponent.value?.name ?? 'blush/icon'}[]{${attributeText('name', icon.name)}}`;
}

function chooseIcon(icon: SiteIcon): void {
	iconsOpen.value = false;

	if (iconComponent.value === undefined) {
		bodyEditor.value?.insertText(iconPreview(icon));
	} else {
		bodyEditor.value?.insert(iconComponent.value, { name: icon.name });
	}

	toast(`Inserted the ${inSentence(icon.label)} icon`);
}

function closeIcons(): void {
	iconsOpen.value = false;
	bodyEditor.value?.focusAt(caret.value);
}

// The media picker: inserting a file, or choosing one for a field or a
// component's option.
const picking = ref<{ title: string; action: string; use: (file: MediaItem) => void } | null>(null);

function pickMedia(): void {
	picking.value = {
		title: 'Insert media',
		action: 'Insert',
		use: (file) => {
			// An image is plain Markdown (on its own line, the site makes it a
			// figure, with a quoted title as its caption, D-267), with the
			// caret in its alternative text; a video is a video, a sound
			// audio, and anything else a download.
			if (file.kind === 'image') {
				bodyEditor.value?.insertBlock((selected) => imageText(file.reference, selected));
				toast(`Inserted ${file.name}`);

				return;
			}

			const name      = file.kind === 'video' ? 'video' : (file.kind === 'audio' ? 'audio' : 'file');
			const component = componentFor(name);

			if (component === undefined) {
				bodyEditor.value?.insertText(`::blush/${name}{${attributeText('src', file.reference)}}`);
			} else {
				bodyEditor.value?.insert(component, { src: file.reference });
			}

			tab.value = 'component';
			toast(`Inserted ${file.name}`);
		}
	};
}

function pickForField(field: FieldDescription): void {
	picking.value = {
		title: `Choose ${label(field).toLowerCase()}`,
		action: 'Choose',
		use: (file) => {
			form.value[field.name] = file.reference;
		}
	};
}

function pickForOption(prop: ComponentProp): void {
	const item = directive.value;

	if (item === undefined) {
		return;
	}

	picking.value = {
		title: `Choose ${label(prop).toLowerCase()}`,
		action: 'Choose',
		use: (file) => {
			const edit = withAttribute(body.value, item, prop.name, file.reference);

			if (edit !== null) {
				applyOption(edit);
			}
		}
	};
}

function picked(file: MediaItem): void {
	const current = picking.value;

	picking.value = null;
	current?.use(file);
}

// The editor's own commands in the palette (D-248).
useCommands(() => {
	const detail = entry.value;

	if (detail === null) {
		return [];
	}

	const found: Command[] = [
		{ id: 'editor-focus', label: focusMode.value ? 'Leave focus mode' : 'Focus mode', icon: 'maximize-2', keywords: 'writing zen distraction', shortcut: '⌘⇧F', run: toggleFocus },
		{ id: 'editor-settings', label: sideOpen.value ? 'Hide the settings' : 'Show the settings', icon: 'panel-right', keywords: 'document fields sidebar', shortcut: '⌘/', run: toggleSide },
		{ id: 'editor-component', label: 'Insert a component', icon: 'plus', keywords: 'callout figure block', shortcut: '/', run: () => void togglePanel() },
		{ id: 'editor-media', label: 'Insert media', icon: 'image', keywords: 'image picture video audio file', run: pickMedia },
		{ id: 'editor-icon', label: 'Insert an icon', icon: 'shapes', keywords: 'symbol glyph', run: openIcons },
		{ id: 'editor-save', label: 'Save', icon: 'file-text', shortcut: '⌘S', run: () => void save() }
	];

	if (primary.value !== null && primary.value.status !== undefined) {
		const next = primary.value;

		found.push({ id: 'editor-primary', label: next.label, icon: 'circle-check', keywords: 'publish go live', run: () => void save(next.status) });
	}

	if (secondary.value !== null && secondary.value.status !== undefined) {
		const next = secondary.value;

		found.push({ id: 'editor-secondary', label: next.label, icon: 'file-text', keywords: 'unpublish draft', run: () => void save(next.status) });
	}

	if (detail.can.delete) {
		found.push({ id: 'editor-trash', label: 'Move to trash', icon: 'trash-2', keywords: 'delete remove', run: () => void trash() });
	}

	return found;
});

const markdown  = computed(() => outline(body.value));
const directive = computed<Directive | undefined>(() => markdown.value.directives[directiveAt(markdown.value.directives, caret.value)]);
const words     = computed(() => wordCount(markdown.value));

/**
 * The inserter's description of a directive's component, by its full or
 * core short name.
 */
function componentFor(name: string): ComponentDescription | undefined {
	return available.value.find((component) => component.name === name || component.name === `blush/${name}`);
}

function componentLabel(item: Directive): string {
	return componentFor(item.name)?.label ?? humanize(item.name.replace(/^.*\//, ''));
}

const selected = computed(() => directive.value === undefined ? undefined : componentFor(directive.value.name));

// Each component in the body, with a hint of which one it is.
const used = computed(() => markdown.value.directives.map((item, index) => {
	const values = attributesOf(body.value, item);
	const known  = componentFor(item.name);

	return {
		index,
		item,
		label: componentLabel(item),
		icon: known === undefined ? 'code' as const : componentIcon(known),
		hint: directiveHead(body.value, item).label?.text || values.title || values.caption || values.src || values.url || values.name || ''
	};
}));

function jumpTo(item: Directive): void {
	bodyEditor.value?.focusAt(directiveHead(body.value, item).end);
}

function openComponent(item: Directive): void {
	tab.value = 'component';
	jumpTo(item);
}

/**
 * Applies an option's edit to the body directly (so typing in the
 * settings keeps its focus), keeping the caret on the same text.
 */
function applyOption(edit: Edit): void {
	const delta = edit.text.length - (edit.to - edit.from);

	body.value = body.value.slice(0, edit.from) + edit.text + body.value.slice(edit.to);

	if (caret.value >= edit.to) {
		caret.value += delta;
	} else if (caret.value > edit.from) {
		caret.value = edit.from + edit.text.length;
	}
}

function removeComponent(): void {
	const item = directive.value;

	if (item !== undefined) {
		bodyEditor.value?.apply(withoutDirective(body.value, item));
		toast(`Removed the ${inSentence(componentLabel(item))}`);
	}
}

function toggleSide(): void {
	sideOpen.value = !sideOpen.value;
}

function showOptions(): void {
	tab.value      = 'component';
	sideOpen.value = true;
}

// Arrow keys move between the two tabs.
function tabKey(event: KeyboardEvent): void {
	if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
		return;
	}

	event.preventDefault();

	const next = tab.value === 'document' ? 'component' : 'document';

	tab.value = next;
	document.getElementById(`editor-tab-${next}`)?.focus();
}

// The title wraps to as many lines as it needs: by CSS where the browser
// sizes fields to their content, else measured whenever its text or
// width changes.
const sizesItself = typeof CSS !== 'undefined' && CSS.supports('field-sizing', 'content');
let titleWidth: ResizeObserver | null = null;

function sizeTitle(): void {
	const element = titleField.value;

	if (element !== null && !sizesItself) {
		element.style.height = 'auto';
		element.style.height = `${element.scrollHeight}px`;
	}
}

watch(title, () => void nextTick(sizeTitle));

watch(titleField, (element) => {
	titleWidth?.disconnect();
	titleWidth = null;

	if (element !== null && !sizesItself) {
		titleWidth = new ResizeObserver(sizeTitle);
		titleWidth.observe(element);
	}
});

// Enter in the title moves to the body.
function titleKey(event: KeyboardEvent): void {
	if (event.key === 'Enter' && !event.isComposing) {
		event.preventDefault();
		bodyEditor.value?.focusAt(0);
	}
}

// The chrome recedes while keys move, and comes back on any pointer move.
let quiet: ReturnType<typeof setTimeout> | undefined;

function typed(): void {
	writing.value = true;
	clearTimeout(quiet);
	quiet = setTimeout(() => {
		writing.value = false;
	}, 2600);
}

function moved(): void {
	if (writing.value) {
		clearTimeout(quiet);
		writing.value = false;
	}
}

function toggleFocus(): void {
	focusMode.value = !focusMode.value;
}

function keydown(event: KeyboardEvent): void {
	const command = event.metaKey || event.ctrlKey;

	if (command && event.key.toLowerCase() === 's') {
		event.preventDefault();
		void save();
	} else if (command && event.key === '/') {
		event.preventDefault();
		toggleSide();
	} else if (command && event.shiftKey && event.key.toLowerCase() === 'f') {
		event.preventDefault();
		toggleFocus();
	} else if (event.key === 'Escape' && !event.defaultPrevented) {
		if (focusMode.value) {
			focusMode.value = false;
		} else if (panelOpen.value) {
			closePanel();
		} else if (sideOpen.value) {
			sideOpen.value = false;
		}
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

	if (entry.value !== null) {
		forget(entry.value.id);
	}

	return true;
});

onMounted(() => {
	document.addEventListener('keydown', keydown);
	document.addEventListener('pointermove', moved, { passive: true });
	document.addEventListener('pointerdown', moved, { passive: true });
	window.addEventListener('beforeunload', beforeUnload);

	// The "just created" notice shows once.
	if (created.value) {
		void router.replace({ query: {} });
	}
});

onBeforeUnmount(() => {
	clearTimeout(keeping);
	clearTimeout(quiet);
	focusMode.value = false;
	document.removeEventListener('keydown', keydown);
	document.removeEventListener('pointermove', moved);
	document.removeEventListener('pointerdown', moved);
	window.removeEventListener('beforeunload', beforeUnload);
	titleWidth?.disconnect();
});

// Another entry loads; the same one at its handle doesn't.
watch(address, () => {
	if (entry.value === null || !isAt(entry.value)) {
		void load();
	}
}, { immediate: true });

// Counts are exact, with the locale's digit grouping: "1,204 words".
function count(value: number, one: string): string {
	return `${value.toLocaleString()} ${value === 1 ? one : `${one}s`}`;
}

function fieldKey(field: FieldDescription): string {
	return `${entry.value?.revision ?? ''}-${field.name}`;
}
</script>

<template>
	<section class="editor" :class="{ 'is-side-open': sideOpen, 'is-writing': writing, 'is-focus': focusMode }" aria-labelledby="editor-heading">
		<h1 id="editor-heading" class="visually-hidden" tabindex="-1">Edit {{ noun }}</h1>

		<header class="editor__head">
			<template v-if="entry">
				<RouterLink class="button button--ghost button--icon" :to="{ name: 'type', params: { type: entry.type.name } }">
					<AdminIcon name="arrow-left" />
					<span class="visually-hidden">Back to {{ typeInfo?.label ?? humanize(entry.type.name) }}</span>
				</RouterLink>
				<span class="editor__where">{{ typeInfo?.label ?? humanize(entry.type.name) }}</span>
				<span class="editor__divider" aria-hidden="true" />
				<button type="button" class="button button--ghost button--icon" :class="{ 'is-on': panelOpen }" title="Components ( / )" aria-controls="editor-components" :aria-expanded="panelOpen" @click="togglePanel">
					<AdminIcon name="plus" />
					<span class="visually-hidden">Insert a component</span>
				</button>
				<button type="button" class="button button--ghost button--icon" title="Media" aria-haspopup="dialog" @click="pickMedia">
					<AdminIcon name="image" />
					<span class="visually-hidden">Insert media</span>
				</button>
				<button type="button" class="button button--ghost button--icon" title="Icon" aria-haspopup="dialog" @click="openIcons">
					<AdminIcon name="shapes" />
					<span class="visually-hidden">Insert an icon</span>
				</button>
				<MenuButton v-if="inlineComponents.length" button-class="button button--ghost editor__wide" label="Insert an inline component" align="start" floating>
					<template #button>
						<AdminIcon name="baseline" /><AdminIcon name="chevron-down" class="editor__caret" />
					</template>
					<button v-for="component in inlineComponents" :key="component.name" type="button" class="menu-item menu-item--described" @click="chooseInline(component)">
						<AdminIcon :name="componentIcon(component)" />
						<span>
							<span class="menu-item__name">{{ component.label }}<template v-if="component.source && component.source.kind !== 'site'"> · {{ component.source.label }}</template></span>
							<span v-if="component.description" class="menu-item__text">{{ component.description }}</span>
						</span>
					</button>
				</MenuButton>
			</template>
			<span v-else class="skeleton editor__where-skeleton" />
			<span class="editor__grow" />
			<span class="editor__save" :class="saveState.tone ? `editor__save--${saveState.tone}` : undefined" aria-live="polite">
				<span v-if="saveState.text" class="editor__save-dot" aria-hidden="true" /><span class="editor__save-text">{{ saveState.text }}</span>
			</span>
			<template v-if="entry">
				<StatusPill :status="entry.status" />
				<button type="button" class="button button--ghost button--icon" :class="{ 'is-on': sideOpen }" title="Settings (⌘/)" aria-controls="editor-settings" :aria-expanded="sideOpen" @click="toggleSide">
					<AdminIcon name="panel-right" />
					<span class="visually-hidden">Settings</span>
				</button>
				<MenuButton button-class="button button--ghost button--icon" label="More actions">
					<template #button>
						<AdminIcon name="ellipsis" />
					</template>
					<button v-if="secondary" type="button" class="menu-item" :disabled="blocked" @click="save(secondary.status)">
						<AdminIcon name="file-text" />{{ secondary.label }}
					</button>
					<a v-if="entry.url" class="menu-item" :href="entry.url" target="_blank" rel="noopener">
						<AdminIcon name="external-link" />View<span class="visually-hidden"> the live {{ noun }} (new tab)</span>
					</a>
					<button type="button" class="menu-item" @click="toggleFocus">
						<AdminIcon name="maximize-2" />{{ focusMode ? 'Leave focus mode' : 'Focus mode' }}<kbd class="menu-kbd">⌘⇧F</kbd>
					</button>
					<template v-if="entry.can.delete">
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" @click="trash">
							<AdminIcon name="trash-2" />Move to trash
						</button>
					</template>
				</MenuButton>
				<button v-if="primary" type="button" class="button button--primary button--small" :disabled="blocked" @click="save(primary.status)">{{ primary.label }}</button>
			</template>
		</header>

		<div v-if="error" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" /><p>{{ error }}</p>
		</div>
		<div v-if="created" class="editor__notice editor__notice--good" role="status">
			<AdminIcon name="circle-check" /><p>Created. It's a draft until you publish it.</p>
		</div>

		<div v-if="offer" class="editor__notice" role="status">
			<AdminIcon name="triangle-alert" />
			<p>This browser kept changes to this {{ noun }} that weren't saved, from {{ formatDate(offer.kept) }}.</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="discard">Throw them away</button>
				<button type="button" class="button button--small button--primary" @click="restore">Restore them</button>
			</p>
		</div>

		<div v-if="failure" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" />
			<p>{{ safe }}, but they weren't saved. {{ failure.message }}</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="retry">Try again</button>
			</p>
		</div>

		<div v-if="attempted && missing.length" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" />
			<p>{{ plural(missing.length, 'required field is', 'required fields are') }} empty, so it wasn't published: {{ missing.map((item) => item.label).join(', ') }}. Fill {{ missing.length === 1 ? 'it' : 'them' }} in, or save it as a draft.</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="showMissing">Show me</button>
			</p>
		</div>

		<section v-if="conflict" class="editor__conflict" aria-labelledby="conflict-heading">
			<div class="editor__notice editor__notice--conflict">
				<AdminIcon name="triangle-alert" />
				<div class="editor__conflict-text">
					<h2 id="conflict-heading">This {{ noun }} changed while you were editing</h2>
					<p>
						<template v-if="conflict.theirs?.modified">Its file was saved at {{ formatDate(conflict.theirs.modified) }}, </template>
						<template v-else>Its file was saved again, </template>
						from the admin or by editing the file itself. {{ safe }}; nothing has been saved over. Keep theirs throws away your changes here; Keep mine saves your version of every field shown here over theirs.
					</p>
					<p v-if="!conflict.loading && conflict.theirs === null" class="field__error">The saved version couldn't be loaded. Check your connection, then try again.</p>
				</div>
				<p class="editor__notice-buttons">
					<template v-if="conflict.theirs">
						<button type="button" class="button button--small" @click="keepTheirs">Keep theirs</button>
						<button type="button" class="button button--small" :aria-expanded="comparing" aria-controls="conflict-compare" @click="comparing = !comparing">Compare</button>
						<button type="button" class="button button--small button--primary" @click="keepMine">Keep mine</button>
					</template>
					<button v-else type="button" class="button button--small" :disabled="conflict.loading" @click="fetchTheirs">{{ conflict.loading ? 'Loading the saved version…' : 'Try again' }}</button>
				</p>
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
				<p v-if="!differences.rows.length && !differences.body" class="compare__same">Your changes and theirs match in everything the editor shows.</p>
			</div>
		</section>

		<div class="editor__body">
			<aside id="editor-components" class="editor__inserter" aria-labelledby="component-panel-heading" :inert="!panelOpen">
				<ComponentPanel
					ref="panel"
					v-model:query="panelQuery"
					:components="blockComponents"
					:failed="componentsFailed"
					:slash="panelSlash"
					@choose="chooseComponent"
					@close="closePanel()"
				/>
			</aside>

			<div class="editor__main">
				<div class="editor__scroll" data-scroller>
					<div v-if="entry" class="editor__doc">
						<label class="visually-hidden" for="editor-title">Title</label>
						<textarea
							id="editor-title"
							ref="titleField"
							v-model="title"
							class="editor__title"
							rows="1"
							placeholder="Untitled"
							autocomplete="off"
							spellcheck="true"
							:aria-invalid="errorFor('title') ? 'true' : undefined"
							:aria-describedby="errorFor('title') ? 'editor-title-error' : undefined"
							@keydown="titleKey"
							@input="typed"
						/>
						<p v-if="errorFor('title')" id="editor-title-error" class="field__error">{{ errorFor('title') }}</p>

						<MarkdownEditor
							id="editor-body"
							ref="bodyEditor"
							class="editor__body-text"
							v-model="body"
							v-model:caret="caret"
							label="Body (Markdown)"
							placeholder="Write in Markdown…"
							:slash-open="panelOpen && panelSlash"
							@typed="typed"
							@slash="slashed"
							@slash-key="slashKey"
						/>
					</div>

					<div v-else-if="!error" class="editor__doc" aria-hidden="true">
						<span class="skeleton editor__title-skeleton" />
						<div class="editor__body-skeleton">
							<span v-for="line in 8" :key="line" class="skeleton" :style="{ width: `${55 + (line * 23) % 40}%` }" />
						</div>
						<p class="visually-hidden" role="status">Loading the {{ noun }}…</p>
					</div>
				</div>

				<footer v-if="entry" class="editor__foot">
					<span>{{ count(words, 'word') }}</span>
					<span class="editor__sep" aria-hidden="true">·</span>
					<span>{{ Math.max(1, Math.round(words / 220)) }} min read</span>
					<button v-if="directive && !(sideOpen && tab === 'component')" type="button" class="editor__chip" @click="showOptions">
						<AdminIcon :name="selected ? componentIcon(selected) : 'code'" />{{ componentLabel(directive) }} options
					</button>
					<span class="editor__foot-end">
						<button v-if="focusMode" type="button" class="editor__chip" @click="focusMode = false">
							<AdminIcon name="x" />Leave focus mode
						</button>
						<span class="editor__hide-small">Type <kbd>/</kbd> to insert</span>
						<span class="editor__hide-small"><kbd>⌘/</kbd> settings</span>
					</span>
				</footer>
			</div>

			<aside id="editor-settings" class="editor__side" aria-label="Settings" :inert="!sideOpen">
				<div class="editor__side-inner">
					<div class="editor__tabs">
						<div class="editor__tablist" role="tablist" aria-label="Settings" @keydown="tabKey">
							<button id="editor-tab-document" type="button" class="editor__tab" role="tab" aria-controls="editor-panel-document" :aria-selected="tab === 'document'" :tabindex="tab === 'document' ? 0 : -1" @click="tab = 'document'">
								<AdminIcon name="file-text" />Document
							</button>
							<button id="editor-tab-component" type="button" class="editor__tab" role="tab" aria-controls="editor-panel-component" :aria-selected="tab === 'component'" :tabindex="tab === 'component' ? 0 : -1" @click="tab = 'component'">
								<AdminIcon :name="selected ? componentIcon(selected) : 'code'" />
								<template v-if="directive">{{ componentLabel(directive) }}</template>
								<template v-else>Components</template>
								<span v-if="used.length" class="editor__tab-count mono"><span class="visually-hidden">(</span>{{ used.length }}<span class="visually-hidden"> in this {{ noun }})</span></span>
							</button>
						</div>
						<button type="button" class="button button--ghost button--icon editor__side-close" @click="sideOpen = false">
							<AdminIcon name="x" />
							<span class="visually-hidden">Close the settings</span>
						</button>
					</div>

					<div v-if="entry" v-show="tab === 'document'" id="editor-panel-document" role="tabpanel" aria-labelledby="editor-tab-document">
						<div class="editor__group">
							<p class="editor__group-heading">Publishing</p>
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
								<PreviewLinkControl :entry="{ id: entry.id, title: entry.title }" />
							</div>
						</div>

						<div v-if="fields.length" class="editor__group">
							<p class="editor__group-heading">{{ typeInfo?.singular ?? humanize(entry.type.name) }} fields</p>
							<FieldControl v-for="field in fields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" pickable @update:model-value="form[field.name] = $event" @pick="pickForField(field)" />
						</div>

						<div v-if="Object.keys(entry.extra).length" class="editor__group">
							<p class="editor__group-heading">Other front matter <span class="editor__group-hint">Kept as it is</span></p>
							<dl class="editor__extra">
								<div v-for="(value, key) in entry.extra" :key="key">
									<dt class="mono">{{ key }}</dt>
									<dd class="mono">{{ typeof value === 'string' ? value : JSON.stringify(value) }}</dd>
								</div>
							</dl>
						</div>

						<div v-if="entry.violations.length" class="editor__group">
							<p class="editor__group-heading">Problems <span class="editor__group-hint">As last saved</span></p>
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
					</div>

					<div v-show="tab === 'component'" id="editor-panel-component" role="tabpanel" aria-labelledby="editor-tab-component">
						<ComponentOptions
							v-if="directive"
							:source="body"
							:directive="directive"
							:component="selected"
							@edit="applyOption"
							@remove="removeComponent"
							@pick="pickForOption"
						/>
						<div v-else class="editor__none">
							<AdminIcon name="code" />
							<p class="editor__none-heading">No component selected</p>
							<p class="editor__none-text">Put the cursor inside one in the text, or pick it from the list below.</p>
						</div>

						<div class="editor__group">
							<p class="editor__group-heading">Components in this {{ noun }}</p>
							<p v-if="!used.length" class="field__help">None yet. Use <strong>+</strong> in the header, or type <kbd>/</kbd> at the start of a line.</p>
							<ul v-else class="editor__used">
								<li v-for="item in used" :key="`${item.index}-${item.item.start}`">
									<button type="button" class="editor__used-item" :class="{ 'is-current': directive === item.item }" :aria-current="directive === item.item ? 'true' : undefined" @click="openComponent(item.item)">
										<AdminIcon :name="item.icon" />
										<span class="editor__used-name">{{ item.label }}</span>
										<span class="editor__used-hint">{{ item.hint }}</span>
									</button>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</aside>
		</div>

		<IconPicker v-if="iconsOpen" :preview="iconPreview" @choose="chooseIcon" @close="closeIcons" />
		<MediaPicker v-if="picking" :entry="entry?.id" :title="picking.title" :action="picking.action" @choose="picked" @close="picking = null" />
	</section>
</template>

<style scoped>
/*
 * Writing first: one centered column, chrome that gets out of the way
 * while typing, and settings on demand rather than always beside the
 * text (admin.md §8).
 */

.editor {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-height: 0;
}

.editor__head {
	display: flex;
	flex: none;
	align-items: center;
	gap: 10px;
	min-height: 68px;
	padding: var(--s-3) var(--s-5);
	border-bottom: 1px solid var(--border);
	background: var(--surface);
	transition: opacity 250ms ease;
}

/* The most used toolbar in the admin: bigger targets, further apart. */
.editor__head :deep(.button--icon) {
	width: 36px;
	height: 36px;
}

.editor__head :deep(.button--icon svg) {
	width: 18px;
	height: 18px;
}

/* The inline menu's button carries a caret, so it reads as a menu. */
.editor__head :deep(.editor__wide) {
	gap: 2px;
	height: 36px;
	padding: 0 7px 0 9px;
}

.editor__head :deep(.editor__wide svg) {
	width: 18px;
	height: 18px;
}

.editor__head :deep(.editor__wide .editor__caret) {
	width: 12px;
	height: 12px;
	color: var(--fg-3);
}

.editor__head .is-on {
	background: var(--accent-soft);
	color: var(--accent);
}

.editor__where {
	max-width: 22ch;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.editor__where-skeleton {
	width: 5rem;
}

.editor__save {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	margin-left: 4px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	white-space: nowrap;
}

.editor__save-dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: var(--good-dot);
}

.editor__save--warn {
	color: var(--warn);
}

.editor__save--warn .editor__save-dot {
	background: var(--warn-dot);
}

.editor__save--danger {
	color: var(--danger);
}

.editor__save--danger .editor__save-dot {
	background: var(--danger-dot);
}

.editor__divider {
	flex: none;
	width: 1px;
	height: 22px;
	margin: 0 var(--s-1);
	background: var(--border);
}

.editor__grow {
	flex: 1;
	min-width: 0;
}

.menu-kbd {
	margin-left: auto;
	padding-left: 12px;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

/* Notices: one bar each, under the header. */

.editor__notice {
	display: flex;
	flex: none;
	align-items: center;
	gap: var(--s-3);
	padding: 12px var(--s-5);
	border-bottom: 1px solid var(--border);
	background: var(--warn-soft);
	color: var(--warn);
	font-size: var(--text-sm);
}

.editor__notice > svg {
	flex: none;
	width: 16px;
	height: 16px;
}

.editor__notice > p:first-of-type {
	flex: 1;
	min-width: 0;
}

.editor__notice-buttons {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	gap: 6px;
}

.editor__notice--danger {
	background: var(--danger-soft);
	color: var(--danger);
}

.editor__notice--good {
	background: var(--good-soft);
	color: var(--good);
}

.editor__notice--conflict {
	align-items: flex-start;
	padding-block: 15px;
	border-bottom-color: var(--border-strong);
	background: var(--surface-2);
	color: var(--fg);
}

.editor__notice--conflict > svg {
	margin-top: 2px;
	color: var(--warn);
}

.editor__conflict-text {
	display: grid;
	flex: 1;
	gap: 3px;
	min-width: 0;
}

.editor__conflict-text h2 {
	font-size: var(--text-sm);
	font-weight: 500;
}

.editor__conflict-text p {
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.editor__conflict {
	flex: none;
}

/* The column, and the drawer that pushes it aside. */

.editor__body {
	position: relative;
	display: flex;
	flex: 1;
	min-height: 0;
}

.editor__main {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-width: 0;
	min-height: 0;
	background: var(--surface);
}

.editor__scroll {
	flex: 1;
	min-height: 0;
	overflow: auto;
}

.editor__doc {
	max-width: 100%;
	padding: clamp(var(--s-6), 7vh, 72px) max(var(--s-5), calc((100% - var(--measure)) / 2)) 40vh;
}

.editor__title {
	display: block;
	field-sizing: content;
	width: 100%;
	margin: 0;
	padding: 0;
	overflow: hidden;
	border: 0;
	background: none;
	color: var(--fg);
	font-family: var(--font-display);
	font-size: var(--doc-title);
	font-weight: 600;
	letter-spacing: -.028em;
	line-height: 1.18;
	overflow-wrap: break-word;
	resize: none;
	white-space: pre-wrap;
}

.editor__title::placeholder {
	color: var(--fg-3);
	opacity: .6;
}

.editor__title:focus-visible {
	outline: none;
}

.editor__title[aria-invalid="true"] {
	text-decoration: underline wavy var(--danger-dot);
	text-decoration-skip-ink: none;
	text-underline-offset: .2em;
}

.editor__body-text {
	margin-top: var(--s-6);
}

.editor__title-skeleton {
	display: block;
	width: 60%;
	height: 40px;
	margin-bottom: var(--s-6);
}

.editor__body-skeleton {
	display: grid;
	gap: 14px;
}

.editor__foot {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
	padding: 11px var(--s-5);
	border-top: 1px solid var(--border);
	background: var(--surface);
	color: var(--fg-3);
	font-size: var(--text-xs);
	transition: opacity 250ms ease;
}

.editor__sep {
	opacity: .5;
}

.editor__foot-end {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-left: auto;
}

.editor__chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	margin-left: 8px;
	padding: 2px 8px;
	border: 1px solid var(--accent-line);
	border-radius: 99px;
	background: var(--accent-soft);
	color: var(--accent);
	font-size: var(--text-xs);
	font-weight: 500;
	cursor: pointer;
}

.editor__chip:hover {
	border-color: var(--accent);
	background: var(--accent);
	color: var(--accent-fg);
}

.editor__chip svg {
	width: 11px;
	height: 11px;
}

/* The chrome recedes while keys move. */

.is-writing .editor__head,
.is-writing .editor__foot {
	opacity: .32;
}

.is-focus .editor__head {
	border-bottom-color: transparent;
	background: transparent;
}

@media (prefers-reduced-motion: reduce) {
	.editor__head,
	.editor__foot,
	.editor__side,
	.editor__inserter {
		transition: none;
	}
}

/*
 * The component panel slides in from the left and pushes the column
 * aside, so it never covers the sentence being written.
 */

.editor__inserter {
	flex: none;
	width: 0;
	min-height: 0;
	overflow: hidden;
	background: var(--surface);
	transition: width 180ms ease;
}

.editor__inserter:not([inert]) {
	width: var(--inserter);
	border-right: 1px solid var(--border);
}

/* Settings push the column aside, so nothing is hidden behind them. */

.editor__side {
	flex: none;
	width: 0;
	min-height: 0;
	overflow: hidden;
	background: var(--surface);
	transition: width 180ms ease;
}

.is-side-open .editor__side {
	width: var(--drawer);
	border-left: 1px solid var(--border);
}

.editor__side-inner {
	width: var(--drawer);
	max-width: 100vw;
	height: 100%;
	overflow-y: auto;
}

/*
 * The tabs sit left, flush with the drawer's edge (the first one's own
 * padding lines its label up with the fields below), and the close
 * button sits right.
 */
.editor__tabs {
	position: sticky;
	top: 0;
	z-index: 2;
	display: flex;
	align-items: center;
	gap: var(--s-1);
	padding-right: var(--s-3);
	border-bottom: 1px solid var(--border);
	background: var(--surface);
}

.editor__tablist {
	display: flex;
	flex: 1;
	gap: var(--s-1);
	min-width: 0;
}

.editor__tab {
	display: flex;
	align-items: center;
	gap: 7px;
	min-width: 0;
	margin-bottom: -1px;
	padding: 16px 12px 13px;
	border: 0;
	border-bottom: 2px solid transparent;
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	white-space: nowrap;
	cursor: pointer;
}

.editor__tab:first-child {
	padding-left: var(--s-5);
}

.editor__tab svg {
	flex: none;
	width: 13px;
	height: 13px;
}

.editor__tab:hover {
	color: var(--fg);
}

.editor__tab[aria-selected="true"] {
	border-bottom-color: var(--accent);
	color: var(--fg);
	font-weight: 500;
}

.editor__tab-count {
	padding: 1px 6px;
	border-radius: 99px;
	background: var(--surface-2);
	color: var(--fg-3);
	font-size: var(--text-2xs);
	font-weight: 400;
}

.editor__side-close {
	flex: none;
}

.editor__group {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-5);
	border-bottom: 1px solid var(--border);
}

/* The Component tab with nothing selected says so, over the list. */
.editor__none {
	display: grid;
	justify-items: center;
	gap: 6px;
	padding: 52px var(--s-5) 46px;
	border-bottom: 1px solid var(--border);
	color: var(--fg-2);
	text-align: center;
}

.editor__none svg {
	width: 22px;
	height: 22px;
	margin-bottom: 6px;
	color: var(--fg-3);
}

.editor__none-heading {
	color: var(--fg);
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
}

.editor__none-text {
	max-width: 34ch;
	color: var(--fg-3);
	font-size: var(--text-sm);
	line-height: 1.6;
}

.editor__group-heading {
	display: flex;
	justify-content: space-between;
	gap: 8px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.editor__group-hint {
	font-weight: 400;
	letter-spacing: normal;
	text-transform: none;
}

.editor__preview {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.editor__used {
	display: grid;
	gap: 3px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.editor__used-item {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	padding: 8px 10px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.editor__used-item:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.editor__used-item.is-current {
	background: var(--accent-soft);
	color: var(--accent);
}

.editor__used-item svg {
	flex: none;
	width: 14px;
	height: 14px;
}

.editor__used-name {
	color: var(--accent);
	font-weight: 500;
	white-space: nowrap;
}

.editor__used-hint {
	min-width: 0;
	margin-left: auto;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.editor__extra {
	display: grid;
	gap: 6px;
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

/* The comparison, under the conflict's bar. */

.compare {
	max-height: 40vh;
	overflow: auto;
	border-bottom: 1px solid var(--border-strong);
	background: var(--surface);
}

.compare__fields td {
	white-space: pre-wrap;
	overflow-wrap: anywhere;
}

.compare__body {
	display: grid;
	gap: 8px;
	padding: 12px var(--pad-x) 16px;
}

.compare__same {
	padding: 12px var(--pad-x);
	color: var(--fg-2);
	font-size: var(--text-sm);
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
	margin: 0;
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

/* No room to push: the drawer lies over the column. */

@media (width <= 980px) {
	.editor__side {
		position: absolute;
		top: 0;
		right: 0;
		bottom: 0;
		z-index: 20;
		max-width: 100%;
	}

	.is-side-open .editor__side {
		box-shadow: var(--shadow-3);
	}

	.editor__inserter {
		position: absolute;
		top: 0;
		bottom: 0;
		left: 0;
		z-index: 20;
		max-width: 100%;
	}

	.editor__inserter:not([inert]) {
		box-shadow: var(--shadow-3);
	}
}

@media (width <= 760px) {
	.editor__doc {
		padding: var(--s-5) var(--s-4) 40vh;
	}

	.editor__where,
	.editor__hide-small {
		display: none;
	}

	.editor__head {
		gap: 2px;
		padding-inline: var(--s-2);
	}

	.editor__head :deep(.button--icon) {
		width: var(--ctl);
		height: var(--ctl);
	}
}

/* A phone keeps the save state's dot; its words are still read out. */

@media (width <= 480px) {
	.editor__save-text {
		position: absolute;
		width: 1px;
		height: 1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}
}
</style>
