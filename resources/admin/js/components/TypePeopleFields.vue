<script setup lang="ts">
/**
 * How a type's entries credit people (D-353; the profiles sketch's
 * People panel): one card per people field, each a relation to the one
 * profiles type in this type's own words. Recipes credit cooks and
 * photographers; both point at the same profiles, so a person has one
 * profile and one slug however a type names them.
 *
 * A field has its names, its key in front matter (fixed once saved,
 * since entries are written with it), how many people an entry takes and
 * whether it needs one, and whether it has archives under the type, at
 * which word. Turning archives off stops the routing and deletes
 * nothing. A field with archives can have a page introducing its list
 * (`_cooks` in the folder), which, like the index page, a type gets once
 * and keeps. **Add a people field** adds another; the first is the
 * type's main byline.
 */

import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import type { PeopleFieldInfo } from '../api';
import AdminIcon from './AdminIcon.vue';
import AdminSelect from './AdminSelect.vue';
import { keyOf, peopleWordOf, singularOf, type PeopleForm } from '../type-form';

const props = defineProps<{
	idPrefix: string;
	// The prefix the archives sit under, without slashes.
	prefix: string;
	// Whether the type may set its URLs, so whether archives can change.
	urls: boolean;
	// The fields as saved, for each one's list page.
	saved: PeopleFieldInfo[];
	// What the site calls its profiles ("Profiles").
	profilesLabel: string;
}>();

const people    = defineModel<PeopleForm[]>({ required: true });
// The fields to give a list page when saved.
const listPages = defineModel<string[]>('listPages', { default: () => [] });

const ARITY = [
	{ value: 'many', label: 'One or more' },
	{ value: 'one', label: 'One' }
];

const taken = computed(() => new Set(people.value.map((item) => item.field)));

function listPageOf(field: string): { id: string; title: string } | null {
	return props.saved.find((item) => item.field === field)?.listPage ?? null;
}

function archiveBase(item: PeopleForm): string {
	const word = peopleWordOf(item);

	return word === false ? '' : `/${props.prefix}/${word}`;
}

// A new field's key follows its name until it's saved.
function rename(item: PeopleForm, plural: string): void {
	item.plural = plural;

	if (item.added) {
		item.field    = keyOf(plural);
		item.singular = singularOf(plural);
	}
}

function add(): void {
	let field = 'people';

	for (let number = 2; taken.value.has(field); number++) {
		field = `people_${number}`;
	}

	people.value = [...people.value, { field, plural: '', singular: '', aliases: [], archives: props.urls, word: '', multiple: true, required: false, added: true }];
}

function remove(item: PeopleForm): void {
	if (!item.added && !window.confirm(`Stop crediting ${item.plural.toLowerCase() || item.field}? Entries keep what they wrote under "${item.field}", but nothing reads it, and its archives stop.`)) {
		return;
	}

	people.value    = people.value.filter((other) => other !== item);
	listPages.value = listPages.value.filter((field) => field !== item.field);
}

function wantPage(field: string, on: boolean): void {
	listPages.value = on ? [...new Set([...listPages.value, field])] : listPages.value.filter((other) => other !== field);
}
</script>

<template>
	<div class="type-people">
		<p v-if="people.length === 0" class="field__help">Entries of this type credit no one. Add a people field to credit {{ profilesLabel.toLowerCase() }} here.</p>

		<fieldset v-for="(item, index) in people" :key="index" class="type-people__field">
			<legend class="type-people__legend">
				{{ item.plural || 'New people field' }}
				<span v-if="index === 0" class="tag" title="Bylines and feeds name these people">Main byline</span>
			</legend>

			<div class="type-people__grid">
				<div class="field">
					<label :for="`${idPrefix}${index}-plural`">Name</label>
					<input :id="`${idPrefix}${index}-plural`" :value="item.plural" autocomplete="off" required placeholder="Cooks" @input="rename(item, ($event.target as HTMLInputElement).value)">
				</div>
				<div class="field">
					<label :for="`${idPrefix}${index}-singular`">One of them</label>
					<input :id="`${idPrefix}${index}-singular`" v-model="item.singular" autocomplete="off" :placeholder="singularOf(item.plural) || 'Cook'">
				</div>
				<div class="field">
					<label :for="`${idPrefix}${index}-field`">Front matter key</label>
					<input :id="`${idPrefix}${index}-field`" v-model="item.field" class="mono" autocomplete="off" spellcheck="false" :disabled="!item.added" :aria-describedby="`${idPrefix}${index}-field-help`">
					<p :id="`${idPrefix}${index}-field-help`" class="field__help">
						{{ item.added ? 'What entries write it as.' : 'Fixed: entries are written with it.' }}
						<template v-if="item.aliases.length">Also read from <span class="mono">{{ item.aliases.join(', ') }}</span>.</template>
					</p>
				</div>
				<div class="field">
					<label :for="`${idPrefix}${index}-arity`">Entries take</label>
					<AdminSelect :id="`${idPrefix}${index}-arity`" :model-value="item.multiple ? 'many' : 'one'" :options="ARITY" @update:model-value="item.multiple = $event === 'many'" />
					<label class="checkbox"><input v-model="item.required" type="checkbox"> Required to publish</label>
				</div>
			</div>

			<div class="type-people__archive">
				<label class="checkbox"><input v-model="item.archives" type="checkbox" :disabled="!urls"> Each one has an archive here</label>
				<div v-if="item.archives" class="field type-people__word">
					<label :for="`${idPrefix}${index}-word`">Word in the address</label>
					<input :id="`${idPrefix}${index}-word`" v-model="item.word" class="mono" :placeholder="item.field" :disabled="!urls" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}${index}-word-help`">
					<p :id="`${idPrefix}${index}-word-help`" class="field__help">The list is at <code>{{ archiveBase(item) }}</code> and each person's archive at <code>{{ archiveBase(item) }}/{slug}</code>. Bylines link there.</p>
				</div>
				<p v-else class="field__help">{{ urls ? 'Credit still shows on entries, linking to each profile\'s own page. Nothing routes here, and nothing written for these archives is deleted.' : 'These types can\'t set their URLs, so their archives are as the site has them.' }}</p>
				<template v-if="item.archives && !item.added">
					<p v-if="listPageOf(item.field)" class="field__help">Its list page: <RouterLink :to="{ name: 'entry-file', params: { id: listPageOf(item.field)!.id.split('/') } }">{{ listPageOf(item.field)!.title }}</RouterLink>, which introduces the list. It's an entry, edited like one.</p>
					<template v-else>
						<label class="checkbox"><input type="checkbox" :checked="listPages.includes(item.field)" @change="wantPage(item.field, ($event.target as HTMLInputElement).checked)"> Has a page introducing the list</label>
						<p v-if="listPages.includes(item.field)" class="field__help">An entry is created at <code>_{{ item.field }}</code> in the folder, titled {{ item.plural }}, and pinned in its list. It has no address of its own.</p>
					</template>
				</template>
			</div>

			<div class="type-people__remove">
				<button type="button" class="button button--ghost button--small" @click="remove(item)"><AdminIcon name="x" />Remove</button>
			</div>
		</fieldset>

		<div>
			<button type="button" class="button button--small" @click="add"><AdminIcon name="plus" />Add a people field</button>
		</div>
		<p class="field__help">Every field credits the one {{ profilesLabel.toLowerCase() }} collection. These are this type's words for how a person is credited, so a person keeps one profile and one slug everywhere.</p>
	</div>
</template>

<style scoped>
.type-people {
	display: grid;
	gap: var(--s-4);
}

.type-people__field {
	display: grid;
	gap: var(--s-4);
	margin: 0;
	padding: var(--s-4);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
}

.type-people__legend {
	display: flex;
	align-items: center;
	gap: var(--s-2);
	padding: 0 var(--s-1);
	color: var(--fg);
	font-weight: 500;
}

.type-people__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr));
	align-items: start;
	gap: var(--s-4);
}

.type-people__archive {
	display: grid;
	gap: var(--s-2);
}

.type-people__word {
	max-width: 24rem;
}

.type-people__remove {
	display: flex;
	justify-content: flex-end;
}
</style>
