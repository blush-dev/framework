<script setup lang="ts">
/**
 * Edits an entry (D-229, D-233), as a writing surface (admin.md §8,
 * D-245): one centered column with the title as part of the document and
 * the Markdown body under it (`MarkdownEditor`, D-241); a header with
 * where you are and the actions, whose buttons show the save state; and a
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
 * block's options (`BlockOptions`), an image's (`ImageOptions`),
 * or a block's (`MarkdownBlockOptions`), with a **Content** group, one level
 * deep, for an element that holds others. The breadcrumb names where the
 * caret is, from the entry down, and each crumb selects what it names.
 *
 * The header's left half is what you do to the document (admin.md §8,
 * The toolbar; D-313), in the order a writer asks: what goes in the
 * document, blocks, in a panel that slides in from the left
 * and stays open (also opened by typing `/`; the Markdown elements are
 * tiles in it too, and **Image** opens the media library), and media, a
 * menu of **Media Library** and **Upload a File** (D-268); then, while
 * the caret is in the text, moving the top-level element it's in (⌥↑,
 * ⌥↓); what goes in a sentence, bold, italic, a link (a small form, ⌘K),
 * the other inline elements (a short menu: struck, highlighted, and code
 * text, then the inline blocks), and an icon (a library in a modal),
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
 * the type's fields or a date, so it has no term fields and can't be
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

import { computed, nextTick, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { debounced } from '../action';
import { confirmAction } from '../confirm';
import { confirmLeaving } from '../referrers';
import { makeHomepage } from '../homepage';
import { onBeforeRouteLeave, RouterLink, useRoute, useRouter } from 'vue-router';
import { statusOf } from '../statuses';
import { ApiError, entryPath, entryRoute, errorMessage, request, trashEntry, upload, type EntryDetail, type ContentTypeSummary, type EntryStatus, type LinkedGroup, type NewEntryDetail, type FieldDescription, type EntryTemplateOption, type MediaItem, type PreviewLink } from '../api';
import AdminIcon from '../components/AdminIcon.vue';
import MarkdownBlockOptions from '../components/MarkdownBlockOptions.vue';
import BlockOptions from '../components/BlockOptions.vue';
import BlockPanel from '../components/BlockPanel.vue';
import ConflictBar from '../components/ConflictBar.vue';
import DatePicker from '../components/DatePicker.vue';
import FieldControl from '../components/FieldControl.vue';
import FieldMisfit from '../components/FieldMisfit.vue';
import ImagePreview from '../components/ImagePreview.vue';
import ReferencePicker from '../components/ReferencePicker.vue';
import SummaryPopover from '../components/SummaryPopover.vue';
import IconPicker from '../components/IconPicker.vue';
import ImageOptions from '../components/ImageOptions.vue';
import LinkForm from '../components/LinkForm.vue';
import MarkdownEditor, { type MentionSuggestion } from '../components/MarkdownEditor.vue';
import MediaPicker from '../components/MediaPicker.vue';
import MentionForm from '../components/MentionForm.vue';
import MenuButton from '../components/MenuButton.vue';
import OptionsGroup from '../components/OptionsGroup.vue';
import OutlineList, { type OutlineRow } from '../components/OutlineList.vue';
import AdminSelect, { type SelectOption } from '../components/AdminSelect.vue';
import TabBar from '../components/TabBar.vue';
import StatusPill from '../components/StatusPill.vue';
import TypeIcon from '../components/TypeIcon.vue';
import { MARKDOWN_BLOCK_KINDS } from '../markdown-blocks';
import { bleedClasses, blockIcon, blockText, IMAGE_BLOCK, imageVariants, loadBlocks, MARKDOWN_ELEMENTS, type BleedClasses, type BlockDescription, type BlockProp } from '../blocks';
import { online } from '../connection';
import { diffLines, hunks, type Differences } from '../diff';
import { drawerOpen, keepDrawer } from '../drawer';
import { OPEN_LEVEL, type FieldLevel } from '../field-levels';
import { childrenOf as fieldsInside, fromForm, humanize, inSentence, label, splitDate, stateAt, toForm, withStateAt, type FieldStep, type FormValue } from '../fields';
import { siteDateTime } from '../dates';
import { formatDay, formatWhen, formatWhenInline, series, titleCase } from '../format';
import { loadActivity, type ActivityChange } from '../activity';
import ActivityRows from '../components/ActivityRows.vue';
import { forget, keep, kept, type EditorState, type KeptChanges } from '../kept';
import { childrenOf, elementAt, elementName, excerpt, holdsContent, imageLine, movedElement, outlineItems, pathTo, runIndex, sameElement, siblingRuns, type ElementRef, type OutlineItem } from '../elements';
import { attributeParts, attributeText, markdownBlocks, CORE, blockHead, emphasisAt, imageText, renumberedAt, inProse, linkAt, linkLabel, outline, withAttribute, withMarkdownBlockParts, withBlockParts, withImage, withLink, withoutBlock, withoutImage, withoutLink, withParts, shortName, wordAt, wordCount, type Block, type Edit, type Emphasis, type MarkdownLink } from '../markdown';
import { fieldValue, mediaName } from '../media';
import { onPressOutside } from '../popover';
import { loadReferences, referenceValues, slugOf, type ReferenceItem } from '../references';
import { pickerMeta } from '../picker';
import { canUpload } from '../session';
import type { IconName } from '../icons';
import type { SiteIcon } from '../site-icons';
import { locale, t } from '../messages';
import MessageText from '../components/MessageText.vue';
import { focusMode, screenCrumb, screenTitle, screenTrail } from '../screen';
import { config } from '../config';
import { copyText, toast } from '../toast';
import { useCommands, type Command } from '../commands';
import { profileType, currentType, labelsOf, loadTypes, types } from '../types';

// Fields the editor shows in their own places rather than the form.
const PLACED = ['title', 'status', 'published', 'slug'];

// The built-in fields the editor leaves out (D-728): `updated` is set by
// every save, a list's settings and a template are the file's for now.
const UNSHOWN = ['updated', 'collection', 'template'];

// The built-in fields under Advanced, for the theme (D-728).
const ADVANCED = ['stylesheet', 'class'];

// Why a required field stops publishing.
const REQUIRED = t('editor_view.required', 'Required to publish.');

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
// The save under way, with the status it's moving the entry to.
const saving   = ref<{ status?: EntryStatus } | null>(null);
const savedAt  = ref<Date | null>(null);
const notices  = ref(false);

// A save waiting for the connection, one that failed, and a conflict:
// each remembers the status the save was moving the entry to.
const waiting   = ref<{ status?: EntryStatus } | null>(null);
const failure   = ref<{ message: string; status?: EntryStatus } | null>(null);
const conflict  = ref<{ status?: EntryStatus; theirs: EntryDetail | null; loading: boolean } | null>(null);

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
// the document panel under its label, with its fields in the set's order,
// in the order the server sends the sets (by position, then label; D-768);
// fields stay out of the writing area (D-348). Their fields stay out of
// the panel's other groups. Each folds (D-779), closed until it's opened
// or has something to show, its header counting its fields and the
// required ones left empty.
const setGroups = computed(() => (entry.value?.type.sets ?? []).map((set) => ({
	...set,
	fields: set.fields.flatMap((name) => fields.value.filter((field) => field.name === name))
})).filter((set) => set.fields.length > 0));

const inSets    = computed(() => new Set((entry.value?.type.sets ?? []).flatMap((set) => set.fields)));
const ownFields = computed(() => fields.value.filter((field) => !inSets.value.has(field.name)));
const dateField = computed(() => entry.value?.type.fields.find((field) => field.name === 'published'));
const labels    = computed(() => labelsOf(entry.value?.type.name ?? 'entry'));
// A relation archive's page (D-602) is its list page ("cooks page") or the
// page written for one target's archive ("archive page").
const peopleNoun = computed(() => {
	const page = entry.value?.archive;

	return page ? (page.target === null ? t('editor_view.nouns.list_page', '{label} page', { label: page.label.toLowerCase() }) : t('editor_view.nouns.archive_page', 'archive page')) : null;
});
const noun      = computed(() => entry.value?.index ? t('editor_view.nouns.index_page', 'index page') : (peopleNoun.value ?? labels.value.item));
const entryType = computed(() => types.value.find((type) => type.name === entry.value?.type.name));
const fresh     = computed(() => entry.value !== null && entry.value.id === null);
const editTitle = computed(() => titleCase(entry.value?.index ? t('editor_view.edit_index_page', 'Edit index page') : (peopleNoun.value ? t('editor_view.edit_noun', 'Edit {noun}', { noun: peopleNoun.value }) : (fresh.value ? labels.value.newItem : labels.value.editItem))));

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
		options.unshift({ value: current, label: t('editor_view.parent.no_page', '{name} (no page)', { name: current }), depth: 0 });
	}

	return [{ value: '', label: t('editor_view.parent.top_level', 'None, at the top level') }, ...options];
});

watch(parent, () => {
	parentError.value = '';
});

// The language it's written in, from the site's (D-728). Another one
// renames its file (D-455), so it's sent apart from its fields, as a
// tree page's parent is.
const language        = ref('');
const languageError   = ref('');
const relanguaging    = computed(() => entry.value !== null && language.value !== '' && language.value !== entry.value.language);
const languageOptions = computed<SelectOption[]>(() => config.languages.map((item) => ({ value: item.code, label: item.label })));

watch(language, () => {
	languageError.value = '';
});

// The template it's drawn with (D-783): Default leaves it to the
// template hierarchy, and another is one the active theme declares for
// its type, written as its name. What the menu can't show (a list of
// names, or one the theme doesn't offer) is kept until something else
// is chosen.
const KEPT_TEMPLATE = '\u0000kept';

const template      = ref('');
const templateStart = ref('');
const templateError = ref('');
const retemplating  = computed(() => template.value !== templateStart.value);

const templateNames = computed(() => {
	const value = entry.value?.values.template;

	return (Array.isArray(value) ? value : [value]).filter((name): name is string => typeof name === 'string' && name !== '');
});

const templateOptions = computed<EntryTemplateOption[]>(() => [
	{ name: '', label: t('editor_view.template.default', 'Default'), description: t('editor_view.template.default_description', "The theme chooses, by the entry's type.") },
	...(entry.value?.templates ?? [])
]);

const templateLabel = computed(() => template.value === KEPT_TEMPLATE
	? t('editor_view.template.not_in_theme', 'Not in this theme ({names})', { names: templateNames.value.join(', ') })
	: templateOptions.value.find((option) => option.name === template.value)?.label ?? t('editor_view.template.default', 'Default'));

function templateOf(detail: EntryDetail | NewEntryDetail): string {
	const value = detail.values.template;
	const names = (Array.isArray(value) ? value : [value]).filter((name): name is string => typeof name === 'string' && name !== '');

	if (names.length === 0) {
		return '';
	}

	return names.length === 1 && detail.templates.some((item) => item.name === names[0]) ? names[0]! : KEPT_TEMPLATE;
}

watch(template, () => {
	templateError.value = '';
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
	screenCrumb.value = fresh.value ? t('editor_view.crumb.new', 'New') : t('editor_view.crumb.editing', 'Editing');
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

	return start !== null && (title.value !== start.title || body.value !== start.body || date.value !== start.date || slug.value.trim() !== start.slug || moving.value || relanguaging.value || retemplating.value || changedFields.value.length > 0);
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

	language.value = detail.language;
	template.value = templateStart.value = templateOf(detail);

	initial.value  = current();
	conflict.value = null;
	failure.value  = null;
	waiting.value  = null;

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
		error.value = errorMessage(caught, t('editor_view.errors.not_loaded', "The {noun} couldn't be loaded.", { noun: noun.value }));
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
const keeping = debounced(keepNow, 400);

function keepNow(): void {
	keeping.cancel();

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

watch([title, body, date, form], () => keeping(), { deep: true });

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
			found.push({ name: field.name, label: t('editor_view.title', 'Title'), control: 'editor-title' });
		} else if (field.name === 'published' && empty(date.value)) {
			found.push({ name: field.name, label: t('editor_view.publish_date', 'Publish date'), control: 'editor-date' });
		} else if (!PLACED.includes(field.name) && typeof form.value[field.name] !== 'boolean' && empty(form.value[field.name])) {
			found.push({ name: field.name, label: label(field), control: `field-${field.name}` });
		} else if ((field.relation?.min ?? 0) > 1 && referenceValues(String(form.value[field.name] ?? '')).length < (field.relation?.min ?? 0)) {
			// A relation's `min` above one (D-599).
			found.push({ name: field.name, label: t('editor_view.at_least', '{label} (at least {min})', { label: label(field), min: field.relation?.min ?? 0 }), control: `field-${field.name}` });
		}
	}

	return found;
});

// How many of a set's required fields are left empty, for its header.
function setMissing(set: { fields: FieldDescription[] }): number {
	return set.fields.filter((field) => missing.value.some((item) => item.name === field.name)).length;
}

// A set opens to show an error or a warning in it.
function setOpen(set: { fields: FieldDescription[] }): boolean {
	return set.fields.some((field) => errorFor(field.name) !== undefined || misfit(field) !== undefined);
}

// The values, as last saved, that don't fit their fields (D-779), only
// from a file edited by hand, each the linter's words for what's wrong.
// A field's warning shows while it's as it was loaded.
const misfits = computed(() => new Map((entry.value?.violations ?? [])
	.filter((violation) => violation.kind === 'value' && violation.severity !== 'notice')
	.map((violation) => [violation.field, violation.message])));

function misfit(field: FieldDescription): string | undefined {
	const start = initial.value;

	return start !== null && form.value[field.name] === start.form[field.name] ? misfits.value.get(field.name) : undefined;
}

function errorFor(name: string): string | undefined {
	if (attempted.value && missing.value.some((item) => item.name === name)) {
		return REQUIRED;
	}

	const refused = fullNow.value;

	return refused?.field === name
		? (refused.target.room ? t('editor_view.full_with_room', '{full} is full. {room} has room for {left} more.', { full: refused.target.title, room: refused.target.room.title, left: refused.target.room.left }) : t('editor_view.full', '{full} is full.', { full: refused.target.title }))
		: undefined;
}

// A save refused because a target already has as many entries naming it
// as its inverse's `max` takes (D-608). The limit is the other side's,
// so the bar names it, and the field which has room; it's settled once
// the field changes.
interface FullTarget {
	id: string | null;
	title: string;
	type: string;
	max: number;
	room: { title: string; slug: string; left: number } | null;
}

const full    = ref<{ field: string; value: unknown; target: FullTarget } | null>(null);
const fullNow = computed(() => full.value !== null && form.value[full.value.field] === full.value.value ? full.value : null);

function fullIn(data: unknown): FullTarget | null {
	const found = typeof data === 'object' && data !== null && 'full' in data ? data.full : null;

	return typeof found === 'object' && found !== null && 'title' in found ? found as FullTarget : null;
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

	if (detail === null || start === null || saving.value !== null || conflict.value !== null || offer.value !== null) {
		return;
	}

	// A plain save with nothing changed has nothing to do.
	if (status === undefined && !dirty.value) {
		return;
	}

	// Live entries linking to it stop showing it (D-598).
	if (detail.id !== null && detail.status === 'published' && (status === 'draft' || status === 'scheduled') && !await confirmLeaving(detail.id, t('editor_view.quoted', '“{title}”', { title: detail.title || t('editor_view.untitled', 'Untitled') }), 'draft')) {
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
		failure.value = { message: t('editor_view.errors.needs_title', 'Give the {noun} a title to save it.', { noun: noun.value }), status };
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

	if (retemplating.value) {
		put('template', template.value === '' ? null : template.value);
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

	if (relanguaging.value) {
		change.language = language.value;
	}

	if (status !== undefined) {
		change.status = status;

		if (status === 'scheduled' && dateField.value !== undefined) {
			change.published = fromForm(dateField.value, date.value, detail.values.published);
		}
	}

	change.set    = set;
	change.remove = remove;

	saving.value  = { status };
	waiting.value = null;

	try {
		const saved = detail.id === null
			? await request<EntryDetail>('POST', '/entries', created(detail, change, status))
			: await request<EntryDetail>('PATCH', entryPath(detail.id), change);

		fill(saved);
		full.value = null;
		forget(keptAs(saved));
		forget(keptAs(detail));
		keptHere.value  = false;
		attempted.value = false;
		savedAt.value   = new Date();

		// A plain save shows on its button; a change of status says
		// so, and so does a new entry's first.
		if (detail.id === null && saved.status === 'draft') {
			toast(t('editor_view.toasts.saved_draft', 'Saved as a draft'));
		} else if (status !== undefined && status !== detail.status) {
			toast(status === 'published' ? t('editor_view.toasts.published', 'Published') : (status === 'scheduled' ? t('editor_view.toasts.scheduled', 'Scheduled') : t('editor_view.toasts.switched_to_draft', 'Switched to draft')));
		}
	} catch (caught) {
		if (caught instanceof ApiError && (caught.field === 'slug' || caught.field === 'parent' || caught.field === 'language' || caught.field === 'template')) {
			// Nothing was saved; the slug, parent, language, or template
			// row says why.
			({ slug: slugError, parent: parentError, language: languageError, template: templateError })[caught.field].value = caught.message;
			sideOpen.value  = true;
			tab.value       = 'document';
			await nextTick();
			document.getElementById(`editor-${caught.field}`)?.focus();
		} else if (caught instanceof ApiError && caught.field !== null && fullIn(caught.data) !== null) {
			// Nothing was saved; the bar names what's full, and the field
			// what has room.
			const field = caught.field;

			full.value     = { field, value: form.value[field], target: fullIn(caught.data) as FullTarget };
			sideOpen.value = true;
			tab.value      = 'document';
			await nextTick();
			document.getElementById(`field-${field}`)?.focus();
		} else if (caught instanceof ApiError && caught.status === 409) {
			void openConflict(status);
		} else if (caught instanceof ApiError && caught.status === 0 && !online.value) {
			waiting.value = { status };
		} else {
			failure.value = { message: errorMessage(caught, t('editor_view.errors.not_saved', "The {noun} couldn't be saved.", { noun: noun.value })), status };
		}
	} finally {
		saving.value = null;
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
		...(change.language === undefined ? {} : { language: change.language }),
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

	entry.value    = theirs;
	initial.value  = stateOf(theirs);
	conflict.value = null;
	apply(mine);

	void save(open.status);
}

// What differs between the file as it is now and the changes here, for
// the conflict bar's comparison.
function differences(): Differences | null {
	const theirs = conflict.value?.theirs;

	if (theirs === null || theirs === undefined) {
		return null;
	}

	const their   = stateOf(theirs);
	const mine    = current();
	const display = (value: FormValue | undefined): string => typeof value === 'boolean' ? (value ? t('common.yes', 'Yes') : t('common.no', 'No')) : (value === undefined || value === '' ? '—' : value);
	const rows: { label: string; theirs: string; mine: string }[] = [];

	if (their.title !== mine.title) {
		rows.push({ label: t('editor_view.title', 'Title'), theirs: display(their.title), mine: display(mine.title) });
	}

	if (their.date !== mine.date) {
		rows.push({ label: t('editor_view.publish_date', 'Publish date'), theirs: display(their.date), mine: display(mine.date) });
	}

	for (const field of fields.value) {
		if (their.form[field.name] !== mine.form[field.name]) {
			rows.push({ label: label(field), theirs: display(their.form[field.name]), mine: display(mine.form[field.name]) });
		}
	}

	return { rows, body: their.body === mine.body ? null : hunks(diffLines(their.body, mine.body)) };
}

async function trash(): Promise<void> {
	const detail = entry.value;

	if (detail === null || detail.id === null || !await confirmLeaving(detail.id, t('editor_view.quoted', '“{title}”', { title: detail.title || t('editor_view.untitled', 'Untitled') }), 'trash')) {
		return;
	}

	const back = route.fullPath;
	const name = t('editor_view.quoted', '“{title}”', { title: detail.title || t('editor_view.untitled', 'Untitled') });

	try {
		const undo = await trashEntry(detail.id, detail.revision);

		forget(detail.id);
		initial.value = null;
		await router.push({ name: 'type', params: { type: detail.type.name } });

		// Its Undo puts it back and opens it again (D-525).
		toast(t('editor_view.toasts.trashed', 'Moved {name} to the trash', { name }), {
			kind: 'danger',
			undo: () => void undo().then(
				() => router.push(back),
				(caught: unknown) => toast(errorMessage(caught, t('editor_view.errors.not_restored', "{name} couldn't be restored.", { name })), { kind: 'warn' })
			)
		});
	} catch (caught) {
		error.value = errorMessage(caught, t('editor_view.errors.not_trashed', "The {noun} couldn't be moved to the trash.", { noun: noun.value }));
	}
}

// What the primary and secondary buttons do, from the status and date.
const primary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const detail = entry.value;

	if (detail === null) {
		return null;
	}

	if (!detail.can.publish) {
		return detail.status === 'draft' ? { label: t('editor_view.save_draft', 'Save Draft') } : { label: t('editor_view.update', 'Update') };
	}

	if (future.value) {
		return detail.status === 'scheduled' && date.value === initial.value?.date ? { label: t('editor_view.update', 'Update') } : { label: t('editor_view.schedule', 'Schedule'), status: 'scheduled' };
	}

	return detail.status === 'published' ? { label: t('editor_view.update', 'Update') } : { label: t('editor_view.publish', 'Publish'), status: 'published' };
});

const secondary = computed<{ label: string; status?: EntryStatus } | null>(() => {
	const detail = entry.value;

	if (detail === null || (!detail.can.publish && detail.status === 'draft')) {
		return null;
	}

	return detail.status === 'draft' ? { label: t('editor_view.save_draft', 'Save Draft') } : { label: t('editor_view.switch_to_draft', 'Switch to draft'), status: 'draft' };
});

// The Status value's menu: what each status does, in a sentence, and
// only the ones this account can move it to. Choosing one saves. The
// trash is never offered (D-484): Move to Trash sets it.
const coreStatusNames: Record<string, { label: string; icon: IconName }> = {
	published: { label: t('editor_view.statuses.published', 'Published'), icon: 'circle-check' },
	scheduled: { label: t('editor_view.statuses.scheduled', 'Scheduled'), icon: 'calendar-clock' },
	draft: { label: t('editor_view.statuses.draft', 'Draft'), icon: 'file-pen-line' },
	trash: { label: t('editor_view.statuses.trash', 'Trash'), icon: 'trash-2' }
};

// A status's label and icon: core's own, or a registered one's label
// (D-850).
function statusLook(status: EntryStatus): { label: string; icon: IconName } {
	return coreStatusNames[status] ?? { label: statusOf(status).label, icon: 'circle-dot' };
}

const statusOptions = computed(() => {
	const detail = entry.value;

	if (detail === null) {
		return [];
	}

	const options: { status: EntryStatus; label: string; text: string; icon: IconName }[] = [
		{ status: 'draft', label: t('editor_view.statuses.draft', 'Draft'), text: t('editor_view.statuses.draft_text', 'Not on the site. Only people who can edit it see it.'), icon: 'file-pen-line' }
	];

	if (detail.can.publish && future.value) {
		options.push({ status: 'scheduled', label: t('editor_view.statuses.scheduled', 'Scheduled'), text: t('editor_view.statuses.scheduled_text', 'Goes live on its date, on its own.'), icon: 'calendar-clock' });
	} else if (detail.can.publish) {
		options.push({ status: 'published', label: t('editor_view.statuses.published', 'Published'), text: t('editor_view.statuses.published_text', 'On the site, for anyone to read.'), icon: 'circle-check' });
	}

	if (detail.status !== 'trash' && !options.some((option) => option.status === detail.status)) {
		options.push({ status: detail.status, ...statusLook(detail.status), text: detail.status === 'scheduled' ? t('editor_view.statuses.overdue_text', 'Its date has passed, so it goes live the next time it is published.') : t('editor_view.statuses.published_text', 'On the site, for anyone to read.') });
	}

	return options;
});

// What the date means, so nobody has to work it out.
const dateMeaning = computed(() => {
	const offset = splitDate(entry.value?.values.published)?.offset ?? '';
	const when   = date.value === '' ? Number.NaN : Date.parse(`${date.value}:00${offset === '' ? '' : offset.replace(/^([+-]\d{2})(\d{2})$/, '$1:$2')}`);

	if (Number.isNaN(when)) {
		return t('editor_view.date.none', "No date yet. It's dated when it's published.");
	}

	const difference = when - Date.now();
	const days       = Math.round(Math.abs(difference) / 86400000);
	const near       = Math.abs(difference) < 3600000 ? t('editor_view.date.within_hour', 'within the hour')
		: (days === 0 ? t('editor_view.date.today', 'today') : (days === 1 ? (difference > 0 ? t('editor_view.date.tomorrow', 'tomorrow') : t('editor_view.date.yesterday', 'yesterday')) : (difference > 0 ? t('editor_view.date.days_from_now', '{count, plural, one {# day from now} other {# days from now}}', { count: days }) : t('editor_view.date.days_ago', '{count, plural, one {# day ago} other {# days ago}}', { count: days }))));

	if (difference > 0) {
		return entry.value?.status === 'scheduled' ? t('editor_view.date.goes_live', 'Goes live {when}.', { when: near }) : t('editor_view.date.goes_live_once_scheduled', "Goes live {when}, once it's scheduled.", { when: near });
	}

	return entry.value?.status === 'published' ? t('editor_view.date.published', 'Published {when}.', { when: near }) : t('editor_view.date.dated', 'Dated {when}.', { when: near });
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
		return t('editor_view.ago.just_now', 'just now');
	}

	if (minutes < 60) {
		return t('editor_view.ago.minutes', '{count, plural, one {# minute ago} other {# minutes ago}}', { count: minutes });
	}

	const hours = Math.round(minutes / 60);

	return hours < 24 ? t('editor_view.ago.hours', '{count, plural, one {# hour ago} other {# hours ago}}', { count: hours }) : t('editor_view.ago.days', '{count, plural, one {# day ago} other {# days ago}}', { count: Math.round(hours / 24) });
}

const publishNote = computed(() => {
	const edited = ago(entry.value?.modified ?? null);

	return [dateField.value === undefined ? '' : dateMeaning.value.replace(/\.$/, ''), edited === '' ? '' : t('editor_view.last_edited', 'Last edited {when}', { when: edited })].filter((part) => part !== '').join(' · ') + '.';
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
			toast(t('editor_view.toasts.preview_last_saved', 'The preview shows the last saved version'), { kind: 'info' });
		}
	} catch (caught) {
		tab?.close();
		error.value = errorMessage(caught, t('editor_view.errors.preview', "The preview couldn't be opened."));
	}
}

// The Document tab's fields, in the order they're touched (admin.md §8,
// The document panel; D-728): the featured image and the summary at the
// top of Publish, under the title; visibility, the subtitle, a term's
// parent, and a tree page's or term's position as rows under Publish;
// then each people field, each other reference (terms, as a picker), the
// type's other fields as a form, each field set's (D-337), and the
// theme's fields under Advanced.
const visibilityField = computed(() => ownFields.value.find((field) => field.name === 'visibility' && field.type === 'enum'));
const parentField     = computed(() => ownFields.value.find((field) => field.name === 'parent' && field.type === 'reference' && field.multiple === false && field.to !== undefined));
// A tree page's or term's place among its siblings (D-412), a row beside
// its parent.
const positionField   = computed(() => ownFields.value.find((field) => field.name === 'position' && field.type === 'number'));
// A term has no author or featured image of its own (admin.md §8), so
// those show only when its file has one.
const term = computed(() => entry.value?.type.terms === true);
const imageField      = computed(() => ownFields.value.find((field) => field.name === 'image' && field.type === 'media'));
// Each people field (D-353): a reference to profiles, such as `authors`
// or a recipe's `cooks`, as a people picker under its own label.
const peopleFields    = computed(() => ownFields.value.filter((field) => field.type === 'reference' && field.to !== undefined && field.to === profileType.value && field.multiple !== false));
const summaryField    = computed(() => ownFields.value.find((field) => field.name === 'summary' && field.type === 'markdown'));
const subtitleField   = computed(() => ownFields.value.find((field) => field.name === 'subtitle' && field.type === 'text'));
// What the theme draws it with (D-728): a layout, a stylesheet, and the
// body's classes, these last typed on one line, apart by spaces.
const advancedFields  = computed(() => ownFields.value.filter((field) => ADVANCED.includes(field.name)));
// Advanced folds away (D-730), open while one of its fields is set or
// has an error, so nothing in it is out of sight.
const advancedSet     = computed(() => advancedFields.value.some((field) => String(form.value[field.name] ?? '').trim() !== '' || errorFor(field.name) !== undefined));
const referenceFields = computed(() => ownFields.value.filter((field) => field.type === 'reference' && field.to !== undefined && field.multiple !== false && !peopleFields.value.includes(field)));
// A relation that takes exactly one (a cuisine, or who tested a recipe)
// is a value in the Publish rows, as Status and Date are (D-607).
const singleFields    = computed(() => ownFields.value.filter((field) => field.type === 'reference' && field.to !== undefined && field.multiple === false && field !== parentField.value && field.relation !== undefined));

const otherFields = computed(() => {
	const placed = [visibilityField.value, parentField.value, positionField.value, imageField.value, ...peopleFields.value, summaryField.value, subtitleField.value, ...referenceFields.value, ...singleFields.value, ...advancedFields.value];

	return ownFields.value.filter((field) => !placed.includes(field) && !UNSHOWN.includes(field.name));
});

// A field's state as a line of text: a list's items apart by spaces.
function inline(field: FieldDescription): FormValue {
	return field.type === 'list' ? String(form.value[field.name] ?? '').replace(/\n/g, ' ') : form.value[field.name] ?? '';
}

function setInline(field: FieldDescription, value: FormValue): void {
	form.value[field.name] = field.type === 'list' ? String(value).trim().replace(/\s+/g, '\n') : value;
}

function referenceCount(field: FieldDescription): number {
	return String(form.value[field.name] ?? '').split(',').filter((item) => item.trim() !== '').length;
}

// A relation's state at a glance, for its heading (D-607).
function referenceMeta(field: FieldDescription, people = false) {
	return pickerMeta(field, referenceCount(field), {
		...(entry.value?.inherited?.[field.name] === undefined ? {} : { inherited: entry.value.inherited[field.name] }),
		people,
		attempted: attempted.value && missing.value.some((item) => item.name === field.name)
	});
}

// Who can reach it (D-082), with what each choice does. Public is the
// default, so choosing it writes nothing.
type VisibilityName = 'public' | 'unlisted' | 'hidden';

const visibilityNames: Record<VisibilityName, { label: string; icon: IconName; text: string }> = {
	public: { label: t('editor_view.visibility.public', 'Public'), icon: 'eye', text: t('editor_view.visibility.public_text', "Anyone can read it once it's live, and it's listed with the others.") },
	unlisted: { label: t('editor_view.visibility.unlisted', 'Unlisted'), icon: 'link', text: t('editor_view.visibility.unlisted_text', "It has an address, but isn't in lists, feeds, or the sitemap.") },
	hidden: { label: t('editor_view.visibility.hidden', 'Hidden'), icon: 'eye-off', text: t('editor_view.visibility.hidden_text', "No address and not listed. The site's templates can still show it.") }
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
const blocked = computed(() => saving.value !== null || conflict.value !== null || offer.value !== null);

// Whether a button's save does nothing: no status change and no changes.
function idle(action: { status?: EntryStatus }): boolean {
	return action.status === undefined && !dirty.value;
}

/**
 * What a button says (D-520): its save under way or waiting for the
 * connection, and **Saved** once a plain save has nothing left to do.
 */
function buttonLabel(action: { label: string; status?: EntryStatus }): string {
	if (saving.value !== null && saving.value.status === action.status) {
		return t('editor_view.saving', 'Saving…');
	}

	if (waiting.value !== null && waiting.value.status === action.status) {
		return t('editor_view.waiting', 'Waiting…');
	}

	return idle(action) && savedAt.value !== null ? t('editor_view.saved', 'Saved') : action.label;
}

// The save state, read out only: the buttons show it (D-520).
const saveState = computed<string>(() => {
	if (conflict.value !== null) {
		return t('editor_view.save_state.conflict', 'Not saved: changed elsewhere');
	}

	if (saving.value !== null) {
		return t('editor_view.saving', 'Saving…');
	}

	if (waiting.value !== null) {
		return t('editor_view.save_state.offline', 'Waiting for a connection');
	}

	if (failure.value !== null) {
		return t('editor_view.save_state.not_saved', 'Not saved');
	}

	if (dirty.value) {
		return t('editor_view.save_state.unsaved', 'Unsaved changes');
	}

	return savedAt.value === null ? '' : t('editor_view.save_state.saved_at', 'Saved {time}', { time: savedAt.value.toLocaleTimeString(locale(), { hour: 'numeric', minute: '2-digit' }) });
});

// Where the changes are while they aren't saved.
const safe = computed(() => keptHere.value ? t('editor_view.safe.kept', 'Your changes are kept in this browser') : t('editor_view.safe.here', 'Your changes are still here'));

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
const available  = ref<BlockDescription[]>([]);

const blocksFailed = ref(false);
const imageStyles      = ref<Awaited<ReturnType<typeof imageVariants>>>([]);
const bleeds           = ref<BleedClasses>({ wide: 'bleed-wide', full: 'bleed-full' });

loadBlocks().then(async (blocks) => {
	available.value   = blocks;
	imageStyles.value = await imageVariants();
	bleeds.value      = await bleedClasses();
}, () => {
	blocksFailed.value = true;
});

// The block panel: open from its button, or for a slash typed at the
// start of a line (then the query is in the text, and it closes once a
// block replaces it).
const panel      = ref<InstanceType<typeof BlockPanel> | null>(null);
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

// The panel offers the Markdown elements and the blocks that aren't inline, the
// Markdown first in each group, with a Markdown image first among the
// media (D-268, D-313); inline ones have their own menu, less the icon,
// which has its own picker.
const offeredBlocks = computed(() => available.value.length === 0 ? [] : [...MARKDOWN_ELEMENTS, IMAGE_BLOCK, ...available.value.filter((described) => described.kind !== 'inline')]);
// Inside a container that holds only some things, only those are offered.
const panelBlocks = computed(() => {
	const only = holder.value?.only;

	return only ? offeredBlocks.value.filter((described) => only.includes(described.name)) : offeredBlocks.value;
});

const panelNote = computed(() => {
	const only = holder.value?.only;

	if (holder.value === undefined || !only) {
		return undefined;
	}

	return t('editor_view.holds_only', '{holder} holds only {names}.', { holder: titleCase(holder.value.label), names: onlyNames(only) });
});

// "images", "images, buttons".
function onlyNames(only: string[]): string {
	return only.map((name) => name === 'image' ? t('editor_view.only.images', 'images') : t('editor_view.only.plural', '{label}s', { label: inSentence(blockFor(name)?.label ?? name) })).join(', ');
}

const panelNoteNames = computed(() => onlyNames(selected.value?.only ?? []));

const inlineBlocks = computed(() => available.value.filter((described) => described.kind === 'inline' && described !== iconBlock.value));

function chooseBlock(described: BlockDescription): void {
	const markdown = described.markdown;

	if (described === IMAGE_BLOCK) {
		pickMedia('library', 'image');
	} else if (markdown !== undefined) {
		// The placeholder arrives selected, so the first keystroke replaces it.
		const at = markdown.pick === '' ? markdown.text.length : markdown.text.indexOf(markdown.pick);

		bodyEditor.value?.insertBlock(() => ({ text: markdown.text, caret: at, end: at + markdown.pick.length }));
	} else {
		bodyEditor.value?.insert(described);
	}

	// A slash's panel has done its job; one opened from its button stays.
	if (panelSlash.value) {
		panelOpen.value  = false;
		panelSlash.value = false;
	}
}

function chooseInline(described: BlockDescription): void {
	bodyEditor.value?.insert(described);
}

// The icon picker's modal.
const iconsOpen = ref(false);

function openIcons(): void {
	closeOverlays();
	iconsOpen.value = true;
}

const iconBlock = computed(() => blockFor('icon'));

function iconPreview(icon: SiteIcon): string {
	return `:${iconBlock.value?.name ?? `${CORE}/icon`}[]{${attributeText('name', icon.name)}}`;
}

function chooseIcon(icon: SiteIcon): void {
	iconsOpen.value = false;

	if (iconBlock.value === undefined) {
		bodyEditor.value?.insertText(iconPreview(icon));
	} else {
		bodyEditor.value?.insert(iconBlock.value, { name: icon.name });
	}
}

function closeIcons(): void {
	iconsOpen.value = false;
	bodyEditor.value?.focusAt(caret.value);
}

// The media picker: inserting files, or choosing one for a field, a
// block's option, or an image. It opens on the Library tab, or on
// Upload from the media menu's Upload a File. Inserting takes several
// (D-526); choosing a file for one place takes one.
type MediaKind = 'image' | 'video' | 'audio' | 'document' | 'file';

const picking = ref<{ title: string; action: string; tab?: 'library' | 'upload'; kind?: MediaKind; locked?: boolean; multiple?: boolean; use: (file: MediaItem) => void; useAll?: (files: MediaItem[]) => void } | null>(null);
const uploads = computed(() => canUpload());

function pickMedia(start: 'library' | 'upload' = 'library', kind?: 'image'): void {
	// Inside a gallery, only images go in; inside a playlist, only sounds.
	const only = kind ?? heldKind();

	closeOverlays();
	picking.value = {
		title: only === 'image' ? t('editor_view.media.insert_images', 'Insert Images') : (only === 'audio' ? t('editor_view.media.insert_audio', 'Insert Audio') : t('editor_view.media.insert_media', 'Insert Media')),
		action: t('editor_view.media.insert', 'Insert'),
		tab: start,
		kind: only,
		locked: only !== undefined,
		multiple: true,
		use: insertFile,
		useAll: insertFiles
	};
}

// The kind of file the container the caret is in holds, if it holds
// only one: images for a gallery, sounds for a playlist (D-708).
function heldKind(): 'image' | 'audio' | undefined {
	const only = holder.value?.only;

	return only?.includes('image') === true ? 'image' : (only?.includes(`${CORE}/audio`) === true ? 'audio' : undefined);
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

	const name      = fileBlock(file);
	const described = blockFor(name);

	if (described === undefined) {
		bodyEditor.value?.insertText(`::${CORE}/${name}{${attributeText('src', file.reference)}}`);
	} else {
		bodyEditor.value?.insert(described, { src: file.reference });
	}

	tab.value = 'element';
}

// The block a file that isn't an image goes in as.
function fileBlock(file: MediaItem): 'video' | 'audio' | 'file' {
	return file.kind === 'video' ? 'video' : (file.kind === 'audio' ? 'audio' : 'file');
}

/**
 * Puts several files in the text at once, in the order chosen (D-526):
 * each a block of its own, as `insertFile()` writes it, with a blank line
 * between; inside a gallery, its images one to a line. Selected text is
 * the first image's alt text. The caret ends after the last.
 */
function insertFiles(files: MediaItem[]): void {
	const [first] = files;

	if (first === undefined) {
		return;
	}

	if (files.length === 1) {
		insertFile(first);

		return;
	}

	const gap = holder.value?.only?.includes('image') === true ? '\n' : '\n\n';

	bodyEditor.value?.insertBlock((selected) => {
		const text = files.map((file, index) => {
			if (file.kind === 'image') {
				return imageText(file.reference, (index === 0 ? selected : '') || file.alt, file.caption).text;
			}

			const name      = fileBlock(file);
			const described = blockFor(name);

			return described === undefined ? `::${CORE}/${name}{${attributeText('src', file.reference)}}` : blockText(described, false, '', { src: file.reference }).text;
		}).join(gap);

		return { text, caret: text.length };
	});

	tab.value = 'element';
	toast(t('editor_view.toasts.inserted_files', '{count, plural, one {Inserted # file} other {Inserted # files}}', { count: files.length }));
}

/**
 * Uploads files dropped or pasted into the text (D-284), one at a time,
 * then inserts them together where the caret is, as the picker inserts
 * several (D-526, D-527): in the order given, less any refused.
 */
async function uploadFiles(files: File[]): Promise<void> {
	if (!uploads.value) {
		toast(t('editor_view.toasts.cant_upload', "Your account can't upload files"), { kind: 'warn' });

		return;
	}

	const added: MediaItem[] = [];

	for (const file of files) {
		toast(t('editor_view.toasts.uploading', 'Uploading {name}…', { name: file.name }), { kind: 'info' });

		try {
			added.push(await upload<MediaItem>('/media', file));
		} catch (caught) {
			error.value = t('editor_view.errors.not_uploaded', "{name} wasn't uploaded. {reason}", { name: file.name, reason: errorMessage(caught, t('editor_view.errors.check_connection', 'Check your connection, then try again.')) });
		}
	}

	insertFiles(added);
}

// A field deep inside a group is picked for by its form field (`root`)
// and the steps down to it (D-768).
function pickForField(field: FieldDescription, root = field.name, steps: FieldStep[] = []): void {
	// A field says which kind it takes; a featured image takes images.
	const kind = field.kind ?? (field === imageField.value ? 'image' : undefined);

	picking.value = {
		title: titleCase(t('editor_view.media.choose_field', 'Choose {label}', { label: inSentence(label(field)) })),
		action: t('editor_view.media.choose', 'Choose'),
		kind,
		locked: kind !== undefined,
		use: (file) => {
			// A media field holds the file's id (D-773).
			form.value[root] = steps.length === 0 ? fieldValue(file) : withStateAt(form.value[root], steps, fieldValue(file));
		}
	};
}

function pickForOption(prop: BlockProp): void {
	const item = block.value;

	if (item === undefined) {
		return;
	}

	picking.value = {
		title: titleCase(t('editor_view.media.choose_field', 'Choose {label}', { label: inSentence(label(prop)) })),
		action: t('editor_view.media.choose', 'Choose'),
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
		title: item.src === '' ? t('editor_view.media.choose_image', 'Choose an Image') : t('editor_view.media.replace_image', 'Replace the Image'),
		action: t('editor_view.media.choose', 'Choose'),
		kind: 'image',
		locked: true,
		use: (file) => {
			// The library's alt text and caption fill what the image lacks.
			applyOption(withImage(item, {
				src: file.reference,
				...(item.alt === '' && file.alt !== '' ? { alt: file.alt } : {}),
				...((item.title ?? '') === '' && file.caption !== '' ? { title: file.caption } : {})
			}));
			toast(t('editor_view.toasts.chose', 'Chose {name}', { name: mediaName(file) }));
		}
	};
}

function picked(files: MediaItem[]): void {
	const current = picking.value;
	const [first] = files;

	picking.value = null;

	if (current?.useAll !== undefined) {
		current.useAll(files);
	} else if (first !== undefined) {
		current?.use(first);
	}
}

// The editor's own commands in the palette (D-248).
useCommands(() => {
	const detail = entry.value;

	if (detail === null) {
		return [];
	}

	const found: Command[] = [
		{ id: 'editor-focus', label: focusMode.value ? t('editor_view.commands.leave_focus', 'Leave focus mode') : t('editor_view.commands.focus', 'Focus mode'), icon: 'maximize-2', keywords: 'writing zen distraction', shortcut: '⌘⇧F', run: toggleFocus },
		{ id: 'editor-settings', label: sideOpen.value ? t('editor_view.commands.hide_settings', 'Hide the settings') : t('editor_view.commands.show_settings', 'Show the settings'), icon: 'panel-right', keywords: 'document fields sidebar', shortcut: '⌘/', run: toggleSide },
		{ id: 'editor-outline', label: t('editor_view.commands.outline', 'Outline'), icon: 'list', keywords: 'elements structure contents blocks', run: () => void showOutline() },
		{ id: 'editor-block', label: t('editor_view.commands.block', 'Insert a block'), icon: 'plus', keywords: 'callout figure block component', shortcut: '/', run: () => void togglePanel() },
		{ id: 'editor-media', label: t('editor_view.commands.media', 'Insert media'), icon: 'image', keywords: 'image picture video audio file library', run: () => pickMedia() },
		...(uploads.value ? [{ id: 'editor-upload', label: t('editor_view.commands.upload', 'Upload a file'), icon: 'upload' as const, keywords: 'media image picture video audio add', run: () => pickMedia('upload') }] : []),
		{ id: 'editor-icon', label: t('editor_view.commands.icon', 'Insert an icon'), icon: 'shapes', keywords: 'symbol glyph', run: openIcons },
		{ id: 'editor-bold', label: t('editor_view.commands.bold', 'Bold'), icon: 'bold', keywords: 'strong format', shortcut: '⌘B', run: () => bodyEditor.value?.emphasis('strong') },
		{ id: 'editor-italic', label: t('editor_view.commands.italic', 'Italic'), icon: 'italic', keywords: 'emphasis format', shortcut: '⌘I', run: () => bodyEditor.value?.emphasis('em') },
		{ id: 'editor-strike', label: t('editor_view.commands.strike', 'Strikethrough'), icon: 'strikethrough', keywords: 'format delete struck', shortcut: '⌘⇧X', run: () => bodyEditor.value?.emphasis('strike') },
		{ id: 'editor-mark', label: t('editor_view.commands.mark', 'Highlight'), icon: 'highlighter', keywords: 'format mark', shortcut: '⌘⇧H', run: () => bodyEditor.value?.emphasis('marked') },
		{ id: 'editor-code', label: t('editor_view.commands.code', 'Inline code'), icon: 'code', keywords: 'format monospace', shortcut: '⌘E', run: () => bodyEditor.value?.code() },
		...(canMention.value ? [{ id: 'editor-mention', label: t('editor_view.commands.mention', 'Mention'), icon: 'at-sign' as const, keywords: 'profile person author @', run: openMention }] : []),
		{ id: 'editor-link', label: t('editor_view.commands.link', 'Link'), icon: 'link', keywords: 'url address format', shortcut: '⌘K', run: openLink },
		{ id: 'editor-unlink', label: t('editor_view.commands.unlink', 'Remove link'), icon: 'link', keywords: 'url address unlink', shortcut: '⌘⇧K', run: () => bodyEditor.value?.unlink() },
		...[1, 2, 3, 4, 5, 6].map((level) => ({ id: `editor-heading-${level}`, label: t('editor_view.commands.heading', 'Heading {level}', { level }), icon: 'heading' as const, keywords: 'title level format', shortcut: `⌘⌥${level}`, run: () => bodyEditor.value?.heading(level) })),
		{ id: 'editor-paragraph', label: t('editor_view.commands.paragraph', 'Paragraph'), icon: 'pilcrow', keywords: 'text body format heading', shortcut: '⌘⌥0', run: () => bodyEditor.value?.heading(0) },
		{ id: 'editor-move-up', label: t('editor_view.commands.move_up', 'Move element up'), icon: 'chevron-up', keywords: 'reorder swap block', shortcut: '⌥↑', run: () => moveElement(true) },
		{ id: 'editor-move-down', label: t('editor_view.commands.move_down', 'Move element down'), icon: 'chevron-down', keywords: 'reorder swap block', shortcut: '⌥↓', run: () => moveElement(false) },
		{ id: 'editor-save', label: t('common.save', 'Save'), icon: 'file-text', shortcut: '⌘S', run: () => void save() }
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
		found.push({ id: 'editor-trash', label: t('editor_view.commands.trash', 'Move to trash'), icon: 'trash-2', keywords: 'delete remove', run: () => void trash() });
	}

	return found;
});

const markdown  = computed(() => outline(body.value));
const allMarkdownBlocks = computed(() => markdownBlocks(markdown.value));
const words             = computed(() => wordCount(markdown.value));
const items             = computed(() => outlineItems(body.value, markdown.value, allMarkdownBlocks.value));

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

	return elementAt(markdown.value, allMarkdownBlocks.value, caret.value);
});

const block         = computed<Block | undefined>(() => selection.value?.kind === 'block' ? markdown.value.blocks[selection.value.index] : undefined);
const image         = computed(() => selection.value?.kind === 'image' ? markdown.value.images[selection.value.index] : undefined);
const markdownBlock = computed(() => selection.value?.kind === 'markdown' ? allMarkdownBlocks.value[selection.value.index] : undefined);

/**
 * The inserter's description of a block, by its full or
 * core short name.
 */
function blockFor(name: string): BlockDescription | undefined {
	return available.value.find((described) => described.name === name || described.name === `${CORE}/${name}`);
}

function blockName(name: string): string {
	return blockFor(name)?.label ?? humanize(name.replace(/^.*\//, ''));
}

function blockLabel(item: Block): string {
	return blockName(item.name);
}

const selected = computed(() => block.value === undefined ? undefined : blockFor(block.value.name));

// An element's name and icon: a block's, "Image", or its Markdown block's.
function nameOf(element: ElementRef): string {
	return elementName(markdown.value, allMarkdownBlocks.value, element, blockName);
}

function iconOf(element: ElementRef): IconName {
	if (element.kind === 'block') {
		const known = blockFor(markdown.value.blocks[element.index]?.name ?? '');

		return known === undefined ? 'code' : blockIcon(known);
	}

	if (element.kind === 'image') {
		return 'image';
	}

	return MARKDOWN_BLOCK_KINDS[allMarkdownBlocks.value[element.index]?.kind ?? 'paragraph'].icon;
}

// Whether an element was placed, not written: a container or leaf
// block, which the outline and breadcrumb name in the accent.
function placed(element: ElementRef): boolean {
	return element.kind === 'block' && markdown.value.blocks[element.index]?.kind !== 'inline';
}

// The tabs are named for what they hold: the entry's type ("Post"), and
// the element the caret is in ("Callout", "Heading 2"), else "Elements".
const typeName = computed(() => titleCase(entry.value?.index ? t('editor_view.index_page', 'Index page') : (peopleNoun.value ?? labels.value.singular)));
const tabName  = computed(() => selection.value === null ? t('editor_view.elements', 'Elements') : nameOf(selection.value));
const tabIcon  = computed<IconName>(() => selection.value === null ? 'list' : iconOf(selection.value));

// Where the caret is, from the entry down (the breadcrumb): every element
// holding the selected one, outermost first.
const path = computed(() => {
	const current = selection.value;
	const span    = current === null ? undefined : (current.kind === 'block' ? markdown.value.blocks[current.index] : undefined);

	return pathTo(items.value, current, span);
});

// A holder's Content group: the elements directly inside it.
const content = computed(() => selection.value !== null && holdsContent(markdown.value, allMarkdownBlocks.value, selection.value) ? childrenOf(items.value, selection.value) : null);

// What's in a container that it doesn't hold (D-314): the author can
// always type anyway, and the site leaves it out (D-529), so its panel
// says so rather than the site's rendering being the first to notice.
// Each line of its body is checked, since images one to a line are one
// paragraph to Markdown.
const strays = computed<string[]>(() => {
	const only = selected.value?.only;
	const item = block.value;

	if (!only || item === undefined) {
		return [];
	}

	const found: string[] = [];
	let depth = 0;

	for (const line of markdown.value.lines) {
		if (line.start <= item.start || line.start >= item.end) {
			continue;
		}

		// Inside an allowed block, its own lines are its business.
		if (line.kind === 'open' || line.kind === 'leaf') {
			const name = blockFor(markdown.value.blocks[line.block ?? -1]?.name ?? '')?.name;

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

// The elements directly in a container that it doesn't hold, by kind
// and index, each with the container's label (D-529), and blocks
// written as another kind than they're registered (D-531): the outline
// marks them, and each one's own panel says the site leaves it out.
const strayItems = computed(() => {
	const found = new Map<string, string>();

	for (const item of items.value) {
		const parent = items.value[item.parent];
		const holder = parent?.kind === 'block' ? blockFor(markdown.value.blocks[parent.index]?.name ?? '') : undefined;
		const only   = holder?.only;

		if (holder === undefined || !only) {
			continue;
		}

		const name = item.kind === 'block' ? blockFor(markdown.value.blocks[item.index]?.name ?? '')?.name : undefined;
		const held = item.kind === 'image' ? only.includes('image') : (name !== undefined && only.includes(name));

		if (!held) {
			found.set(`${item.kind}-${item.index}`, t('editor_view.holds_only_stray', '{holder} holds only {names}, so the site leaves out the rest of this.', { holder: titleCase(holder.label), names: onlyNames(only) }));
		}
	}

	// A block written as another kind than it's registered (D-531),
	// anywhere, inline ones included.
	markdown.value.blocks.forEach((item, index) => {
		const described = blockFor(item.name);
		const written   = item.misplaced ? 'container' : item.kind;

		if (described !== undefined && !described.name.startsWith('markdown/') && described.kind !== written) {
			found.set(`block-${index}`, misplacedNote(described));
		}
	});

	return found;
});

// What's said of a block written as another kind than it is.
function misplacedNote(described: BlockDescription): string {
	const name = shortName(described.name);
	const how  = {
		container: t('editor_view.misplaced.container', 'wraps other content, written “:::{name}” … “:::”', { name }),
		leaf: t('editor_view.misplaced.leaf', 'goes on a line of its own, written “::{name}”', { name }),
		inline: t('editor_view.misplaced.inline', 'goes inside a sentence, written “:{name}[…]”', { name })
	}[described.kind];

	return t('editor_view.misplaced.note', "{block} {how}, so the site doesn't show it as it's written here.", { block: titleCase(described.label), how });
}

const strayNote = computed(() => selection.value === null ? undefined : strayItems.value.get(`${selection.value.kind}-${selection.value.index}`));

// How many of what it holds are in it: images, for a gallery, and
// tracks, for a playlist (D-708).
const held = computed(() => {
	const item = block.value;

	if (item === undefined) {
		return 0;
	}

	return selected.value?.only?.includes('image') === true
		? markdown.value.images.filter((image) => image.start > item.start && image.end < item.end).length
		: markdown.value.blocks.filter((found) => found.start > item.start && found.end < item.end && blockFor(found.name)?.name === `${CORE}/audio`).length;
});

// The rows pinned to the end of the Document tab (D-608) each open a
// level down in its place, with a path back: the Outline, what links
// here, the archive the page introduces, and its last changes, with Undo
// (D-758). Picking from the Outline, moving the caret, changing tabs, or
// Escape puts the fields back.
type Drill = 'outline' | 'linked' | 'archive' | 'activity';

const drill = ref<Drill | null>(null);

// A field group past the first level opens in the panel's place too, a
// level at a time, each with a path back (D-768).
const levels      = ref<FieldLevel[]>([]);
const level       = computed(() => levels.value.at(-1) ?? null);
const levelField  = computed(() => level.value === null ? null : fieldAt(level.value));
const levelPrefix = computed(() => level.value === null ? 'field-' : `field-${[level.value.root, ...level.value.steps.slice(0, -1)].join('-')}-`);

provide(OPEN_LEVEL, (at) => {
	void openLevel(at);
});

watch([caret, tab], () => {
	drill.value  = null;
	levels.value = [];
});

// The field a level is: the form field, then down through its groups.
function fieldAt(at: FieldLevel): FieldDescription | null {
	let found = fields.value.find((field) => field.name === at.root) ?? null;

	for (const step of at.steps) {
		if (found !== null && typeof step === 'string') {
			found = fieldsInside(found).find((child) => child.name === step) ?? null;
		}
	}

	return found;
}

async function openLevel(at: FieldLevel): Promise<void> {
	levels.value = [...levels.value, at];
	await nextTick();
	document.querySelector('.editor__side-inner')?.scrollTo({ top: 0 });
	document.getElementById('editor-level-back')?.focus();
}

// The open level's state, read and written through its form field.
const levelState = computed(() => level.value === null ? '' : stateAt(form.value[level.value.root], level.value.steps));

function setLevel(value: FormValue): void {
	const at = level.value;

	if (at !== null) {
		form.value[at.root] = withStateAt(form.value[at.root], at.steps, value);
	}
}

function pickInLevel(target: FieldDescription, steps: FieldStep[]): void {
	if (level.value !== null) {
		pickForField(target, level.value.root, steps);
	}
}

// Back a level, to the row that opened it.
async function closeLevel(): Promise<void> {
	const from = levels.value.at(-1);

	levels.value = levels.value.slice(0, -1);
	await nextTick();

	if (from !== undefined) {
		document.getElementById(`field-${[from.root, ...from.steps].join('-')}`)?.focus();
	}
}

// The entry's last changes, loaded when its Activity row opens.
const changes      = ref<ActivityChange[] | null>(null);
const changesError = ref('');

async function loadChanges(): Promise<void> {
	changesError.value = '';

	try {
		changes.value = entry.value?.id ? (await loadActivity({ subject: entry.value.id })).changes : [];
	} catch (caught) {
		changesError.value = errorMessage(caught, t('editor_view.errors.changes_not_loaded', "Its changes couldn't be loaded."));
	}
}

// After an Undo there, the entry is read again, as it is now.
async function changedHere(): Promise<void> {
	await loadChanges();

	if (entry.value?.id) {
		entry.value = await request<EntryDetail>('GET', entryPath(entry.value.id));
	}
}

// What the Activity row says: who made the latest change, when an app
// did, so it's known an app was there.
const activityLine = computed(() => {
	const by = entry.value?.changedBy;

	return by ? t('editor_view.activity.changed_by', 'Changed by {client} ({account}) {when}', { client: by.client, account: by.account, when: formatWhenInline(by.time) }) : t('editor_view.activity.line', 'Who changed it, and Undo');
});

async function openDrill(which: Drill): Promise<void> {
	if (which === 'activity') {
		changes.value = null;
		void loadChanges();
	}

	sideOpen.value = true;
	tab.value      = 'document';
	await nextTick();
	drill.value = which;
	await nextTick();
	document.querySelector('.editor__side-inner')?.scrollTo({ top: 0 });
	document.getElementById('editor-drill-back')?.focus();
}

async function closeDrill(): Promise<void> {
	const from = drill.value;

	drill.value = null;
	await nextTick();
	document.getElementById(`editor-row-${from}`)?.focus();
}

function showOutline(): Promise<void> {
	return openDrill('outline');
}

// Escape goes back from a level down.
function leaveDrill(event: KeyboardEvent): void {
	if (levels.value.length > 0) {
		event.stopPropagation();
		void closeLevel();
	} else if (drill.value !== null) {
		event.stopPropagation();
		void closeDrill();
	}
}

const quotedTitle = computed(() => t('editor_view.quoted', '“{title}”', { title: entry.value?.title || t('editor_view.untitled', 'Untitled') }));

// What links here, in all, and the row's line: up to three named, else
// counted by relationship, so it stays one line whatever the numbers.
// How many entries an archive page lists, with the listed type's noun.
const listedCount = computed(() => {
	const introduces = entry.value?.introduces;

	return introduces ? t('editor_view.facts.listed', '{count, plural, one {# {item}} other {# {items}}}', { count: introduces.listed, item: introduces.item, items: introduces.items }) : '';
});

const linkedGroups = computed(() => entry.value?.linkedFrom ?? []);
const linkedTotal  = computed(() => linkedGroups.value.reduce((sum, group) => sum + group.count, 0));
const linkedDrafts = computed(() => linkedGroups.value.reduce((sum, group) => sum + group.drafts, 0));
const linkedLine   = computed(() => {
	if (linkedTotal.value === 0) {
		return t('editor_view.linked.nothing', 'Nothing links here.');
	}

	const titles = [...new Set(linkedGroups.value.flatMap((group) => group.entries.map((item) => item.title || t('editor_view.untitled', 'Untitled'))))];

	return linkedTotal.value <= 3 && titles.length <= 3
		? series(titles)
		: linkedGroups.value.map((group) => t('editor_view.linked.group_count', '{relation} {count}', { relation: titleCase(group.relation), count: group.count })).join(' · ');
});

// A Linked From group past 8 shows the rest in place up to 50 (D-608);
// past that, it links to the linking type's list, filtered to this entry.
const LINKED_SHOWN = 8;
const expanded     = ref(new Set<string>());

function linkedShown(group: LinkedGroup): LinkedGroup['entries'] {
	return expanded.value.has(group.key) ? group.entries : group.entries.slice(0, LINKED_SHOWN);
}

function typeNamed(name: string): ContentTypeSummary | undefined {
	return types.value.find((type) => type.name === name);
}

watch(() => entry.value?.id, () => {
	expanded.value = new Set();
});

// The archive an index page or a relation archive's page introduces.
const archiveLine = computed(() => {
	const page = entry.value?.archive;

	if (page === null || page === undefined) {
		return labels.value.plural;
	}

	return page.target === null ? titleCase(page.label) : `${titleCase(page.label)} › ${page.targetTitle ?? page.target}`;
});

// What the page is, and what it can't be, at the top of its level down.
const archiveNote = computed(() => {
	const detail = entry.value;

	if (detail === null) {
		return '';
	}

	const page   = detail.archive;
	const what   = page === null
		? (detail.homepage
			? t('editor_view.archive.introduces_homepage', "This page introduces the archive of every {item}. It's also the site's homepage, at /.", { item: labels.value.item })
			: t('editor_view.archive.introduces', 'This page introduces the archive of every {item}.', { item: labels.value.item }))
		: (page.target === null
			? t('editor_view.archive.introduces_list', 'This page introduces the list of {label} on {plural}.', { label: page.label.toLowerCase(), plural: labels.value.plural })
			: t('editor_view.archive.introduces_one', "This page introduces one {item}'s archive.", { item: labelsOf(page.targetType ?? '').item }));

	if (!detail.can.duplicate && !detail.can.delete) {
		return t('editor_view.archive.cant_duplicate_or_trash', "{what} It can't be duplicated or moved to the trash.", { what });
	}

	if (!detail.can.duplicate) {
		return t('editor_view.archive.cant_duplicate', "{what} It can't be duplicated.", { what });
	}

	return detail.can.delete ? what : t('editor_view.archive.cant_trash', "{what} It can't be moved to the trash.", { what });
});

// What the archive shows when the page isn't there.
const archiveWithout = computed(() => {
	const page = entry.value?.archive;

	if (page === null || page === undefined) {
		return t('editor_view.archive.without_page', 'The archive lists its {items} with no title or text of its own.', { items: labels.value.items });
	}

	if (page.target === null) {
		return t('editor_view.archive.without_list_page', 'The list is headed “{label}”, with nothing before it.', { label: titleCase(page.label) });
	}

	return page.targetType === profileType.value
		? t('editor_view.archive.shows_profile', "The archive shows {name}'s profile instead.", { name: page.targetTitle ?? page.target })
		: t('editor_view.archive.shows_own_text', "The archive shows {name}'s own text instead.", { name: page.targetTitle ?? page.target });
});

/**
 * Where the caret goes for an element: in a block's head, after an
 * image, or at the start of a block's first line.
 */
function caretFor(element: ElementRef): number {
	if (element.kind === 'block') {
		const item = markdown.value.blocks[element.index];

		return item === undefined ? 0 : blockHead(body.value, item).end;
	}

	if (element.kind === 'image') {
		return markdown.value.images[element.index]?.end ?? 0;
	}

	const found = allMarkdownBlocks.value[element.index];
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
	drill.value    = null;
	bodyEditor.value?.focusAt(at);
	chosen.value   = { element: { kind: element.kind, index: element.index }, caret: at };
}

// A crumb opens what it names: the root, the entry's own tab.
function openCrumb(item: OutlineItem | null): void {
	if (item === null) {
		sideOpen.value = true;
		tab.value      = 'document';
		drill.value    = null;

		return;
	}

	select(item);
}

function outlineText(item: ElementRef): string {
	return excerpt(body.value, markdown.value, allMarkdownBlocks.value, item);
}

// A row of the Outline or a Content group: what the element is, and its
// excerpt.
function rowOf(item: ElementRef): OutlineRow {
	return { name: nameOf(item), icon: iconOf(item), text: outlineText(item), placed: placed(item), stray: strayItems.value.get(`${item.kind}-${item.index}`) };
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

function removeBlock(): void {
	const item = block.value;

	if (item !== undefined) {
		bodyEditor.value?.apply(withoutBlock(body.value, item));
		toast(t('editor_view.toasts.removed_block', 'Removed the {block}', { block: inSentence(blockLabel(item)) }), { kind: 'danger' });
	}
}

function removeImage(): void {
	const item = image.value;

	if (item !== undefined) {
		bodyEditor.value?.apply(withoutImage(body.value, item));
		toast(t('editor_view.toasts.removed_image', 'Removed the image'), { kind: 'danger' });
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
const holder = computed<BlockDescription | undefined>(() => {
	for (const item of [...path.value].reverse()) {
		const found = item.kind === 'block' ? blockFor(markdown.value.blocks[item.index]?.name ?? '') : undefined;

		if (found?.only) {
			return found;
		}
	}

	return undefined;
});

// Nothing a container holds only some of is text, so the sentence tools
// go inside one.
const sentence  = computed(() => inText.value && inProse(markdown.value, caret.value) && holder.value === undefined);
const emphasis  = computed<Record<Emphasis, boolean>>(() => sentence.value ? emphasisAt(markdown.value, Math.min(caret.value, extent.value), Math.max(caret.value, extent.value)) : { strong: false, em: false, strike: false, marked: false });

// Moving the element the caret is in among its siblings (admin.md §8,
// Reordering; D-314): a list item within its list, a paragraph within its
// callout, a top-level element among the rest. A term or definition
// moves its whole definition list, since one alone would come apart
// from what it defines.
const mover = computed<OutlineItem | undefined>(() => {
	const last   = path.value.at(-1);
	const parent = path.value.at(-2);
	const kind   = (item: OutlineItem | undefined): string | undefined => item?.kind === 'markdown' ? allMarkdownBlocks.value[item.index]?.kind : undefined;

	return parent !== undefined && kind(parent) === 'definitions' ? parent : last;
});

const runs      = computed(() => mover.value === undefined ? [] : siblingRuns(body.value, items.value, mover.value.parent));
const moveIndex = computed(() => runIndex(runs.value, mover.value));
const moveName  = computed(() => mover.value === undefined ? '' : nameOf(mover.value));

function moveElement(up: boolean): void {
	const item   = mover.value;
	let change   = moveIndex.value === -1 ? null : movedElement(body.value, runs.value, moveIndex.value, up, { from: Math.min(caret.value, extent.value), to: Math.max(caret.value, extent.value) });

	// A numbered list counts on from the number it started at.
	if (change !== null && item?.kind === 'markdown' && allMarkdownBlocks.value[item.index]?.kind === 'item') {
		const list  = items.value[item.parent];
		const first = list?.kind === 'markdown' ? markdown.value.lines[allMarkdownBlocks.value[list.index]?.first ?? -1]?.text : undefined;
		const start = /^ *(\d{1,9})[.)]/.exec(first ?? '')?.[1];

		change = renumberedAt(change, change.from, start === undefined ? undefined : Number.parseInt(start, 10));
	}

	if (change !== null) {
		void bodyEditor.value?.change(change);
	}
}

// The link form (⌘K; LinkForm): Text and Address, filled from the
// selection, the word at the caret, or the link the caret is in.
const linkOpen   = ref(false);
const linkTarget = ref<{ start: number; end: number; link: MarkdownLink | null; text: string; url: string } | null>(null);
const linkWrap   = ref<HTMLElement | null>(null);

function openLink(): void {
	if (!sentence.value) {
		return;
	}

	closeOverlays();

	const start = Math.min(caret.value, extent.value);
	const end   = Math.max(caret.value, extent.value);
	const link  = linkAt(body.value, start, end);

	if (link !== null) {
		linkTarget.value = { start: link.start, end: link.end, link, text: linkLabel(link.label), url: link.url };
	} else {
		const word     = start === end ? wordAt(body.value, start) : null;
		const from     = word?.start ?? start;
		const to       = word?.end ?? end;
		const selected = body.value.slice(from, to);
		const address  = /^(?:https?:\/\/|mailto:|\/)\S*$/.test(selected.trim()) && selected.trim() !== '';

		linkTarget.value = { start: from, end: to, link: null, text: address ? '' : selected, url: address ? selected.trim() : '' };
	}

	linkOpen.value = true;
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

function applyLink(text: string, url: string): void {
	const target = linkTarget.value;

	if (target === null) {
		return;
	}

	linkOpen.value = false;
	bodyEditor.value?.change(withLink(body.value, target.start, target.end, text.trim() === '' ? url.trim() : text, url, target.link));
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
		openLink();
	}
}

// A press outside the link form closes it.
onPressOutside([linkWrap], () => linkOpen.value, () => closeLink(false));

// The mention form (D-493; MentionForm): a profile, found by name,
// written as `@slug` where the caret is (in place of a selection, or the
// `@word` it's in).
const canMention  = computed(() => config.mentions && profileType.value !== null);
const mentionOpen = ref(false);
const mentionWrap = ref<HTMLElement | null>(null);
const mentionSpot = ref<{ start: number; end: number; query: string } | null>(null);

// The profiles typing `@` suggests (D-498): published ones, whose names
// or slugs match.
async function findPeople(query: string): Promise<MentionSuggestion[]> {
	const type = profileType.value;

	if (type === null) {
		return [];
	}

	const found = await loadReferences(type, { search: query, limit: 8 });

	return found.items.filter((item) => item.status === 'published').map((item) => ({ slug: item.slug, title: item.title }));
}

function openMention(): void {
	if (!sentence.value || !canMention.value) {
		return;
	}

	closeOverlays();

	const start = Math.min(caret.value, extent.value);
	const end   = Math.max(caret.value, extent.value);
	// The `@word` the caret is in, which a pick finishes.
	const typed = start === end ? /@[\p{L}\p{N}_-]*$/u.exec(body.value.slice(0, start)) : null;

	mentionSpot.value = typed === null ? { start, end, query: body.value.slice(start, end).trim() } : { start: start - typed[0].length, end, query: typed[0].slice(1) };
	mentionOpen.value = true;
}

function closeMention(refocus = true): void {
	if (!mentionOpen.value) {
		return;
	}

	mentionOpen.value = false;

	if (refocus) {
		bodyEditor.value?.focusAt(mentionSpot.value?.end ?? caret.value);
	}
}

function chooseMention(item: ReferenceItem): void {
	const spot = mentionSpot.value;

	if (spot === null) {
		return;
	}

	const text = `@${item.slug}`;

	mentionOpen.value = false;
	bodyEditor.value?.change({ text: body.value.slice(0, spot.start) + text + body.value.slice(spot.end), from: spot.start + text.length, to: spot.start + text.length });
}

// A press outside the mention form closes it.
onPressOutside([mentionWrap], () => mentionOpen.value, () => closeMention(false));

/**
 * One thing is open at a time (admin.md §8, The toolbar): opening any of
 * the toolbar's panels, menus, or modals closes the others. Menus close
 * themselves on the press that opens something else.
 */
function closeOverlays(): void {
	closePanel(false);
	closeLink(false);
	closeMention(false);
}

// Bleed (admin.md §8, Bleed; D-313): how far a top-level element reaches
// past the text column. Base is no class at all; the theme names the
// other two.
type BleedName = 'base' | 'wide' | 'full';

const BLEEDS: Record<BleedName, { label: string; icon: IconName; text: string }> = {
	base: { label: t('editor_view.bleed.base', 'Base'), icon: 'bleed-base', text: t('editor_view.bleed.base_text', 'At the width of the text column. It writes no class at all.') },
	wide: { label: t('editor_view.bleed.wide', 'Wide'), icon: 'bleed-wide', text: t('editor_view.bleed.wide_text', 'Wider than the text, as much of the margin as the theme gives.') },
	full: { label: t('editor_view.bleed.full', 'Full'), icon: 'bleed-full', text: t('editor_view.bleed.full_text', "Edge to edge, ignoring the text's width.") }
};

// The element bleed acts on: a top-level element, not an inline one.
const bleedTarget = computed<ElementRef | null>(() => {
	const current = selection.value;
	const item    = current === null ? undefined : items.value.find((entry) => sameElement(entry, current));

	return item === undefined || item.depth !== 0 ? null : current;
});

// An element's classes and id, wherever its syntax keeps them.
function partsOf(element: ElementRef): { classes: string[]; id: string } {
	if (element.kind === 'block') {
		const item = markdown.value.blocks[element.index];

		return attributeParts(item === undefined ? '' : (blockHead(body.value, item).attributes?.text ?? ''));
	}

	if (element.kind === 'image') {
		return attributeParts(markdown.value.images[element.index]?.attributes?.text ?? '');
	}

	return attributeParts(allMarkdownBlocks.value[element.index]?.attributes?.text ?? '');
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

	if (target.kind === 'block') {
		const item = markdown.value.blocks[target.index];

		edit = item === undefined ? null : withBlockParts(body.value, item, next, id);
	} else if (target.kind === 'image') {
		const item = markdown.value.images[target.index];

		edit = item === undefined ? null : withImage(item, { attributes: withParts(item.attributes?.text ?? '', next, id) });
	} else {
		const item = allMarkdownBlocks.value[target.index];

		edit = item === undefined ? null : withMarkdownBlockParts(body.value, markdown.value, item, next, id);
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

	await copyText(new URL(url, config.site.url).href, t('editor_view.the_link', 'the link'));
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

		toast(t('editor_view.toasts.duplicated', 'Duplicated as a draft: “{title}”', { title: copy.title || t('editor_view.untitled', 'Untitled') }));
	} catch (caught) {
		error.value = errorMessage(caught, t('editor_view.errors.not_duplicated', "The {noun} couldn't be duplicated.", { noun: noun.value }));
	}
}

// Arrow keys move between the two tabs.
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
		title: t('editor_view.reload.title', 'Reload Without Saving?'),
		body: [t('editor_view.reload.body', 'Your unsaved changes to this {noun} stay in this browser only, and are offered back when the editor opens again.', { noun: noun.value })],
		confirm: t('editor_view.reload.confirm', 'Reload'),
		cancel: t('editor_view.stay', 'Stay')
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
		title: t('editor_view.leave.title', 'Leave Without Saving?'),
		body: [t('editor_view.leave.body', 'Your unsaved changes to this {noun} stay in this browser only, and are offered back when you open it here again.', { noun: noun.value })],
		confirm: t('editor_view.leave.confirm', 'Leave'),
		cancel: t('editor_view.stay', 'Stay')
	});
});

onMounted(() => {
	document.addEventListener('keydown', keydown);
	document.addEventListener('pointerdown', press, true);
	window.addEventListener('pagehide', pageHide);
	window.addEventListener('keydown', reloadKey, true);
});

onBeforeUnmount(() => {
	focusMode.value = false;
	document.removeEventListener('keydown', keydown);
	document.removeEventListener('pointerdown', press, true);
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

function fieldKey(field: FieldDescription): string {
	return `${entry.value?.revision ?? ''}-${field.name}`;
}
</script>

<template>
	<section class="editor" :class="{ 'is-side-open': sideOpen, 'is-focus': focusMode }" aria-labelledby="editor-heading">
		<h1 id="editor-heading" class="visually-hidden" tabindex="-1">{{ editTitle }}</h1>

		<header class="editor__head">
			<template v-if="entry">
				<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': panelOpen }" :title="t('editor_view.toolbar.blocks_title', 'Blocks ( / )')" aria-controls="editor-blocks" :aria-expanded="panelOpen" @click="togglePanel">
					<AdminIcon name="plus" />
					<span class="visually-hidden">{{ t('editor_view.commands.block', 'Insert a block') }}</span>
				</button>
				<MenuButton v-if="uploads" button-class="button button--ghost editor__tool editor__wide" :label="t('editor_view.commands.media', 'Insert media')" align="start" floating @open="closeOverlays">
					<template #button>
						<AdminIcon name="image" /><AdminIcon name="chevron-down" class="editor__caret" />
					</template>
					<button type="button" class="menu-item menu-item--described" @click="pickMedia('library')">
						<AdminIcon name="image" />
						<span>
							<span class="menu-item__name">{{ t('editor_view.toolbar.media_library', 'Media Library') }}</span>
							<span class="menu-item__text">{{ t('editor_view.toolbar.media_library_text', 'Choose a file already in the library.') }}</span>
						</span>
					</button>
					<button type="button" class="menu-item menu-item--described" @click="pickMedia('upload')">
						<AdminIcon name="upload" />
						<span>
							<span class="menu-item__name">{{ t('editor_view.toolbar.upload', 'Upload a File') }}</span>
							<span class="menu-item__text">{{ t('editor_view.toolbar.upload_text', 'Add one from this computer, then place it.') }}</span>
						</span>
					</button>
				</MenuButton>
				<button v-else type="button" class="button button--ghost button--icon editor__tool" :title="t('editor_view.toolbar.media_title', 'Media')" aria-haspopup="dialog" @click="pickMedia()">
					<AdminIcon name="image" />
					<span class="visually-hidden">{{ t('editor_view.commands.media', 'Insert media') }}</span>
				</button>

				<template v-if="moveIndex !== -1">
					<span class="editor__divider" aria-hidden="true" />
					<span class="editor__spin" role="group" :aria-label="t('editor_view.toolbar.move', 'Move the {name}', { name: moveName.toLowerCase() })">
						<button type="button" class="editor__spin-button" :disabled="moveIndex === 0" :title="t('editor_view.toolbar.move_up_title', 'Move the {name} up  ⌥↑', { name: moveName.toLowerCase() })" @mousedown.prevent @click="moveElement(true)">
							<AdminIcon name="chevron-up" />
							<span class="visually-hidden">{{ t('editor_view.toolbar.move_up', 'Move up') }}</span>
						</button>
						<button type="button" class="editor__spin-button" :disabled="moveIndex === runs.length - 1" :title="t('editor_view.toolbar.move_down_title', 'Move the {name} down  ⌥↓', { name: moveName.toLowerCase() })" @mousedown.prevent @click="moveElement(false)">
							<AdminIcon name="chevron-down" />
							<span class="visually-hidden">{{ t('editor_view.toolbar.move_down', 'Move down') }}</span>
						</button>
					</span>
				</template>

				<template v-if="sentence">
					<span class="editor__divider" aria-hidden="true" />
					<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': emphasis.strong }" :aria-pressed="emphasis.strong" :title="t('editor_view.toolbar.bold_title', 'Bold  ⌘B')" @mousedown.prevent @click="bodyEditor?.emphasis('strong')">
						<AdminIcon name="bold" />
						<span class="visually-hidden">{{ t('editor_view.commands.bold', 'Bold') }}</span>
					</button>
					<button type="button" class="button button--ghost button--icon editor__tool" :class="{ 'is-on': emphasis.em }" :aria-pressed="emphasis.em" :title="t('editor_view.toolbar.italic_title', 'Italic  ⌘I')" @mousedown.prevent @click="bodyEditor?.emphasis('em')">
						<AdminIcon name="italic" />
						<span class="visually-hidden">{{ t('editor_view.commands.italic', 'Italic') }}</span>
					</button>
					<span ref="linkWrap" class="editor__pop">
						<button type="button" class="button button--ghost editor__tool editor__wide" :class="{ 'is-on': linkOpen }" :title="t('editor_view.toolbar.link_title', 'Link  ⌘K')" aria-haspopup="dialog" :aria-expanded="linkOpen" aria-controls="editor-link" @click="toggleLink">
							<AdminIcon name="link" /><AdminIcon name="chevron-down" class="editor__caret" />
							<span class="visually-hidden">{{ t('editor_view.commands.link', 'Link') }}</span>
						</button>
						<LinkForm v-if="linkOpen && linkTarget" :start="linkTarget" :editing="linkTarget.link !== null" @apply="applyLink" @remove="removeLink" @close="closeLink()" />
					</span>
					<span ref="mentionWrap" class="editor__pop">
					<MenuButton :button-class="`button button--ghost editor__tool editor__wide${emphasis.strike || emphasis.marked ? ' is-on' : ''}`" :label="t('editor_view.toolbar.more_inline', 'More inline elements')" align="start" floating @open="closeOverlays">
						<template #button>
							<AdminIcon name="baseline" /><AdminIcon name="chevron-down" class="editor__caret" />
						</template>
						<button type="button" class="menu-item" :aria-pressed="emphasis.strike" @mousedown.prevent @click="bodyEditor?.emphasis('strike')">
							<AdminIcon name="strikethrough" /><span class="menu-item__name">{{ t('editor_view.commands.strike', 'Strikethrough') }}</span><kbd class="menu-item__shortcut">⌘⇧X</kbd>
						</button>
						<button type="button" class="menu-item" :aria-pressed="emphasis.marked" @mousedown.prevent @click="bodyEditor?.emphasis('marked')">
							<AdminIcon name="highlighter" /><span class="menu-item__name">{{ t('editor_view.commands.mark', 'Highlight') }}</span><kbd class="menu-item__shortcut">⌘⇧H</kbd>
						</button>
						<button type="button" class="menu-item" @mousedown.prevent @click="bodyEditor?.code()">
							<AdminIcon name="code" /><span class="menu-item__name">{{ t('editor_view.commands.code', 'Inline code') }}</span><kbd class="menu-item__shortcut">⌘E</kbd>
						</button>
						<button v-if="canMention" type="button" class="menu-item" aria-haspopup="dialog" @mousedown.prevent @click="openMention">
							<AdminIcon name="at-sign" /><span class="menu-item__name">{{ t('editor_view.commands.mention', 'Mention') }}</span>
						</button>
						<template v-if="inlineBlocks.length">
							<div class="menu-divider" />
							<button v-for="described in inlineBlocks" :key="described.name" type="button" class="menu-item menu-item--described" @click="chooseInline(described)">
								<AdminIcon :name="blockIcon(described)" />
								<span>
									<span class="menu-item__name">{{ described.label }}<template v-if="described.source"> · {{ described.source.label }}</template></span>
									<span v-if="described.description" class="menu-item__text">{{ described.description }}</span>
								</span>
							</button>
						</template>
					</MenuButton>
					<MentionForm v-if="mentionOpen && mentionSpot" :start="mentionSpot" @choose="chooseMention" @close="closeMention()" />
					</span>
					<button type="button" class="button button--ghost button--icon editor__tool" :title="t('editor_view.toolbar.icon_title', 'Icon')" aria-haspopup="dialog" @click="openIcons">
						<AdminIcon name="shapes" />
						<span class="visually-hidden">{{ t('editor_view.commands.icon', 'Insert an icon') }}</span>
					</button>
				</template>

				<template v-if="bleedTarget">
					<span class="editor__divider" aria-hidden="true" />
					<MenuButton :button-class="`button button--ghost editor__tool editor__wide${bleed === 'base' ? '' : ' is-on'}`" :label="t('editor_view.toolbar.bleed', 'Bleed: {name}', { name: BLEEDS[bleed].label })" align="start" floating @open="closeOverlays">
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
			<span class="visually-hidden" aria-live="polite">{{ saveState }}</span>
			<template v-if="entry">
				<button v-if="secondary && !secondary.status" type="button" class="button button--ghost button--small" :disabled="blocked || idle(secondary)" :title="waiting ? t('editor_view.save_state.offline', 'Waiting for a connection') : t('editor_view.toolbar.save_title', 'Save (⌘S)')" @click="save()">{{ buttonLabel(secondary) }}</button>
				<button type="button" class="button button--ghost button--icon" :class="{ 'is-on': sideOpen }" :title="t('editor_view.toolbar.settings_title', 'Settings (⌘/)')" aria-controls="editor-settings" :aria-expanded="sideOpen" @click="toggleSide">
					<AdminIcon name="panel-right" />
					<span class="visually-hidden">{{ t('editor_view.toolbar.settings', 'Settings') }}</span>
				</button>
				<button v-if="primary" type="button" class="button button--primary button--small" :disabled="blocked || idle(primary)" :title="waiting ? t('editor_view.save_state.offline', 'Waiting for a connection') : undefined" @click="save(primary.status)">{{ buttonLabel(primary) }}</button>
				<MenuButton button-class="button button--ghost button--icon" :label="t('editor_view.toolbar.more_actions', 'More actions')">
					<template #button>
						<AdminIcon name="ellipsis-vertical" />
					</template>
					<p class="menu-heading">{{ t('editor_view.menu.view_heading', 'View') }}</p>
					<button type="button" class="menu-item" @click="toggleSide">
						<AdminIcon name="panel-right" />{{ sideOpen ? t('editor_view.menu.hide_settings_panel', 'Hide the settings panel') : t('editor_view.menu.settings_panel', 'Settings panel') }}<kbd class="menu-kbd">⌘/</kbd>
					</button>
					<button type="button" class="menu-item" @click="showOutline">
						<AdminIcon name="list" />{{ t('editor_view.commands.outline', 'Outline') }}
					</button>
					<button type="button" class="menu-item" @click="toggleFocus">
						<AdminIcon name="maximize-2" />{{ focusMode ? t('editor_view.commands.leave_focus', 'Leave focus mode') : t('editor_view.commands.focus', 'Focus mode') }}<kbd class="menu-kbd">⌘⇧F</kbd>
					</button>
					<button v-if="entry.id !== null && !(entry.url && entry.status === 'published')" type="button" class="menu-item" @click="preview">
						<AdminIcon name="eye" />{{ t('editor_view.menu.preview', 'Preview') }}
					</button>
					<a v-if="entry.url && entry.status === 'published'" class="menu-item" :href="entry.url" target="_blank" rel="noopener">
						<AdminIcon name="external-link" />{{ t('editor_view.menu.view', 'View') }}<span class="visually-hidden"> {{ t('editor_view.menu.view_hidden', 'the live {noun} (new tab)', { noun }) }}</span>
					</a>
					<p class="menu-heading">{{ t('editor_view.menu.entry_heading', 'Entry') }}</p>
					<button v-if="secondary && secondary.status" type="button" class="menu-item" :disabled="blocked" @click="save(secondary.status)">
						<AdminIcon name="file-text" />{{ secondary.label }}
					</button>
					<button v-if="entry.url && entry.status === 'published'" type="button" class="menu-item" @click="copyLink">
						<AdminIcon name="link" />{{ t('editor_view.menu.copy_link', 'Copy link') }}
					</button>
					<button v-if="entry.can.duplicate && !entry.type.terms" type="button" class="menu-item" @click="duplicate">
						<AdminIcon name="copy" />{{ t('editor_view.menu.duplicate', 'Duplicate') }}
					</button>
					<template v-if="entry.can.delete">
						<div class="menu-divider" />
						<button type="button" class="menu-item menu-item--danger" @click="trash">
							<AdminIcon name="trash-2" />{{ t('editor_view.commands.trash', 'Move to trash') }}
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
			<p>{{ t('editor_view.offer.text', 'Your unsaved changes to this {noun} from {when} were kept in this browser when you left. Restore them to carry on where you were.', { noun, when: siteDateTime(offer.kept) }) }}</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="discard">{{ t('editor_view.offer.discard', 'Throw Them Away') }}</button>
				<button type="button" class="button button--small button--primary" @click="restore">{{ t('editor_view.offer.restore', 'Restore Them') }}</button>
			</p>
		</div>

		<div v-if="failure" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" />
			<p>{{ t('editor_view.failure', "{safe}, but they weren't saved. {message}", { safe, message: failure.message }) }}</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="retry">{{ t('common.retry', 'Try Again') }}</button>
			</p>
		</div>

		<!-- A target already full (D-608): the limit is the other side's,
		     so the bar names it and offers it, in a new tab. -->
		<div v-if="fullNow" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" />
			<p><MessageText :text="t('editor_view.full_notice', '{safe}, but they weren\'t saved: {target} already has {count, plural, one {# {item}} other {# {items}}}, the most it takes. Choose another, or take one out of {title} first.', { safe, count: fullNow.target.max, item: labels.item, items: labels.items, title: fullNow.target.title })"><template #target><strong>{{ fullNow.target.title }}</strong></template></MessageText></p>
			<p v-if="fullNow.target.id" class="editor__notice-buttons">
				<a class="button button--small" :href="router.resolve(entryRoute({ id: fullNow.target.id, type: fullNow.target.type })).href" target="_blank" rel="noopener">{{ t('editor_view.open_target', 'Open {title}', { title: fullNow.target.title }) }}</a>
			</p>
		</div>

		<div v-if="attempted && missing.length" class="editor__notice editor__notice--danger" role="alert">
			<AdminIcon name="triangle-alert" />
			<p>{{ t('editor_view.missing', '{count, plural, one {# required field is empty, so it wasn\'t published: {fields}. Fill it in, or save it as a draft.} other {# required fields are empty, so it wasn\'t published: {fields}. Fill them in, or save it as a draft.} }', { count: missing.length, fields: missing.map((item) => item.label).join(', ') }) }}</p>
			<p class="editor__notice-buttons">
				<button type="button" class="button button--small" @click="showMissing">{{ t('editor_view.show_me', 'Show Me') }}</button>
			</p>
		</div>

		<ConflictBar v-if="conflict" :noun="noun" :safe="safe" :theirs="conflict.theirs" :loading="conflict.loading" :compare="differences" @keep-theirs="keepTheirs" @keep-mine="keepMine" @retry="fetchTheirs" />

		<div class="editor__body">
			<aside id="editor-blocks" class="editor__inserter" aria-labelledby="block-panel-heading" :inert="!panelOpen">
				<BlockPanel
					ref="panel"
					v-model:query="panelQuery"
					:blocks="panelBlocks"
					:note="panelNote"
					:failed="blocksFailed"
					:slash="panelSlash"
					@choose="chooseBlock"
					@close="closePanel()"
				/>
			</aside>

			<div class="editor__main">
				<div class="editor__scroll" data-scroller>
					<div v-if="entry" class="editor__doc">
						<label class="visually-hidden" for="editor-title">{{ t('editor_view.title', 'Title') }}</label>
						<textarea
							id="editor-title"
							ref="titleField"
							v-model="title"
							class="editor__title"
							rows="1"
							:placeholder="t('editor_view.untitled', 'Untitled')"
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
							:people="canMention ? findPeople : undefined"
							:label="t('editor_view.body_label', 'Body (Markdown)')"
							:placeholder="t('editor_view.body_placeholder', 'Write in Markdown. Type / on an empty line, or use +, to add a block.')"
							v-model:extent="extent"
							:parsed="markdown"
							:markdown-blocks="allMarkdownBlocks"
							:slash-open="panelOpen && panelSlash"
							:block="selection?.kind === 'block' ? selection.index : -1"
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
						<p class="visually-hidden" role="status">{{ t('editor_view.loading_noun', 'Loading the {noun}…', { noun }) }}</p>
					</div>
				</div>

				<footer v-if="entry" class="editor__foot">
					<nav class="editor__crumbs" :aria-label="t('editor_view.crumbs_label', 'Where the cursor is')">
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
							<AdminIcon name="x" />{{ t('editor_view.leave_focus_mode', 'Leave Focus Mode') }}
						</button>
						<span>{{ t('editor_view.words', '{count, plural, one {# word} other {# words} }', { count: words }) }}</span>
						<span class="editor__sep" aria-hidden="true">·</span>
						<span>{{ t('editor_view.min_read', '{count} min read', { count: Math.max(1, Math.round(words / 220)) }) }}</span>
					</span>
				</footer>
			</div>

			<aside id="editor-settings" class="editor__side" :aria-label="t('editor_view.toolbar.settings', 'Settings')" :inert="!sideOpen">
				<div class="editor__side-inner">
					<div class="editor__tabs">
						<TabBar v-model="tab" class="editor__tablist" :tabs="[{ key: 'document', label: typeName }, { key: 'element', label: tabName }]" :label="t('editor_view.toolbar.settings', 'Settings')" prefix="editor">
							<template #tab="{ tab: item }">
								<template v-if="item.key === 'document'"><TypeIcon v-if="entryType" :type="entryType" /><AdminIcon v-else name="file-text" />{{ typeName }}</template>
								<template v-else><AdminIcon :name="tabIcon" /><span class="editor__tab-name">{{ tabName }}</span></template>
							</template>
						</TabBar>
						<button type="button" class="button button--ghost button--icon editor__side-close" @click="sideOpen = false">
							<AdminIcon name="x" />
							<span class="visually-hidden">{{ t('editor_view.close_settings', 'Close the settings') }}</span>
						</button>
					</div>

					<div v-if="entry" v-show="tab === 'document'" id="editor-panel-document" class="editor__panel" role="tabpanel" aria-labelledby="editor-tab-document" @keydown.esc="leaveDrill">
						<template v-if="drill === null && level === null">
							<OptionsGroup :heading="t('editor_view.groups.publish', 'Publish')">
								<ImagePreview v-if="imageField && (!term || form[imageField.name])" :src="String(form[imageField.name] ?? '')" wide bare :noun="t('editor_view.featured_image', 'the featured image')" @pick="pickForField(imageField)" @remove="form[imageField.name] = ''" />
								<SummaryPopover v-if="summaryField" :id="`field-${summaryField.name}`" :model-value="String(form[summaryField.name] ?? '')" :error="errorFor(summaryField.name)" @update:model-value="form[summaryField.name] = $event" />
								<dl class="settings">
									<div class="settings__row">
										<dt>{{ t('editor_view.settings.status', 'Status') }}</dt>
										<dd>
											<MenuButton v-if="statusOptions.length > 1" :button-class="`settings__value settings__value--${entry.status}`" :label="t('editor_view.settings.status_value', 'Status: {value}', { value: statusLook(entry.status).label })" align="start" floating>
												<template #button>
													<AdminIcon :name="statusLook(entry.status).icon" /><span class="settings__text">{{ statusLook(entry.status).label }}</span><AdminIcon name="chevron-down" class="settings__caret" />
												</template>
												<button v-for="option in statusOptions" :key="option.status" type="button" class="menu-item menu-item--described" :aria-current="option.status === entry.status ? 'true' : undefined" :disabled="option.status !== entry.status && blocked" @click="option.status === entry.status || save(option.status)">
													<AdminIcon :name="option.icon" />
													<span>
														<span class="menu-item__name">{{ option.label }}</span>
														<span class="menu-item__text">{{ option.text }}</span>
													</span>
												</button>
											</MenuButton>
											<span v-else class="settings__static" :class="`settings__value--${entry.status}`"><AdminIcon :name="statusLook(entry.status).icon" />{{ statusLook(entry.status).label }}</span>
										</dd>
									</div>
									<div v-if="visibilityField" class="settings__row">
										<dt>{{ t('editor_view.settings.visibility', 'Visibility') }}</dt>
										<dd>
											<MenuButton button-class="settings__value" :label="t('editor_view.settings.visibility_value', 'Visibility: {value}', { value: visibilityNames[visibility].label })" align="start" floating>
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
										<dt><label for="editor-date">{{ future ? t('editor_view.settings.goes_live', 'Goes live') : t('editor_view.settings.date', 'Date') }}</label></dt>
										<dd>
											<DatePicker id="editor-date" v-model="date" :invalid="Boolean(errorFor('published'))" :described-by="errorFor('published') ? 'editor-publish-help editor-date-error' : 'editor-publish-help'" />
										</dd>
									</div>
									<div v-if="entry.can.rename" class="settings__row">
										<dt><label for="editor-slug">{{ t('editor_view.settings.slug', 'Slug') }}</label></dt>
										<dd>
											<input id="editor-slug" v-model="slug" class="settings__input mono" :placeholder="fresh ? slugOf(title) : undefined" autocomplete="off" autocapitalize="none" spellcheck="false" :title="t('editor_view.settings.slug_title', 'Lowercase letters, numbers, and hyphens')" :aria-invalid="slugError ? 'true' : undefined" :aria-describedby="slugError ? 'editor-slug-help editor-slug-error' : 'editor-slug-help'">
										</dd>
									</div>
									<div v-if="subtitleField" class="settings__row">
										<dt><label :for="`field-${subtitleField.name}`">{{ t('editor_view.settings.subtitle', 'Subtitle') }}</label></dt>
										<dd>
											<input :id="`field-${subtitleField.name}`" class="settings__input" autocomplete="off" :value="String(form[subtitleField.name] ?? '')" :aria-invalid="errorFor(subtitleField.name) ? 'true' : undefined" @input="form[subtitleField.name] = ($event.target as HTMLInputElement).value">
										</dd>
									</div>
									<div v-if="entry.can.language" class="settings__row">
										<dt><label for="editor-language">{{ t('editor_view.settings.language', 'Language') }}</label></dt>
										<dd>
											<AdminSelect id="editor-language" v-model="language" :options="languageOptions" :invalid="Boolean(languageError)" :described-by="languageError ? 'editor-language-error' : undefined" plain />
										</dd>
									</div>
									<div v-if="treeParent" class="settings__row">
										<dt><label for="editor-parent">{{ t('editor_view.settings.parent', 'Parent') }}</label></dt>
										<dd>
											<AdminSelect id="editor-parent" v-model="parent" :options="parentOptions" :disabled="parentItems === null" :invalid="Boolean(parentError)" :described-by="parentError ? 'editor-parent-error' : undefined" plain />
										</dd>
									</div>
									<div v-if="parentField" class="settings__row">
										<dt><label :for="`field-${parentField.name}`">{{ t('editor_view.settings.parent', 'Parent') }}</label></dt>
										<dd>
											<ReferencePicker :id="`field-${parentField.name}`" :key="fieldKey(parentField)" :field="parentField" :self="entry.slug" branch plain :model-value="String(form[parentField.name] ?? '')" @update:model-value="form[parentField.name] = $event" />
										</dd>
									</div>
									<div v-if="positionField" class="settings__row">
										<dt><label for="editor-position">{{ t('editor_view.settings.position', 'Position') }}</label></dt>
										<dd>
											<input id="editor-position" class="settings__input mono" type="number" step="1" inputmode="numeric" :placeholder="t('editor_view.settings.position_placeholder', 'By title')" :value="String(form[positionField.name] ?? '')" aria-describedby="editor-position-help" @input="form[positionField.name] = ($event.target as HTMLInputElement).value">
										</dd>
									</div>
									<div v-for="single in singleFields" :key="fieldKey(single)" class="settings__row">
										<dt><label :for="`field-${single.name}`">{{ titleCase(single.relation?.label || label(single)) }}</label></dt>
										<dd>
											<ReferencePicker :id="`field-${single.name}`" :field="single" :self="entry.slug" :people="single.to === profileType" :keep-last="entry.type.byline === single.name || single.required === true" :inherited="entry.inherited?.[single.name]" :invalid="Boolean(errorFor(single.name))" plain :model-value="String(form[single.name] ?? '')" @update:model-value="form[single.name] = $event" />
										</dd>
									</div>
									<div v-if="entry.templates.length > 0" class="settings__row">
										<dt>{{ t('editor_view.settings.template', 'Template') }}</dt>
										<dd>
											<MenuButton button-class="settings__value" :label="t('editor_view.settings.template_value', 'Template: {value}', { value: templateLabel })" align="start" floating>
												<template #button>
													<AdminIcon name="layout-template" /><span class="settings__text">{{ templateLabel }}</span><AdminIcon name="chevron-down" class="settings__caret" />
												</template>
												<button v-for="option in templateOptions" :key="option.name" type="button" class="menu-item menu-item--described" :aria-current="option.name === template ? 'true' : undefined" @click="template = option.name">
													<AdminIcon name="layout-template" />
													<span>
														<span class="menu-item__name">{{ option.label }}</span>
														<span v-if="option.description !== ''" class="menu-item__text">{{ option.description }}</span>
													</span>
												</button>
											</MenuButton>
										</dd>
									</div>
								</dl>
								<p id="editor-publish-help" class="editor__group-note">{{ publishNote }}</p>
								<p v-if="errorFor('published')" id="editor-date-error" class="field__error">{{ errorFor('published') }}</p>
								<p v-if="slugError" id="editor-slug-error" class="field__error">{{ slugError }}</p>
								<p v-if="parentError" id="editor-parent-error" class="field__error">{{ parentError }}</p>
								<p v-if="languageError" id="editor-language-error" class="field__error">{{ languageError }}</p>
								<p v-if="templateError" class="field__error">{{ templateError }}</p>
								<p v-if="subtitleField && errorFor(subtitleField.name)" class="field__error">{{ errorFor(subtitleField.name) }}</p>
								<template v-for="single in singleFields" :key="`error-${fieldKey(single)}`">
									<p v-if="errorFor(single.name)" class="field__error">{{ errorFor(single.name) }}</p>
								</template>
								<p v-if="positionField" id="editor-position-help" class="visually-hidden">{{ t('editor_view.settings.position_help', 'Its place among the {items} beside it, lowest first. Without one, it follows those with one, by title.', { items: labels.plural.toLowerCase() }) }}</p>
								<template v-if="entry.status === 'published' && ((entry.can.rename && slug.trim() !== entry.slug) || moving)">
									<p id="editor-slug-help" class="editor__group-note"><MessageText :text="redirect ? t('editor_view.settings.moves_to', 'Saving moves it to {address}.') : t('editor_view.settings.moves_to_broken', 'Saving moves it to {address}, and links to the old address will stop working.')"><template #address><span class="mono">{{ slugAddress ?? t('editor_view.settings.new_address', 'a new address') }}</span></template></MessageText></p>
									<label class="checkbox">
										<input v-model="redirect" type="checkbox">
										{{ t('editor_view.settings.redirect', 'Redirect the old address here') }}
									</label>
								</template>
								<p v-else id="editor-slug-help" class="visually-hidden">{{ t('editor_view.settings.slug_help', 'The slug is lowercase letters, numbers, and hyphens, and the address ends in it.') }}</p>
								<p v-if="entry.errorPage !== null" class="editor__group-note">{{ entry.errorPage === 404 ? t('editor_view.error_page_404', "The page the site shows for error {code}, when an address doesn't exist: its title and text, in the theme's error layout. Its slug is the status. Without it, the theme's own message is shown.", { code: entry.errorPage }) : t('editor_view.error_page', "The page the site shows for error {code}: its title and text, in the theme's error layout. Its slug is the status. Without it, the theme's own message is shown.", { code: entry.errorPage }) }}</p>
								<p v-if="entry.rootPage && entry.homepage" class="editor__group-note">{{ t('editor_view.root_homepage', "The site's homepage, at /. It's the top of the page tree, so it has no parent, slug, or position. Without it, the site shows a welcome page.") }}</p>
								<p v-else-if="entry.rootPage" class="editor__group-note">
									<MessageText :text="t('editor_view.root_not_home', 'The page at {file}. The homepage shows {instead} instead, so this page isn\'t on the site.', { instead: entry.homeInstead?.toLowerCase() ?? t('editor_view.something_else', 'something else') })"><template #file><code>user/content/index.md</code></template></MessageText>
									<template v-if="entry.can.makeHomepage">{{ ' ' }}<button type="button" class="lnk editor__note-action" @click="toHomepage">{{ t('editor_view.make_homepage', 'Make homepage') }}</button></template>
								</p>
							</OptionsGroup>

							<template v-for="peopleField in peopleFields" :key="fieldKey(peopleField)">
								<OptionsGroup v-if="!term || referenceCount(peopleField)" :heading="titleCase(peopleField.relation?.label || peopleField.label || (peopleField.multiple === false ? t('editor_view.author', 'Author') : t('editor_view.authors', 'Authors')))">
									<template v-if="referenceMeta(peopleField, true).text" #hint><span :class="`is-${referenceMeta(peopleField, true).tone}`">{{ referenceMeta(peopleField, true).text }}</span></template>
									<ReferencePicker :id="`field-${peopleField.name}`" :field="peopleField" :inherited="entry.inherited?.[peopleField.name]" people :self="entry.slug" :attempted="Boolean(errorFor(peopleField.name))" :keep-last="entry.type.byline === peopleField.name || peopleField.required === true" :model-value="String(form[peopleField.name] ?? '')" :invalid="Boolean(errorFor(peopleField.name))" @update:model-value="form[peopleField.name] = $event" />
									<p v-if="errorFor(peopleField.name)" class="field__error">{{ errorFor(peopleField.name) }}</p>
								</OptionsGroup>
							</template>

							<OptionsGroup v-for="field in referenceFields" :key="fieldKey(field)">
								<template #heading><label :for="`field-${field.name}`">{{ titleCase(field.label ?? labelsOf(field.to ?? '').plural) }}</label></template>
								<template v-if="referenceMeta(field).text" #hint><span :class="`is-${referenceMeta(field).tone}`">{{ referenceMeta(field).text }}</span></template>
								<ReferencePicker :id="`field-${field.name}`" :field="field" :inherited="entry.inherited?.[field.name]" :self="entry.slug" :attempted="Boolean(errorFor(field.name))" :model-value="String(form[field.name] ?? '')" :invalid="Boolean(errorFor(field.name))" @update:model-value="form[field.name] = $event" />
								<p v-if="errorFor(field.name)" class="field__error">{{ errorFor(field.name) }}</p>
							</OptionsGroup>

							<OptionsGroup v-if="otherFields.length" :heading="t('editor_view.groups.type_fields', '{type} Fields', { type: labels.singular })">
								<FieldControl v-for="field in otherFields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" :warn="misfit(field) !== undefined" pickable narrow @update:model-value="form[field.name] = $event" @pick="(target, steps) => pickForField(target, field.name, steps)">
									<template v-if="misfit(field) !== undefined" #help="{ id }"><FieldMisfit :id="id" :label="label(field)" :message="misfit(field) ?? ''" /></template>
								</FieldControl>
							</OptionsGroup>

							<OptionsGroup v-for="set in setGroups" :key="set.name" :heading="set.label" collapsible :open="setOpen(set)">
								<template #hint>
									<span v-if="setMissing(set)" class="pill pill--danger">{{ setMissing(set) }}<span class="visually-hidden"> {{ t('editor_view.sets.required_left', '{count, plural, one {required field left empty} other {required fields left empty} }', { count: setMissing(set) }) }}</span></span>
									<span class="mono">{{ set.fields.length }}<span class="visually-hidden"> {{ t('editor_view.sets.fields', '{count, plural, one {field} other {fields} }', { count: set.fields.length }) }}</span></span>
								</template>
								<p v-if="set.description" class="editor__group-note">{{ set.description }}</p>
								<FieldControl v-for="field in set.fields" :key="fieldKey(field)" :field="field" :model-value="form[field.name] ?? ''" :error="errorFor(field.name)" :warn="misfit(field) !== undefined" pickable narrow @update:model-value="form[field.name] = $event" @pick="(target, steps) => pickForField(target, field.name, steps)">
									<template v-if="misfit(field) !== undefined" #help="{ id }"><FieldMisfit :id="id" :label="label(field)" :message="misfit(field) ?? ''" /></template>
								</FieldControl>
							</OptionsGroup>

							<OptionsGroup v-if="advancedFields.length" :heading="t('editor_view.groups.advanced', 'Advanced')" collapsible :open="advancedSet">
								<FieldControl v-for="field in advancedFields" :key="fieldKey(field)" :field="field.type === 'list' ? { ...field, control: 'code' } : field" :model-value="inline(field)" :error="errorFor(field.name)" @update:model-value="setInline(field, $event)" />
							</OptionsGroup>


							<OptionsGroup v-if="entry.violations.length" :heading="t('editor_view.groups.problems', 'Problems')">
								<template #hint>{{ t('editor_view.groups.problems_hint', 'As last saved') }}</template>
								<ul v-if="shown.length" class="editor__problems">
									<li v-for="(violation, index) in shown" :key="index">
										<span class="pill" :class="{ 'pill--danger': violation.severity === 'error', 'pill--warn': violation.severity === 'warning' }">{{ humanize(violation.severity) }}</span>
										<span><code>{{ violation.field }}</code>: {{ violation.message }}</span>
									</li>
								</ul>
								<label v-if="noticeCount" class="checkbox">
									<input v-model="notices" type="checkbox">
									{{ t('editor_view.show_notices', '{count, plural, one {Show # notice} other {Show # notices} }', { count: noticeCount }) }}
								</label>
							</OptionsGroup>

						<!-- Pinned to the end of the tab, after every field (D-608):
						     facts kept elsewhere, each opening a level down. -->
						<div class="editor__pinned">
							<button v-if="entry.introduces" id="editor-row-archive" type="button" class="level-link" @click="openDrill('archive')">
								<AdminIcon name="layers" />
								<span class="level-link__text"><strong>{{ t('editor_view.drill.archive', 'Archive Page') }}</strong><span>{{ archiveLine }}</span></span>
								<AdminIcon name="chevron-right" class="level-link__go" />
							</button>
							<button v-if="entry.id !== null" id="editor-row-linked" type="button" class="level-link" :class="{ 'is-quiet': linkedTotal === 0 }" @click="openDrill('linked')">
								<AdminIcon name="link" />
								<span class="level-link__text"><strong>{{ t('editor_view.drill.linked', 'Linked From') }}</strong><span>{{ linkedLine }}</span></span>
								<span class="level-link__count mono">{{ linkedTotal }}</span>
								<AdminIcon name="chevron-right" class="level-link__go" />
							</button>
							<button id="editor-row-outline" type="button" class="level-link" @click="showOutline">
								<AdminIcon name="list" />
								<span class="level-link__text"><strong>{{ t('editor_view.commands.outline', 'Outline') }}</strong></span>
								<span class="level-link__count mono">{{ items.length }}</span>
								<AdminIcon name="chevron-right" class="level-link__go" />
							</button>
							<button v-if="entry.id !== null" id="editor-row-activity" type="button" class="level-link" :class="{ 'is-quiet': !entry.changedBy }" @click="openDrill('activity')">
								<AdminIcon :name="entry.changedBy ? 'bot' : 'clock'" />
								<span class="level-link__text"><strong>{{ t('editor_view.drill.activity', 'Activity') }}</strong><span :title="entry.changedBy ? formatWhen(entry.changedBy.time) : undefined">{{ activityLine }}</span></span>
								<AdminIcon name="chevron-right" class="level-link__go" />
							</button>
						</div>
					</template>

					<template v-else-if="level !== null && levelField !== null">
						<button id="editor-level-back" type="button" class="level-link level-link--back" @click="closeLevel">
							<AdminIcon name="arrow-left" />
							<span class="level-link__path"><template v-for="part in [typeName, ...level.trail.slice(0, -1)]" :key="part">{{ part }} <span aria-hidden="true">/</span><span class="visually-hidden">:</span> </template><strong>{{ level.trail.at(-1) }}</strong></span>
						</button>
						<OptionsGroup>
							<FieldControl
								:field="levelField"
								:id-prefix="levelPrefix"
								:model-value="levelState"
								:error="errorFor(level.root)"
								pickable
								narrow
								:nest="0"
								:at="level"
								@update:model-value="setLevel"
								@pick="pickInLevel"
							/>
						</OptionsGroup>
					</template>

					<template v-else-if="drill !== null">
						<button id="editor-drill-back" type="button" class="level-link level-link--back" @click="closeDrill">
							<AdminIcon name="arrow-left" />
							<span class="level-link__path">{{ typeName }} <span aria-hidden="true">/</span><span class="visually-hidden">:</span> <strong>{{ { outline: t('editor_view.commands.outline', 'Outline'), linked: t('editor_view.drill.linked', 'Linked From'), archive: t('editor_view.drill.archive', 'Archive Page'), activity: t('editor_view.drill.activity', 'Activity') }[drill] }}</strong></span>
							<span v-if="drill === 'outline' || drill === 'linked'" class="level-link__count mono">{{ drill === 'outline' ? items.length : linkedTotal }}</span>
						</button>

						<OptionsGroup v-if="drill === 'outline'">
							<OutlineList :items="items" :describe="rowOf" :current="selection" indent :empty="t('editor_view.drill.outline_empty', 'Nothing yet. Start writing, or use the insert buttons in the header.')" @select="select" />
						</OptionsGroup>

						<template v-else-if="drill === 'activity'">
							<p class="editor__drill-note">
								<AdminIcon name="info" />
								<span>{{ t('editor_view.drill.activity_note', 'Every change to {title}, here and by connected apps, newest first. What a change replaced is kept for 30 days, for Undo.', { title: quotedTitle }) }}</span>
							</p>
							<p v-if="changesError" class="notice notice--error" role="alert">{{ changesError }}</p>
							<p v-else-if="changes === null" class="loading">{{ t('editor_view.drill.loading_changes', 'Loading its changes…') }}</p>
							<p v-else-if="!changes.length" class="editor__drill-note"><span>{{ t('editor_view.drill.no_changes', 'No changes are listed yet.') }}</span></p>
							<OptionsGroup v-else>
								<ActivityRows :changes="changes" @changed="changedHere" />
							</OptionsGroup>
						</template>

						<template v-else-if="drill === 'linked'">
							<p class="editor__drill-note">
								<AdminIcon name="info" />
								<span v-if="linkedTotal === 0">{{ t('editor_view.drill.linked_none', "Nothing links here. When another entry names {title} in one of its fields, it's listed here.", { title: quotedTitle }) }}</span>
								<span v-else>{{ t('editor_view.drill.linked_note', "Change these on the entries that link here; {title} doesn't store them.", { title: quotedTitle }) }}<template v-if="linkedDrafts"> {{ t('editor_view.drill.linked_drafts', '{count, plural, one {# of them is a draft, linking here once published.} other {# of them are drafts, linking here once published.} }', { count: linkedDrafts }) }}</template></span>
							</p>
							<OptionsGroup v-for="group in linkedGroups" :key="group.key">
								<template #heading>{{ titleCase(group.relation) }} <span class="editor__group-type">{{ group.type }}</span></template>
								<template #hint>{{ group.count }}</template>
								<ul class="reference__rows">
									<li v-for="item in linkedShown(group)" :key="item.id ?? item.title">
										<component :is="item.id ? RouterLink : 'div'" class="reference__row reference__row--link" :class="{ 'is-draft': item.status !== 'published' }" v-bind="item.id ? { to: entryRoute({ id: item.id, type: group.typeName }) } : {}">
											<span class="reference__still reference__still--row" aria-hidden="true">
												<img v-if="item.image" :src="item.image" alt="" loading="lazy">
												<TypeIcon v-else-if="typeNamed(group.typeName)" :type="typeNamed(group.typeName)!" />
											</span>
											<span class="reference__who">
												<span class="reference__name"><span>{{ item.title || t('editor_view.untitled', 'Untitled') }}</span><StatusPill v-if="item.status !== 'published'" :status="item.status" /></span>
												<span class="reference__meta">{{ [item.type, item.date ? formatDay(item.date) : null].filter(Boolean).join(' · ') }}</span>
											</span>
											<AdminIcon v-if="item.id" name="arrow-right" class="reference__go" />
										</component>
									</li>
								</ul>
								<button v-if="group.entries.length > LINKED_SHOWN && !expanded.has(group.key)" type="button" class="lnk editor__more" @click="expanded = new Set([...expanded, group.key])">
									<AdminIcon name="chevron-down" />{{ t('editor_view.drill.show_more', 'Show {count} More', { count: group.entries.length - LINKED_SHOWN }) }}
								</button>
								<RouterLink v-else-if="group.count > group.entries.length" class="lnk editor__more" :to="{ name: 'type', params: { type: group.typeName }, query: { linking: entry.id ?? undefined, via: group.key } }">
									{{ t('editor_view.drill.view_all', 'View All {count} in {type}', { count: group.count, type: group.type }) }}<AdminIcon name="arrow-right" />
								</RouterLink>
							</OptionsGroup>
						</template>

						<template v-else-if="entry.introduces">
							<p class="editor__drill-note"><AdminIcon name="info" /><span>{{ archiveNote }}</span></p>
							<OptionsGroup>
								<dl class="editor__facts">
									<div>
										<dt>{{ t('editor_view.facts.archive', 'Archive') }}</dt>
										<dd>{{ entry.archive ? t('editor_view.facts.archive_on', '{label} on {plural}', { label: titleCase(entry.archive.label), plural: labels.plural }) : labels.plural }}</dd>
									</div>
									<div v-if="entry.archive?.target">
										<dt>{{ t('editor_view.facts.for', 'For') }}</dt>
										<dd>
											<RouterLink v-if="entry.archive.targetType === profileType && entry.archive.target" :to="{ name: 'profile-detail', params: { slug: entry.archive.target } }">{{ entry.archive.targetTitle }}</RouterLink>
											<RouterLink v-else-if="entry.archive.targetId && entry.archive.targetType" :to="entryRoute({ id: entry.archive.targetId, type: entry.archive.targetType })">{{ entry.archive.targetTitle }}</RouterLink>
											<template v-else>{{ entry.archive.targetTitle }}</template>
										</dd>
									</div>
									<div>
										<dt>{{ t('editor_view.facts.address', 'Address') }}</dt>
										<dd>
											<a v-if="entry.introduces.url" class="mono" :href="entry.introduces.url" target="_blank" rel="noopener">{{ entry.introduces.url }}</a>
											<span v-else class="editor__dim">{{ t('editor_view.facts.address_none', 'None; its archive is off.') }}</span>
										</dd>
									</div>
									<div>
										<dt>{{ t('editor_view.facts.lists', 'Lists') }}</dt>
										<dd>{{ listedCount }}<span v-if="entry.introduces.drafts" class="editor__dim"> · {{ t('editor_view.facts.drafts_not_shown', '{count, plural, one {# draft not shown} other {# drafts not shown} }', { count: entry.introduces.drafts }) }}</span></dd>
									</div>
									<div>
										<dt>{{ t('editor_view.facts.without', 'Without it') }}</dt>
										<dd>{{ archiveWithout }}</dd>
									</div>
								</dl>
							</OptionsGroup>
						</template>
					</template>
					</div>

					<div v-show="tab === 'element'" id="editor-panel-element" role="tabpanel" aria-labelledby="editor-tab-element">
						<template v-if="selection">
							<OptionsGroup v-if="strayNote">
								<p class="notice notice--warn" role="status">
									<AdminIcon name="triangle-alert" />
									<span class="notice__text">{{ strayNote }}</span>
								</p>
							</OptionsGroup>
							<BlockOptions
								v-if="block"
								:source="body"
								:block="block"
								:described="selected"
								@edit="applyOption"
								@remove="removeBlock"
								@pick="pickForOption"
							>
								<OptionsGroup v-if="content" :heading="t('editor_view.groups.content', 'Content')">
									<template v-if="selected?.only?.includes('image')" #hint>{{ t('editor_view.held_images', '{count, plural, one {# image} other {# images} }', { count: held }) }}</template>
									<template v-else-if="selected?.only?.includes(`${CORE}/audio`)" #hint>{{ t('editor_view.held_tracks', '{count, plural, one {# track} other {# tracks} }', { count: held }) }}</template>
									<OutlineList :items="content" :describe="rowOf" :empty="t('editor_view.content_empty', 'Nothing inside it yet.')" @select="select" />
									<p v-if="strays.length" class="field__error">{{ t('editor_view.strays', "{count, plural, one {{holder} holds only {names}, so the site won't show this line: {lines}.} other {{holder} holds only {names}, so the site won't show these lines: {lines}.} }", { count: strays.length, holder: titleCase(selected?.label ?? ''), names: panelNoteNames, lines: strays.map((text) => t('editor_view.quoted', '“{title}”', { title: text.length > 40 ? `${text.slice(0, 40)}…` : text })).join(', ') }) }}</p>
								</OptionsGroup>
							</BlockOptions>
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
							<MarkdownBlockOptions
								v-else-if="markdownBlock"
								:key="`markdown-block-${markdownBlock.kind}-${markdownBlock.start}`"
								:source="body"
								:markdown="markdown"
								:markdown-blocks="allMarkdownBlocks"
								:markdown-block="markdownBlock"
								@edit="applyOption"
							>
								<OptionsGroup v-if="content" :heading="t('editor_view.groups.content', 'Content')">
									<OutlineList :items="content" :describe="rowOf" :empty="t('editor_view.content_empty', 'Nothing inside it yet.')" @select="select" />
									<p v-if="content.length" class="field__help">{{ t('editor_view.content_inside', "{count, plural, one {# element directly inside.} other {# elements directly inside.} } What's nested deeper is listed under the one holding it.", { count: content.length }) }}</p>
								</OptionsGroup>
							</MarkdownBlockOptions>
						</template>

						<div v-else class="editor__none">
							<AdminIcon name="list" />
							<p class="editor__none-heading">{{ t('editor_view.none.heading', 'Nothing Selected') }}</p>
							<p class="editor__none-text">{{ t('editor_view.none.text', "The cursor isn't in the text. Click anywhere in it, and this panel follows what it's in.") }}</p>
						</div>
					</div>
				</div>
			</aside>
		</div>

		<IconPicker v-if="iconsOpen" :preview="iconPreview" @choose="chooseIcon" @close="closeIcons" />
		<MediaPicker v-if="picking" :title="picking.title" :action="picking.action" :tab="picking.tab" :kind="picking.kind" :locked="picking.locked" :multiple="picking.multiple" @choose="picked" @close="picking = null" />
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
	border-bottom: 1px solid var(--border-color);
	background: var(--color-surface);
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
	color: var(--color-text-muted);
}

.editor__head :deep(.is-on) {
	background: var(--color-accent-subtle);
	color: var(--color-accent);
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
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-text-secondary);
	cursor: pointer;
}

.editor__spin-button:hover:not(:disabled) {
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.editor__spin-button:disabled {
	color: var(--color-text-muted);
	opacity: .5;
	cursor: default;
}

.editor__spin-button svg {
	width: 16px;
	height: 16px;
}

/* The link and mention forms open under their buttons. */
.editor__pop {
	position: relative;
	flex: none;
}

.editor__where-skeleton {
	width: 5rem;
}

.editor__divider {
	flex: none;
	width: 1px;
	height: 22px;
	margin: 0 var(--s-1);
	background: var(--border-color);
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
	border-bottom: 1px solid var(--border-color);
	background: var(--color-warning-subtle);
	color: var(--color-warning);
	font-size: var(--font-size-small);
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
	background: var(--color-danger-subtle);
	color: var(--color-danger);
}

.editor__notice--good {
	background: var(--color-success-subtle);
	color: var(--color-success);
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
	background: var(--color-surface);
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
	color: var(--color-text);
	font-family: var(--font-family-display);
	font-size: var(--font-size-editor-title);
	font-weight: var(--font-weight-display);
	letter-spacing: -.028em;
	line-height: 1.18;
	overflow-wrap: break-word;
	resize: none;
	white-space: pre-wrap;
}

.editor__title::placeholder {
	color: var(--color-text-muted);
	opacity: .6;
}

.editor__title:focus-visible {
	outline: none;
}

.editor__title[aria-invalid="true"] {
	text-decoration: underline wavy var(--color-danger-indicator);
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
	border-top: 1px solid var(--border-color);
	background: var(--color-surface);
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
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
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	text-overflow: ellipsis;
	white-space: nowrap;
	cursor: pointer;
}

.editor__crumb--root {
	margin-left: -6px;
}

.editor__crumb:hover {
	background: var(--color-surface-subtle);
	color: var(--color-text);
}

.editor__crumb.is-here {
	color: var(--color-text);
	font-weight: 500;
}

.editor__crumb.is-placed {
	color: var(--color-accent);
}

.editor__crumb :deep(svg),
.editor__crumb :deep(.site-icon) {
	flex: none;
	width: 12px;
	height: 12px;
}

.editor__crumb-sep {
	flex: none;
	width: 11px;
	height: 11px;
	color: var(--color-text-muted);
}

.editor__chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	margin-left: 8px;
	padding: 2px 8px;
	border: 1px solid var(--color-accent-border);
	border-radius: 99px;
	background: var(--color-accent-subtle);
	color: var(--color-accent);
	font-size: var(--font-size-xsmall);
	font-weight: 500;
	cursor: pointer;
}

.editor__chip:hover {
	border-color: var(--color-accent);
	background: var(--color-accent);
	color: var(--color-accent-text);
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
 * The block panel slides in from the left and pushes the column
 * aside, so it never covers the sentence being written.
 */

.editor__inserter {
	flex: none;
	width: 0;
	min-height: 0;
	overflow: hidden;
	background: var(--color-surface);
	transition: width 180ms ease;
}

.editor__inserter:not([inert]) {
	width: var(--inserter);
	border-right: 1px solid var(--border-color);
}

/* Settings push the column aside, so nothing is hidden behind them. */

.editor__side {
	flex: none;
	width: 0;
	min-height: 0;
	overflow: hidden;
	background: var(--color-surface);
	transition: width 180ms ease;
}

.is-side-open .editor__side {
	width: var(--drawer);
	border-left: 1px solid var(--border-color);
}

.editor__side-inner {
	display: flex;
	flex-direction: column;
	width: var(--drawer);
	max-width: 100vw;
	height: 100%;
	overflow-y: auto;
}

/*
 * The Document tab is a column at least the drawer's height, so the rows
 * pinned to its end (D-608) take the space left over: on a short tab they
 * sit at the drawer's bottom edge, and on a long one they follow the last
 * field and scroll with it. Nothing goes below them.
 */
.editor__panel {
	display: flex;
	flex: 1 0 auto;
	flex-direction: column;
}

.editor__pinned {
	margin-top: auto;
	border-top: 1px solid var(--border-color);
}

/* The group right above the rows leaves the line to them, so a long
   tab has one line between, not two. */
.editor__panel > :deep(.options__group:has(+ .editor__pinned)) {
	border-bottom: 0;
}

.editor__pinned .level-link:last-child {
	border-bottom: 0;
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
	border-bottom: 1px solid var(--border-color);
	background: var(--color-surface);
}

.editor__tablist {
	flex: 1;
}

.editor__tablist :deep(.tab-bar__tab) {
	padding: 16px 12px 13px;
}

.editor__tablist :deep(.tab-bar__tab:first-child) {
	padding-left: var(--s-5);
}

.editor__tab-name {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
}

.editor__tablist :deep(.site-icon) {
	flex: none;
	width: 13px;
	height: 13px;
}

.editor__side-close {
	flex: none;
}

/* The element tab with nothing selected says so. */
.editor__none {
	display: grid;
	justify-items: center;
	gap: 6px;
	padding: 52px var(--s-5) 46px;
	border-bottom: 1px solid var(--border-color);
	color: var(--color-text-secondary);
	text-align: center;
}

.editor__none svg {
	width: 22px;
	height: 22px;
	margin-bottom: 6px;
	color: var(--color-text-muted);
}

.editor__none-heading {
	color: var(--color-text);
	font-family: var(--font-family-display);
	font-size: var(--font-size-panel-title);
	font-weight: var(--font-weight-display);
}

.editor__none-text {
	max-width: 34ch;
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
	line-height: 1.6;
}

/* A level down: a note on where its facts are kept, then its groups. */
.editor__drill-note {
	display: flex;
	align-items: flex-start;
	gap: var(--s-2);
	margin: 0;
	padding: var(--s-3) var(--s-5);
	border-bottom: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	line-height: 1.5;
}

.editor__drill-note svg {
	flex: none;
	width: 13px;
	height: 13px;
	margin-top: 2px;
	color: var(--color-text-muted);
}

.editor__group-type {
	margin-left: var(--s-1);
	color: var(--color-text-muted);
	font-weight: 400;
	letter-spacing: 0;
	text-transform: none;
}

.editor__more {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	align-self: flex-start;
	margin-top: var(--s-2);
	font-size: var(--font-size-xsmall);
	font-weight: 500;
}

.editor__more svg {
	width: 13px;
	height: 13px;
}

/* An archive's facts, as label → value rows, each with the way to
   what it names. */
.editor__facts {
	display: grid;
	margin: 0;
}

.editor__facts div {
	display: flex;
	align-items: baseline;
	gap: var(--s-3);
	padding: 9px 0;
	border-bottom: 1px solid var(--border-color);
	font-size: var(--font-size-small);
}

.editor__facts div:last-child {
	border-bottom: 0;
}

.editor__facts dt {
	flex: none;
	width: 92px;
	color: var(--color-text-secondary);
}

.editor__facts dd {
	min-width: 0;
	margin: 0;
	color: var(--color-text);
	overflow-wrap: anywhere;
}

.editor__facts dd a {
	color: var(--color-accent);
}

.editor__facts .mono {
	font-size: var(--font-size-xsmall);
}

.editor__dim {
	color: var(--color-text-muted);
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
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
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
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-accent);
	font: inherit;
	font-size: var(--font-size-small);
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
	background: var(--color-surface-subtle);
}

/* Status says its state in its own ink, never color alone: an icon and a
   word go with it. */
.settings__row :deep(.settings__value--published),
.settings__value--published {
	color: var(--color-success);
}

.settings__row :deep(.settings__value--draft),
.settings__value--draft {
	color: var(--color-text-secondary);
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
	color: var(--color-text-muted);
}

.settings__input {
	flex: 1;
	width: 100%;
	min-width: 0;
	padding: 7px 8px;
	border: 1px solid transparent;
	border-radius: var(--border-radius-small);
	background: none;
	color: var(--color-accent);
	font-size: var(--font-size-small);
}

.settings__input:hover {
	background: var(--color-surface-subtle);
}

.settings__input:focus-visible {
	border-color: var(--color-accent);
	outline: none;
	background: var(--color-surface);
	box-shadow: 0 0 0 3px var(--color-accent-subtle);
}

.settings__input[aria-invalid="true"] {
	border-color: var(--color-danger);
}

/* In the drawer, typed-into fields are filled wells, as the panel's
   search fields are. */
.editor__side :deep(.field input:not([type="checkbox"])),
.editor__side :deep(.field textarea),
.editor__side :deep(.select__button:not(.select__button--plain)) {
	border-color: var(--border-color);
	background: var(--color-background);
	font-size: var(--font-size-small);
}

.editor__side :deep(.field input:not([type="checkbox"]):focus-visible),
.editor__side :deep(.field textarea:focus-visible) {
	border-color: var(--color-accent);
	background: var(--color-surface);
	box-shadow: 0 0 0 3px var(--color-accent-subtle);
}

/* A quiet line under a group, tucked up to what it describes. */
.editor__group-note {
	margin-top: -11px;
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	line-height: 1.5;
}

/* Make homepage, at the end of the root page's note (D-420). */
.editor__note-action {
	font-weight: 500;
}

.editor__group-note + .editor__group-note {
	margin-top: -14px;
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
}
</style>
