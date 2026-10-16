<script setup lang="ts">
/**
 * A content type's page, and a new type's (the content types sketch,
 * D-866): one long page of panels in a fixed order. A new type is the same page, empty, with its kind and
 * name still open and **Create** in the bar at the foot, so learning one
 * teaches the other.
 *
 * - **General:** its kind (chosen for a new one: collection, taxonomy,
 *   or tree), its name, its icon, and who can use it.
 * - **Labels:** its plural and singular, its description, and every
 *   label made from them, each blank for its default.
 * - **Supports:** the optional core fields its entries have.
 * - **In the Menu:** where it sits in the Content menu.
 * - **Behavior:** on the site (public, sitemap, `llms.txt`, feeds), its
 *   entries (order, listing page, workflow), and its files.
 * - **Archives:** date archives and its index page.
 * - **Relationships** (`TypeRelations`): nesting, author credits and
 *   the byline above every relationship it's part of.
 * - **Addresses:** routes, its prefix, and each route's path, a table
 *   of pattern fields grouped as the sketch has them (`TypeRoutesFields`).
 * - **Fields** (`TypeFields`), and the **Danger Zone**.
 *
 * Every row is a `SettingRow`. What a kind doesn't have is left out with
 * a line saying so; what another setting turns off stays, disabled, with
 * that setting named. A type from a plugin marks each setting the site
 * changes. One the admin can't change (the built-in pages and profiles,
 * or every type while `dataTypes` is off) shows its settings as values;
 * its menu place, its fields, and its relationships still change.
 *
 * It saves from its header, as the other Structure screens do (D-868):
 * **Update** once something's changed (**Saved** when nothing has), with
 * **Revert changes** in the menu beside it; a new type has **Cancel**
 * and **Create**. What a change does (pages that move, files that keep
 * their names, entries that keep their statuses) is said under the
 * setting that makes it. Leaving with changes unsaved asks first. Saving (`PATCH types/{name}`)
 * or creating (`POST types`) asks the server to compile the routes and
 * reindex, and loads the types again.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import DangerZone from '../components/DangerZone.vue';
import IconField from '../components/IconField.vue';
import MessageText from '../components/MessageText.vue';
import MenuButton from '../components/MenuButton.vue';
import SettingRow from '../components/SettingRow.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import TypeFields from '../components/TypeFields.vue';
import TypeIcon from '../components/TypeIcon.vue';
import TypeRelations from '../components/TypeRelations.vue';
import PatternField from '../components/PatternField.vue';
import TypeRoutesFields from '../components/TypeRoutesFields.vue';
import { entryRoute, errorMessage, request, type ContentTypeDetail, type LibraryFieldList, type LibraryFieldOption, type TypeRoute } from '../api';
import { useAction } from '../action';
import { confirmAction, guardLeave } from '../confirm';
import { formatNumber, series } from '../format';
import { humanize } from '../fields';
import { t } from '../messages';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { changesOf, DATE_ARCHIVES, emptyForm, FILENAMES, folderOf, FOLDERS, formOf, LABEL_KEYS, ORDERS, singularOf, typeKeyOf, type LabelKey, type TypeForm, type TypeKind } from '../type-form';
import { canCreateTypes, contentMenu, loadTypes, profileType, refreshTypes, typeFiles, types, typeUrls } from '../types';

const route  = useRoute();
const router = useRouter();

loadTypes().catch(() => undefined);

const fresh   = computed(() => route.name === 'type-new');
const type    = ref<ContentTypeDetail | null>(null);
const error   = ref('');
const form    = ref<TypeForm>(emptyForm());
const initial = ref<TypeForm | null>(null);

// A new type's: what it is (a taxonomy is a collection with its classify
// relationship, D-593), its name until typed, the types a taxonomy files,
// and whether Create writes its index page and authors list page.
const choice       = ref<'collection' | 'taxonomy' | 'tree'>('collection');
const key          = ref('');
const keyTyped     = ref(false);
const singularTyped = ref(false);
const files        = ref<string[]>([]);
const library      = ref<LibraryFieldOption[]>([]);
const created      = ref(false);
const tried        = ref(false);
const positionTouched = ref(false);

// Pages a save writes: its index page, and each relationship archive's
// list page (D-602), by relationship.
const index = ref(false);
const pages = ref<string[]>([]);

const { busy: saving, error: failure, run } = useAction();
const { error: removal, run: runRemoval }   = useAction();

async function load(name: string): Promise<void> {
	error.value = '';

	try {
		const detail = await request<ContentTypeDetail>('GET', `/types/${encodeURIComponent(name)}`);

		show(detail);
	} catch (caught) {
		error.value = errorMessage(caught, t('type_view.load_failed', 'The content type couldn\'t be loaded.'));
	}
}

function show(detail: ContentTypeDetail): void {
	type.value    = detail;
	form.value    = formOf(detail);
	initial.value = formOf(detail);
	index.value   = false;
	pages.value   = [];
}

function start(): void {
	type.value          = null;
	error.value         = '';
	form.value          = emptyForm();
	initial.value       = null;
	choice.value        = 'collection';
	key.value           = '';
	keyTyped.value      = false;
	singularTyped.value = false;
	files.value         = [];
	index.value         = true;
	pages.value         = [];
	created.value       = false;
	tried.value         = false;
	positionTouched.value = false;

	void request<LibraryFieldList>('GET', '/fields/library').then((list) => {
		library.value = list.fields.map((field) => ({ name: field.name, label: field.label, description: field.description, required: field.required, type: field.type, control: field.control }));
	}).catch(() => undefined);
}

watch(() => [route.name, route.params.name], () => {
	if (fresh.value) {
		start();
	} else if (typeof route.params.name === 'string') {
		type.value = null;
		void load(route.params.name);
	}
}, { immediate: true });

// --- What it is -----------------------------------------------------------

const kind     = computed<TypeKind | 'profiles'>(() => fresh.value ? (choice.value === 'tree' ? 'tree' : 'collection') : (type.value?.kind ?? 'collection'));
const formKind = computed<TypeKind>(() => kind.value === 'tree' ? 'tree' : 'collection');
const taxonomy = computed(() => fresh.value ? choice.value === 'taxonomy' : type.value?.taxonomy === true);
const name     = computed(() => fresh.value ? key.value : (type.value?.name ?? ''));
const people   = computed(() => kind.value === 'profiles');

// What the admin may change: everything on a new type or an editable one;
// on the others, only their menu place, fields, and relationships.
const editable       = computed(() => fresh.value || type.value?.editable === true);
const menuEditable   = computed(() => fresh.value || type.value?.placeable === true);
const fieldsEditable = computed(() => fresh.value || type.value?.fieldsEditable === true);
const fromPlugin     = computed(() => !fresh.value && type.value?.origin === 'extension');

watch(() => type.value?.labels.plural, (plural) => {
	screenTitle.value = fresh.value ? null : (plural ?? null);
});

// A new type's names follow one another until they're typed: the singular
// the plural, and the name the singular.
watch(() => form.value.plural, (plural, before) => {
	if (!fresh.value) {
		return;
	}

	if (!singularTyped.value && (form.value.singular === '' || form.value.singular === singularOf(before ?? ''))) {
		form.value.singular = singularOf(plural);
	}

	if (!keyTyped.value) {
		key.value = typeKeyOf(plural);
	}

	if (typeUrls.value && (form.value.prefix === '' || form.value.prefix === folderOf(before ?? ''))) {
		form.value.prefix = folderOf(plural);
	}
});

// A new type's kind sets its defaults (D-866): a taxonomy is ordered by
// position and left out of `llms.txt`, credits no one, and has a
// position; a tree has a position; a collection credits authors.
watch(choice, (value) => {
	form.value.order    = value === 'taxonomy' ? 'position' : 'date';
	form.value.llms     = value !== 'taxonomy';
	form.value.authors  = value === 'collection';
	form.value.position = value !== 'collection';
});

// On a new type, Position follows Hierarchical and Order until it's
// switched by hand.
watch(() => [form.value.hierarchical, form.value.order], () => {
	if (fresh.value && !positionTouched.value) {
		form.value.position = choice.value !== 'collection' || form.value.hierarchical || form.value.order === 'position';
	}
});

const keyError = computed(() => {
	if (!fresh.value) {
		return '';
	}

	if (key.value === '') {
		return t('type_view.key_required', 'Give it a name.');
	}

	if (!/^[a-z][a-z0-9_]*$/.test(key.value)) {
		return t('type_view.key_invalid', 'Start with a lowercase letter, then use lowercase letters, digits, and underscores.');
	}

	if (['system', 'error'].includes(key.value)) {
		return t('type_view.key_reserved', '{name} is kept for the site\'s own pages.', { name: key.value });
	}

	const taken = types.value.find((other) => other.name === key.value);

	return taken ? t('type_view.key_taken', '{name} is already the name of {types}.', { name: key.value, types: taken.labels.plural }) : '';
});

const pluralError = computed(() => (fresh.value ? tried.value : true) && form.value.plural.trim() === '' ? t('type_view.plural_required', 'Give it a plural name.') : '');

// --- Labels -----------------------------------------------------------------

// Each label's default, made from the names as the server makes it.
const labelDefaults = computed<Record<LabelKey, string>>(() => {
	const singular = form.value.singular.trim() || singularOf(form.value.plural.trim()) || t('type_view.item', 'Item');
	const plural   = form.value.plural.trim() || t('type_view.items', 'Items');
	const lower    = (text: string): string => text === '' ? text : text[0]!.toLowerCase() + text.slice(1);
	const title    = (text: string): string => text.replace(/\b([a-z])/g, (letter) => letter.toUpperCase());
	const item     = form.value.labels.item.trim() || lower(singular);
	const items    = form.value.labels.items.trim() || lower(plural);

	return { menu: plural, item: lower(singular), items: lower(plural), newItem: t('type_view.new_item_default', 'New {singular}', { singular: title(singular) }), editItem: t('type_view.edit_item_default', 'Edit {item}', { item }), searchItems: t('type_view.search_items_default', 'Search {items}', { items }) };
});

const LABEL_NAMES: Record<LabelKey, [string, string]> = {
	menu: [t('type_view.label_menu', 'In the Menu'), t('type_view.label_menu_help', 'Its name in the Content menu.')],
	item: [t('type_view.label_item', 'One, in a Sentence'), t('type_view.label_item_help', 'As in “Saved the recipe.”')],
	items: [t('type_view.label_items', 'Several, in a Sentence'), t('type_view.label_items_help', 'As in “No recipes yet.”')],
	newItem: [t('type_view.label_new', 'New Button'), t('type_view.label_new_help', 'The button that starts one.')],
	editItem: [t('type_view.label_edit', 'Editor Title'), t('type_view.label_edit_help', 'The editor\'s title for one.')],
	searchItems: [t('type_view.label_search', 'Search Box'), t('type_view.label_search_help', 'The prompt in its list\'s search.')]
};

// --- Who can use it ---------------------------------------------------------

const rolesLine = computed(() => {
	const roles = type.value?.roles;

	if (!roles) {
		return t('type_view.roles_new', 'Roles that grant every type can use it once it\'s made; a role that names types one by one needs it added under Users → Roles.');
	}

	const parts = [
		roles.every.length ? t('type_view.roles_every', '{roles} grant every type, so they include it.', { roles: series(roles.every) }) : '',
		roles.named.length ? t('type_view.roles_named', '{roles} {count, plural, one {names} other {name} } it.', { roles: series(roles.named), count: roles.named.length }) : '',
		roles.others.length ? t('type_view.roles_others', '{roles} {count, plural, one {names types one by one and doesn\'t include it} other {name types one by one and don\'t include it} }.', { roles: series(roles.others), count: roles.others.length }) : ''
	];

	return parts.filter((part) => part !== '').join(' ');
});

// --- Choices ----------------------------------------------------------------

const workflowOptions = computed(() => (type.value?.workflows ?? [{ name: 'standard', label: t('type_view.workflow_standard', 'Standard') }]).map((workflow) => ({ value: workflow.name === 'standard' ? '' : workflow.name, label: workflow.label })));
const taxonomyOptions = computed(() => [{ value: '', label: t('type_view.feed_categories_default', 'Every taxonomy but authors') }, ...types.value.filter((other) => other.taxonomy).map((other) => ({ value: other.name, label: other.labels.plural }))]);
const listsOptions    = computed(() => [{ value: '', label: t('type_view.lists_itself', 'Itself') }, ...types.value.filter((other) => other.name !== name.value && other.kind !== 'profiles').map((other) => ({ value: other.name, label: other.labels.plural }))]);
// What entries can be ordered by, for people: core's fields, then the
// fields placed on the type, by their labels.
const ORDER_BY: Record<string, string> = {
	date: t('type_view.order_by_date', 'Date'),
	updated: t('type_view.order_by_updated', 'Last updated'),
	title: t('type_view.order_by_title', 'Title'),
	slug: t('type_view.order_by_slug', 'Slug'),
	author: t('type_view.order_by_author', 'Author'),
	position: t('type_view.order_by_position', 'Position')
};
const fieldLabels    = computed(() => new Map((fresh.value ? library.value : (type.value?.library ?? [])).map((field) => [field.name, field.label])));
const orderByOptions  = computed(() => [{ value: '', label: t('type_view.order_by_default', 'Default ({by})', { by: form.value.order === 'position' ? ORDER_BY.position : ORDER_BY.date }) }, ...Object.entries(ORDER_BY).map(([value, label]) => ({ value, label })), ...placedKeys.value.map((field) => ({ value: field, label: t('type_view.order_by_field', '{field} (field)', { field: fieldLabels.value.get(field) || field }) }))]);
const directionOptions = [{ value: '', label: t('type_view.direction_default', 'Default') }, { value: 'desc', label: t('type_view.direction_desc', 'Descending') }, { value: 'asc', label: t('type_view.direction_asc', 'Ascending') }];
const filenameOptions = computed(() => FILENAMES.some((option) => option.value === form.value.filename) ? FILENAMES : [...FILENAMES, { value: form.value.filename, label: form.value.filename, hint: t('type_view.from_file', 'as written') }]);
const folderOptions   = computed(() => FOLDERS.some((option) => option.value === form.value.folders) ? FOLDERS : [...FOLDERS, { value: form.value.folders, label: form.value.folders, hint: t('type_view.from_file', 'as written') }]);
const underOptions    = computed(() => [{ value: '', label: t('type_view.top_level', 'The top level') }, ...contentMenu(types.value).filter(({ type: other }) => other.name !== name.value).map(({ type: other }) => ({ value: other.name, label: other.labels.menu }))]);
const menuChildren    = computed(() => types.value.filter((other) => other.adminUnder === name.value && name.value !== ''));
const fileable        = computed(() => types.value.filter((other) => other.kind !== 'profiles'));
const bylineOptions   = computed(() => (type.value?.credits ?? []).map((relation) => ({ value: relation, label: humanize(relation) })));

// The library fields it places, by key, for a listing's order.
const placedKeys = computed(() => form.value.fields.flatMap((item) => typeof item === 'object' && 'section' in item ? item.fields : [item]).map((placed) => typeof placed === 'string' ? placed : placed.field));

// What another setting turns off.
const routed  = computed(() => form.value.public && (formKind.value === 'tree' || form.value.routes));
const feedOn  = computed(() => routed.value && form.value.feed);
const credits = computed(() => profileType.value !== null && !people.value && !taxonomy.value);

// --- Changes -----------------------------------------------------------------

const changes = computed(() => fresh.value || initial.value === null ? {} : changesOf(form.value, initial.value, formKind.value));
const authorsChanged = computed(() => !fresh.value && initial.value !== null && form.value.authors !== initial.value.authors);
const count   = computed(() => Object.keys(changes.value).length + (index.value && !fresh.value ? 1 : 0) + pages.value.length + (authorsChanged.value ? 1 : 0));

const changedKey = (keyName: keyof TypeForm): boolean => !fresh.value && initial.value !== null && JSON.stringify(form.value[keyName]) !== JSON.stringify(initial.value[keyName]);

// What the plugin sets for an option the site changes, or `null`.
function code(option: string, value: unknown): string | null {
	const defined = type.value?.code;

	if (!fromPlugin.value || !defined || !(type.value?.overrides ?? []).includes(option)) {
		return null;
	}

	const set = defined[option];

	return typeof set === 'boolean' ? (set ? t('type_view.on', 'on') : t('type_view.off', 'off')) : (set === undefined || set === null ? t('type_view.its_default', 'its default') : (typeof set === 'object' ? JSON.stringify(set) : String(set ?? value)));
}


// What saving does, for the toast after it (D-866); each setting says
// it under its control as it's changed.
const consequences = computed(() => {
	const before  = initial.value;
	const entries = type.value?.entries ?? 0;

	if (fresh.value || before === null) {
		return [];
	}

	const said: string[] = [];
	const moved = form.value.prefix !== before.prefix || JSON.stringify(form.value.paths) !== JSON.stringify(before.paths) || form.value.dateArchives !== before.dateArchives || (form.value.routes && !before.routes);

	if (moved && routed.value && entries > 0) {
		said.push(t('type_view.saves_moves', 'moves {count, plural, one {# page} other {# pages} } to new addresses', { count: entries }));
	}

	if (before.routes && !form.value.routes && form.value.public) {
		said.push(t('type_view.saves_unroutes', 'takes every address it has off the site'));
	}

	if (before.public && !form.value.public) {
		said.push(t('type_view.saves_private', 'takes {count, plural, one {# page} other {# pages} } off the site', { count: entries }));
	}

	if (typeFiles.value && (form.value.filename !== before.filename || form.value.folders !== before.folders)) {
		said.push(t('type_view.saves_files', 'names new files the new way; {count, plural, one {# file keeps its name} other {# files keep theirs} }', { count: entries }));
	}

	if (form.value.workflow !== before.workflow) {
		said.push(t('type_view.saves_statuses', 'keeps every entry\'s status as it is'));
	}

	if (authorsChanged.value) {
		said.push(form.value.authors ? t('type_view.saves_joins', 'adds it to Authors') : t('type_view.saves_leaves', 'takes it out of Authors'));
	}

	return said;
});

// --- A new type's addresses ----------------------------------------------

// The routes a new type would have, asked of the server as the draft
// changes (D-870), so its paths can be set before Create.
const previewRoutes = ref<TypeRoute[]>([]);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

watch(() => fresh.value ? JSON.stringify([key.value, choice.value, form.value.prefix, form.value.routes, form.value.public, form.value.dateArchives, form.value.feed, form.value.authors, files.value]) : '', (draft) => {
	clearTimeout(previewTimer);

	if (draft === '') {
		return;
	}

	previewTimer = setTimeout(() => {
		void request<{ routes: TypeRoute[] }>('POST', '/types/routes', {
			name: key.value,
			kind: formKind.value,
			set: changesOf(form.value, null, formKind.value),
			authors: credits.value && form.value.authors,
			taxonomy: taxonomy.value,
			files: files.value
		}).then((answer) => {
			previewRoutes.value = answer.routes;
		}, () => undefined);
	}, 250);
}, { immediate: true });

const shownRoutes = computed(() => fresh.value ? previewRoutes.value : (type.value?.routes ?? []));

// --- Saving ------------------------------------------------------------------

async function save(): Promise<void> {
	const current = type.value;

	if (current === null || count.value === 0 || saving.value) {
		return;
	}

	const said = consequences.value;

	await run(t('type_view.save_failed', 'Nothing was saved.'), async () => {
		const saved = await request<ContentTypeDetail>('PATCH', `/types/${encodeURIComponent(current.name)}`, {
			set: changes.value,
			index: index.value,
			listPages: pages.value,
			...(authorsChanged.value ? { authors: form.value.authors } : {})
		});

		show(saved);
		refreshTypes();
		toast(said.length ? t('type_view.saved_with', 'Saved {name}: {what}.', { name: saved.labels.plural, what: series(said) }) : t('type_view.saved', 'Saved {name}', { name: saved.labels.plural }));
	});
}

async function create(): Promise<void> {
	tried.value = true;

	if (keyError.value !== '' || pluralError.value !== '' || saving.value) {
		document.getElementById(pluralError.value !== '' ? 'type-plural' : 'type-key')?.focus();

		return;
	}

	await run(t('type_view.create_failed', 'Nothing was made.'), async () => {
		const made = await request<ContentTypeDetail>('POST', '/types', {
			name: key.value,
			kind: formKind.value,
			index: index.value && kind.value !== 'tree',
			authors: credits.value && form.value.authors,
			listPages: pages.value,
			set: changesOf(form.value, null, formKind.value)
		});

		// A taxonomy files other entries through a relationship named
		// after it (D-593).
		if (taxonomy.value) {
			await request('POST', '/relations', { name: made.name, kind: 'classify', from: files.value, to: [made.name], create: true });
		}

		created.value = true;
		refreshTypes();
		toast(t('type_view.created', 'Created {name}', { name: made.labels.plural }));
		await router.push({ name: 'content-type', params: { name: made.name } });
	});
}

function submit(): void {
	if (fresh.value) {
		void create();
	} else {
		void save();
	}
}

function revert(): void {
	if (fresh.value) {
		void router.push({ name: 'types' });

		return;
	}

	if (type.value !== null) {
		show(type.value);
		failure.value = '';
		toast(t('type_view.reverted', 'Changes reverted'), { kind: 'info' });
	}
}

function wantPage(relation: string, on: boolean): void {
	pages.value = on ? [...pages.value, relation] : pages.value.filter((item) => item !== relation);
}

function filed(other: string, on: boolean): void {
	files.value = on ? [...files.value, other] : files.value.filter((item) => item !== other);
}

// --- Danger zone ----------------------------------------------------------------

const dangerZone = computed(() => !fresh.value && type.value !== null && type.value.editable && type.value.origin !== 'built-in');

// What still names the type, which has to go before it can be deleted.
const naming = computed(() => (type.value?.relations ?? []).filter((relation) => relation.from.includes(name.value) || relation.to.includes(name.value)));

async function reset(): Promise<void> {
	const current = type.value;

	removal.value = '';

	if (current === null || !await confirmAction({ title: t('type_view.reset_title', 'Reset {name}?', { name: current.labels.plural }), body: t('type_view.reset_body', 'It goes back to how its plugin defines it: **{file}** is removed, and every change made here with it.', { file: current.file ?? '' }), confirm: t('type_view.reset_confirm', 'Reset the Type'), danger: true })) {
		return;
	}

	await runRemoval(t('type_view.reset_failed', 'The type couldn\'t be reset.'), async () => {
		show(await request<ContentTypeDetail>('POST', `/types/${encodeURIComponent(current.name)}/reset`));
		refreshTypes();
		toast(t('type_view.reset_done', 'Reset {name}', { name: current.labels.plural }));
	});
}

async function remove(): Promise<void> {
	const current = type.value;

	removal.value = '';

	if (current === null || !await confirmAction({ title: t('type_view.delete_title', 'Delete {name}?', { name: current.labels.plural }), body: [t('type_view.delete_body', 'The type is removed. Its {count, plural, one {# entry stays} other {# entries stay} } in **user/content/{folder}**, off the site, until a type named {name} claims them again.', { count: current.entries, folder: current.folder, name: current.name })], confirm: t('type_view.delete_confirm', 'Delete the Type'), danger: true })) {
		return;
	}

	await runRemoval(t('type_view.delete_failed', 'The type couldn\'t be deleted.'), async () => {
		await request('DELETE', `/types/${encodeURIComponent(current.name)}`);
		initial.value = form.value;
		refreshTypes();
		toast(t('type_view.deleted', 'Deleted {name}', { name: current.labels.plural }), { kind: 'danger' });
		await router.push({ name: 'types', query: { deleted: current.name, entries: String(current.entries) } });
	});
}

guardLeave(() => !created.value && (fresh.value ? form.value.plural.trim() !== '' : count.value > 0));

const origin = computed(() => {
	const current = type.value;

	if (current === null) {
		return '';
	}

	return { 'built-in': t('type_view.origin_built_in', 'Built in'), extension: current.plugin ?? t('type_view.origin_plugin', 'A plugin'), data: t('type_view.origin_site', 'This site') }[current.origin];
});

const address = computed(() => {
	if (!form.value.public) {
		return '';
	}

	const prefix = (form.value.prefix || (fresh.value ? key.value : (type.value?.defaultPrefix ?? ''))).replace(/^\/+|\/+$/g, '');

	return kind.value === 'tree' && name.value === 'page' ? '/' : `/${prefix || '…'}`;
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'types' }"><AdminIcon name="chevron-left" />{{ t('type_view.all_types', 'All Content Types') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ fresh ? (form.plural.trim() ? t('type_view.new_named', 'New: {name}', { name: form.plural.trim() }) : t('type_view.new_title', 'New Content Type')) : (type?.labels.plural ?? t('type_view.title', 'Content Type')) }}</h1>
			<div v-if="fresh || type" class="page-facts">
				<span class="page-facts__item">{{ taxonomy ? t('type_view.kind_taxonomy', 'Taxonomy') : humanize(kind) }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon name="key-round" /><span class="mono">{{ name || t('type_view.no_name', 'No name yet') }}</span></span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon :name="address ? 'globe' : 'eye-off'" /><span :class="{ mono: address }">{{ address || t('type_view.no_pages', 'No pages on the site') }}</span></span>
				<template v-if="type">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item">{{ t('type_view.entries_count', '{count, plural, one {# entry} other {# entries} }', { count: type.entries }) }}</span>
				</template>
			</div>
		</div>
		<div v-if="fresh && canCreateTypes" class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'types' }">{{ t('common.cancel', 'Cancel') }}</RouterLink>
			<button type="submit" form="type-page" class="button button--primary" :disabled="saving">{{ saving ? t('type_view.creating', 'Creating…') : (form.plural.trim() ? t('type_view.create_named', 'Create {name}', { name: form.plural.trim() }) : t('type_view.create', 'Create Type')) }}</button>
		</div>
		<div v-else-if="type" class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'type', params: { type: type.name } }"><AdminIcon name="files" />{{ t('type_view.view_items', 'View {items}', { items: type.labels.items }) }}</RouterLink>
			<button type="submit" form="type-page" class="button button--primary" :disabled="count === 0 || saving">{{ saving ? t('type_view.saving', 'Saving…') : (count > 0 ? t('type_view.update', 'Update') : t('type_view.saved_button', 'Saved')) }}</button>
			<MenuButton button-class="button button--icon" :label="t('type_view.more', 'More actions')" align="end">
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<button type="button" class="menu-item" :disabled="count === 0" @click="revert"><AdminIcon name="undo-2" />{{ t('type_view.revert', 'Revert changes') }}</button>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
	<p v-if="fresh && !canCreateTypes" class="notice notice--warn"><span><MessageText :text="t('type_view.cant_create', 'The site\'s config turns off {option}, so types can\'t be made here.')"><template #option><code>dataTypes</code></template></MessageText></span></p>
	<p v-else-if="type && !type.editable" class="notice"><AdminIcon name="lock" /><span>{{ type.origin === 'built-in' ? t('type_view.locked_core', '{name} is built in, so its settings are shown as they are. Its place in the menu, its fields, and its relationships still change here.', { name: type.labels.plural }) : t('type_view.locked_config', 'The site\'s config turns off changing types here, so its settings are shown as they are. Its fields and relationships still change.') }}</span></p>
	<p v-else-if="fromPlugin && type" class="notice"><AdminIcon name="plug" /><span><MessageText :text="t('type_view.from_plugin', 'Defined by {plugin}. Everything but its kind and name changes here, saved in {file} as only what\'s different. Reset, at the end of the page, takes those changes back.')"><template #plugin>{{ origin }}</template><template #file><code>user/data/types/{{ type.name }}.json</code></template></MessageText></span></p>

	<form v-if="(fresh && canCreateTypes) || type" id="type-page" class="setting-panels type-page" novalidate @submit.prevent="submit">
		<!-- General -->
		<section id="type-general" class="panel" aria-labelledby="type-general-heading">
			<header class="panel__header">
				<h2 id="type-general-heading">{{ t('type_view.general', 'General') }}</h2>
				<p v-if="fresh" class="panel__hint">{{ t('type_view.general_hint', 'What it is and what it\'s called. The kind and the name can\'t change later.') }}</p>
			</header>
			<div class="setting-panels__rows">
				<SettingRow v-if="fresh" :label="t('type_view.kind', 'Kind')" wide>
					<div class="choices choices--detailed" role="radiogroup" :aria-label="t('type_view.kind', 'Kind')">
						<label class="checkbox"><input v-model="choice" type="radio" name="type-kind" value="collection"><span class="choice"><span class="choice__name">{{ t('type_view.collection', 'Collection') }}</span><span class="choice__text">{{ t('type_view.collection_text', 'Entries listed together, newest first, with a listing page and feeds if you want them: posts, recipes, movies.') }}</span></span></label>
						<label class="checkbox"><input v-model="choice" type="radio" name="type-kind" value="taxonomy"><span class="choice"><span class="choice__name">{{ t('type_view.taxonomy', 'Taxonomy') }}</span><span class="choice__text">{{ t('type_view.taxonomy_text', 'Entries other types are filed under: topics, genres, tags. It\'s a collection, with a relationship made along with it.') }}</span></span></label>
						<label class="checkbox"><input v-model="choice" type="radio" name="type-kind" value="tree"><span class="choice"><span class="choice__name">{{ t('type_view.tree', 'Tree') }}</span><span class="choice__text">{{ t('type_view.tree_text', 'Pages that nest by folder, each at its path: docs, a handbook.') }}</span></span></label>
					</div>
				</SettingRow>
				<SettingRow v-else :label="t('type_view.kind', 'Kind')" :help="taxonomy ? t('type_view.kind_taxonomy_help', 'A collection that other types are filed under. Fixed once the type is made.') : t('type_view.kind_fixed', 'Fixed once the type is made.')">
					<p class="setting__value"><AdminIcon name="lock" /> {{ taxonomy ? t('type_view.kind_taxonomy', 'Taxonomy') : humanize(kind) }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.name', 'Name')" control-id="type-key" :help="fresh ? t('type_view.name_help_new', 'Lowercase letters, digits, and underscores. Its files, addresses, and permissions use it, so it\'s fixed once the type is made.') + (keyTyped ? '' : ' ' + t('type_view.name_follows', 'Made from the singular until you type one.')) : t('type_view.name_help', 'Its files, addresses, and permissions use it, so it\'s fixed.')">
					<template v-if="fresh">
						<input id="type-key" v-model="key" class="mono" placeholder="recipe" autocomplete="off" spellcheck="false" :aria-invalid="(tried || keyTyped) && keyError ? 'true' : undefined" @input="keyTyped = true">
						<p v-if="(tried || keyTyped) && keyError" class="field__error">{{ keyError }}</p>
					</template>
					<p v-else class="setting__value mono"><AdminIcon name="lock" /> {{ name }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.icon', 'Icon')" control-id="type-icon" :help="t('type_view.icon_help', 'Shown in the menu and lists. Without one, its kind\'s.')" :changed="changedKey('icon')" :code="code('icon', form.icon)">
					<IconField v-if="editable" id="type-icon" v-model="form.icon" :preview="(icon) => `icon: ${icon.name}`" />
					<p v-else class="setting__value"><TypeIcon v-if="type" :type="type" /> {{ form.icon || t('type_view.kind_icon', 'Its kind\'s') }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.who', 'Who Can Use It')">
					<p class="setting__value mono">content.{{ name || 'name' }}.*</p>
					<template #help>{{ rolesLine }} {{ t('type_view.who_help', 'Changing the type itself needs Site Settings.') }}</template>
				</SettingRow>
			</div>
		</section>

		<!-- Labels -->
		<section id="type-labels" class="panel" aria-labelledby="type-labels-heading">
			<header class="panel__header">
				<h2 id="type-labels-heading">{{ t('type_view.labels', 'Labels') }}</h2>
				<p class="panel__hint">{{ t('type_view.labels_hint', 'What people see it called. Leave one blank to use the default shown.') }}</p>
			</header>
			<div class="setting-panels__rows">
				<SettingRow :label="t('type_view.plural', 'Plural')" control-id="type-plural" :changed="changedKey('plural')">
					<input v-if="editable" id="type-plural" v-model="form.plural" :placeholder="fresh ? t('type_view.plural_placeholder', 'Recipes') : ''" autocomplete="off" :aria-invalid="pluralError ? 'true' : undefined">
					<p v-else class="setting__value">{{ form.plural }}</p>
					<p v-if="pluralError" class="field__error">{{ pluralError }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.singular', 'Singular')" control-id="type-singular" :help="fresh && !singularTyped ? t('type_view.singular_follows', 'Made from the plural until you type one.') : ''" :changed="changedKey('singular')">
					<input v-if="editable" id="type-singular" v-model="form.singular" :placeholder="fresh ? t('type_view.singular_placeholder', 'Recipe') : ''" autocomplete="off" @input="singularTyped = true">
					<p v-else class="setting__value">{{ form.singular }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.description', 'Description')" control-id="type-description" :help="t('type_view.description_help', 'What it\'s for, in a sentence. Shown on its list while it\'s empty.')" :changed="changedKey('description')">
					<input v-if="editable" id="type-description" v-model="form.description" autocomplete="off">
					<p v-else class="setting__value">{{ form.description || t('type_view.none', 'None') }}</p>
				</SettingRow>
				<SettingRow v-for="labelKey in LABEL_KEYS" :key="labelKey" :label="LABEL_NAMES[labelKey][0]" :control-id="`type-label-${labelKey}`" :help="LABEL_NAMES[labelKey][1]" :changed="!fresh && initial !== null && form.labels[labelKey] !== initial.labels[labelKey]">
					<input v-if="editable" :id="`type-label-${labelKey}`" v-model="form.labels[labelKey]" :placeholder="labelDefaults[labelKey]" autocomplete="off">
					<p v-else class="setting__value">{{ form.labels[labelKey] || labelDefaults[labelKey] }}</p>
				</SettingRow>
			</div>
		</section>

		<!-- Supports -->
		<section id="type-supports" class="panel" aria-labelledby="type-supports-heading">
			<header class="panel__header">
				<h2 id="type-supports-heading">{{ t('type_view.supports', 'Supports') }}</h2>
				<p class="panel__hint">{{ t('type_view.supports_hint', 'Built-in fields its entries can have. Off, the field isn\'t in the editor and isn\'t read from files.') }}</p>
			</header>
			<div class="setting-panels__rows">
				<SettingRow :label="t('type_view.featured_image', 'Featured Image')" :help="t('type_view.featured_image_help', 'An image for lists, feeds, and shared links (image).')" :changed="changedKey('image')" :code="code('image', form.image)">
					<ToggleSwitch :checked="form.image" :label="t('type_view.featured_image', 'Featured Image')" form :locked="!editable" @change="form.image = $event" />
				</SettingRow>
				<SettingRow :label="t('type_view.summary', 'Summary')" :help="t('type_view.summary_help', 'A short description for lists, feeds, and search (summary).')" :changed="changedKey('summary')" :code="code('summary', form.summary)">
					<ToggleSwitch :checked="form.summary" :label="t('type_view.summary', 'Summary')" form :locked="!editable" @change="form.summary = $event" />
				</SettingRow>
				<SettingRow :label="t('type_view.subtitle', 'Subtitle')" :help="t('type_view.subtitle_help', 'A line under the title (subtitle).')" :changed="changedKey('subtitle')" :code="code('subtitle', form.subtitle)">
					<ToggleSwitch :checked="form.subtitle" :label="t('type_view.subtitle', 'Subtitle')" form :locked="!editable" @change="form.subtitle = $event" />
				</SettingRow>
				<SettingRow :label="t('type_view.position', 'Position')" :help="t('type_view.position_help', 'A hand-picked order among its siblings (position). Used when the order is by position; without it, entries sort by title.')" :changed="changedKey('position')" :code="code('position', form.position)">
					<ToggleSwitch :checked="form.position" :label="t('type_view.position', 'Position')" form :locked="!editable" @change="form.position = $event; positionTouched = true" />
				</SettingRow>
				<SettingRow :label="t('type_view.visibility', 'Visibility')" :changed="changedKey('visibility')" :code="code('visibility', form.visibility)">
					<ToggleSwitch :checked="form.public && form.visibility" :label="t('type_view.visibility', 'Visibility')" form :locked="!editable || !form.public" @change="form.visibility = $event" />
					<template #help>{{ form.public ? t('type_view.visibility_help', 'Each entry can be public, unlisted (an address, but in no lists), or hidden (no address). Off, every entry is public.') : t('type_view.visibility_off', 'Disabled while Public is off.') }}</template>
				</SettingRow>
				<SettingRow :label="t('type_view.expiration', 'Expiration')" :help="t('type_view.expiration_help', 'An Expires date, after which an entry is off the site, as a draft is, until the date moves (expires).')" :changed="changedKey('expires')" :code="code('expires', form.expires)">
					<ToggleSwitch :checked="form.expires" :label="t('type_view.expiration', 'Expiration')" form :locked="!editable" @change="form.expires = $event" />
				</SettingRow>
			</div>
		</section>

		<!-- In the Menu -->
		<section v-if="!people" id="type-menu" class="panel" aria-labelledby="type-menu-heading">
			<header class="panel__header">
				<h2 id="type-menu-heading">{{ t('type_view.in_the_menu', 'In the Menu') }}</h2>
				<p class="panel__hint">{{ t('type_view.menu_hint', 'Where it sits in the Content menu, for everyone.') }}</p>
			</header>
			<div class="setting-panels__rows">
				<SettingRow :label="t('type_view.under', 'Sits Under')" control-id="type-under" :changed="changedKey('adminUnder')" :code="code('adminUnder', form.adminUnder)">
					<AdminSelect id="type-under" v-model="form.adminUnder" :options="underOptions" :disabled="!menuEditable || menuChildren.length > 0" />
					<template #help>{{ menuChildren.length ? t('type_view.under_children', '{names} {count, plural, one {sits} other {sit} } under it, so it stays at the top level. The menu is one level deep.', { names: series(menuChildren.map((child) => child.labels.menu)), count: menuChildren.length }) : t('type_view.under_help', 'Nests it under another type, one level deep.') }}</template>
				</SettingRow>
				<SettingRow :label="t('type_view.menu_position', 'Position')" control-id="type-admin-position" :help="t('type_view.menu_position_help', 'Lower comes first among its siblings. Without one, after those with one, by name.')" :changed="changedKey('adminPosition')" :code="code('adminPosition', form.adminPosition)">
					<input id="type-admin-position" v-model="form.adminPosition" type="number" min="0" step="1" inputmode="numeric" :placeholder="t('type_view.none', 'None')" :readonly="!menuEditable">
				</SettingRow>
			</div>
		</section>

		<!-- Behavior -->
		<section id="type-behavior" class="panel" aria-labelledby="type-behavior-heading">
			<header class="panel__header">
				<h2 id="type-behavior-heading">{{ t('type_view.behavior', 'Behavior') }}</h2>
			</header>
			<h3 class="setting-panels__group"><AdminIcon name="globe" />{{ t('type_view.on_the_site', 'On the Site') }}</h3>
			<div class="setting-panels__rows">
				<SettingRow :label="t('type_view.public', 'Public')" :changed="changedKey('public')" :code="code('public', form.public)">
					<ToggleSwitch :checked="form.public" :label="t('type_view.public', 'Public')" form :locked="!editable" @change="form.public = $event" />
					<template #help>{{ t('type_view.public_help', 'Entries have pages on the site. Off, it has no addresses and is left out of the sitemap and llms.txt.') }}</template>
				</SettingRow>
				<SettingRow :label="t('type_view.sitemap', 'Sitemap')" :changed="changedKey('sitemap')" :code="code('sitemap', form.sitemap)">
					<ToggleSwitch :checked="routed && form.sitemap" :label="t('type_view.sitemap', 'Sitemap')" form :locked="!editable || !routed" @change="form.sitemap = $event" />
					<template #help>{{ routed ? t('type_view.sitemap_help', 'Lists its entries in sitemap.xml.') : (form.public ? t('type_view.sitemap_no_routes', 'Disabled while Routes is off: there\'s no address to list.') : t('type_view.off_public', 'Disabled while Public is off.')) }}</template>
				</SettingRow>
				<SettingRow :label="t('type_view.llms', 'Listed for AI')" :changed="changedKey('llms')" :code="code('llms', form.llms)">
					<ToggleSwitch :checked="form.public && form.llms" :label="t('type_view.llms', 'Listed for AI')" form :locked="!editable || !form.public" @change="form.llms = $event" />
					<template #help>{{ form.public ? t('type_view.llms_help', 'Lists its entries in llms.txt, the site\'s plain-text index for language models.') : t('type_view.off_public', 'Disabled while Public is off.') }}</template>
				</SettingRow>
				<template v-if="formKind !== 'tree'">
					<SettingRow :label="t('type_view.feeds', 'Feeds')" :changed="changedKey('feed')" :code="code('feed', form.feed)">
						<ToggleSwitch :checked="routed && form.feed" :label="t('type_view.feeds', 'Feeds')" form :locked="!editable || !routed" @change="form.feed = $event" />
						<template #help>{{ routed ? (taxonomy || people ? t('type_view.feeds_help_each', 'RSS, Atom, and JSON feeds of its entries, and one for each of them, of what links to it.') : t('type_view.feeds_help', 'RSS, Atom, and JSON feeds of its entries.')) : t('type_view.feeds_no_routes', 'Disabled while Routes is off.') }}</template>
					</SettingRow>
					<SettingRow :label="t('type_view.feed_categories', 'Feed Categories')" control-id="type-feed-categories" :help="feedOn ? t('type_view.feed_categories_help', 'The taxonomy whose entries become each feed item\'s categories.') : t('type_view.off_feeds', 'Disabled while Feeds is off.')" :changed="changedKey('feedCategories')">
						<AdminSelect id="type-feed-categories" v-model="form.feedCategories" :options="taxonomyOptions" :disabled="!editable || !feedOn" />
					</SettingRow>
					<SettingRow :label="t('type_view.feed_listing', 'Feed Listing')" :help="feedOn ? t('type_view.feed_listing_help', 'How the feeds list entries. Empty, newest first, as many as the site\'s feed limit.') : t('type_view.off_feeds', 'Disabled while Feeds is off.')" :changed="changedKey('feedListing')" wide>
						<div class="type-page__trio">
							<label><span>{{ t('type_view.order_by', 'Order By') }}</span><AdminSelect id="type-feed-order-by" v-model="form.feedListing.orderBy" :options="orderByOptions" :disabled="!editable || !feedOn" /></label>
							<label><span>{{ t('type_view.direction', 'Direction') }}</span><AdminSelect id="type-feed-order" v-model="form.feedListing.order" :options="directionOptions" :disabled="!editable || !feedOn" /></label>
							<label><span>{{ t('type_view.how_many', 'How Many') }}</span><input v-model="form.feedListing.perPage" inputmode="numeric" :placeholder="t('type_view.feed_limit', 'Site\'s limit')" :disabled="!editable || !feedOn"></label>
						</div>
					</SettingRow>
				</template>
				<p v-else class="type-page__absent"><AdminIcon name="info" />{{ t('type_view.tree_no_feed', 'A tree has no feed: it has no listing to feed from.') }}</p>
			</div>

			<h3 class="setting-panels__group"><AdminIcon name="list" />{{ t('type_view.entries', 'Entries') }}</h3>
			<div class="setting-panels__rows">
				<SettingRow v-if="formKind === 'collection'" :label="t('type_view.order', 'Order')" control-id="type-order" :changed="changedKey('order')" :code="code('order', form.order)">
					<AdminSelect id="type-order" v-model="form.order" :options="ORDERS" :disabled="!editable" />
					<template #help>{{ form.order === 'position' && !form.position ? t('type_view.order_no_position', 'By position, but Position is off under Supports, so entries sort by title.') : t('type_view.order_help', 'How entries sort in lists and the admin.') }}</template>
				</SettingRow>
				<SettingRow v-else-if="formKind === 'tree'" :label="t('type_view.order', 'Order')" :help="t('type_view.tree_order', 'A tree\'s pages sort by position, then title, and nest by folder.')">
					<p class="setting__value">{{ t('type_view.by_position', 'By position, then title') }}</p>
				</SettingRow>
				<SettingRow v-if="formKind === 'collection' || people" :label="t('type_view.listing', 'Listing Page')" :help="t('type_view.listing_help', 'How its listing page lists entries. Empty uses each default: sorted by its order, ten to a page.')" :changed="changedKey('listing')" wide>
					<div class="type-page__trio">
						<label><span>{{ t('type_view.lists', 'Lists') }}</span><AdminSelect id="type-listing-type" v-model="form.listing.type" :options="listsOptions" :disabled="!editable" /></label>
						<label><span>{{ t('type_view.order_by', 'Order By') }}</span><AdminSelect id="type-listing-order-by" v-model="form.listing.orderBy" :options="orderByOptions" :disabled="!editable" /></label>
						<label><span>{{ t('type_view.direction', 'Direction') }}</span><AdminSelect id="type-listing-order" v-model="form.listing.order" :options="directionOptions" :disabled="!editable" /></label>
						<label><span>{{ t('type_view.per_page', 'Per Page') }}</span><input v-model="form.listing.perPage" inputmode="numeric" placeholder="10" :disabled="!editable"></label>
					</div>
					<p v-if="form.listing.perPage !== '' && !/^\d+$/.test(form.listing.perPage)" class="field__error">{{ t('type_view.per_page_error', 'A whole number; 0 lists every entry on one page.') }}</p>
				</SettingRow>
				<SettingRow :label="t('type_view.workflow', 'Workflow')" control-id="type-workflow" :changed="changedKey('workflow')" :code="code('workflow', form.workflow)">
					<AdminSelect v-if="workflowOptions.length > 1" id="type-workflow" v-model="form.workflow" :options="workflowOptions" :disabled="!editable" />
					<p v-else class="setting__value">{{ workflowOptions[0]?.label }}</p>
					<template #help>{{ workflowOptions.length > 1 ? (changedKey('workflow') ? t('type_view.workflow_changed', 'Entries keep their statuses. Site Health lists any the new workflow doesn\'t use.') : t('type_view.workflow_help', 'The statuses its entries move through, and who may move them.')) : t('type_view.workflow_one', 'The site has one workflow: draft and published.') }}</template>
				</SettingRow>
			</div>

			<template v-if="typeFiles">
				<h3 class="setting-panels__group"><AdminIcon name="file" />{{ t('type_view.files', 'Files') }}</h3>
				<div class="setting-panels__rows">
					<SettingRow :label="t('type_view.file_names', 'File Names')" control-id="type-filename" :changed="changedKey('filename')" :code="code('filename', form.filename)">
						<AdminSelect id="type-filename" v-model="form.filename" :options="filenameOptions" :disabled="!editable" />
						<template #help>{{ changedKey('filename') ? t('type_view.files_changed', 'Only new files are named this way. Site Health can rename the others.') : t('type_view.file_names_help', 'How new files are named. It\'s never part of an address.') }}</template>
					</SettingRow>
					<SettingRow v-if="formKind === 'collection' || people" :label="t('type_view.folders', 'Folders')" control-id="type-folders" :changed="changedKey('folders')" :code="code('folders', form.folders)">
						<AdminSelect id="type-folders" v-model="form.folders" :options="folderOptions" :disabled="!editable" />
						<template #help>{{ changedKey('folders') ? t('type_view.files_moved', 'Only new files go in these folders. Site Health can move the others.') : t('type_view.folders_help', 'Folders inside {folder} that files are kept in. Never part of an address.', { folder: `user/content/_${name || 'name'}` }) }}</template>
					</SettingRow>
					<p v-else class="type-page__absent"><AdminIcon name="info" />{{ t('type_view.tree_folders', 'A tree\'s folders are its addresses, so they aren\'t a setting.') }}</p>
				</div>
			</template>
		</section>

		<!-- Archives -->
		<section id="type-archives" class="panel" aria-labelledby="type-archives-heading">
			<header class="panel__header">
				<h2 id="type-archives-heading">{{ t('type_view.archives', 'Archives') }}</h2>
			</header>
			<div class="setting-panels__rows">
				<template v-if="formKind === 'collection'">
					<SettingRow :label="t('type_view.date_archives', 'Date Archives')" control-id="type-date-archives" :changed="changedKey('dateArchives')" :code="code('dateArchives', form.dateArchives)">
						<AdminSelect id="type-date-archives" v-model="form.dateArchives" :options="DATE_ARCHIVES" :disabled="!editable" />
						<template #help>{{ form.dateArchives === 'none' ? t('type_view.undated', 'Undated: no archive pages by date.') : t('type_view.dated', 'Dated: new entries get a date, and there\'s an archive page for each level down to this one.') }}</template>
					</SettingRow>
					<SettingRow :label="t('type_view.index_page', 'Index Page')">
						<p v-if="type?.index" class="setting__value"><RouterLink :to="entryRoute(type.index)">{{ type.index.title }}</RouterLink></p>
						<ToggleSwitch v-else :checked="index" :label="t('type_view.index_page', 'Index Page')" form :locked="!editable" @change="index = $event" />
						<template #help>{{ type?.index ? t('type_view.index_has', 'The page at its address, pinned at the top of its list. It\'s an entry, edited like one.') : t('type_view.index_help', 'Makes a page at {address}, titled with its plural name and pinned at the top of its list. It\'s made once, then edited like any entry.', { address: address || '/…' }) }}</template>
					</SettingRow>
				</template>
				<p v-else-if="formKind === 'tree'" class="type-page__absent"><AdminIcon name="info" />{{ t('type_view.tree_archives', 'A tree has no listing or date archives. Its folder\'s index.md is its landing page.') }}</p>
				<p v-else class="type-page__absent"><AdminIcon name="info" />{{ t('type_view.profiles_archives', 'Each profile has a page of its own; there are no archives.') }}</p>
			</div>
		</section>

		<!-- Relationships -->
		<TypeRelations v-if="type" id="type-relationships" :type="type" @changed="load(type.name)">
			<div class="setting-panels__rows type-page__relation-settings">
				<SettingRow v-if="formKind === 'collection'" :label="t('type_view.hierarchical', 'Nesting')" :changed="changedKey('hierarchical')" :code="code('hierarchical', form.hierarchical)">
					<ToggleSwitch :checked="form.hierarchical" :label="t('type_view.hierarchical', 'Nesting')" form :locked="!editable" @change="form.hierarchical = $event" />
					<template #help>{{ t('type_view.hierarchical_help', 'An entry can name another as its parent, as categories nest. The editor gets a Parent field.') }}</template>
				</SettingRow>
				<SettingRow v-else-if="formKind === 'tree'" :label="t('type_view.hierarchical', 'Nesting')" :help="t('type_view.tree_nesting', 'A page\'s parent is the folder its file is in.')">
					<p class="setting__value">{{ t('type_view.by_folder', 'By folder') }}</p>
				</SettingRow>
				<template v-if="credits">
					<SettingRow :label="t('type_view.author_credits', 'Author Credits')" :changed="authorsChanged">
						<ToggleSwitch :checked="form.authors" :label="t('type_view.author_credits', 'Author Credits')" form @change="form.authors = $event" />
						<template #help>{{ t('type_view.author_credits_help', 'Entries credit profiles as their authors, in order, the first the lead. This joins the Authors relationship.') }}</template>
					</SettingRow>
					<SettingRow v-for="archive in type.archivePages" :key="archive.relation" :label="t('type_view.archive_page', '{label} List Page', { label: archive.label })">
						<p v-if="archive.page" class="setting__value"><RouterLink :to="entryRoute(archive.page)">{{ archive.page.title }}</RouterLink></p>
						<ToggleSwitch v-else :checked="pages.includes(archive.relation)" :label="t('type_view.archive_page', '{label} List Page', { label: archive.label })" form @change="wantPage(archive.relation, $event)" />
						<template #help>{{ t('type_view.archive_page_help', 'A page introducing the list at {address}.', { address: `${address}/${archive.word}` }) }}</template>
					</SettingRow>
					<SettingRow v-if="type.credits.length > 1" :label="t('type_view.byline', 'Byline')" control-id="type-byline" :help="t('type_view.byline_help', 'Which credit names an entry\'s author, first and linked in its byline.')" :changed="changedKey('byline')">
						<AdminSelect id="type-byline" v-model="form.byline" :options="bylineOptions" :disabled="!editable" />
					</SettingRow>
				</template>
			</div>
		</TypeRelations>
		<section v-else id="type-relationships" class="panel" aria-labelledby="type-relationships-heading">
			<header class="panel__header">
				<h2 id="type-relationships-heading">{{ t('type_view.relationships', 'Relationships') }}</h2>
				<p class="panel__hint">{{ t('type_view.relationships_new', 'How its entries connect to others. More are added once it\'s made.') }}</p>
			</header>
			<div class="setting-panels__rows">
				<SettingRow v-if="taxonomy" :label="t('type_view.files_types', 'Files These Types')" wide>
					<div class="choices">
						<label v-for="other in fileable" :key="other.name" class="checkbox"><input type="checkbox" :checked="files.includes(other.name)" @change="filed(other.name, ($event.target as HTMLInputElement).checked)"> {{ other.labels.plural }}</label>
					</div>
					<template #help>{{ files.length ? t('type_view.files_some', 'Their entries are filed under its entries, through the relationship Create makes. Change it later on its own screen.') : t('type_view.files_none', 'None chosen: it can file any type.') }}</template>
				</SettingRow>
				<SettingRow v-if="formKind === 'collection'" :label="t('type_view.hierarchical', 'Nesting')">
					<ToggleSwitch :checked="form.hierarchical" :label="t('type_view.hierarchical', 'Nesting')" form @change="form.hierarchical = $event" />
					<template #help>{{ t('type_view.hierarchical_help', 'An entry can name another as its parent, as categories nest. The editor gets a Parent field.') }}</template>
				</SettingRow>
				<SettingRow v-if="credits" :label="t('type_view.author_credits', 'Author Credits')">
					<ToggleSwitch :checked="form.authors" :label="t('type_view.author_credits', 'Author Credits')" form @change="form.authors = $event" />
					<template #help>{{ t('type_view.author_credits_help', 'Entries credit profiles as their authors, in order, the first the lead. This joins the Authors relationship.') }}</template>
				</SettingRow>
			</div>
		</section>

		<!-- Addresses -->
		<section id="type-addresses" class="panel" aria-labelledby="type-addresses-heading">
			<header class="panel__header">
				<h2 id="type-addresses-heading">{{ t('type_view.addresses', 'Addresses') }}</h2>
			</header>
			<div class="setting-panels__rows">
				<template v-if="formKind === 'collection' || people">
					<SettingRow :label="t('type_view.routes', 'Routes')" :changed="changedKey('routes')">
						<ToggleSwitch :checked="form.public && form.routes" :label="t('type_view.routes', 'Routes')" form :locked="!editable || !form.public || !typeUrls" @change="form.routes = $event" />
						<template #help>{{ form.public ? t('type_view.routes_help', 'Its listing, entries, archives, and feeds have addresses. Off, it stays public (in llms.txt, linkable from other entries) but nothing has an address.') : t('type_view.off_public', 'Disabled while Public is off.') }}</template>
					</SettingRow>
					<SettingRow :label="t('type_view.prefix', 'URL Prefix')" control-id="type-prefix" :changed="changedKey('prefix')">
						<PatternField id="type-prefix" v-model="form.prefix" lead="/" :placeholder="people ? 'profiles' : (name || 'name')" :label="t('type_view.prefix', 'URL Prefix')" :disabled="!editable || !typeUrls || !routed" />
						<template #help>{{ !typeUrls ? t('type_view.prefix_locked', 'The site\'s config turns off setting addresses here, so it answers at its name.') : (changedKey('prefix') && type?.entries ? t('type_view.prefix_moves', 'Saving moves {count, plural, one {# page} other {# pages} } to new addresses. Add redirects for the old ones under Tools → Redirects.', { count: type.entries }) : t('type_view.prefix_help', 'Where every address starts. Empty uses its name.')) }}</template>
					</SettingRow>
				</template>
				<SettingRow v-else-if="name !== 'page'" :label="t('type_view.prefix', 'URL Prefix')" control-id="type-prefix" :changed="changedKey('prefix')">
					<PatternField id="type-prefix" v-model="form.prefix" lead="/" :placeholder="name || 'name'" :label="t('type_view.prefix', 'URL Prefix')" :disabled="!editable || !typeUrls" />
					<template #help>{{ t('type_view.tree_prefix_help', 'Where its pages are. Each page is at its folder path under it: install/requirements.md is at {address}.', { address: `${address}/install/requirements` }) }}</template>
				</SettingRow>
				<SettingRow v-else :label="t('type_view.prefix', 'URL Prefix')" :help="t('type_view.page_prefix', 'The site\'s pages are at its root.')">
					<p class="setting__value mono">/</p>
				</SettingRow>
			</div>
			<TypeRoutesFields v-if="routed && shownRoutes.length" v-model="form" id-prefix="route-" :routes="shownRoutes" :taxonomy="taxonomy" :prefix="(form.prefix || (fresh ? key : (type?.defaultPrefix ?? ''))).replace(/^\/+|\/+$/g, '')" :editable="editable && typeUrls" :saved="fresh ? null : (initial?.paths ?? null)" />
		</section>

		<!-- Fields -->
		<div id="fields-heading-panel">
			<TypeFields v-model="form.fields" :type="fresh ? null : name" :library="fresh ? library : (type?.library ?? [])" :own="type?.ownFields ?? []" :locked="!fieldsEditable" heading-id="fields-heading" id-prefix="type-field-" :noun="(form.plural || t('type_view.entries_noun', 'entries')).toLowerCase()" />
		</div>

		<!-- Danger zone -->
		<section v-if="dangerZone && type" id="type-danger" class="panel" aria-labelledby="type-danger-heading">
			<header class="panel__header">
				<h2 id="type-danger-heading">{{ t('type_view.danger_zone', 'Danger Zone') }}</h2>
			</header>
			<div class="panel__body">
				<DangerZone v-if="type.origin === 'extension'" :error="removal">
					{{ type.overridden ? t('type_view.reset_text', 'Removes {file}, so it\'s exactly as {plugin} defines it.', { file: type.file ?? '', plugin: origin }) : t('type_view.reset_nothing', 'Nothing has been changed here, so there\'s nothing to reset.') }} {{ t('type_view.plugin_delete', 'A plugin\'s type goes when the plugin is turned off.') }}
					<template #action><button type="button" class="button button--danger" :disabled="!type.overridden || count > 0" @click="reset"><AdminIcon name="refresh-cw" />{{ t('type_view.reset', 'Reset') }}</button></template>
				</DangerZone>
				<DangerZone v-else :error="removal">
					<template v-if="naming.length">{{ t('type_view.delete_blocked', 'The {names} {count, plural, one {relationship names} other {relationships name} } it; remove {count, plural, one {it} other {them} } first.', { names: series(naming.map((relation) => relation.label || humanize(relation.name))), count: naming.length }) }}</template>
					<template v-else>{{ t('type_view.delete_text', 'Removes the type. Its {count, plural, one {# entry stays} other {# entries stay} } in user/content/{folder}, off the site.', { count: type.entries, folder: type.folder }) }}</template>
					<template v-if="count > 0"> {{ t('type_view.save_first', 'Save or revert your changes first.') }}</template>
					<template #action><button type="button" class="button button--danger" :disabled="naming.length > 0 || count > 0" @click="remove"><AdminIcon name="trash-2" />{{ t('type_view.delete', 'Delete {name}', { name: type.labels.plural }) }}</button></template>
				</DangerZone>
			</div>
		</section>

	</form>

	<div v-else-if="!error && !(fresh && !canCreateTypes)" class="panel" aria-hidden="true"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
</template>

<style scoped>

/* What a kind doesn't have, said once in place of its settings. */
.type-page__absent {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	margin: 0;
	padding: var(--s-3) var(--padding-inline);
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

.type-page__absent .icon {
	flex: none;
	width: 14px;
	height: 14px;
}

.type-page__trio {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
	gap: var(--s-3);
}

.type-page__trio label {
	display: grid;
	gap: 4px;
	color: var(--color-text-secondary);
	font-size: var(--font-size-small);
}

.type-page__relation-settings {
	border-bottom: 1px solid var(--border-color);
}

.setting__value {
	display: flex;
	align-items: center;
	gap: 6px;
	min-height: var(--ctl);
	margin: 0;
}

.setting__value .icon {
	width: 14px;
	height: 14px;
	color: var(--color-text-muted);
}
</style>
