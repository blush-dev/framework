<script setup lang="ts">
/**
 * A reference field in the editor (D-242's picker, D-281; admin.md §8,
 * The document panel): the entries of another type it points at, by
 * slug, from `GET references/{type}`. The form value stays what the form
 * has always held, the slugs separated by commas, so saving is unchanged.
 * One picker, shaped by what it points at:
 *
 * - **A hierarchical collection's terms are one box**: a search field, the tree of
 *   terms as checkboxes, indented by depth, each with how many entries
 *   use it, and **New {term}** at its foot, which opens a name and a
 *   parent (the tree, indented) and writes the term. A search keeps a
 *   match's parents in view.
 * - **Anything else it can hold several of is a token field**: chips and
 *   a bare input in one box. Typing searches; Enter takes the first
 *   suggestion, or with nothing matching writes a new term named as it
 *   was typed and adds it, when the type allows it and the account may
 *   create its entries (a term is its file, D-584); Backspace in an
 *   empty input removes the last chip.
 * - **Authors are people**: each a mark, a name, and its slug, the first
 *   marked **Lead** when there can be several, removed with an × that
 *   isn't there for the last one when it must stay (`keepLast`: an
 *   entry's main byline, or a required people field; the handler refuses
 *   too), and added by a search.
 * - **One value is a select**: none, or one of the entries, in tree
 *   order for a hierarchical collection (the parent of a term, without the
 *   term itself and the terms under it).
 *
 * A slug the field holds that nothing answers to is shown as it's
 * written, marked "not found".
 *
 * The field's relation decides the rest (D-599): whether a typed value
 * may be written as a new entry (`create`), how many it takes (a count
 * against `max`, and nothing more taken at it; `min` said when it's
 * above one), and whether its order matters (`ordered`; people always):
 * then chips and people move by dragging, or with ⌥ and the arrow keys
 * (`reorder.ts`). A translation shows what it uses from its original,
 * dimmed (`inherited`).
 */

import { computed, nextTick, ref, watch } from 'vue';
import { debounced } from '../action';
import { errorMessage, request, type FieldDescription, type InheritedValues } from '../api';
import { label } from '../fields';
import { formatDate, plural } from '../format';
import { listMove } from '../grid';
import { initials } from '../people';
import { loadReferences, referenceValues, slugOf, type ReferenceItem } from '../references';
import { moved, useReorder } from '../reorder';
import { canType } from '../session';
import { labelsOf } from '../types';
import AdminIcon from './AdminIcon.vue';
import AdminSelect from './AdminSelect.vue';

const props = defineProps<{
	id: string;
	field: FieldDescription;
	// Whether it points at people (the site's profiles).
	people?: boolean;
	// Whether the last person stays (an entry's main byline, or a
	// required people field).
	keepLast?: boolean;
	// The entry's own slug, so a term isn't its own parent.
	self?: string;
	invalid?: boolean;
	describedBy?: string;
	// A single value drawn as a value in a row, not a box.
	plain?: boolean;
	// What a translation uses from its original.
	inherited?: InheritedValues;
}>();

const model = defineModel<string>({ required: true });

const type     = computed(() => props.field.to ?? '');
const multiple = computed(() => props.field.multiple !== false);

// How the server says to pick it (D-599): people, a tree, cards, or
// tokens; a select for one value whatever it says.
const control  = computed(() => props.field.relation?.control);
const asPeople = computed(() => props.people === true || control.value === 'people');
const cards    = computed(() => control.value === 'cards');
const names    = computed(() => labelsOf(type.value));

// What's known about each slug, from every answer so far.
const known  = ref(new Map<string, ReferenceItem>());
const tree   = ref<ReferenceItem[] | null>(null);
// What the first answer offered, for a select.
const options = ref<ReferenceItem[]>([]);
const create = ref(false);
const error  = ref('');

function remember(items: ReferenceItem[]): void {
	const next = new Map(known.value);

	for (const item of items) {
		if (!item.missing || !next.has(item.slug)) {
			next.set(item.slug, item);
		}
	}

	known.value = next;
}

const values = computed(() => referenceValues(model.value));
const slugs  = computed(() => values.value.map(slugOf));

function itemOf(value: string): ReferenceItem {
	return known.value.get(slugOf(value)) ?? { slug: slugOf(value), title: value, status: null, parent: null, uses: null, depth: null, missing: false };
}

function write(next: string[]): void {
	model.value = next.join(', ');
}

function has(slug: string): boolean {
	return slugs.value.includes(slug);
}

// How many it takes, and needs to publish (D-599).
const max     = computed(() => multiple.value ? props.field.relation?.max ?? null : null);
const minimum = computed(() => props.field.relation?.min ?? 0);
const full    = computed(() => max.value !== null && values.value.length >= max.value);

// Whether its order matters: people's always does (the first is the lead).
const ordered = computed(() => asPeople.value || props.field.relation?.ordered === true);
const reorder = useReorder(() => values.value.length, (from, to) => write(moved(values.value, from, to)));

// What a translation shows from its original: the original's, when it
// has none of its own, or always beside its own.
const inheritedShown = computed(() => props.inherited !== undefined && (props.inherited.rule === 'add' || values.value.length === 0) ? props.inherited.values : []);

function add(value: string): void {
	const slug = slugOf(value);

	if (slug === '' || has(slug) || (multiple.value && full.value)) {
		return;
	}

	write(multiple.value ? [...values.value, slug] : [slug]);
}

function remove(slug: string): void {
	// An entry always has an author (admin.md §8): the last one stays.
	if (asPeople.value && props.keepLast && values.value.length <= 1) {
		return;
	}

	write(values.value.filter((value) => slugOf(value) !== slug));
}

function toggle(slug: string): void {
	if (has(slug)) {
		write(values.value.filter((value) => slugOf(value) !== slug));
	} else {
		add(slug);
	}
}

// The first answer: the whole tree for a hierarchical collection, else the
// field's own slugs, so its chips have names.
async function start(): Promise<void> {
	error.value = '';

	try {
		const list = await loadReferences(type.value, { slugs: [...slugs.value, ...(props.inherited?.values ?? [])], limit: 100 });

		create.value = props.field.relation === undefined ? list.create : props.field.relation.create;
		tree.value   = list.tree ? list.items.filter((item) => !item.missing) : null;
		remember(list.items);
		options.value = list.items.filter((item) => !item.missing);
	} catch (caught) {
		error.value = errorMessage(caught, `The ${names.value.items} couldn't be loaded.`);
	}
}

watch(type, () => void start(), { immediate: true });

// The search, for the token field and people: asked of the server a
// moment after typing stops.
const query       = ref('');
const suggestions = ref<ReferenceItem[]>([]);
const active      = ref(0);

const search = debounced(async (text: string) => {
	try {
		const list = await loadReferences(type.value, { search: text.trim(), limit: 6 });

		if (text === query.value) {
			remember(list.items);
			suggestions.value = list.items.filter((item) => !item.missing && !has(item.slug));
		}
	} catch {
		suggestions.value = [];
	}
}, 150);

watch(query, (text) => {
	search.cancel();
	active.value = 0;

	if (text.trim() === '' || tree.value !== null) {
		suggestions.value = [];

		return;
	}

	search(text);
});

const canCreate = computed(() => canType(type.value, 'create'));
const writing   = ref(false);

// Whether Enter would write a new term named as typed: nothing matches it
// exactly, the type takes new ones, and the account may create them.
// People are chosen, never written here.
const creatable = computed(() => create.value && canCreate.value && !asPeople.value && !full.value && query.value.trim() !== '' && slugOf(query.value) !== ''
	&& !suggestions.value.some((item) => item.slug === slugOf(query.value)) && !has(slugOf(query.value)));

/**
 * Writes a term (`POST entries`), published when the account may
 * publish, and answers its slug, or `null` when it couldn't be written.
 */
async function writeTerm(name: string, parent = ''): Promise<string | null> {
	writing.value = true;
	error.value   = '';

	try {
		const created = await request<{ slug: string; title: string; status: ReferenceItem['status'] }>('POST', '/entries', {
			type: type.value,
			title: name,
			status: canType(type.value, 'publish') ? 'published' : 'draft',
			set: parent === '' ? {} : { parent }
		});

		remember([{ slug: created.slug, title: created.title, status: created.status, parent: parent === '' ? null : parent, uses: 0, depth: null, missing: false }]);

		return created.slug;
	} catch (caught) {
		error.value = errorMessage(caught, `The ${names.value.item} couldn't be created.`);

		return null;
	} finally {
		writing.value = false;
	}
}

async function choose(item: ReferenceItem | null): Promise<void> {
	const typed = query.value.trim();

	query.value = '';

	if (item !== null) {
		add(item.slug);
	} else if (typed !== '' && create.value && canCreate.value && !asPeople.value && !writing.value) {
		const slug = await writeTerm(typed);

		if (slug !== null) {
			add(slug);
		}
	}
}

function searchKey(event: KeyboardEvent): void {
	const count = suggestions.value.length + (creatable.value ? 1 : 0);

	if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
		event.preventDefault();
		active.value = listMove(event.key, active.value, count) ?? 0;
	} else if (event.key === 'Enter') {
		event.preventDefault();

		if (count > 0) {
			void choose(suggestions.value[active.value] ?? null);
		}
	} else if (event.key === 'Backspace' && query.value === '' && values.value.length > 0 && !asPeople.value) {
		const last = values.value.at(-1);

		if (last !== undefined) {
			remove(slugOf(last));
		}
	} else if (event.key === 'Escape' && query.value !== '') {
		event.preventDefault();
		event.stopPropagation();
		query.value = '';
	}
}

const input = ref<HTMLInputElement | null>(null);

function focusInput(event: MouseEvent): void {
	if ((event.target as HTMLElement).closest('button, input') === null) {
		input.value?.focus();
	}
}

// The tree, searched: a match keeps its parents in view.
const treeQuery = ref('');

const treeRows = computed(() => {
	const all  = tree.value ?? [];
	const text = treeQuery.value.trim().toLowerCase();

	if (text === '') {
		return all;
	}

	const bySlug = new Map(all.map((item) => [item.slug, item]));
	const keep   = new Set<string>();

	for (const item of all) {
		if (item.title.toLowerCase().includes(text) || item.slug.includes(text)) {
			let at: ReferenceItem | undefined = item;

			while (at !== undefined && !keep.has(at.slug)) {
				keep.add(at.slug);
				at = at.parent === null ? undefined : bySlug.get(at.parent);
			}
		}
	}

	return all.filter((item) => keep.has(item.slug));
});

// The field's slugs the tree doesn't have (a missing term).
const outside = computed(() => tree.value === null ? [] : values.value.filter((value) => !tree.value?.some((item) => item.slug === slugOf(value))));

// A new term, written where it's being chosen.
const adding     = ref(false);
const newName    = ref('');
const newParent  = ref('');
const newField   = ref<HTMLInputElement | null>(null);

async function openNew(): Promise<void> {
	adding.value    = true;
	newName.value   = treeQuery.value.trim();
	newParent.value = '';
	await nextTick();
	newField.value?.focus();
}

async function saveNew(): Promise<void> {
	const name = newName.value.trim();

	if (name === '' || writing.value) {
		newField.value?.focus();

		return;
	}

	const slug = await writeTerm(name, newParent.value);

	if (slug !== null) {
		adding.value    = false;
		treeQuery.value = '';
		await start();
		add(slug);
	}
}

function newKey(event: KeyboardEvent): void {
	if (event.key === 'Enter') {
		event.preventDefault();
		void saveNew();
	} else if (event.key === 'Escape') {
		event.preventDefault();
		event.stopPropagation();
		adding.value = false;
	}
}

// Everything the select offers, and the terms that can't be a term's
// parent: itself and the terms under it.

const selectOptions = computed(() => {
	const all      = tree.value ?? options.value;
	const excluded = new Set<string>(props.self === undefined ? [] : [props.self]);
	let grew       = true;

	while (grew) {
		grew = false;

		for (const item of all) {
			if (item.parent !== null && excluded.has(item.parent) && !excluded.has(item.slug)) {
				excluded.add(item.slug);
				grew = true;
			}
		}
	}

	const current = slugs.value[0];
	const list    = all.filter((item) => !excluded.has(item.slug)).map((item) => ({ value: item.slug, label: item.title, depth: item.depth ?? 0 }));

	if (current !== undefined && !list.some((item) => item.value === current)) {
		list.push({ value: current, label: `${itemOf(current).title} (not found)`, depth: 0 });
	}

	return [{ value: '', label: 'None', depth: 0 }, ...list];
});

const parentOptions = computed(() => [{ value: '', label: 'None, at the top level' }, ...(tree.value ?? []).map((item) => ({ value: item.slug, label: item.title, depth: item.depth ?? 0 }))]);

const persons = computed(() => values.value.map((value) => itemOf(value)));
</script>

<template>
	<div class="reference">
		<!-- One value: a select. -->
		<AdminSelect
			v-if="!multiple"
			:id="id"
			:model-value="slugs[0] ?? ''"
			:options="selectOptions"
			:described-by="describedBy"
			:invalid="invalid"
			:plain="plain"
			@update:model-value="write($event === '' ? [] : [$event])"
		/>

		<!-- Authors: people. -->
		<template v-else-if="asPeople">
			<ul v-if="persons.length" class="reference__people">
				<li v-for="(person, index) in persons" :key="person.slug" class="reference__person" v-bind="ordered && persons.length > 1 ? reorder.item(index) : {}" :tabindex="ordered && persons.length > 1 ? 0 : undefined">
					<span class="avatar reference__avatar" aria-hidden="true">{{ initials(person.title) }}</span>
					<span class="reference__who">
						<span class="reference__name">{{ person.title }}</span>
						<span class="reference__meta"><template v-if="index === 0 && persons.length > 1">Lead · </template><span class="mono">{{ person.slug }}</span><template v-if="person.missing"> · not found</template></span>
					</span>
					<button v-if="persons.length > 1 || !keepLast" type="button" class="reference__remove" @click="remove(person.slug)">
						<AdminIcon name="x" /><span class="visually-hidden">Remove {{ person.title }}</span>
					</button>
				</li>
			</ul>
			<div class="reference__search">
				<AdminIcon name="search" />
				<input :id="id" ref="input" v-model="query" type="text" autocomplete="off" :disabled="full" :placeholder="full ? 'That\'s the most it takes' : `Add ${names.item === 'author' ? 'an author' : names.item}…`" role="combobox" :aria-expanded="suggestions.length > 0" :aria-controls="`${id}-suggestions`" :aria-describedby="describedBy" @keydown="searchKey">
			</div>
			<ul v-if="query.trim() && (suggestions.length || creatable)" :id="`${id}-suggestions`" class="reference__suggestions" role="listbox">
				<li v-for="(item, index) in suggestions" :key="item.slug" role="option" :aria-selected="index === active">
					<button type="button" :class="{ 'is-active': index === active }" @mousedown.prevent @click="void choose(item)">
						<span class="avatar reference__avatar reference__avatar--small" aria-hidden="true">{{ initials(item.title) }}</span>
						<span class="reference__suggestion-name">{{ item.title }}</span>
						<span class="reference__count mono">{{ item.slug }}</span>
					</button>
				</li>
			</ul>
			<p v-else-if="query.trim() && !suggestions.length" class="field__help">Nobody matches “{{ query.trim() }}”.</p>
			<p v-if="persons.length === 1" class="field__help">An entry always has at least one {{ names.item }}, so this one can't be removed until another is added.</p>
		</template>

		<!-- A hierarchical collection: one box of search, tree, and new term. -->
		<div v-else-if="tree && control !== 'tokens' && control !== 'cards'" class="reference__box">
			<div class="reference__search reference__search--inside">
				<AdminIcon name="search" />
				<input :id="id" v-model="treeQuery" type="search" autocomplete="off" :placeholder="`Search ${names.items}…`" :aria-describedby="describedBy">
			</div>
			<div class="reference__tree" role="group" :aria-label="label(field)">
				<label v-for="item in treeRows" :key="item.slug" class="reference__term" :class="{ 'is-on': has(item.slug) }" :style="{ '--depth': item.depth ?? 0 }">
					<input type="checkbox" class="check-input" :checked="has(item.slug)" :disabled="full && !has(item.slug)" @change="toggle(item.slug)">
					<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
					<span class="reference__term-name">{{ item.title }}<span v-if="item.status === 'draft'" class="reference__draft"> · draft</span></span>
					<span class="reference__count mono">{{ item.uses ?? 0 }}</span>
				</label>
				<label v-for="value in outside" :key="`outside-${value}`" class="reference__term is-on">
					<input type="checkbox" class="check-input" checked @change="toggle(slugOf(value))">
					<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
					<span class="reference__term-name">{{ itemOf(value).title }} <span class="reference__draft">· not found</span></span>
				</label>
				<p v-if="!treeRows.length && !outside.length" class="reference__empty">{{ treeQuery.trim() ? `No ${names.items} match “${treeQuery.trim()}”.` : `No ${names.items} yet.` }}</p>
			</div>
			<template v-if="canCreate">
				<div v-if="adding" class="reference__new">
					<label class="visually-hidden" :for="`${id}-new`">Name</label>
					<input :id="`${id}-new`" ref="newField" v-model="newName" type="text" autocomplete="off" :placeholder="`${names.singular} name`" @keydown="newKey">
					<label class="visually-hidden" :for="`${id}-parent`">Parent</label>
					<AdminSelect :id="`${id}-parent`" v-model="newParent" :options="parentOptions" />
					<div class="reference__new-actions">
						<button type="button" class="button button--small button--primary" :disabled="writing || !newName.trim()" @click="saveNew">{{ writing ? 'Adding…' : 'Add' }}</button>
						<button type="button" class="button button--small" @click="adding = false">Cancel</button>
					</div>
				</div>
				<button v-else type="button" class="reference__add" @click="openNew">
					<AdminIcon name="plus" />{{ names.newItem }}
				</button>
			</template>
		</div>

		<!-- Anything else: a token field. -->
		<template v-else>
			<div class="reference__tokens" :class="{ 'is-invalid': invalid }" @click="focusInput">
				<span v-for="(value, index) in values" :key="value" class="reference__token" :class="{ 'is-missing': itemOf(value).missing }" v-bind="ordered && values.length > 1 ? reorder.item(index) : {}" :tabindex="ordered && values.length > 1 ? 0 : undefined">
					{{ itemOf(value).title }}
					<button type="button" @click="remove(slugOf(value))"><AdminIcon name="x" /><span class="visually-hidden">Remove {{ itemOf(value).title }}</span></button>
				</span>
				<input :id="id" ref="input" v-model="query" type="text" autocomplete="off" :disabled="full" :placeholder="full ? 'That\'s the most it takes' : (values.length ? 'Add another…' : 'Type to add…')" role="combobox" :aria-expanded="suggestions.length > 0 || creatable" :aria-controls="`${id}-suggestions`" :aria-describedby="describedBy" @keydown="searchKey">
			</div>
			<ul v-if="query.trim() && (suggestions.length || creatable)" :id="`${id}-suggestions`" class="reference__suggestions" role="listbox">
				<li v-for="(item, index) in suggestions" :key="item.slug" role="option" :aria-selected="index === active">
					<button type="button" :class="{ 'is-active': index === active, 'reference__card': cards }" @mousedown.prevent @click="void choose(item)">
						<template v-if="cards">
							<img v-if="item.image" :src="item.image" alt="" class="reference__thumb" loading="lazy">
							<span v-else class="avatar reference__avatar" aria-hidden="true">{{ initials(item.title) }}</span>
							<span class="reference__who">
								<span class="reference__name">{{ item.title || 'Untitled' }}</span>
								<span class="reference__meta"><template v-if="item.date">{{ formatDate(`${item.date}T12:00:00`) }} · </template><template v-if="item.status && item.status !== 'published'">{{ item.status }} · </template><span class="mono">{{ item.slug }}</span></span>
							</span>
						</template>
						<template v-else>
							<span class="reference__suggestion-name">{{ item.title }}</span>
							<span v-if="item.uses !== null" class="reference__count mono">{{ item.uses }}</span>
						</template>
					</button>
				</li>
				<li v-if="creatable" role="option" :aria-selected="active === suggestions.length">
					<button type="button" :class="{ 'is-active': active === suggestions.length }" @mousedown.prevent @click="void choose(null)">
						<AdminIcon name="plus" /><span class="reference__suggestion-name">Add “{{ query.trim() }}”</span>
					</button>
				</li>
			</ul>
			<p v-else-if="query.trim() && !suggestions.length" class="field__help">Nothing matches “{{ query.trim() }}”.</p>
		</template>

		<p v-if="inheritedShown.length" class="reference__inherited">
			<span>{{ inherited?.rule === 'add' ? 'Also from the original:' : 'From the original:' }}</span>
			<span v-for="value in inheritedShown" :key="`inherited-${value}`" class="reference__token is-inherited">{{ itemOf(value).title }}</span>
		</p>
		<p v-if="multiple && (max !== null || minimum > 1 || (ordered && values.length > 1))" class="field__help">
			<template v-if="max !== null">{{ values.length }} of {{ max }}{{ full ? ', the most it takes' : '' }}. </template>
			<template v-if="minimum > 1">Needs at least {{ minimum }} to publish. </template>
			<template v-if="ordered && values.length > 1">Drag to reorder, or move one with ⌥↑ and ⌥↓.</template>
		</p>
		<p v-if="error" class="field__error">{{ error }}</p>
		<p v-if="multiple && !people && values.some((value) => itemOf(value).missing)" class="field__help">{{ plural(values.filter((value) => itemOf(value).missing).length, `${names.item} isn't`, `${names.items} aren't`) }} on the site.</p>
	</div>
</template>

<style scoped>
.reference {
	display: grid;
	gap: var(--s-2);
	min-width: 0;
}

/* Search, tree, and "new" are one object, not three stacked controls. */
.reference__box {
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
}

.reference__search {
	display: flex;
	align-items: center;
	gap: 9px;
	height: var(--ctl);
	padding: 0 12px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	color: var(--fg-3);
}

.reference__search--inside {
	border: 0;
	border-bottom: 1px solid var(--border);
	border-radius: 0;
}

.reference__search svg {
	flex: none;
	width: 14px;
	height: 14px;
}

.reference__search input,
.reference__tokens input {
	flex: 1;
	min-width: 90px;
	height: 22px;
	padding: 0;
	border: 0;
	background: none;
	color: var(--fg);
	font: inherit;
	font-size: var(--text-sm);
}

.reference__search input:focus-visible,
.reference__tokens input:focus-visible {
	outline: none;
}

.reference__search:not(.reference__search--inside):focus-within,
.reference__tokens:focus-within {
	border-color: var(--accent);
	background: var(--surface);
	box-shadow: 0 0 0 3px var(--accent-soft);
}

.reference__tree {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	max-height: 236px;
	overflow-y: auto;
}

/* Indent carries depth: this is picking, not browsing. */
.reference__term {
	--depth: 0;
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	padding: 8px 12px 8px calc(18px + var(--depth) * 16px);
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.reference__term:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.reference__term.is-on {
	color: var(--fg);
}

.reference__term-name,
.reference__suggestion-name {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.reference__draft {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.reference__count {
	flex: none;
	color: var(--fg-3);
	font-size: var(--text-2xs);
}

.reference__empty {
	padding: 26px 14px;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-align: center;
}

.reference__add {
	display: flex;
	align-items: center;
	gap: 9px;
	width: 100%;
	padding: 10px 12px;
	border: 0;
	border-top: 1px solid var(--border);
	background: var(--bg);
	color: var(--fg-2);
	font: inherit;
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.reference__add:hover {
	background: var(--surface-2);
	color: var(--fg);
}

.reference__add svg {
	width: 14px;
	height: 14px;
	color: var(--fg-3);
}

.reference__new {
	display: grid;
	gap: var(--s-2);
	padding: var(--s-3);
	border-top: 1px solid var(--border);
	background: var(--bg);
}

.reference__new > input {
	height: var(--ctl);
	padding: 0 12px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg);
	font: inherit;
	font-size: var(--text-sm);
}

.reference__new > input:focus-visible {
	border-color: var(--accent);
	outline: none;
	box-shadow: 0 0 0 3px var(--accent-soft);
}

.reference__new-actions {
	display: flex;
	gap: 6px;
}

/* Tags are neutral chips with a border: accent is ink here, not fill. */
.reference__tokens {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
	min-height: var(--ctl);
	padding: 6px 8px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	cursor: text;
}

.reference__tokens.is-invalid {
	border-color: var(--danger);
}

.reference__token[draggable="true"] {
	cursor: grab;
}

.reference__token.is-drop,
.reference__person.is-drop {
	box-shadow: inset 2px 0 0 var(--accent);
}

.reference__token.is-dragging,
.reference__person.is-dragging {
	opacity: 0.5;
}

.reference__inherited {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-1);
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.reference__token.is-inherited {
	padding-right: 9px;
	border-style: dashed;
	background: none;
	color: var(--fg-3);
}

.reference__token {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	height: 22px;
	padding: 0 4px 0 9px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	color: var(--fg-2);
	font-size: var(--text-xs);
	font-weight: 500;
}

.reference__token.is-missing {
	border-style: dashed;
}

.reference__token button {
	display: grid;
	place-items: center;
	padding: 0;
	border: 0;
	border-radius: 3px;
	background: none;
	color: var(--fg-3);
	cursor: pointer;
}

.reference__token button:hover {
	color: var(--danger);
}

.reference__token svg {
	width: 11px;
	height: 11px;
	stroke-width: 2.4;
}

.reference__suggestions {
	display: grid;
	margin: 0;
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
	list-style: none;
}

.reference__suggestions button {
	display: flex;
	align-items: center;
	gap: 9px;
	width: 100%;
	padding: 9px 12px;
	border: 0;
	background: none;
	color: var(--fg-2);
	font: inherit;
	font-size: var(--text-sm);
	text-align: left;
	cursor: pointer;
}

.reference__suggestions button:hover,
.reference__suggestions button.is-active {
	background: var(--surface-2);
	color: var(--fg);
}

.reference__suggestions svg {
	flex: none;
	width: 13px;
	height: 13px;
	color: var(--fg-3);
}

/* Authors are people: the avatar identifies, the name confirms, the line
   under it qualifies. */
.reference__people {
	display: grid;
	gap: 2px;
	margin: 0 -8px;
	padding: 0;
	list-style: none;
}

.reference__person {
	display: flex;
	align-items: center;
	gap: 11px;
	padding: 8px;
	border-radius: var(--r-1);
}

.reference__person:hover {
	background: var(--surface-2);
}

/* Larger than the global avatar, with smaller initials. */
.reference__avatar {
	width: 29px;
	height: 29px;
	font-size: var(--text-2xs);
}

.reference__suggestions .reference__card {
	gap: var(--s-2);
	padding-block: var(--s-1);
}

.reference__thumb {
	flex: none;
	width: 29px;
	height: 29px;
	border-radius: var(--r-1);
	object-fit: cover;
}

.reference__avatar--small {
	width: 20px;
	height: 20px;
}

.reference__who {
	display: grid;
	flex: 1;
	min-width: 0;
}

.reference__name {
	overflow: hidden;
	color: var(--fg);
	font-size: var(--text-sm);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.reference__meta {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

/* The × shows on hover or focus, where it's wanted. */
.reference__remove {
	display: grid;
	flex: none;
	place-items: center;
	width: 24px;
	height: 24px;
	padding: 0;
	border: 0;
	border-radius: var(--r-1);
	background: none;
	color: var(--fg-3);
	opacity: 0;
	cursor: pointer;
}

.reference__remove svg {
	width: 13px;
	height: 13px;
}

.reference__person:hover .reference__remove,
.reference__remove:focus-visible {
	opacity: 1;
}

.reference__remove:hover {
	background: var(--surface-3);
	color: var(--danger);
}

@media (hover: none) {
	.reference__remove {
		opacity: 1;
	}
}
</style>
