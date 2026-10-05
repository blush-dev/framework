<script setup lang="ts">
/**
 * Edits an entry (D-229, D-233), as a writing surface (admin.md §8,
 * D-245): one centered column with the title as part of the document and
 * the Markdown body under it (`MarkdownEditor`, D-241); a header with
 * where you are, the save state, the status, and the actions; and a
 * footer with the breadcrumb, and words and reading time. It opens with
 * the section panel closed (the layout collapses it while it's open), and
 * the settings drawer as it was left (`drawer.ts`, D-299). Settings are a
 * drawer that pushes the column aside (⌘/), with two
 * tabs, left, named for what they hold, and a close button, right: the
 * entry's, named for its type ("Post"), with its publishing, schema
 * fields, other front matter, and problems, and at its foot one quiet
 * row into the **Outline** of every element in it, which opens over the
 * fields with a way back; and the element's, named for the element the
 * caret is in ("Callout", "Heading 2", else "Elements",
 * `elements.ts`): the most specific element wins, and a blank line
 * belongs to the element above it at its own level. It shows that
 * component's options (`ComponentOptions`), an image's (`ImageOptions`),
 * or a block's (`BlockOptions`), with a **Content** group, one level
 * deep, for an element that holds others. The breadcrumb names where the
 * caret is, from the entry down, and each crumb selects what it names.
 *
 * The header's left half is what you do to the document (admin.md §8,
 * The toolbar; D-313), in the order a writer asks: what goes in the
 * document, block components, in a panel that slides in from the left
 * and stays open (also opened by typing `/`; the Markdown elements are
 * tiles in it too, and **Image** opens the media library), and media, a
 * menu of **Media Library** and **Upload a File** (D-268); then, while
 * the caret is in the text, moving the top-level element it's in (⌥↑,
 * ⌥↓); what goes in a sentence, bold, italic, a link (a small form, ⌘K),
 * an icon (a library in a modal), and inline components (a short menu),
 * shown only where emphasis is emphasis; and how wide an element is,
 * bleed, for a top-level element. Contextual groups are hidden, not
 * disabled, and come after the fixed ones, so nothing that's always
 * there moves. One thing is open at a time, and none of it toasts: the
 * result is in the text. There's no back button: the type in the top
 * bar's trail is the way out. Its right half says what the entry is and
 * what happens to it.
 *
 * When the caret leaves the text, the selection leaves with it: the
 * breadcrumb is the entry alone and the element tab says nothing's
 * selected. The editor's own chrome (the toolbar, footer, drawer, the
 * inserter, and the pickers) isn't leaving; where a press lands decides
 * it, not where focus goes. The drawer opens on the element tab when
 * something's selected.
 *
 * Focus mode (⌘⇧F) leaves only the column; Escape returns. The ⋮ menu,
 * after the primary button, has the rest in two named sections, View and
 * Entry, with their shortcuts.
 *
 * A save sends only what changed, so untouched keys stay exactly as the
 * file has them, with the revision it was loaded at. Ctrl+S (⌘S) saves.
 *
 * **A new entry** (`/entries/new?type=…`, D-336) opens here, with no step
 * in front: the server describes one that isn't written yet (no id), the
 * title has the caret, and the first save creates it (the slug from the
 * title unless one's given) and moves the address to it. Leaving before
 * then writes nothing.
 *
 * Nothing typed is lost (D-240):
 *
 * - Unsaved changes are kept in this browser (`kept.ts`) as they're
 *   made, and as the page goes; opening the entry again offers them
 *   back. So leaving the page gets no browser warning (D-374), the
 *   reload shortcuts ask in the admin's own modal, and leaving for
 *   another admin screen asks, saying they stay in this browser (D-375).
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
 *
 * A type's index page (D-255, D-274) is marked **Index** beside its type
 * and says what it is on the Document tab. The server sends it without
 * the type's fields or a date, so it has no taxonomy fields and can't be
 * scheduled, and without `can.delete`, so it has no Move to trash. A
 * people field's list page, and a page written for one person's archive
 * (D-329, D-353), are edited the same way and say what they introduce;
 * they keep their slugs. A list page may be trashed; an archive's page
 * is removed from its profile's screen instead.
 *
 * A profile is edited here like any entry (D-355); Your Account is the
 * account's own settings, and links here. A profile an account is
 * linked to keeps its slug (`can.rename` is false), since the link is by
 * it.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { confirmAction } from '../confirm';
import { makeHomepage } from '../homepage';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import { ApiError, entryPath, entryRoute, request, upload, type EntryDetail, type EntryStatus, type NewEntryDetail, type FieldDescription, type MediaItem, type PreviewLink } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import BlockOptions from '../components/BlockOptions.vue';
import ComponentOptions from '../components/ComponentOptions.vue';
import ComponentPanel from '../components/ComponentPanel.vue';
import DatePicker from '../components/DatePicker.vue';
import FieldControl from '../components/FieldControl.vue';
import ImagePreview from '../components/ImagePreview.vue';
import ReferencePicker from '../components/ReferencePicker.vue';
import IconPicker from '../components/IconPicker.vue';
import ImageOptions from '../components/ImageOptions.vue';
import MarkdownEditor from '../components/MarkdownEditor.vue';
import MediaPicker from '../components/MediaPicker.vue';
import MenuButton from '../components/MenuButton.vue';
import StatusPill from '../components/StatusPill.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import TypeIcon from '../components/TypeIcon.vue';
import { BLOCK_KINDS } from '../blocks';
import { bleedClasses, componentIcon, IMAGE_COMPONENT, imageVariants, loadComponents, MARKDOWN_ELEMENTS, type BleedClasses, type ComponentDescription, type ComponentProp } from '../components';
import { online } from '../connection';
import { diffLines, type DiffLine } from '../diff';
import { drawerOpen, keepDrawer } from '../drawer';
import { fromForm, humanize, inSentence, label, splitDate, toForm, type FormValue } from '../fields';
import { formatDate, plural, titleCase } from '../format';
import { forget, keep, kept, type EditorState, type KeptChanges } from '../kept';
import { childrenOf, elementAt, elementName, excerpt, holdsContent, imageLine, movedElement, outlineItems, pathTo, runIndex, sameElement, siblingRuns, type ElementRef, type OutlineItem } from '../elements';
import { attributeParts, attributeText, blocks, directiveHead, emphasisAt, imageText, renumberedAt, inProse, linkAt, linkLabel, outline, withAttribute, withBlockParts, withDirectiveParts, withImage, withLink, withoutDirective, withoutImage, withoutLink, withParts, wordAt, wordCount, type Directive, type Edit, type Emphasis, type MarkdownLink } from '../markdown';
import { mediaName } from '../media';
import { loadReferences, slugOf, type ReferenceItem } from '../references';
import { canUpload } from '../session';
import type { IconName } from '../icons';
import type { SiteIcon } from '../site-icons';
import { focusMode, screenCrumb, screenTitle, screenTrail } from '../screen';
import { config } from '../config';
import { toast } from '../toast';
import { useCommands, type Command } from '../commands';
import { profileType, currentType, labelsOf, loadTypes, types } from '../types';

// Fields the editor shows in their own places rather than the form.
const PLACED = ['title', 'status', 'published', 'slug'];

// Why a required field stops publishing.
const REQUIRED = 'Required to publish.';

const route  = useRoute();
const router = useRouter();

function joined(segments: string | string[] | undefined): string {
	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
}

// What the route names: an entry's id (D-483), or a new entry's type
// (D-336).
const address = computed(() => route.name === 'entry-new'
	? { fresh: true, name: typeof route.query.type === 'string' ? route.query.type : '' }
	: { fresh: false, name: joined(route.params.id) });

function isAt(detail: EntryDetail | NewEntryDetail): boolean {
	const at = address.value;

	if (at.fresh) {
		return detail.id === null && detail.type.name === at.name;
	}

	return at.name === detail.id;
}

/**
 * What an entry's unsaved changes are kept under in this browser: its
 * id, or its type's for a new one.
 */
function keptAs(detail: EntryDetail | NewEntryDetail): string {
	return detail.id ?? `new:${detail.type.name}`;
}


/**
 * Keeps the address on the entry's type and id: a new entry's first
 * save, or an address naming another type.
 */
function follow(detail: EntryDetail | NewEntryDetail): void {
	if (detail.id === null || (route.name === 'entry' && joined(route.params.id) === detail.id && joined(route.params.type) === detail.type.name)) {
		return;
	}

	void router.replace({ ...entryRoute({ id: detail.id, type: detail.type.name }), query: address.value.fresh ? {} : route.query, hash: route.hash });
}

const entry    = ref<EntryDetail | NewEntryDetail | null>(null);
const title    = ref('');
const body     = ref('');
const date     = ref('');
const slug     = ref('');
const form     = ref<Record<string, FormValue>>({});
const initial  = ref<EditorState | null>(null);
const error    = ref('');
const saving   = ref(false);
const savedAt  = ref<Date | null>(null);
const notices  = ref(false);

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

function fieldsOf(detail: EntryDetail | NewEntryDetail | null): FieldDescription[] {
	return detail?.type.fields.filter((field) => !PLACED.includes(field.name)) ?? [];
}

const fields    = computed(() => fieldsOf(entry.value));

// The field sets attached to the type (D-337), each a group of its own in
// the document panel under its label, with its fields in the set's order;
// fields stay out of the writing area (D-348). Their fields stay out of
// the panel's other groups.
const setGroups = computed(() => (entry.value?.type.sets ?? []).map((set) => ({
	...set,
	fields: set.fields.flatMap((name) => fields.value.filter((field) => field.name === name))
})).filter((set) => set.fields.length > 0));

const inSets    = computed(() => new Set((entry.value?.type.sets ?? []).flatMap((set) => set.fields)));
const ownFields = computed(() => fields.value.filter((field) => !inSets.value.has(field.name)));
const dateField = computed(() => entry.value?.type.fields.find((field) => field.name === 'published'));
const labels    = computed(() => labelsOf(entry.value?.type.name ?? 'entry'));
// A people page (D-353) is a field's list page ("cooks page") or the page
// written for one person's archive ("archive page").
const peopleNoun = computed(() => {
	const page = entry.value?.peoplePage;

	return page ? (page.profile === null ? `${page.label.toLowerCase()} page` : 'archive page') : null;
});
const noun      = computed(() => entry.value?.index ? 'index page' : (peopleNoun.value ?? labels.value.item));
const entryType = computed(() => types.value.find((type) => type.name === entry.value?.type.name));
const fresh     = computed(() => entry.value !== null && entry.value.id === null);
const editTitle = computed(() => titleCase(entry.value?.index ? 'Edit index page' : (peopleNoun.value ? `Edit ${peopleNoun.value}` : (fresh.value ? labels.value.newItem : labels.value.editItem))));

// A tree's page goes under the page chosen here, or at the top: a new
// one when it's first saved (D-408), one that exists by moving (D-410).
// The choices are the tree's pages, indented, from `GET references/{type}`,
// without the page itself and those under it. A new page's address may
// name one to start with (`?parent=about`).
const treeParent  = computed(() => entry.value?.type.kind === 'tree' && (fresh.value || entry.value.can.move));
const parent      = ref(typeof route.query.parent === 'string' ? route.query.parent : '');
const parentItems = ref<ReferenceItem[] | null>(null);
const parentError = ref('');
const moving      = computed(() => !fresh.value && entry.value !== null && entry.value.parent !== null && parent.value !== entry.value.parent);

const parentOptions = computed<SelectOption[]>(() => {
	const key     = fresh.value ? null : entry.value?.key ?? null;
	const options = (parentItems.value ?? [])
		.filter((item) => !item.missing && (key === null || (item.slug !== key && !item.slug.startsWith(`${key}/`))))
		.map((item) => ({ value: item.slug, label: item.title, depth: item.depth ?? 0 }));
	const current = entry.value?.parent ?? '';

	// A folder with no page of its own is still where the page is.
	if (current !== '' && !options.some((option) => option.value === current)) {
		options.unshift({ value: current, label: `${current} (no page)`, depth: 0 });
	}

	return [{ value: '', label: 'None, at the top level' }, ...options];
});

watch(parent, () => {
	parentError.value = '';
});

watch(() => treeParent.value ? `${entry.value?.type.name}:${entry.value?.id ?? ''}` : undefined, async (at) => {
	const name = entry.value?.type.name;

	parentItems.value = null;

	if (at === undefined || name === undefined) {
		return;
	}

	try {
		parentItems.value = (await loadReferences(name, { tree: true })).items;
	} catch {
		parentItems.value = [];
	}

	if (fresh.value && !parentOptions.value.some((option) => option.value === parent.value)) {
		parent.value = '';
	}
}, { immediate: true });

// The navigation marks the entry's type; the top bar names what's edited.
loadTypes().catch(() => undefined);

watch(entry, (value) => {
	currentType.value = value?.type.name ?? null;
});

watch(editTitle, (value) => {
	screenTitle.value = value;
}, { immediate: true });

// The trail's type is the way out: the editor has no back button.
watch([entry, labels], () => {
	const name = entry.value?.type.name;

	screenTrail.value = name === undefined ? [] : [{ label: titleCase(labels.value.plural), to: { name: 'type', params: { type: name } } }];
	screenCrumb.value = fresh.value ? 'New' : 'Editing';
}, { immediate: true });

/**
 * The form state an entry the server sent starts from.
 */
function stateOf(detail: EntryDetail | NewEntryDetail): EditorState {
	const published = detail.type.fields.find((field) => field.name === 'published');

	return {
		title: typeof detail.values.title === 'string' ? detail.values.title : detail.title,
		body: detail.body,
		date: published === undefined ? '' : String(toForm(published, detail.values.published)),
		slug: detail.slug,
		form: Object.fromEntries(fieldsOf(detail).map((field) => [field.name, toForm(field, detail.values[field.name])]))
	};
}

function current(): EditorState {
	return { title: title.value, body: body.value, date: date.value, slug: slug.value, form: { ...form.value } };
}

function apply(state: EditorState): void {
	title.value = state.title;
	body.value  = state.body;
	date.value  = state.date;
	slug.value  = state.slug ?? entry.value?.slug ?? '';
	form.value  = Object.fromEntries(fields.value.map((field) => [field.name, state.form[field.name] ?? toForm(field, undefined)]));
}

function same(a: EditorState, b: EditorState): boolean {
	return a.title === b.title && a.body === b.body && a.date === b.date && (a.slug === undefined || b.slug === undefined || a.slug === b.slug)
		&& fields.value.every((field) => a.form[field.name] === b.form[field.name]);
}

const changedFields = computed(() => {
	const start = initial.value;

	return start === null ? [] : fields.value.filter((field) => form.value[field.name] !== start.form[field.name]);
});

const dirty = computed(() => {
	const start = initial.value;

	return start !== null && (title.value !== start.title || body.value !== start.body || date.value !== start.date || slug.value.trim() !== start.slug || moving.value || changedFields.value.length > 0);
});

// Why the last save refused the new slug (D-277), until it's changed;
// and whether a live entry's old address redirects to its new one.
const slugError = ref('');
const redirect  = ref(true);

watch(slug, () => {
	slugError.value = '';
});

// The address the new slug gives, when the address ends in the slug.
const slugAddress = computed(() => {
	const detail = entry.value;
	const url    = detail?.url ?? null;

	if (detail === null || url === null || slug.value.trim() === '' || moving.value) {
		return null;
	}

	const trailing = url.endsWith('/') ? '/' : '';
	const path     = url.replace(/\/$/, '');

	return path.endsWith(`/${detail.slug}`) ? `${path.slice(0, -detail.slug.length)}${slug.value.trim()}${trailing}` : null;
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
function fill(detail: EntryDetail | NewEntryDetail): void {
	entry.value = detail;
	apply(stateOf(detail));

	if (detail.id !== null) {
		parent.value = detail.parent ?? '';
	}

	initial.value   = current();
	conflict.value  = null;
	comparing.value = false;
	failure.value   = null;
	waiting.value   = null;

	follow(detail);
}

/**
 * The type a new entry is of: the one asked for, else the first
 * collection (the address follows).
 */
async function freshType(asked: string): Promise<string> {
	const all = await loadTypes();

	if (all.some((item) => item.name === asked)) {
		return asked;
	}

	const name = all.find((item) => item.kind === 'collection')?.name ?? all[0]?.name ?? asked;

	await router.replace({ name: 'entry-new', query: { type: name } });

	return name;
}

/**
 * Makes the root page the homepage (D-420). Only its homepage marks
 * change, so unsaved edits stay.
 */
async function toHomepage(): Promise<void> {
	const current = entry.value;

	if (current !== null && await makeHomepage(current.title, current.homeInstead)) {
		current.homepage         = true;
		current.homeInstead      = null;
		current.can.makeHomepage = false;
	}
}

async function load(): Promise<void> {
	error.value = '';
	offer.value = null;

	try {
		const at     = address.value;
		const path   = at.fresh
			? `/entries/new?type=${encodeURIComponent(await freshType(at.name))}`
			: entryPath(at.name);
		const detail = await request<EntryDetail | NewEntryDetail>('GET', path);

		// One in the trash is looked at, not edited (D-484).
		if (detail.id !== null && detail.status === 'trash') {
			await router.replace({ name: 'trashed', params: { id: detail.id } });

			return;
		}

		fill(detail);

		// Changes kept from before are offered back, unless they're what
		// the file already says.
		const earlier = kept(keptAs(detail));

		if (earlier !== null && same(earlier.state, current())) {
			forget(keptAs(detail));
		} else {
			offer.value = earlier;
		}

		// A new entry starts at its title.
		if (detail.id === null && earlier === null) {
			await nextTick();
			titleField.value?.focus();
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

	if (entry.value.id !== null && earlier.revision !== entry.value.revision) {
		conflict.value = { theirs: entry.value, loading: false };
	}
}

function discard(): void {
	offer.value = null;

	if (entry.value !== null) {
		forget(keptAs(entry.value));
	}
}

// Changes are kept in the browser a moment after typing stops; a clean
// form keeps nothing. While an offer is open, what's kept stays as it is.
let keeping: ReturnType<typeof setTimeout> | undefined;

function keepNow(): void {
	clearTimeout(keeping);

	const detail = entry.value;

	if (detail === null || offer.value !== null) {
		return;
	}

	if (dirty.value) {
		keptHere.value = keep(keptAs(detail), detail.revision ?? '', current());
	} else {
		forget(keptAs(detail));
		keptHere.value = false;
	}
}

watch([title, body, date, form], () => {
	clearTimeout(keeping);
	keeping = setTimeout(keepNow, 400);
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

	// A plain save with nothing changed has nothing to do.
	if (status === undefined && !dirty.value) {
		return;
	}

	if (publishes(status) && missing.value.length > 0) {
		attempted.value = true;
		await showMissing();

		return;
	}

	failure.value = null;

	// A new entry is named for its title, so it needs one to be written.
	if (detail.id === null && title.value.trim() === '') {
		failure.value = { message: `Give the ${noun.value} a title to save it.`, status };
		titleField.value?.focus();

		return;
	}

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

	if (slug.value.trim() !== start.slug) {
		change.slug     = slug.value.trim();
		change.redirect = redirect.value;
	}

	if (moving.value) {
		change.parent   = parent.value;
		change.redirect = redirect.value;
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

	try {
		const saved = detail.id === null
			? await request<EntryDetail>('POST', '/entries', created(detail, change, status))
			: await request<EntryDetail>('PATCH', entryPath(detail.id), change);

		fill(saved);
		forget(keptAs(saved));
		forget(keptAs(detail));
		keptHere.value  = false;
		attempted.value = false;
		savedAt.value   = new Date();

		// A plain save shows in the save state; a change of status says
		// so, and so does a new entry's first.
		if (detail.id === null && saved.status === 'draft') {
			toast('Saved as a draft');
		} else if (status !== undefined && status !== detail.status) {
			toast(status === 'published' ? 'Published' : (status === 'scheduled' ? 'Scheduled' : 'Switched to draft'));
		}
	} catch (caught) {
		if (caught instanceof ApiError && (caught.field === 'slug' || caught.field === 'parent')) {
			// Nothing was saved; the slug or parent field says why.
			(caught.field === 'slug' ? slugError : parentError).value = caught.message;
			sideOpen.value  = true;
			tab.value       = 'document';
			await nextTick();
			document.getElementById(`editor-${caught.field}`)?.focus();
		} else if (caught instanceof ApiError && caught.status === 409) {
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

/**
 * What creates a new entry (D-229) from a save's changes: its type,
 * title, and slug if one's given, a tree page's parent (D-408), the
 * fields set, the body, and the status (a draft unless publishing).
 */
function created(detail: NewEntryDetail, change: Record<string, unknown>, status?: EntryStatus): Record<string, unknown> {
	const { title: named, ...set } = change.set as Record<string, unknown>;

	return {
		type: detail.type.name,
		title: named,
		set,
		status: status ?? 'draft',
		...(change.slug === undefined || change.slug === '' ? {} : { slug: change.slug }),
		...(treeParent.value && parent.value !== '' ? { parent: parent.value } : {}),
		...(change.body === undefined ? {} : { body: change.body }),
		...(change.published === undefined ? {} : { published: change.published })
	};
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

	if (open === null || entry.value === null || entry.value.id === null) {
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

	if (detail === null || detail.id === null || !await confirmAction({ title: `Move “${detail.title || 'Untitled'}” to the Trash?`, body: 'You can restore it from the Trash tab.', confirm: 'Move to trash', danger: true })) {
		return;
	}

	try {
		await request<void>('DELETE', `${entryPath(detail.id)}?revision=${encodeURIComponent(detail.revision)}`);
		forget(detail.id);
		initial.value = null;
		await router.push({ name: 'type', params: { type: detail.type.name } });
		toast(`Moved “${detail.title || 'Untitled'}” to the trash`, { kind: 'danger' });
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

// The Status value's menu: what each status does, in a sentence, and
// only the ones this account can move it to. Choosing one saves. The
// trash is never offered (D-484): Move to Trash sets it.
const statusNames: Record<EntryStatus, { label: string; icon: IconName }> = {
	published: { label: 'Published', icon: 'circle-check' },
	scheduled: { label: 'Scheduled', icon: 'calendar-clock' },
	draft: { label: 'Draft', icon: 'file-pen-line' },
	trash: { label: 'Trash', icon: 'trash-2' }
};

const statusOptions = computed(() => {
	const detail = entry.value;

	if (detail === null) {
		return [];
	}

	const options: { status: EntryStatus; label: string; text: string; icon: IconName }[] = [
		{ status: 'draft', label: 'Draft', text: 'Not on the site. Only people who can edit it see it.', icon: 'file-pen-line' }
	];

	if (detail.can.publish && future.value) {
		options.push({ status: 'scheduled', label: 'Scheduled', text: 'Goes live on its date, on its own.', icon: 'calendar-clock' });
	} else if (detail.can.publish) {
		options.push({ status: 'published', label: 'Published', text: 'On the site, for anyone to read.', icon: 'circle-check' });
	}

	if (detail.status !== 'trash' && !options.some((option) => option.status === detail.status)) {
		options.push({ status: detail.status, ...statusNames[detail.status], text: detail.status === 'scheduled' ? 'Its date has passed, so it goes live the next time it is published.' : 'On the site, for anyone to read.' });
	}

	return options;
});

// What the date means, so nobody has to work it out.
const dateMeaning = computed(() => {
	const offset = splitDate(entry.value?.values.published)?.offset ?? '';
	const when   = date.value === '' ? Number.NaN : Date.parse(`${date.value}:00${offset === '' ? '' : offset.replace(/^([+-]\d{2})(\d{2})$/, '$1:$2')}`);

	if (Number.isNaN(when)) {
		return 'No date yet. It\'s dated when it\'s published.';
	}

	const difference = when - Date.now();
	const days       = Math.round(Math.abs(difference) / 86400000);
	const near       = Math.abs(difference) < 3600000 ? 'within the hour'
		: (days === 0 ? 'today' : (days === 1 ? (difference > 0 ? 'tomorrow' : 'yesterday') : `${days.toLocaleString()} days ${difference > 0 ? 'from now' : 'ago'}`));

	if (difference > 0) {
		return entry.value?.status === 'scheduled' ? `Goes live ${near}.` : `Goes live ${near}, once it's scheduled.`;
	}

	return entry.value?.status === 'published' ? `Published ${near}.` : `Dated ${near}.`;
});

// The line under Publish: what the date means, and when the file was
// last written, as a fact about now.
function ago(iso: string | null): string {
	const when = iso === null ? Number.NaN : Date.parse(iso);

	if (Number.isNaN(when)) {
		return '';
	}

	const minutes = Math.round((Date.now() - when) / 60000);

	if (minutes < 1) {
		return 'just now';
	}

	if (minutes < 60) {
		return `${plural(minutes, 'minute')} ago`;
	}

	const hours = Math.round(minutes / 60);

	return hours < 24 ? `${plural(hours, 'hour')} ago` : `${plural(Math.round(hours / 24), 'day')} ago`;
}

const publishNote = computed(() => {
	const edited = ago(entry.value?.modified ?? null);

	return [dateField.value === undefined ? '' : dateMeaning.value.replace(/\.$/, ''), edited === '' ? '' : `Last edited ${edited}`].filter((part) => part !== '').join(' · ') + '.';
});

/**
 * Opens a preview of the entry as last saved, in a new tab: a signed
 * link (D-226). The tab opens as the menu is chosen, so it isn't
 * blocked, and goes to the link once it's made.
 */
async function preview(): Promise<void> {
	const detail = entry.value;
	const tab    = window.open('about:blank', '_blank');

	if (detail === null || detail.id === null) {
		tab?.close();

		return;
	}

	try {
		const link = await request<PreviewLink>('POST', '/previews', { entry: detail.id });

		if (tab === null) {
			window.open(link.url, '_blank', 'noopener');
		} else {
			tab.opener = null;
			tab.location.href = link.url;
		}

		if (dirty.value) {
			toast('The preview shows the last saved version', { kind: 'info' });
		}
	} catch (caught) {
		tab?.close();
		error.value = caught instanceof ApiError ? caught.message : 'The preview couldn\'t be opened.';
	}
}

// The Document tab's fields, in the order they're touched (admin.md §8,
// The document panel): visibility, a term's parent, and a tree page's or
// term's position as rows under Publish, then the featured image, each
// people field, each other reference
// (a taxonomy's terms, as a picker), the summary, the type's other
// fields as a form, and then each field set's (D-337).
const visibilityField = computed(() => ownFields.value.find((field) => field.name === 'visibility' && field.type === 'enum'));
const parentField     = computed(() => ownFields.value.find((field) => field.name === 'parent' && field.type === 'reference' && field.multiple === false && field.to !== undefined));
// A tree page's or term's place among its siblings (D-412), a row beside
// its parent.
const positionField   = computed(() => ownFields.value.find((field) => field.name === 'position' && field.type === 'number'));
// A term has no author or featured image of its own (admin.md §8), so
// those show only when its file has one.
const term = computed(() => entry.value?.type.kind === 'taxonomy');
const imageField      = computed(() => ownFields.value.find((field) => field.name === 'image' && field.type === 'media'));
// Each people field (D-353): a reference to profiles, such as `authors`
// or a recipe's `cooks`, as a people picker under its own label.
const peopleFields    = computed(() => ownFields.value.filter((field) => field.type === 'reference' && field.to !== undefined && field.to === profileType.value));
const summaryField    = computed(() => ownFields.value.find((field) => field.name === 'summary' && field.type === 'markdown'));
const referenceFields = computed(() => ownFields.value.filter((field) => field.type === 'reference' && field.to !== undefined && field.multiple !== false && !peopleFields.value.includes(field)));

const otherFields = computed(() => {
	const placed = [visibilityField.value, parentField.value, positionField.value, imageField.value, ...peopleFields.value, summaryField.value, ...referenceFields.value];

	return ownFields.value.filter((field) => !placed.includes(field));
});

function referenceCount(field: FieldDescription): number {
	return String(form.value[field.name] ?? '').split(',').filter((item) => item.trim() !== '').length;
}

// Who can reach it (D-082), with what each choice does. Public is the
// default, so choosing it writes nothing.
type VisibilityName = 'public' | 'unlisted' | 'hidden';

const visibilityNames: Record<VisibilityName, { label: string; icon: IconName; text: string }> = {
	public: { label: 'Public', icon: 'eye', text: 'Anyone can read it once it\'s live, and it\'s listed with the others.' },
	unlisted: { label: 'Unlisted', icon: 'link', text: 'It has an address, but isn\'t in lists, feeds, or the sitemap.' },
	hidden: { label: 'Hidden', icon: 'eye-off', text: 'No address and not listed. The site\'s templates can still show it.' }
};

const visibility = computed<VisibilityName>(() => {
	const value = visibilityField.value === undefined ? '' : String(form.value[visibilityField.value.name] ?? '');

	return value === 'unlisted' || value === 'hidden' ? value : 'public';
});

function setVisibility(value: VisibilityName): void {
	const field = visibilityField.value;

	if (field !== undefined) {
		form.value[field.name] = value === 'public' && (initial.value?.form[field.name] ?? '') === '' ? '' : value;
	}
}

// Saving stops while a conflict or an offer of kept changes is open.
const blocked = computed(() => saving.value || conflict.value !== null || offer.value !== null);

// Whether a button's save does nothing: no status change and no changes.
function idle(action: { status?: EntryStatus }): boolean {
	return action.status === undefined && !dirty.value;
}

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
const sideOpen   = ref(drawerOpen());
const tab        = ref<'document' | 'element'>('document');
const caret      = ref(0);
const available  = ref<ComponentDescription[]>([]);

const componentsFailed = ref(false);
const imageStyles      = ref<Awaited<ReturnType<typeof imageVariants>>>([]);
const bleeds           = ref<BleedClasses>({ wide: 'bleed-wide', full: 'bleed-full' });

loadComponents().then(async (components) => {
	available.value   = components;
	imageStyles.value = await imageVariants();
	bleeds.value      = await bleedClasses();
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

	closeOverlays();
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

// The panel offers the Markdown elements and block components, the
// Markdown first in each group, with a Markdown image first among the
// media (D-268, D-313); inline ones have their own menu, less the icon,
// which has its own picker.
const blockComponents  = computed(() => available.value.length === 0 ? [] : [...MARKDOWN_ELEMENTS, IMAGE_COMPONENT, ...available.value.filter((component) => component.kind !== 'inline')]);
// Inside a container that holds only some things, only those are offered.
const panelComponents = computed(() => {
	const only = holder.value?.only;

	return only ? blockComponents.value.filter((component) => only.includes(component.name)) : blockComponents.value;
});

const panelNote = computed(() => {
	const only = holder.value?.only;

	if (holder.value === undefined || !only) {
		return undefined;
	}

	return `${titleCase(holder.value.label)} holds only ${onlyNames(only)}.`;
});

// "images", "images, buttons".
function onlyNames(only: string[]): string {
	return only.map((name) => name === 'image' ? 'images' : `${inSentence(componentFor(name)?.label ?? name)}s`).join(', ');
}

const panelNoteNames = computed(() => onlyNames(selected.value?.only ?? []));

const inlineComponents = computed(() => available.value.filter((component) => component.kind === 'inline' && component !== iconComponent.value));

function chooseComponent(component: ComponentDescription): void {
	const markdown = component.markdown;

	if (component === IMAGE_COMPONENT) {
		pickMedia('library', 'image');
	} else if (markdown !== undefined) {
		// The placeholder arrives selected, so the first keystroke replaces it.
		const at = markdown.pick === '' ? markdown.text.length : markdown.text.indexOf(markdown.pick);

		bodyEditor.value?.insertBlock(() => ({ text: markdown.text, caret: at, end: at + markdown.pick.length }));
	} else {
		bodyEditor.value?.insert(component);
	}

	// A slash's panel has done its job; one opened from its button stays.
	if (panelSlash.value) {
		panelOpen.value  = false;
		panelSlash.value = false;
	}
}

function chooseInline(component: ComponentDescription): void {
	bodyEditor.value?.insert(component);
}

// The icon picker's modal.
const iconsOpen = ref(false);

function openIcons(): void {
	closeOverlays();
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
}

function closeIcons(): void {
	iconsOpen.value = false;
	bodyEditor.value?.focusAt(caret.value);
}

// The media picker: inserting a file, or choosing one for a field, a
// component's option, or an image. It opens on the Library tab, or on
// Upload from the media menu's Upload a File.
type MediaKind = 'image' | 'video' | 'audio' | 'document' | 'file';

const picking = ref<{ title: string; action: string; tab?: 'library' | 'upload'; kind?: MediaKind; locked?: boolean; use: (file: MediaItem) => void } | null>(null);
const uploads = computed(() => canUpload());

function pickMedia(start: 'library' | 'upload' = 'library', kind?: 'image'): void {
	// Inside a gallery, only images go in.
	const only = kind ?? (holder.value?.only?.includes('image') === true ? 'image' : undefined);

	closeOverlays();
	picking.value = {
		title: only === 'image' ? 'Insert an Image' : 'Insert Media',
		action: 'Insert',
		tab: start,
		kind: only,
		locked: only !== undefined,
		use: insertFile
	};
}

/**
 * Puts a file in the text: an image as plain Markdown (on its own line,
 * the site makes it a figure, with a quoted title as its caption, D-267),
 * with the library's alt text and caption (D-269; selected text is its
 * alt text instead), the caret in its alt text when it has none, and
 * selected, so its panel is next; a video as a video, a sound as audio,
 * and anything else as a download.
 */
function insertFile(file: MediaItem): void {
	if (file.kind === 'image') {
		bodyEditor.value?.insertBlock((selected) => imageText(file.reference, selected || file.alt, file.caption));
		tab.value = 'element';

		return;
	}

	const name      = file.kind === 'video' ? 'video' : (file.kind === 'audio' ? 'audio' : 'file');
	const component = componentFor(name);

	if (component === undefined) {
		bodyEditor.value?.insertText(`::blush/${name}{${attributeText('src', file.reference)}}`);
	} else {
		bodyEditor.value?.insert(component, { src: file.reference });
	}

	tab.value = 'element';
}

/**
 * Uploads files dropped or pasted into the text (D-284), one at a time,
 * and inserts each where the caret is.
 */
async function uploadFiles(files: File[]): Promise<void> {
	if (!uploads.value) {
		toast('Your account can\'t upload files', { kind: 'warn' });

		return;
	}

	for (const file of files) {
		toast(`Uploading ${file.name}…`, { kind: 'info' });

		try {
			insertFile(await upload<MediaItem>('/media', file));
		} catch (caught) {
			error.value = `${file.name} wasn't uploaded. ${caught instanceof ApiError ? caught.message : 'Check your connection, then try again.'}`;
		}
	}
}

function pickForField(field: FieldDescription): void {
	// A field says which kind it takes; a featured image takes images.
	const kind = field.kind ?? (field === imageField.value ? 'image' : undefined);

	picking.value = {
		title: titleCase(`Choose ${inSentence(label(field))}`),
		action: 'Choose',
		kind,
		locked: kind !== undefined,
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
		title: titleCase(`Choose ${inSentence(label(prop))}`),
		action: 'Choose',
		kind: prop.kind,
		locked: prop.kind !== undefined,
		use: (file) => {
			const edit = withAttribute(body.value, item, prop.name, file.reference);

			if (edit !== null) {
				applyOption(edit);
			}
		}
	};
}

// An image's Replace (or Choose, without a file yet): a new address, the
// rest kept, but for alt text or a caption it didn't have.
function pickForImage(): void {
	const item = image.value;

	if (item === undefined) {
		return;
	}

	picking.value = {
		title: item.src === '' ? 'Choose an Image' : 'Replace the Image',
		action: 'Choose',
		kind: 'image',
		locked: true,
		use: (file) => {
			// The library's alt text and caption fill what the image lacks.
			applyOption(withImage(item, {
				src: file.reference,
				...(item.alt === '' && file.alt !== '' ? { alt: file.alt } : {}),
				...((item.title ?? '') === '' && file.caption !== '' ? { title: file.caption } : {})
			}));
			toast(`Chose ${mediaName(file)}`);
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
		{ id: 'editor-outline', label: 'Outline', icon: 'list', keywords: 'elements structure contents blocks', run: () => void showOutline() },
		{ id: 'editor-component', label: 'Insert a component', icon: 'plus', keywords: 'callout figure block', shortcut: '/', run: () => void togglePanel() },
		{ id: 'editor-media', label: 'Insert media', icon: 'image', keywords: 'image picture video audio file library', run: () => pickMedia() },
		...(uploads.value ? [{ id: 'editor-upload', label: 'Upload a file', icon: 'upload' as const, keywords: 'media image picture video audio add', run: () => pickMedia('upload') }] : []),
		{ id: 'editor-icon', label: 'Insert an icon', icon: 'shapes', keywords: 'symbol glyph', run: openIcons },
		{ id: 'editor-bold', label: 'Bold', icon: 'bold', keywords: 'strong format', shortcut: '⌘B', run: () => bodyEditor.value?.emphasis('strong') },
		{ id: 'editor-italic', label: 'Italic', icon: 'italic', keywords: 'emphasis format', shortcut: '⌘I', run: () => bodyEditor.value?.emphasis('em') },
		{ id: 'editor-strike', label: 'Strikethrough', icon: 'minus', keywords: 'format delete', shortcut: '⌘⇧X', run: () => bodyEditor.value?.emphasis('strike') },
		{ id: 'editor-link', label: 'Link', icon: 'link', keywords: 'url address format', shortcut: '⌘K', run: () => void openLink() },
		{ id: 'editor-unlink', label: 'Remove link', icon: 'link', keywords: 'url address unlink', shortcut: '⌘⇧K', run: () => bodyEditor.value?.unlink() },
		...[1, 2, 3, 4, 5, 6].map((level) => ({ id: `editor-heading-${level}`, label: `Heading ${level}`, icon: 'heading' as const, keywords: 'title level format', shortcut: `⌘⌥${level}`, run: () => bodyEditor.value?.heading(level) })),
		{ id: 'editor-paragraph', label: 'Paragraph', icon: 'pilcrow', keywords: 'text body format heading', shortcut: '⌘⌥0', run: () => bodyEditor.value?.heading(0) },
		{ id: 'editor-move-up', label: 'Move element up', icon: 'chevron-up', keywords: 'reorder swap block', shortcut: '⌥↑', run: () => moveElement(true) },
		{ id: 'editor-move-down', label: 'Move element down', icon: 'chevron-down', keywords: 'reorder swap block', shortcut: '⌥↓', run: () => moveElement(false) },
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
const allBlocks = computed(() => blocks(markdown.value));
const words     = computed(() => wordCount(markdown.value));
const items     = computed(() => outlineItems(body.value, markdown.value, allBlocks.value));

// An element chosen from the outline, a Content group, or the
// breadcrumb, with where the caret was put for it. It stays chosen while
// the caret does, since a list and its first item start on one line;
// moving the caret gives the choice back to `elementAt()`.
const chosen = ref<{ element: ElementRef; caret: number } | null>(null);

/**
 * What the element tab shows (admin.md §8, Every element is an object):
 * the most specific element the caret is in, or on a blank line the one
 * above it at the caret's own level; else nothing.
 */
const selection = computed<ElementRef | null>(() => {
	const choice = chosen.value;

	if (!inText.value) {
		return null;
	}

	if (choice !== null && choice.caret === caret.value) {
		return choice.element;
	}

	return elementAt(markdown.value, allBlocks.value, caret.value);
});

const directive = computed<Directive | undefined>(() => selection.value?.kind === 'directive' ? markdown.value.directives[selection.value.index] : undefined);
const image     = computed(() => selection.value?.kind === 'image' ? markdown.value.images[selection.value.index] : undefined);
const block     = computed(() => selection.value?.kind === 'block' ? allBlocks.value[selection.value.index] : undefined);

/**
 * The inserter's description of a directive's component, by its full or
 * core short name.
 */
function componentFor(name: string): ComponentDescription | undefined {
	return available.value.find((component) => component.name === name || component.name === `blush/${name}`);
}

function componentName(name: string): string {
	return componentFor(name)?.label ?? humanize(name.replace(/^.*\//, ''));
}

function componentLabel(item: Directive): string {
	return componentName(item.name);
}

const selected = computed(() => directive.value === undefined ? undefined : componentFor(directive.value.name));

// An element's name and icon: a component's, "Image", or its block's.
function nameOf(element: ElementRef): string {
	return elementName(markdown.value, allBlocks.value, element, componentName);
}

function iconOf(element: ElementRef): IconName {
	if (element.kind === 'directive') {
		const known = componentFor(markdown.value.directives[element.index]?.name ?? '');

		return known === undefined ? 'code' : componentIcon(known);
	}

	if (element.kind === 'image') {
		return 'image';
	}

	return BLOCK_KINDS[allBlocks.value[element.index]?.kind ?? 'paragraph'].icon;
}

// Whether an element was placed, not written: a container or leaf
// component, which the outline and breadcrumb name in the accent.
function placed(element: ElementRef): boolean {
	return element.kind === 'directive' && markdown.value.directives[element.index]?.kind !== 'inline';
}

// The tabs are named for what they hold: the entry's type ("Post"), and
// the element the caret is in ("Callout", "Heading 2"), else "Elements".
const typeName = computed(() => titleCase(entry.value?.index ? 'Index page' : (peopleNoun.value ?? labels.value.singular)));
const tabName  = computed(() => selection.value === null ? 'Elements' : nameOf(selection.value));
const tabIcon  = computed<IconName>(() => selection.value === null ? 'list' : iconOf(selection.value));

// Where the caret is, from the entry down (the breadcrumb): every element
// holding the selected one, outermost first.
const path = computed(() => {
	const current = selection.value;
	const span    = current === null ? undefined : (current.kind === 'directive' ? markdown.value.directives[current.index] : undefined);

	return pathTo(items.value, current, span);
});

// A holder's Content group: the elements directly inside it.
const content = computed(() => selection.value !== null && holdsContent(markdown.value, allBlocks.value, selection.value) ? childrenOf(items.value, selection.value) : null);

// What's in a container that it doesn't hold (D-314): the author can
// always type anyway, so its panel says so rather than the site's
// rendering being the first to notice.
// Each line of its body is checked, since images one to a line are one
// paragraph to Markdown.
const strays = computed<string[]>(() => {
	const only = selected.value?.only;
	const item = directive.value;

	if (!only || item === undefined) {
		return [];
	}

	const found: string[] = [];
	let depth = 0;

	for (const line of markdown.value.lines) {
		if (line.start <= item.start || line.start >= item.end) {
			continue;
		}

		// Inside an allowed component, its own lines are its business.
		if (line.kind === 'open' || line.kind === 'leaf') {
			const name = componentFor(markdown.value.directives[line.directive ?? -1]?.name ?? '')?.name;

			if (depth === 0 && (name === undefined || !only.includes(name))) {
				found.push(line.text.trim());
			}

			depth += line.kind === 'open' ? 1 : 0;
		} else if (line.kind === 'close') {
			depth = Math.max(0, depth - 1);
		} else if (depth === 0 && line.text.trim() !== '' && !(only.includes('image') && imageLine(markdown.value, line.start, line.start + line.text.length))) {
			found.push(line.text.trim());
		}
	}

	return found;
});

// How many of what it holds are in it: images, for a gallery.
const held = computed(() => {
	const item = directive.value;

	return item === undefined ? 0 : markdown.value.images.filter((image) => image.start > item.start && image.end < item.end).length;
});

// The Outline replaces the Document tab's fields on request, with a way
// back; picking from it, moving the caret, or changing tabs puts them
// back.
const listing = ref(false);

watch([caret, tab], () => {
	listing.value = false;
});

async function showOutline(): Promise<void> {
	sideOpen.value = true;
	tab.value      = 'document';
	await nextTick();
	listing.value = true;
	await nextTick();
	document.querySelector('.editor__side-inner')?.scrollTo({ top: 0 });
	document.getElementById('editor-outline-back')?.focus();
}

/**
 * Where the caret goes for an element: in a directive's head, after an
 * image, or at the start of a block's first line.
 */
function caretFor(element: ElementRef): number {
	if (element.kind === 'directive') {
		const item = markdown.value.directives[element.index];

		return item === undefined ? 0 : directiveHead(body.value, item).end;
	}

	if (element.kind === 'image') {
		return markdown.value.images[element.index]?.end ?? 0;
	}

	const found = allBlocks.value[element.index];
	const first = markdown.value.lines[found?.first ?? 0];

	return first === undefined ? 0 : first.start + first.text.length - first.text.trimStart().length;
}

/**
 * Selects an element: the caret in it, the text scrolled to it, and the
 * drawer on its options.
 */
function select(element: ElementRef): void {
	const at = caretFor(element);

	sideOpen.value = true;
	tab.value      = 'element';
	listing.value  = false;
	bodyEditor.value?.focusAt(at);
	chosen.value   = { element: { kind: element.kind, index: element.index }, caret: at };
}

// A crumb opens what it names: the root, the entry's own tab.
function openCrumb(item: OutlineItem | null): void {
	if (item === null) {
		sideOpen.value = true;
		tab.value      = 'document';
		listing.value  = false;

		return;
	}

	select(item);
}

function outlineText(item: ElementRef): string {
	return excerpt(body.value, markdown.value, allBlocks.value, item);
}

/**
 * Applies an option's edit to the body directly (so typing in the
 * settings keeps its focus), keeping the caret on the same text, and
 * the element chosen with it.
 */
function applyOption(edit: Edit): void {
	const delta = edit.text.length - (edit.to - edit.from);
	const kept  = chosen.value !== null && chosen.value.caret === caret.value ? chosen.value : null;

	body.value = body.value.slice(0, edit.from) + edit.text + body.value.slice(edit.to);

	if (caret.value >= edit.to) {
		caret.value += delta;
	} else if (caret.value > edit.from) {
		caret.value = edit.from + edit.text.length;
	}

	if (kept !== null) {
		chosen.value = { element: kept.element, caret: caret.value };
	}
}

function removeComponent(): void {
	const item = directive.value;

	if (item !== undefined) {
		bodyEditor.value?.apply(withoutDirective(body.value, item));
		toast(`Removed the ${inSentence(componentLabel(item))}`, { kind: 'danger' });
	}
}

function removeImage(): void {
	const item = image.value;

	if (item !== undefined) {
		bodyEditor.value?.apply(withoutImage(body.value, item));
		toast('Removed the image', { kind: 'danger' });
	}
}

// Whether the caret is in the text: leaving it drops the selection. A
// press on the editor's own chrome isn't leaving; the press is recorded
// as it happens, and the blur that follows asks where it landed. A Tab
// away carries no press, so where focus goes decides.
const inText = ref(false);
let pressed: EventTarget | null = null;

// The chrome: the toolbar, footer, inserter, and drawer (but its entry
// tab), and the pickers and menus, which float over the page.
const CHROME = '.editor__head, .editor__foot, .editor__inserter, .editor__side, .menu-button__list, .modal, .picker, [role="dialog"]';

function isChrome(target: EventTarget | null): boolean {
	if (!(target instanceof Element)) {
		return false;
	}

	if (target.closest('#editor-panel-document, #editor-tab-document') !== null) {
		return false;
	}

	return target.closest(CHROME) !== null;
}

function press(event: PointerEvent): void {
	pressed = event.target;
}

function textFocused(): void {
	inText.value = true;
}

function textBlurred(event: FocusEvent): void {
	const target = pressed ?? event.relatedTarget;

	pressed = null;

	if (!isChrome(target)) {
		inText.value = false;
	}
}

// What the toolbar's contextual groups act on.
const extent    = ref(0);
// A container that holds only some things (D-314, `only`): the innermost
// one the caret is in, the selected one included.
const holder = computed<ComponentDescription | undefined>(() => {
	for (const item of [...path.value].reverse()) {
		const found = item.kind === 'directive' ? componentFor(markdown.value.directives[item.index]?.name ?? '') : undefined;

		if (found?.only) {
			return found;
		}
	}

	return undefined;
});

// Nothing a container holds only some of is text, so the sentence tools
// go inside one.
const sentence  = computed(() => inText.value && inProse(markdown.value, caret.value) && holder.value === undefined);
const emphasis  = computed<Record<Emphasis, boolean>>(() => sentence.value ? emphasisAt(markdown.value, Math.min(caret.value, extent.value), Math.max(caret.value, extent.value)) : { strong: false, em: false, strike: false });

// Moving the element the caret is in among its siblings (admin.md §8,
// Reordering; D-314): a list item within its list, a paragraph within its
// callout, a top-level element among the rest. A term or definition
// moves its whole definition list, since one alone would come apart
// from what it defines.
const mover = computed<OutlineItem | undefined>(() => {
	const last   = path.value.at(-1);
	const parent = path.value.at(-2);
	const kind   = (item: OutlineItem | undefined): string | undefined => item?.kind === 'block' ? allBlocks.value[item.index]?.kind : undefined;

	return parent !== undefined && kind(parent) === 'definitions' ? parent : last;
});

const runs      = computed(() => mover.value === undefined ? [] : siblingRuns(body.value, items.value, mover.value.parent));
const moveIndex = computed(() => runIndex(runs.value, mover.value));
const moveName  = computed(() => mover.value === undefined ? '' : nameOf(mover.value));

function moveElement(up: boolean): void {
	const item   = mover.value;
	let change   = moveIndex.value === -1 ? null : movedElement(body.value, runs.value, moveIndex.value, up, { from: Math.min(caret.value, extent.value), to: Math.max(caret.value, extent.value) });

	// A numbered list counts on from the number it started at.
	if (change !== null && item?.kind === 'block' && allBlocks.value[item.index]?.kind === 'item') {
		const list  = items.value[item.parent];
		const first = list?.kind === 'block' ? markdown.value.lines[allBlocks.value[list.index]?.first ?? -1]?.text : undefined;
		const start = /^ *(\d{1,9})[.)]/.exec(first ?? '')?.[1];

		change = renumberedAt(change, change.from, start === undefined ? undefined : Number.parseInt(start, 10));
	}

	if (change !== null) {
		void bodyEditor.value?.change(change);
	}
}

// The link form (⌘K): Text and Address, filled from the selection, the
// word at the caret, or the link the caret is in.
const linkOpen   = ref(false);
const linkText   = ref('');
const linkUrl    = ref('');
const linkTarget = ref<{ start: number; end: number; link: MarkdownLink | null } | null>(null);
const linkForm   = ref<HTMLFormElement | null>(null);
const linkWrap   = ref<HTMLElement | null>(null);

async function openLink(): Promise<void> {
	if (!sentence.value) {
		return;
	}

	closeOverlays();

	const start = Math.min(caret.value, extent.value);
	const end   = Math.max(caret.value, extent.value);
	const link  = linkAt(body.value, start, end);

	if (link !== null) {
		linkTarget.value = { start: link.start, end: link.end, link };
		linkText.value   = linkLabel(link.label);
		linkUrl.value    = link.url;
	} else {
		const word     = start === end ? wordAt(body.value, start) : null;
		const from     = word?.start ?? start;
		const to       = word?.end ?? end;
		const selected = body.value.slice(from, to);
		const address  = /^(?:https?:\/\/|mailto:|\/)\S*$/.test(selected.trim()) && selected.trim() !== '';

		linkTarget.value = { start: from, end: to, link: null };
		linkText.value   = address ? '' : selected;
		linkUrl.value    = address ? selected.trim() : '';
	}

	linkOpen.value = true;
	await nextTick();

	// The first empty field: the address, when there's text to hang it on.
	const fields = [...(linkForm.value?.querySelectorAll<HTMLInputElement>('input') ?? [])];

	(fields.find((field) => field.value === '') ?? fields[0])?.focus();
}

function closeLink(refocus = true): void {
	if (!linkOpen.value) {
		return;
	}

	linkOpen.value = false;

	if (refocus) {
		const target = linkTarget.value;

		bodyEditor.value?.focusAt(target?.end ?? caret.value);
	}
}

function applyLink(): void {
	const target = linkTarget.value;

	if (target === null || linkUrl.value.trim() === '') {
		return;
	}

	linkOpen.value = false;
	bodyEditor.value?.change(withLink(body.value, target.start, target.end, linkText.value.trim() === '' ? linkUrl.value.trim() : linkText.value, linkUrl.value, target.link));
}

function removeLink(): void {
	const link = linkTarget.value?.link ?? null;

	linkOpen.value = false;

	if (link !== null) {
		bodyEditor.value?.change(withoutLink(body.value, link));
	}
}

function toggleLink(): void {
	if (linkOpen.value) {
		closeLink();
	} else {
		void openLink();
	}
}

function linkKey(event: KeyboardEvent): void {
	if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		closeLink();
	}
}

// A press outside the link form closes it.
function outsideLink(event: PointerEvent): void {
	if (linkOpen.value && event.target instanceof Node && linkWrap.value?.contains(event.target) !== true) {
		closeLink(false);
	}
}

/**
 * One thing is open at a time (admin.md §8, The toolbar): opening any of
 * the toolbar's panels, menus, or modals closes the others. Menus close
 * themselves on the press that opens something else.
 */
function closeOverlays(): void {
	closePanel(false);
	closeLink(false);
}

// Bleed (admin.md §8, Bleed; D-313): how far a top-level element reaches
// past the text column. Base is no class at all; the theme names the
// other two.
type BleedName = 'base' | 'wide' | 'full';

const BLEEDS: Record<BleedName, { label: string; icon: IconName; text: string }> = {
	base: { label: 'Base', icon: 'bleed-base', text: 'At the width of the text column. It writes no class at all.' },
	wide: { label: 'Wide', icon: 'bleed-wide', text: 'Wider than the text, as much of the margin as the theme gives.' },
	full: { label: 'Full', icon: 'bleed-full', text: 'Edge to edge, ignoring the text\'s width.' }
};

// The element bleed acts on: a top-level element, not an inline one.
const bleedTarget = computed<ElementRef | null>(() => {
	const current = selection.value;
	const item    = current === null ? undefined : items.value.find((entry) => sameElement(entry, current));

	return item === undefined || item.depth !== 0 ? null : current;
});

// An element's classes and id, wherever its syntax keeps them.
function partsOf(element: ElementRef): { classes: string[]; id: string } {
	if (element.kind === 'directive') {
		const item = markdown.value.directives[element.index];

		return attributeParts(item === undefined ? '' : (directiveHead(body.value, item).attributes?.text ?? ''));
	}

	if (element.kind === 'image') {
		return attributeParts(markdown.value.images[element.index]?.attributes?.text ?? '');
	}

	return attributeParts(allBlocks.value[element.index]?.attributes?.text ?? '');
}

const bleed = computed<BleedName>(() => {
	const target = bleedTarget.value;
	const names  = target === null ? [] : partsOf(target).classes;

	return names.includes(bleeds.value.full) ? 'full' : (names.includes(bleeds.value.wide) ? 'wide' : 'base');
});

/**
 * Sets the bleed: the other width's class goes, the chosen one is first,
 * and every other class and attribute stays. An emptied attribute block
 * goes, with a list's line.
 */
function setBleed(name: BleedName): void {
	const target = bleedTarget.value;

	if (target === null) {
		return;
	}

	const { classes, id } = partsOf(target);
	const kept = classes.filter((item) => item !== bleeds.value.wide && item !== bleeds.value.full);
	const next = name === 'base' ? kept : [bleeds.value[name], ...kept];
	let edit: Edit | null = null;

	if (target.kind === 'directive') {
		const item = markdown.value.directives[target.index];

		edit = item === undefined ? null : withDirectiveParts(body.value, item, next, id);
	} else if (target.kind === 'image') {
		const item = markdown.value.images[target.index];

		edit = item === undefined ? null : withImage(item, { attributes: withParts(item.attributes?.text ?? '', next, id) });
	} else {
		const item = allBlocks.value[target.index];

		edit = item === undefined ? null : withBlockParts(body.value, markdown.value, item, next, id);
	}

	if (edit !== null) {
		const at    = caret.value;
		const delta = edit.text.length - (edit.to - edit.from);

		bodyEditor.value?.apply(edit, at <= edit.from ? at : (at >= edit.to ? at + delta : edit.from));
	}
}

/**
 * Opens or closes the drawer. It opens on what the caret is in, or on
 * the entry's tab with nothing selected, decided on every open.
 */
function toggleSide(): void {
	sideOpen.value = !sideOpen.value;

	if (sideOpen.value) {
		tab.value = selection.value === null ? 'document' : 'element';
	}
}

watch(sideOpen, keepDrawer);

// A live entry's full address, to share.
async function copyLink(): Promise<void> {
	const url = entry.value?.url ?? null;

	if (url === null) {
		return;
	}

	try {
		await navigator.clipboard.writeText(new URL(url, config.site.url).href);
		toast('Link copied');
	} catch {
		toast("The link couldn't be copied", { kind: 'warn' });
	}
}

/**
 * Copies the entry as a draft beside it (D-275), as it was last saved.
 */
async function duplicate(): Promise<void> {
	const detail = entry.value;

	if (detail === null || detail.id === null) {
		return;
	}

	try {
		const copy = await request<EntryDetail>('POST', `${entryPath(detail.id)}/duplicate`);

		toast(`Duplicated as a draft: “${copy.title || 'Untitled'}”`);
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : `The ${noun.value} couldn't be duplicated.`;
	}
}

// Arrow keys move between the two tabs.
function tabKey(event: KeyboardEvent): void {
	if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
		return;
	}

	event.preventDefault();

	const order: (typeof tab.value)[] = ['document', 'element'];
	const at    = order.indexOf(tab.value);
	const next  = order[(at + (event.key === 'ArrowRight' ? 1 : order.length - 1)) % order.length] ?? 'document';

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

		// Like Enter at the end of a paragraph: a new, empty paragraph
		// above the body's content, if there's any (or an empty line
		// at the top already), with the caret in it.
		if (body.value.trim() === '' || body.value.startsWith('\n')) {
			bodyEditor.value?.focusAt(0);
		} else {
			bodyEditor.value?.apply({ from: 0, to: 0, text: '\n\n' }, 0);
		}
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

/**
 * Leaving the page (closing the tab, reloading, another address) gets no
 * warning (D-374): the browser's own can't be drawn as the admin's, and
 * nothing is lost, since unsaved changes are kept in this browser and
 * offered back. What was typed in the last moment is kept as the page
 * goes.
 */
function pageHide(): void {
	if (dirty.value) {
		keepNow();
	}
}

/**
 * The reload shortcuts (⌘R, Ctrl+R, F5) are the one way off the page
 * the admin sees first, so with unsaved changes it asks in its own modal
 * instead of reloading straight away (D-374).
 */
async function reloadKey(event: KeyboardEvent): Promise<void> {
	const reload = event.key === 'F5' || ((event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === 'r');

	if (!reload || !dirty.value) {
		return;
	}

	event.preventDefault();
	keepNow();

	if (await confirmAction({
		title: 'Reload Without Saving?',
		body: [`Your unsaved changes to this ${noun.value} stay in this browser only, and are offered back when the editor opens again.`],
		confirm: 'Reload',
		cancel: 'Stay'
	})) {
		window.location.reload();
	}
}

// Leaving for another admin screen keeps unsaved changes in this
// browser, as leaving the page does (D-375), so asking says where they go.
onBeforeRouteLeave(async () => {
	if (!dirty.value) {
		return true;
	}

	keepNow();

	return confirmAction({
		title: 'Leave Without Saving?',
		body: [`Your unsaved changes to this ${noun.value} stay in this browser only, and are offered back when you open it here again.`],
		confirm: 'Leave',
		cancel: 'Stay'
	});
});

onMounted(() => {
	document.addEventListener('keydown', keydown);
	document.addEventListener('pointerdown', press, true);
	document.addEventListener('pointerdown', outsideLink);
	window.addEventListener('pagehide', pageHide);
	window.addEventListener('keydown', reloadKey, true);
});

onBeforeUnmount(() => {
	clearTimeout(keeping);
	focusMode.value = false;
	document.removeEventListener('keydown', keydown);
	document.removeEventListener('pointerdown', press, true);
	document.removeEventListener('pointerdown', outsideLink);
	window.removeEventListener('pagehide', pageHide);
	window.removeEventListener('keydown', reloadKey, true);
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
	<section class="editor" :class="{ 'is-side-open': sideOpen, 'is-focus': focusMode }" aria-labelledby="editor-heading">
		<h1 id="editor-heading" class="visually-hidden" tabindex="-1">{{ editTitle }}</h1>

		<header class="editor__head">
			<template v-if="entry">
				<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': panelOpen }" title="Components ( / )" aria-controls="editor-components" :aria-expanded="panelOpen" @click="togglePanel">
					<AdminIcon name="plus" />
					<span class="visually-hidden">Insert a component</span>
				</button>
				<MenuButton v-if="uploads" button-class="button button--ghost editor__tool editor__wide" label="Insert media" align="start" floating @open="closeOverlays">
					<template #button>
						<AdminIcon name="image" /><AdminIcon name="chevron-down" class="editor__caret" />
					</template>
					<button type="button" class="menu-item menu-item--described" @click="pickMedia('library')">
						<AdminIcon name="image" />
						<span>
							<span class="menu-item__name">Media Library</span>
							<span class="menu-item__text">Choose a file already in the library.</span>
						</span>
					</button>
					<button type="button" class="menu-item menu-item--described" @click="pickMedia('upload')">
						<AdminIcon name="upload" />
						<span>
							<span class="menu-item__name">Upload a File</span>
							<span class="menu-item__text">Add one from this computer, then place it.</span>
						</span>
					</button>
				</MenuButton>
				<button v-else type="button" class="button button--ghost button--icon editor__tool" title="Media" aria-haspopup="dialog" @click="pickMedia()">
					<AdminIcon name="image" />
					<span class="visually-hidden">Insert media</span>
				</button>

				<template v-if="moveIndex !== -1">
					<span class="editor__divider" aria-hidden="true" />
					<span class="editor__spin" role="group" :aria-label="`Move the ${moveName.toLowerCase()}`">
						<button type="button" class="editor__spin-button" :disabled="moveIndex === 0" :title="`Move the ${moveName.toLowerCase()} up  ⌥↑`" @mousedown.prevent @click="moveElement(true)">
							<AdminIcon name="chevron-up" />
							<span class="visually-hidden">Move up</span>
						</button>
						<button type="button" class="editor__spin-button" :disabled="moveIndex === runs.length - 1" :title="`Move the ${moveName.toLowerCase()} down  ⌥↓`" @mousedown.prevent @click="moveElement(false)">
							<AdminIcon name="chevron-down" />
							<span class="visually-hidden">Move down</span>
						</button>
					</span>
				</template>

				<template v-if="sentence">
					<span class="editor__divider" aria-hidden="true" />
					<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': emphasis.strong }" :aria-pressed="emphasis.strong" title="Bold  ⌘B" @mousedown.prevent @click="bodyEditor?.emphasis('strong')">
						<AdminIcon name="bold" />
						<span class="visually-hidden">Bold</span>
					</button>
					<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': emphasis.em }" :aria-pressed="emphasis.em" title="Italic  ⌘I" @mousedown.prevent @click="bodyEditor?.emphasis('em')">
						<AdminIcon name="italic" />
						<span class="visually-hidden">Italic</span>
					</button>
					<span ref="linkWrap" class="editor__pop">
						<button type="button" class="button button--ghost editor__tool editor__wide" :class="{ 'is-on': linkOpen }" title="Link  ⌘K" aria-haspopup="dialog" :aria-expanded="linkOpen" aria-controls="editor-link" @click="toggleLink">
							<AdminIcon name="link" /><AdminIcon name="chevron-down" class="editor__caret" />
							<span class="visually-hidden">Link</span>
						</button>
						<form v-if="linkOpen" id="editor-link" ref="linkForm" class="editor__link" role="dialog" aria-label="Link" @submit.prevent="applyLink" @keydown="linkKey">
							<div class="field">
								<label for="editor-link-text">Text</label>
								<input id="editor-link-text" v-model="linkText" type="text" autocomplete="off">
							</div>
							<div class="field">
								<label for="editor-link-url">Address</label>
								<input id="editor-link-url" v-model="linkUrl" type="text" class="mono" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="https://">
							</div>
							<p class="editor__link-buttons">
								<button v-if="linkTarget?.link" type="button" class="button button--small button--ghost" @click="removeLink">Remove</button>
								<button type="submit" class="button button--small button--primary" :disabled="linkUrl.trim() === ''">{{ linkTarget?.link ? 'Update' : 'Add link' }}</button>
							</p>
						</form>
					</span>
					<button type="button" class="button button--ghost button--icon editor__tool" title="Icon" aria-haspopup="dialog" @click="openIcons">
						<AdminIcon name="shapes" />
						<span class="visually-hidden">Insert an icon</span>
					</button>
					<MenuButton v-if="inlineComponents.length" button-class="button button--ghost editor__tool editor__wide" label="Insert an inline component" align="start" floating @open="closeOverlays">
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

				<template v-if="bleedTarget">
					<span class="editor__divider" aria-hidden="true" />
					<MenuButton :button-class="`button button--ghost editor__tool editor__wide${bleed === 'base' ? '' : ' is-on'}`" :label="`Bleed: ${BLEEDS[bleed].label}`" align="start" floating @open="closeOverlays">
						<template #button>
							<AdminIcon :name="BLEEDS[bleed].icon" /><AdminIcon name="chevron-down" class="editor__caret" />
						</template>
						<button v-for="(option, key) in BLEEDS" :key="key" type="button" class="menu-item menu-item--described" :aria-current="key === bleed ? 'true' : undefined" @click="setBleed(key)">
							<AdminIcon :name="option.icon" />
							<span>
								<span class="menu-item__name">{{ option.label }}</span>
								<span class="menu-item__text">{{ option.text }}</span>
							</span>
						</button>
					</MenuButton>
				</template>
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
				<button v-if="primary" type="button" class="button button--primary button--small" :disabled="blocked || idle(primary)" @click="save(primary.status)">{{ primary.label }}</button>
				<MenuButton button-class="button button--ghost button--icon" label="More actions">
					<template #button>
						<AdminIcon name="ellipsis-vertical" />
					</template>
					<p class="menu-heading">View</p>
					<button type="button" class="menu-item" @click="toggleSide">
						<AdminIcon name="panel-right" />{{ sideOpen ? 'Hide the settings panel' : 'Settings panel' }}<kbd class="menu-kbd">⌘/</kbd>
					</button>
					<button type="button" class="menu-item" @click="showOutline">
						<AdminIcon name="list" />Outline
					</button>
					<button type="button" class="menu-item" @click="toggleFocus">
						<AdminIcon name="maximize-2" />{{ focusMode ? 'Leave focus mode' : 'Focus mode' }}<kbd class="menu-kbd">⌘⇧F</kbd>
					</button>
					<button v-if="entry.id !== null && !(entry.url && entry.status === 'published')" type="button" class="menu-item" @click="preview">
						<AdminIcon name="eye" />Preview
					</button>
					<a v-if="entry.url && entry.status === 'published'" class="menu-item" :href="entry.url" target="_blank" rel="noopener">
						<AdminIcon name="external-link" />View<span class="visually-hidden"> the live {{ noun }} (new tab)</span>
					</a>
					<p class="menu-heading">Entry</p>
					<button v-if="secondary" type="button" class="menu-item" :disabled="blocked || idle(secondary)" @click="save(secondary.status)">
						<AdminIcon name="file-text" />{{ secondary.label }}<kbd v-if="!secondary.status" class="menu-kbd">⌘S</kbd>
					</button>
					<button v-if="entry.url && entry.status === 'published'" type="button" class="menu-item" @click="copyLink">
						<AdminIcon name="link" />Copy link
					</button>
					<button v-if="entry.can.duplicate && entry.type.kind !== 'taxonomy'" type="button" class="menu-item" @click="duplicate">
						<AdminIcon name="copy" />Duplicate
					</button>
					<template v-if="entry.can.delete">
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" @click="trash">
							<AdminIcon name="trash-2" />Move to trash
						</button>
					</template>
				</MenuButton>
			</template>
		</header>

		<div v-if="error" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" /><p>{{ error }}</p>
		</div>

		<div v-if="offer" class="editor__notice" role="status">
			<AdminIcon name="triangle-alert" />
			<p>Your unsaved changes to this {{ noun }} from {{ formatDate(offer.kept) }} were kept in this browser when you left. Restore them to carry on where you were.</p>
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
					:components="panelComponents"
					:note="panelNote"
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
						/>
						<p v-if="errorFor('title')" id="editor-title-error" class="field__error">{{ errorFor('title') }}</p>

						<MarkdownEditor
							id="editor-body"
							ref="bodyEditor"
							class="editor__body-text"
							v-model="body"
							v-model:caret="caret"
							label="Body (Markdown)"
							placeholder="Write in Markdown. Type / on an empty line, or use +, to add a block."
							v-model:extent="extent"
							:parsed="markdown"
							:blocks="allBlocks"
							:slash-open="panelOpen && panelSlash"
							:directive="selection?.kind === 'directive' ? selection.index : -1"
							:image="selection?.kind === 'image' ? selection.index : -1"
							@slash="slashed"
							@slash-key="slashKey"
							@files="uploadFiles"
							@link="openLink"
							@move="moveElement"
							@focusin="textFocused"
							@focusout="textBlurred"
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
					<nav class="editor__crumbs" aria-label="Where the cursor is">
						<ol>
							<li>
								<button type="button" class="editor__crumb editor__crumb--root" :aria-current="path.length === 0 ? 'true' : undefined" @click="openCrumb(null)">
									<TypeIcon v-if="entryType" :type="entryType" />{{ typeName }}
								</button>
							</li>
							<li v-for="(item, index) in path" :key="`${item.kind}-${item.index}`">
								<AdminIcon name="chevron-right" class="editor__crumb-sep" />
								<button type="button" class="editor__crumb" :class="{ 'is-placed': placed(item), 'is-here': index === path.length - 1 }" :aria-current="index === path.length - 1 ? 'true' : undefined" @click="openCrumb(item)">{{ nameOf(item) }}</button>
							</li>
						</ol>
					</nav>
					<span class="editor__foot-end">
						<button v-if="focusMode" type="button" class="editor__chip" @click="focusMode = false">
							<AdminIcon name="x" />Leave focus mode
						</button>
						<span>{{ count(words, 'word') }}</span>
						<span class="editor__sep" aria-hidden="true">·</span>
						<span>{{ Math.max(1, Math.round(words / 220)) }} min read</span>
					</span>
				</footer>
			</div>

			<aside id="editor-settings" class="editor__side" aria-label="Settings" :inert="!sideOpen">
				<div class="editor__side-inner">
					<div class="editor__tabs">
						<div class="editor__tablist" role="tablist" aria-label="Settings" @keydown="tabKey">
							<button id="editor-tab-document" type="button" class="editor__tab" role="tab" aria-controls="editor-panel-document" :aria-selected="tab === 'document'" :tabindex="tab === 'document' ? 0 : -1" @click="tab = 'document'">
								<TypeIcon v-if="entryType" :type="entryType" /><AdminIcon v-else name="file-text" />{{ typeName }}
							</button>
							<button id="editor-tab-element" type="button" class="editor__tab" role="tab" aria-controls="editor-panel-element" :aria-selected="tab === 'element'" :tabindex="tab === 'element' ? 0 : -1" @click="tab = 'element'">
								<AdminIcon :name="tabIcon" /><span class="editor__tab-name">{{ tabName }}</span>
							</button>
						</div>
						<button type="button" class="button button--ghost button--icon editor__side-close" @click="sideOpen = false">
							<AdminIcon name="x" />
							<span class="visually-hidden">Close the settings</span>
						</button>
					</div>

					<div v-if="entry" v-show="tab === 'document'" id="editor-panel-document" role="tabpanel" aria-labelledby="editor-tab-document">
						<template v-if="!listing">
							<div class="editor__group">
								<p class="editor__group-heading">Publish</p>
								<dl class="settings">
									<div class="settings__row">
										<dt>Status</dt>
										<dd>
											<MenuButton v-if="statusOptions.length > 1" :button-class="`settings__value settings__value--${entry.status}`" :label="`Status: ${statusNames[entry.status].label}`" align="start" floating>
												<template #button>
													<AdminIcon :name="statusNames[entry.status].icon" /><span class="settings__text">{{ statusNames[entry.status].label }}</span><AdminIcon name="chevron-down" class="settings__caret" />
												</template>
												<button v-for="option in statusOptions" :key="option.status" type="button" class="menu-item menu-item--described" :aria-current="option.status === entry.status ? 'true' : undefined" :disabled="option.status !== entry.status && blocked" @click="option.status === entry.status || save(option.status)">
													<AdminIcon :name="option.icon" />
													<span>
														<span class="menu-item__name">{{ option.label }}</span>
														<span class="menu-item__text">{{ option.text }}</span>
													</span>
												</button>
											</MenuButton>
											<span v-else class="settings__static" :class="`settings__value--${entry.status}`"><AdminIcon :name="statusNames[entry.status].icon" />{{ statusNames[entry.status].label }}</span>
										</dd>
									</div>
									<div v-if="visibilityField" class="settings__row">
										<dt>Visibility</dt>
										<dd>
											<MenuButton button-class="settings__value" :label="`Visibility: ${visibilityNames[visibility].label}`" align="start" floating>
												<template #button>
													<AdminIcon :name="visibilityNames[visibility].icon" /><span class="settings__text">{{ visibilityNames[visibility].label }}</span><AdminIcon name="chevron-down" class="settings__caret" />
												</template>
												<button v-for="(option, key) in visibilityNames" :key="key" type="button" class="menu-item menu-item--described" :aria-current="key === visibility ? 'true' : undefined" @click="setVisibility(key)">
													<AdminIcon :name="option.icon" />
													<span>
														<span class="menu-item__name">{{ option.label }}</span>
														<span class="menu-item__text">{{ option.text }}</span>
													</span>
												</button>
											</MenuButton>
										</dd>
									</div>
									<div v-if="dateField" class="settings__row">
										<dt><label for="editor-date">{{ future ? 'Goes live' : 'Date' }}</label></dt>
										<dd>
											<DatePicker id="editor-date" v-model="date" :invalid="Boolean(errorFor('published'))" :described-by="errorFor('published') ? 'editor-publish-help editor-date-error' : 'editor-publish-help'" />
										</dd>
									</div>
									<div v-if="entry.can.rename" class="settings__row">
										<dt><label for="editor-slug">Slug</label></dt>
										<dd>
											<input id="editor-slug" v-model="slug" class="settings__input mono" :placeholder="fresh ? slugOf(title) : undefined" autocomplete="off" autocapitalize="none" spellcheck="false" title="Lowercase letters, numbers, and hyphens" :aria-invalid="slugError ? 'true' : undefined" :aria-describedby="slugError ? 'editor-slug-help editor-slug-error' : 'editor-slug-help'">
										</dd>
									</div>
									<div v-if="treeParent" class="settings__row">
										<dt><label for="editor-parent">Parent</label></dt>
										<dd>
											<AdminSelect id="editor-parent" v-model="parent" :options="parentOptions" :disabled="parentItems === null" :invalid="Boolean(parentError)" :described-by="parentError ? 'editor-parent-error' : undefined" plain />
										</dd>
									</div>
									<div v-if="parentField" class="settings__row">
										<dt><label :for="`field-${parentField.name}`">Parent</label></dt>
										<dd>
											<ReferencePicker :id="`field-${parentField.name}`" :key="fieldKey(parentField)" :field="parentField" :self="entry.slug" plain :model-value="String(form[parentField.name] ?? '')" @update:model-value="form[parentField.name] = $event" />
										</dd>
									</div>
									<div v-if="positionField" class="settings__row">
										<dt><label for="editor-position">Position</label></dt>
										<dd>
											<input id="editor-position" class="settings__input mono" type="number" step="1" inputmode="numeric" placeholder="By title" :value="String(form[positionField.name] ?? '')" aria-describedby="editor-position-help" @input="form[positionField.name] = ($event.target as HTMLInputElement).value">
										</dd>
									</div>
								</dl>
								<p id="editor-publish-help" class="editor__group-note">{{ publishNote }}</p>
								<p v-if="errorFor('published')" id="editor-date-error" class="field__error">{{ errorFor('published') }}</p>
								<p v-if="slugError" id="editor-slug-error" class="field__error">{{ slugError }}</p>
								<p v-if="parentError" id="editor-parent-error" class="field__error">{{ parentError }}</p>
								<p v-if="positionField" id="editor-position-help" class="visually-hidden">Its place among the {{ labels.plural.toLowerCase() }} beside it, lowest first. Without one, it follows those with one, by title.</p>
								<template v-if="entry.status === 'published' && ((entry.can.rename && slug.trim() !== entry.slug) || moving)">
									<p id="editor-slug-help" class="editor__group-note">Saving moves it to <span class="mono">{{ slugAddress ?? 'a new address' }}</span>{{ redirect ? '.' : ', and links to the old address will stop working.' }}</p>
									<label class="checkbox">
										<input v-model="redirect" type="checkbox">
										Redirect the old address here
									</label>
								</template>
								<p v-else id="editor-slug-help" class="visually-hidden">The slug is lowercase letters, numbers, and hyphens, and the address ends in it.</p>
								<p v-if="entry.errorPage !== null" class="editor__group-note">The page the site shows for error {{ entry.errorPage }}{{ entry.errorPage === 404 ? ', when an address doesn\'t exist' : '' }}: its title and text, in the theme's error layout. Its slug is the status. Without it, the theme's own message is shown.</p>
								<p v-if="entry.index" class="editor__group-note">The index page for <strong>{{ labels.plural }}</strong>, where readers find all of them. There's only one, so it can't be moved to the trash.<template v-if="entry.homepage"> It's also the site's homepage, at /.</template></p>
								<p v-if="entry.rootPage && entry.homepage" class="editor__group-note">The site's homepage, at /. It's the top of the page tree, so it has no parent, slug, or position. Without it, the site shows a welcome page.</p>
								<p v-else-if="entry.rootPage" class="editor__group-note">
									The page at <code>user/content/index.md</code>. The homepage shows {{ entry.homeInstead?.toLowerCase() ?? 'something else' }} instead, so this page isn't on the site.
									<template v-if="entry.can.makeHomepage">{{ ' ' }}<button type="button" class="lnk editor__note-action" @click="toHomepage">Make homepage</button></template>
								</p>
								<p v-if="entry.peoplePage && entry.peoplePage.profile === null" class="editor__group-note">The page introducing the {{ entry.peoplePage.label.toLowerCase() }} of <strong>{{ labels.plural }}</strong>: its title heads their list, and its body comes before it. It has no address of its own.</p>
								<p v-else-if="entry.peoplePage" class="editor__group-note">The page introducing <RouterLink :to="{ name: 'profile-detail', params: { slug: entry.peoplePage.profile } }">{{ entry.peoplePage.profileTitle }}</RouterLink>'s archive as one of the {{ entry.peoplePage.label.toLowerCase() }} of <strong>{{ labels.plural }}</strong>, in place of their bio there. It has no address of its own.</p>
							</div>

							<div v-if="imageField && (!term || form[imageField.name])" class="editor__group">
								<p class="editor__group-heading">Featured Image</p>
								<ImagePreview :src="String(form[imageField.name] ?? '')" wide noun="the featured image" @pick="pickForField(imageField)" @remove="form[imageField.name] = ''" />
								<p v-if="!form[imageField.name]" class="field__help">Used in listings, link previews, and at the top of the {{ noun }}, as the theme shows it.</p>
							</div>

							<template v-for="peopleField in peopleFields" :key="fieldKey(peopleField)">
								<div v-if="!term || referenceCount(peopleField)" class="editor__group">
									<p class="editor__group-heading">{{ titleCase(peopleField.label ?? (peopleField.multiple === false ? 'Author' : 'Authors')) }}<span v-if="referenceCount(peopleField) > 1" class="editor__group-hint">{{ plural(referenceCount(peopleField), 'person', 'people') }}</span></p>
									<ReferencePicker :id="`field-${peopleField.name}`" :field="peopleField" people :keep-last="peopleFields[0] === peopleField || peopleField.required === true" :model-value="String(form[peopleField.name] ?? '')" :invalid="Boolean(errorFor(peopleField.name))" @update:model-value="form[peopleField.name] = $event" />
									<p v-if="errorFor(peopleField.name)" class="field__error">{{ errorFor(peopleField.name) }}</p>
								</div>
							</template>

							<div v-for="field in referenceFields" :key="fieldKey(field)" class="editor__group">
								<p class="editor__group-heading"><label :for="`field-${field.name}`">{{ titleCase(field.label ?? labelsOf(field.to ?? '').plural) }}</label><span v-if="referenceCount(field)" class="editor__group-hint">{{ referenceCount(field).toLocaleString() }} selected</span></p>
								<ReferencePicker :id="`field-${field.name}`" :field="field" :model-value="String(form[field.name] ?? '')" :invalid="Boolean(errorFor(field.name))" @update:model-value="form[field.name] = $event" />
								<p v-if="errorFor(field.name)" class="field__error">{{ errorFor(field.name) }}</p>
							</div>

							<div v-if="summaryField" class="editor__group">
								<p class="editor__group-heading"><label :for="`field-${summaryField.name}`">Summary</label><span v-if="String(form[summaryField.name] ?? '').length" class="editor__group-hint">{{ String(form[summaryField.name] ?? '').length }} / 160</span></p>
								<div class="field">
									<textarea :id="`field-${summaryField.name}`" :value="String(form[summaryField.name] ?? '')" rows="3" placeholder="Used in listings and feeds. Left empty, the opening words are used." :aria-invalid="errorFor(summaryField.name) ? 'true' : undefined" @input="form[summaryField.name] = ($event.target as HTMLTextAreaElement).value" />
									<p v-if="errorFor(summaryField.name)" class="field__error">{{ errorFor(summaryField.name) }}</p>
								</div>
							</div>

							<div v-if="otherFields.length" class="editor__group">
								<p class="editor__group-heading">{{ labels.singular }} Fields</p>
								<FieldControl v-for="field in otherFields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" pickable @update:model-value="form[field.name] = $event" @pick="pickForField(field)" />
							</div>

							<div v-for="set in setGroups" :key="set.name" class="editor__group">
								<p class="editor__group-heading">{{ set.label }}</p>
								<p v-if="set.description" class="editor__group-note">{{ set.description }}</p>
								<FieldControl v-for="field in set.fields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" pickable @update:model-value="form[field.name] = $event" @pick="pickForField(field)" />
							</div>

							<div v-if="Object.keys(entry.extra).length" class="editor__group">
								<p class="editor__group-heading">Other Front Matter <span class="editor__group-hint">Kept as it is</span></p>
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

						<button type="button" class="editor__list-link" @click="showOutline">
							<AdminIcon name="list" />
							<span>Outline</span>
							<span class="editor__list-count mono">{{ items.length }}</span>
							<AdminIcon name="chevron-right" class="editor__list-go" />
						</button>
					</template>

					<template v-else>
						<button id="editor-outline-back" type="button" class="editor__list-link editor__list-link--back" @click="listing = false">
							<AdminIcon name="arrow-left" />
							<span class="editor__back-path">{{ typeName }} <span aria-hidden="true">/</span><span class="visually-hidden">:</span> <strong>Outline</strong></span>
							<span class="editor__list-count mono">{{ items.length }}</span>
						</button>

						<div class="editor__group">
							<p v-if="!items.length" class="field__help">Nothing yet. Start writing, or use the insert buttons in the header.</p>
							<ul v-else class="editor__outline">
								<li v-for="item in items" :key="`${item.kind}-${item.index}`">
									<button type="button" class="editor__row" :class="{ 'is-current': sameElement(item, selection), 'is-placed': placed(item) }" :style="{ '--depth': item.depth }" :aria-current="sameElement(item, selection) ? 'true' : undefined" @click="select(item)">
										<AdminIcon :name="iconOf(item)" />
										<span class="editor__row-name">{{ nameOf(item) }}</span>
										<span class="editor__row-text">{{ outlineText(item) }}</span>
									</button>
								</li>
							</ul>
						</div>
					</template>
					</div>

					<div v-show="tab === 'element'" id="editor-panel-element" role="tabpanel" aria-labelledby="editor-tab-element">
						<template v-if="selection">
							<ComponentOptions
								v-if="directive"
								:source="body"
								:directive="directive"
								:component="selected"
								@edit="applyOption"
								@remove="removeComponent"
								@pick="pickForOption"
							>
								<div v-if="content" class="options__group">
									<p class="options__heading">Content<span v-if="selected?.only?.includes('image')" class="editor__group-hint">{{ plural(held, 'image') }}</span></p>
									<p v-if="!content.length" class="field__help">Nothing inside it yet.</p>
									<ul v-else class="editor__outline">
										<li v-for="item in content" :key="`${item.kind}-${item.index}`">
											<button type="button" class="editor__row" :class="{ 'is-placed': placed(item) }" @click="select(item)">
												<AdminIcon :name="iconOf(item)" />
												<span class="editor__row-name">{{ nameOf(item) }}</span>
												<span class="editor__row-text">{{ outlineText(item) }}</span>
											</button>
										</li>
									</ul>
									<p v-if="strays.length" class="field__error">{{ titleCase(selected?.label ?? '') }} holds only {{ panelNoteNames }}, so the site may not show {{ strays.length === 1 ? 'this line' : 'these lines' }}: {{ strays.map((text) => `“${text.length > 40 ? `${text.slice(0, 40)}…` : text}”`).join(', ') }}.</p>
								</div>
							</ComponentOptions>
							<ImageOptions
								v-else-if="image"
								:key="`image-${image.start}`"
								:source="body"
								:image="image"
								:variants="imageStyles"
								@edit="applyOption"
								@remove="removeImage"
								@pick="pickForImage"
							/>
							<BlockOptions
								v-else-if="block"
								:key="`block-${block.kind}-${block.start}`"
								:source="body"
								:markdown="markdown"
								:blocks="allBlocks"
								:block="block"
								@edit="applyOption"
							>
								<div v-if="content" class="options__group">
									<p class="options__heading">Content</p>
									<p v-if="!content.length" class="field__help">Nothing inside it yet.</p>
									<ul v-else class="editor__outline">
										<li v-for="item in content" :key="`${item.kind}-${item.index}`">
											<button type="button" class="editor__row" :class="{ 'is-placed': placed(item) }" @click="select(item)">
												<AdminIcon :name="iconOf(item)" />
												<span class="editor__row-name">{{ nameOf(item) }}</span>
												<span class="editor__row-text">{{ outlineText(item) }}</span>
											</button>
										</li>
									</ul>
									<p v-if="content.length" class="field__help">{{ plural(content.length, 'element') }} directly inside. What's nested deeper is listed under the one holding it.</p>
								</div>
							</BlockOptions>
						</template>

						<div v-else class="editor__none">
							<AdminIcon name="list" />
							<p class="editor__none-heading">Nothing Selected</p>
							<p class="editor__none-text">The cursor isn't in the text. Click anywhere in it, and this panel follows what it's in.</p>
						</div>
					</div>
				</div>
			</aside>
		</div>

		<IconPicker v-if="iconsOpen" :preview="iconPreview" @choose="chooseIcon" @close="closeIcons" />
		<MediaPicker v-if="picking" :title="picking.title" :action="picking.action" :tab="picking.tab" :kind="picking.kind" :locked="picking.locked" @choose="picked" @close="picking = null" />
	</section>
</template>

<style scoped>
/*
 * Writing first: one centered column, and settings on demand rather
 * than always beside the text (admin.md §8).
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

.editor__head :deep(.is-on) {
	background: var(--accent-soft);
	color: var(--accent);
}

/* Moving an element: two stacked chevrons, one control. */
.editor__spin {
	display: inline-flex;
	flex: none;
	flex-direction: column;
	gap: 0;
}

.editor__spin-button {
	display: grid;
	place-items: center;
	width: 28px;
	height: 17px;
	padding: 0;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	cursor: pointer;
}

.editor__spin-button:hover:not(:disabled) {
	background: var(--surface-2);
	color: var(--fg);
}

.editor__spin-button:disabled {
	color: var(--fg-3);
	opacity: .5;
	cursor: default;
}

.editor__spin-button svg {
	width: 16px;
	height: 16px;
}

/* The link form opens under its button. */
.editor__pop {
	position: relative;
	flex: none;
}

.editor__link {
	position: absolute;
	top: calc(100% + 6px);
	left: 0;
	z-index: 40;
	display: grid;
	gap: var(--s-3);
	width: 320px;
	max-width: calc(100vw - 2 * var(--s-4));
	padding: var(--s-4);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-2);
}

.editor__link .field {
	margin: 0;
}

.editor__link-buttons {
	display: flex;
	justify-content: flex-end;
	gap: var(--s-2);
	margin: 0;
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
	align-items: center;
	gap: var(--s-2);
	padding: 11px var(--s-5);
	border-top: 1px solid var(--border);
	background: var(--surface);
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.editor__sep {
	opacity: .5;
}

.editor__foot-end {
	display: flex;
	flex: none;
	align-items: center;
	gap: var(--s-2);
	margin-left: auto;
	white-space: nowrap;
}

/* The breadcrumb: where the caret is, from the entry down. Every crumb
   selects what it names; the middle ones shrink first, so the one the
   caret is in stays whole. */
.editor__crumbs {
	flex: 1;
	min-width: 0;
}

.editor__crumbs ol {
	display: flex;
	align-items: center;
	min-width: 0;
	margin: 0;
	padding: 0;
	list-style: none;
}

.editor__crumbs li {
	display: flex;
	align-items: center;
	min-width: 0;
	flex-shrink: 1;
}

.editor__crumbs li:first-child,
.editor__crumbs li:last-child {
	flex-shrink: 0;
}

.editor__crumb {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	min-width: 0;
	padding: 3px 6px;
	overflow: hidden;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-2);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
	cursor: pointer;
}

.editor__crumb--root {
	margin-left: -6px;
}

.editor__crumb:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.editor__crumb.is-here {
	color: var(--fg);
	font-weight: 500;
}

.editor__crumb.is-placed {
	color: var(--accent);
}

.editor__crumb :deep(svg),
.editor__crumb :deep(.type-icon) {
	flex: none;
	width: 12px;
	height: 12px;
}

.editor__crumb-sep {
	flex: none;
	width: 11px;
	height: 11px;
	color: var(--fg-3);
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

.is-focus .editor__head {
	border-bottom-color: transparent;
	background: transparent;
}

@media (prefers-reduced-motion: reduce) {
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

.editor__tab-name {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
}

.editor__tab :deep(.type-icon),
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


.editor__side-close {
	flex: none;
}

.editor__group {
	display: grid;
	gap: var(--s-4);
	padding: var(--s-5);
	border-bottom: 1px solid var(--border);
}

/* The element tab with nothing selected says so. */
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

/* One quiet row, in the same place under every panel, to the list of
   what's in the entry; the list has one back. */
.editor__list-link {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	width: 100%;
	padding: var(--s-4) var(--s-5);
	border: 0;
	border-bottom: 1px solid var(--border);
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.editor__list-link:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.editor__list-link svg {
	flex: none;
	color: var(--fg-3);
}

.editor__list-count {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.editor__list-link .editor__list-go {
	width: 13px;
	height: 13px;
}

.editor__group-heading:focus {
	outline: none;
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

/* The entry's settings as label → value rows (admin.md §8, The document
   panel): the question in flat ink, the answer in the accent, filling the
   row with its caret at the end; boxes only once something is typed. */
.settings {
	display: grid;
	margin: -6px -8px;
}

.settings__row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	min-height: 36px;
}

.settings__row dt {
	flex: none;
	width: 84px;
	padding-left: 8px;
	color: var(--fg-2);
	font-size: var(--text-sm);
}

.settings__row dd {
	display: flex;
	flex: 1;
	min-width: 0;
	margin: 0;
}

.settings__row dd > :deep(.menu-button),
.settings__row dd > .reference {
	flex: 1;
	min-width: 0;
}

.settings__row :deep(.settings__value),
.settings__row :deep(.date),
.settings__static {
	display: flex;
	flex: 1;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-width: 0;
	margin: 0;
	padding: 7px 8px;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--accent);
	font: inherit;
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.settings__static {
	cursor: default;
}

.settings__row :deep(.settings__value:hover),
.settings__row :deep(.settings__value[aria-expanded="true"]),
.settings__row :deep(.date:hover),
.settings__row :deep(.date[aria-expanded="true"]) {
	background: var(--surface-2);
}

/* Status says its state in its own ink, never color alone: an icon and a
   word go with it. */
.settings__row :deep(.settings__value--published),
.settings__value--published {
	color: var(--good);
}

.settings__row :deep(.settings__value--draft),
.settings__value--draft {
	color: var(--fg-2);
}

.settings__row :deep(.settings__value svg),
.settings__static svg {
	flex: none;
	width: 14px;
	height: 14px;
}

.settings__row :deep(.settings__text) {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.settings__row :deep(.settings__caret),
.settings__row :deep(.date__caret) {
	width: 13px;
	height: 13px;
	margin-left: auto;
	color: var(--fg-3);
}

.settings__input {
	flex: 1;
	width: 100%;
	min-width: 0;
	padding: 7px 8px;
	border: 1px solid transparent;
	border-radius: var(--r-1);
	background: none;
	color: var(--accent);
	font-size: var(--text-sm);
}

.settings__input:hover {
	background: var(--surface-2);
}

.settings__input:focus-visible {
	border-color: var(--accent);
	outline: none;
	background: var(--surface);
	box-shadow: 0 0 0 3px var(--accent-soft);
}

.settings__input[aria-invalid="true"] {
	border-color: var(--danger);
}

/* In the drawer, typed-into fields are filled wells, as the panel's
   search fields are. */
.editor__side :deep(.field input:not([type="checkbox"])),
.editor__side :deep(.field textarea),
.editor__side :deep(.select__button:not(.select__button--plain)) {
	border-color: var(--border);
	background: var(--bg);
	font-size: var(--text-sm);
}

.editor__side :deep(.field input:not([type="checkbox"]):focus-visible),
.editor__side :deep(.field textarea:focus-visible) {
	border-color: var(--accent);
	background: var(--surface);
	box-shadow: 0 0 0 3px var(--accent-soft);
}

/* A quiet line under a group, tucked up to what it describes. */
.editor__group-note {
	margin-top: -11px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	line-height: 1.5;
}

/* Make homepage, at the end of the root page's note (D-420). */
.editor__note-action {
	padding: 0;
	border: 0;
	background: none;
	font-weight: 500;
	cursor: pointer;
}

.editor__group-note + .editor__group-note {
	margin-top: -14px;
}

/* The Outline, and a Content group: the type name a quiet column, the
   excerpt flowing out of it, depth drawn as indent and a hairline per
   level. A component's name is in the accent: someone placed it. */
.editor__outline {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: 2px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.editor__outline li {
	min-width: 0;
}

.editor__row {
	--depth: 0;
	display: flex;
	align-items: center;
	gap: var(--s-2);
	width: 100%;
	min-width: 0;
	padding: 7px 10px 7px calc(10px + var(--depth) * 13px);
	overflow: hidden;
	border: 0;
	border-radius: var(--r-1);
	background: repeating-linear-gradient(to right, var(--border-strong) 0 1px, transparent 1px 13px) 15px 4px / calc(var(--depth) * 13px) calc(100% - 8px) no-repeat;
	color: var(--fg-2);
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.editor__row:hover {
	background-color: var(--surface-2);
	color: var(--fg);
}

.editor__row.is-current {
	background-color: var(--accent-soft);
}

.editor__row svg {
	flex: none;
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.editor__row.is-current svg {
	color: var(--accent);
}

/* The type name is a quiet column; the excerpt flows out of it, and both
   give way to the panel's width rather than overflowing it. */
.editor__row-name {
	flex: none;
	min-width: 74px;
	max-width: 50%;
	overflow: hidden;
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.editor__row.is-placed .editor__row-name,
.editor__row.is-current .editor__row-name {
	color: var(--accent);
}

.editor__row-text {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	color: var(--fg-2);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.editor__row:hover .editor__row-text {
	color: var(--fg);
}

.editor__back-path {
	color: var(--fg-3);
}

.editor__back-path strong {
	color: var(--fg);
	font-weight: 500;
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

	.editor__hide-small {
		display: none;
	}

	/* The tools wrap to a second row rather than leave the screen. */
	.editor__head {
		flex-wrap: wrap;
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
	/* The menus' carets go, so the header still fits a phone. */
	.editor__head :deep(.editor__wide) {
		justify-content: center;
		width: var(--ctl);
		height: var(--ctl);
		padding: 0;
	}

	.editor__head :deep(.editor__caret) {
		display: none;
	}

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
