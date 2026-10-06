<script setup lang="ts">
/**
 * How a type's entries credit people (D-353; the profiles sketch's
 * Profiles and Archives panels, D-369, D-370): one row per profile
 * field, each a relation to the one profiles type in this type's own
 * words, and, as its own `part`, each field's archive switch. Recipes credit cooks and
 * photographers; both point at the same profiles, so a person has one
 * profile and one slug however a type names them.
 *
 * A row has the field's label (its singular follows it), its archive
 * base (the word in the address), and what entries take (one, or one
 * or more; optional or required); a new field's front matter key is set
 * under it (fixed once saved, since entries are written with it). The
 * Archives part switches each field's archives on or off. Turning archives off stops the routing and deletes
 * nothing. A field with archives can have a page introducing its list
 * (`_cooks` in the folder), which, like the index page, a type gets once
 * and keeps. **Add a profile field** adds another; the first is the
 * type's main byline.
 */

import { computed } from 'vue';
import { confirmAction } from '../confirm';
import { RouterLink } from 'vue-router';
import { entryRoute, type PeopleFieldInfo } from '../api';
import AdminIcon from './AdminIcon.vue';
import AdminSelect from './AdminSelect.vue';
import ToggleSwitch from './ToggleSwitch.vue';
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
	// The rows of fields, each field's archive switch, or the button
	// that adds a field (for the panel's header).
	part: 'fields' | 'archives' | 'add';
}>();

const people    = defineModel<PeopleForm[]>({ required: true });
// The fields to give a list page when saved.
const listPages = defineModel<string[]>('listPages', { default: () => [] });

// How many an entry takes, and whether it needs any, as one choice.
const TAKES = [
	{ value: 'many-optional', label: 'One or more, optional' },
	{ value: 'many-required', label: 'One or more, required' },
	{ value: 'one-optional', label: 'One, optional' },
	{ value: 'one-required', label: 'One, required' }
];

function takesOf(item: PeopleForm): string {
	return `${item.multiple ? 'many' : 'one'}-${item.required ? 'required' : 'optional'}`;
}

function setTakes(item: PeopleForm, value: string): void {
	item.multiple = value.startsWith('many');
	item.required = value.endsWith('required');
}

function takesHint(item: PeopleForm): string {
	if (item.required) {
		return 'Gates publishing, like any required field';
	}

	return item.multiple ? 'An entry may credit no one' : `An entry may name no ${(item.singular || singularOf(item.plural) || 'one').toLowerCase()}`;
}

const taken = computed(() => new Set(people.value.map((item) => item.field)));

function listPageOf(field: string): { id: string | null; type: string; path: string; title: string } | null {
	return props.saved.find((item) => item.field === field)?.listPage ?? null;
}

function archiveBase(item: PeopleForm): string {
	const word = peopleWordOf(item);

	return word === false ? '' : `/${props.prefix}/${word}`;
}

// A new field's key follows its name until it's saved.
function rename(item: PeopleForm, plural: string): void {
	// The singular follows the label while it's the label's own.
	if (item.added || item.singular === '' || item.singular === singularOf(item.plural)) {
		item.singular = singularOf(plural);
	}

	item.plural = plural;

	if (item.added) {
		item.field = keyOf(plural);
	}
}

function add(): void {
	let field = 'people';

	for (let number = 2; taken.value.has(field); number++) {
		field = `people_${number}`;
	}

	people.value = [...people.value, { field, plural: '', singular: '', aliases: [], archives: props.urls, word: '', multiple: true, required: false, added: true }];
}

async function remove(item: PeopleForm): Promise<void> {
	if (!item.added && !await confirmAction({ title: `Remove the ${item.plural || item.field} Field?`, body: [`Every entry that credits someone in it loses that credit: what they wrote under **${item.field}** stays, but nothing reads it. Its archive stops routing.`, 'Any page written for it is kept, but unreachable. Nothing changes until you save the type.'], confirm: 'Remove the field', danger: true })) {
		return;
	}

	people.value    = people.value.filter((other) => other !== item);
	listPages.value = listPages.value.filter((field) => field !== item.field);
}

// The word shows as the field's name until it's changed; that is the
// default, so it's kept as none.
function setWord(item: PeopleForm, word: string): void {
	item.word = word.trim() === item.field ? '' : word;
}

function wantPage(field: string, on: boolean): void {
	listPages.value = on ? [...new Set([...listPages.value, field])] : listPages.value.filter((other) => other !== field);
}
</script>

<template>
	<template v-if="part === 'fields'">
		<p v-if="people.length === 0" class="type-people__none">Entries of this type credit no one. Add a profile field to credit {{ profilesLabel.toLowerCase() }} here.</p>

		<div v-for="(item, index) in people" :key="index" class="type-people__row">
			<div class="field">
				<label :for="`${idPrefix}${index}-plural`">Label <span v-if="index === 0" class="tag--you" title="Bylines and feeds name these people">Main byline</span></label>
				<input :id="`${idPrefix}${index}-plural`" :value="item.plural" autocomplete="off" required placeholder="Interviewers" :aria-describedby="`${idPrefix}${index}-plural-help`" @input="rename(item, ($event.target as HTMLInputElement).value)">
				<p :id="`${idPrefix}${index}-plural-help`" class="field__help">Singular: {{ item.singular || singularOf(item.plural) || '…' }}<template v-if="!item.added"> · key <span class="mono">{{ item.field }}</span><template v-if="item.aliases.length">, also <span class="mono">{{ item.aliases.join(', ') }}</span></template></template></p>
			</div>
			<div class="field">
				<label :for="`${idPrefix}${index}-word`">Archive base</label>
				<span class="type-people__base" :class="{ 'type-people__base--off': !item.archives }">
					<span class="type-people__affix">/{{ prefix }}/</span>
					<input :id="`${idPrefix}${index}-word`" :value="item.word || item.field" class="mono" :disabled="!urls || !item.archives" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}${index}-word-help`" @input="setWord(item, ($event.target as HTMLInputElement).value)">
					<span class="type-people__affix">/…</span>
				</span>
				<p :id="`${idPrefix}${index}-word-help`" class="field__help">{{ item.archives ? `The list at ${archiveBase(item)}, each person under it` : 'Archives are off, below' }}</p>
			</div>
			<div class="field">
				<label :for="`${idPrefix}${index}-takes`">Entries take</label>
				<AdminSelect :id="`${idPrefix}${index}-takes`" :model-value="takesOf(item)" :options="TAKES" :described-by="`${idPrefix}${index}-takes-help`" @update:model-value="setTakes(item, $event)" />
				<p :id="`${idPrefix}${index}-takes-help`" class="field__help">{{ takesHint(item) }}</p>
			</div>
			<button type="button" class="button button--ghost button--small button--icon type-people__remove" :aria-label="`Remove the ${item.plural || 'new'} field`" title="Remove" @click="remove(item)"><AdminIcon name="x" /></button>
			<div v-if="item.added" class="field type-people__key">
				<label :for="`${idPrefix}${index}-field`">Front matter key</label>
				<input :id="`${idPrefix}${index}-field`" v-model="item.field" class="mono" autocomplete="off" spellcheck="false" :aria-describedby="`${idPrefix}${index}-field-help`">
				<p :id="`${idPrefix}${index}-field-help`" class="field__help">What entries write it as; fixed once it's saved.</p>
			</div>
		</div>
	</template>

	<button v-else-if="part === 'add'" type="button" class="button button--small" @click="add"><AdminIcon name="plus" />Add a profile field</button>

	<div v-else-if="part === 'archives'" class="type-people__switches">
		<p v-if="people.length === 0" class="field__help">No profile fields, so no archives.</p>
		<div v-for="(item, index) in people" :key="index" class="field">
			<span class="type-people__switch-label">{{ item.plural || 'New field' }} archive</span>
			<ToggleSwitch form :checked="item.archives" :label="`${item.plural || 'New field'} archive`" :locked="!urls" @change="item.archives = $event" />
			<p class="field__help">
				<template v-if="!urls">This type can't set its URLs, so its archives are as the site has them.</template>
				<template v-else-if="item.archives">Routes <span class="mono">{{ archiveBase(item) }}/&lt;slug&gt;</span> for every credited profile.</template>
				<template v-else>Credit still shows on the entry. Nothing routes, and any page already written is kept and marked unreachable.</template>
			</p>
			<template v-if="item.archives && !item.added">
				<p v-if="listPageOf(item.field)" class="field__help">Its list page: <RouterLink class="lnk" :to="entryRoute(listPageOf(item.field)!)">{{ listPageOf(item.field)!.title }}</RouterLink>.</p>
				<label v-else class="checkbox"><input type="checkbox" :checked="listPages.includes(item.field)" @change="wantPage(item.field, ($event.target as HTMLInputElement).checked)"> A page introducing the list</label>
			</template>
		</div>
	</div>
</template>

<style scoped>
.type-people__none {
	margin: 0;
	padding: var(--s-4) var(--pad-x);
	color: var(--fg-2);
}

/* A field per row: label, archive base, what entries take, and remove. */
.type-people__row:last-child {
	border-bottom: 0;
}

.type-people__row {
	display: grid;
	grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.1fr) minmax(0, .9fr) auto;
	align-items: start;
	gap: var(--s-3) var(--s-4);
	padding: var(--s-4) var(--pad-x);
	border-bottom: 1px solid var(--border);
}

.type-people__row > * {
	margin: 0;
}

.type-people__remove {
	margin-top: 25px;
}

.type-people__key {
	grid-column: 1 / 2;
}

.type-people__base {
	display: flex;
	align-items: center;
	height: var(--ctl);
	padding: 0 var(--s-3);
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-sm);
}

.type-people__base:focus-within {
	border-color: var(--accent);
}

.type-people__base--off {
	background: var(--surface-2);
}

.field .type-people__base input {
	flex: 1;
	min-width: 3ch;
	height: auto;
	padding: 0;
	border: 0;
	background: none;
	box-shadow: none;
	color: var(--fg);
	font: inherit;
	outline: none;
}

.type-people__affix {
	flex: none;
	white-space: nowrap;
}

.type-people__switches {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: var(--s-4);
}

.type-people__switches > * {
	margin: 0;
}

.type-people__switch-label {
	color: var(--fg);
}

@media (width <= 900px) {
	.type-people__row {
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	}

	.type-people__remove {
		grid-column: 2;
		justify-self: end;
		margin-top: 0;
	}
}

@media (width <= 560px) {
	.type-people__row {
		grid-template-columns: minmax(0, 1fr);
	}

	.type-people__remove {
		grid-column: 1;
		justify-self: start;
	}
}
</style>
