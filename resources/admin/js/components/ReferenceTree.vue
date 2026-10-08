<script setup lang="ts">
/**
 * The tree picker (D-281, D-607): terms that nest, several at once. One
 * box holding a search, an indented checkbox tree, and a way to add a
 * term; every row carries its count, and indentation carries depth (no
 * disclosure triangles: this is picking, not browsing). Ticking a child
 * doesn't tick its parent. A search keeps a match's parents, dimmed, so
 * it still says where it sits; with nothing matching, it says so and
 * offers the search as the new term's name.
 *
 * **Over 50 terms** there's no tree: what's chosen, with each one's
 * path, then the most used, then a line saying search finds the rest;
 * a search answers matches with their paths instead of their ancestors,
 * capped, with the total under them.
 *
 * **New {term}** opens a name and a parent where the author is looking,
 * and writes the term then, since a parent is more than typing can say
 * (a token typed in new waits for the save, D-607).
 */

import { computed, nextTick, ref } from 'vue';
import { errorMessage, request } from '../api';
import { label } from '../fields';
import { plural } from '../format';
import { CAP, loadReferences, marked, ranked, slugOf, type ReferenceItem } from '../references';
import { usePicker, type PickerProps } from '../picker';
import { canType } from '../session';
import AdminIcon from './AdminIcon.vue';
import AdminSelect, { type SelectOption } from './AdminSelect.vue';
import ReferenceMissing from './ReferenceMissing.vue';

const props = defineProps<PickerProps>();
const model = defineModel<string>({ required: true });

const picker = usePicker(props, model, { cap: CAP, suggest: 'uses', suggestions: CAP });
const { type, names, values, has, itemOf, full, whole, total, suggested, query, results, matched, searching, missing, inheritedShown, error, create } = picker;

// A term written here needs only creating: one the account can't
// publish is written as a draft.
const canCreate = computed(() => create.value && canType(type.value, 'create'));

// The whole tree, searched in place: a match keeps its parents in view,
// dimmed.
const rows = computed(() => {
	const all  = whole.value ?? [];
	const text = query.value.trim();

	if (text === '') {
		return all.map((item) => ({ item, context: false }));
	}

	const bySlug = new Map(all.map((item) => [item.slug, item]));
	const hits   = new Set(ranked(all, text).map((item) => item.slug));
	const keep   = new Set<string>();

	for (const slug of hits) {
		let at = bySlug.get(slug);

		while (at !== undefined && !keep.has(at.slug)) {
			keep.add(at.slug);
			at = at.parent === null ? undefined : bySlug.get(at.parent);
		}
	}

	return all.filter((item) => keep.has(item.slug)).map((item) => ({ item, context: !hits.has(item.slug) }));
});

// Over 50: what's chosen, then the most used that aren't.
const chosen  = computed(() => values.value.map(itemOf));
const popular = computed(() => suggested.value.filter((item) => !has(item.slug)));

// The field's slugs the whole tree doesn't have (a missing term).
const outside = computed(() => whole.value === null ? [] : values.value.filter((value) => !whole.value?.some((item) => item.slug === slugOf(value))));

// Chosen drafts, which the site doesn't list the entry under yet.
const drafts = computed(() => chosen.value.filter((item) => item.status === 'draft'));

// A new term, written where it's being chosen.
const adding    = ref(false);
const writing   = ref(false);
const newName   = ref('');
const newParent = ref('');
const newField  = ref<HTMLInputElement | null>(null);
const parents   = ref<ReferenceItem[]>([]);

const parentOptions = computed<SelectOption[]>(() => [
	{ value: '', label: 'None, at the top level' },
	...(whole.value ?? parents.value).map((item) => ({ value: item.slug, label: item.title, depth: whole.value === null ? 0 : item.depth ?? 0, hint: whole.value === null ? item.path ?? null : null }))
]);

// Over 50, the parent list is searched like the field.
async function searchParents(text: string): Promise<void> {
	try {
		parents.value = (await loadReferences(type.value, { search: text.trim(), limit: CAP, upto: 1 })).items.filter((item) => !item.missing);
	} catch {
		parents.value = [];
	}
}

async function openNew(): Promise<void> {
	adding.value    = true;
	newName.value   = query.value.trim();
	newParent.value = '';

	if (whole.value === null) {
		await searchParents('');
	}

	await nextTick();
	newField.value?.focus();
}

/**
 * Writes the term (`POST entries`), published when the account may
 * publish, and ticks it.
 */
async function saveNew(): Promise<void> {
	const name = newName.value.trim();

	if (name === '' || writing.value) {
		newField.value?.focus();

		return;
	}

	writing.value = true;
	error.value   = '';

	try {
		const created = await request<{ slug: string; title: string; status: ReferenceItem['status'] }>('POST', '/entries', {
			type: type.value,
			title: name,
			status: canType(type.value, 'publish') ? 'published' : 'draft',
			set: newParent.value === '' ? {} : { parent: newParent.value }
		});

		adding.value = false;
		query.value  = '';
		await picker.start();
		picker.remember([{ slug: created.slug, title: created.title, status: created.status, parent: newParent.value || null, uses: 0, depth: null, missing: false }]);
		picker.add(created.slug);
	} catch (caught) {
		error.value = errorMessage(caught, `The ${names.value.item} couldn't be created.`);
	} finally {
		writing.value = false;
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

const typed = computed(() => query.value.trim());
</script>

<template>
	<div class="reference">
		<div class="reference__box" :class="{ 'is-invalid': invalid }">
			<div class="reference__search reference__search--inside">
				<AdminIcon name="search" />
				<input :id="id" v-model="query" type="search" autocomplete="off" :placeholder="whole === null && total > 0 ? `Search ${total.toLocaleString()} ${names.items}…` : `Search ${names.items}…`" :aria-describedby="describedBy">
			</div>

			<!-- Up to 50: the tree. -->
			<div v-if="whole !== null" class="reference__tree" role="group" :aria-label="label(field)">
				<label v-for="{ item, context } in rows" :key="item.slug" class="reference__term" :class="{ 'is-on': has(item.slug), 'is-context': context }" :style="{ '--depth': item.depth ?? 0 }">
					<input type="checkbox" class="check-input" :checked="has(item.slug)" :disabled="full && !has(item.slug)" @change="picker.toggle(item.slug)">
					<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
					<span class="reference__term-name">{{ item.title }}</span>
					<span v-if="item.status === 'draft'" class="reference__mark"><AdminIcon name="file-pen-line" />Draft</span>
					<span class="reference__count mono">{{ item.uses ?? 0 }}</span>
				</label>
				<label v-for="value in outside" :key="`outside-${value}`" class="reference__term is-on is-missing">
					<input type="checkbox" class="check-input" checked @change="picker.toggle(slugOf(value))">
					<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
					<span class="reference__term-name mono">{{ value }}</span>
				</label>
				<p v-if="!rows.length && !outside.length" class="reference__empty">
					{{ typed ? `No ${names.item} matches “${typed}”.` : `No ${names.items} yet.` }}
					<button v-if="typed && canCreate" type="button" class="lnk" @click="openNew">{{ names.newItem }} “{{ typed }}”</button>
				</p>
			</div>

			<!-- Over 50: what's chosen and the most used, or a search. -->
			<div v-else class="reference__tree" role="group" :aria-label="label(field)">
				<template v-if="!typed">
					<p v-if="chosen.length" class="reference__group">Chosen</p>
					<label v-for="item in chosen" :key="`chosen-${item.slug}`" class="reference__term is-on" :class="{ 'is-missing': item.missing }">
						<input type="checkbox" class="check-input" checked @change="picker.toggle(item.slug)">
						<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
						<span class="reference__term-name" :class="{ mono: item.missing }">{{ item.title }}</span>
						<span v-if="item.status === 'draft'" class="reference__mark"><AdminIcon name="file-pen-line" />Draft</span>
						<span v-if="item.path" class="reference__path">{{ item.path }}</span>
					</label>
					<p v-if="popular.length" class="reference__group">Most Used</p>
					<label v-for="item in popular" :key="`popular-${item.slug}`" class="reference__term">
						<input type="checkbox" class="check-input" :disabled="full" @change="picker.toggle(item.slug)">
						<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
						<span class="reference__term-name">{{ item.title }}</span>
						<span v-if="item.path" class="reference__path">{{ item.path }}</span>
						<span class="reference__count mono">{{ item.uses ?? 0 }}</span>
					</label>
					<p class="reference__more">{{ total.toLocaleString() }} {{ names.items }}. Search to find the rest.</p>
				</template>
				<template v-else>
					<div v-if="searching" class="reference__empty">Searching…</div>
					<template v-else>
						<label v-for="item in results" :key="`found-${item.slug}`" class="reference__term" :class="{ 'is-on': has(item.slug) }">
							<input type="checkbox" class="check-input" :checked="has(item.slug)" :disabled="full && !has(item.slug)" @change="picker.toggle(item.slug)">
							<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
							<span class="reference__term-name">{{ marked(item.title, typed)[0] }}<b>{{ marked(item.title, typed)[1] }}</b>{{ marked(item.title, typed)[2] }}</span>
							<span v-if="item.path" class="reference__path">{{ item.path }}</span>
							<span class="reference__count mono">{{ item.uses ?? 0 }}</span>
						</label>
						<p v-if="results.length" class="reference__more">{{ results.length < matched ? `${results.length} of ${matched.toLocaleString()} matches. Keep typing to narrow.` : plural(matched, 'match', 'matches') + '.' }}</p>
						<p v-else class="reference__empty">
							No {{ names.item }} matches “{{ typed }}”.
						</p>
					</template>
				</template>
			</div>

			<template v-if="canCreate">
				<div v-if="adding" class="reference__new">
					<label class="visually-hidden" :for="`${id}-new`">Name</label>
					<input :id="`${id}-new`" ref="newField" v-model="newName" type="text" autocomplete="off" :placeholder="`${names.singular} name`" @keydown="newKey">
					<label class="visually-hidden" :for="`${id}-parent`">Parent</label>
					<AdminSelect :id="`${id}-parent`" v-model="newParent" :options="parentOptions" searchable :remote="whole === null" @search="searchParents" />
					<div class="reference__new-actions">
						<button type="button" class="button button--small button--primary" :disabled="writing || !newName.trim()" @click="saveNew">{{ writing ? 'Adding…' : `Add ${names.singular}` }}</button>
						<button type="button" class="button button--small" @click="adding = false">Cancel</button>
					</div>
				</div>
				<button v-else type="button" class="reference__add" @click="openNew">
					<AdminIcon name="plus" />{{ names.newItem }}<template v-if="typed && whole === null"> “{{ typed }}”</template>
				</button>
			</template>
		</div>

		<p v-for="item in drafts" :key="`draft-${item.slug}`" class="field__help"><b>{{ item.title }}</b> is a draft, so this {{ picker.source.value.item }} isn't listed under it on the site yet.</p>
		<p v-if="inheritedShown.length && inherited" class="field__help">
			{{ inherited.rule === 'add' ? 'Also' : 'Until it has its own,' }} filed under {{ inheritedShown.map((value) => itemOf(value).title).join(', ') }}, from {{ inherited.title }} ({{ inherited.language }}).
		</p>
		<ReferenceMissing :missing="missing" :noun="names.item" @replace="picker.replace" />
		<p v-if="error" class="field__error">{{ error }}</p>
	</div>
</template>
