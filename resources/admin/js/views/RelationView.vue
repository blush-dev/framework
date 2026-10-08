<script setup lang="ts">
/**
 * One relationship (D-610, from the pickers sketch's Editing a
 * Relationship board), or a new one: a settings screen, not a builder.
 * Under the title, a sentence says the relationship whole ("Recipes
 * credit Profiles as Cooks."), rewritten as its purpose, endpoints, and
 * names change; then a notice says where it's saved, and panels hold a
 * row per setting, as the Settings screens do (D-404), each result under
 * the control that causes it:
 *
 * - **Purpose**: files entries under terms, links entries to others, or
 *   credits people (D-593, D-602). Chosen when it's made; it decides the
 *   endpoints and the suggested picker.
 * - **Endpoints**: **Stored On**, always a row, the types whose files
 *   carry the key (every type when none is chosen, for terms and
 *   credits), and what it points at.
 * - **Names**: the front matter key, what it's called, and what one is
 *   called.
 * - **Options**: one or several, in order, created as typed, and both
 *   ways (a link to its own type).
 * - **Limits**, restated as a sentence at the panel's foot, since three
 *   boxes of numbers aren't yet a rule. They gate publishing, never
 *   saving.
 * - **Editing**: its picker (D-599) and translations rule (D-587).
 * - **What Links to It**: the list's name on the other side, and whether
 *   it's on the target's own page or in archives under a word (D-602).
 *
 * Opened from a type's screen (`?type=recipes`), a new one arrives with
 * Stored On filled and goes back there when it's saved. A change starts
 * from the definition as written, so options this screen doesn't show
 * (aliases, a term page's listing) are kept; changing one over entries
 * that use it asks first, in the shape of what it does
 * (`confirmRelationChange()`). One from config or a plugin is shown, and
 * changed where it's defined.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import DangerZone from '../components/DangerZone.vue';
import FieldInput from '../components/FieldInput.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { errorMessage, request, type ContentTypeSummary, type FieldDescription, type RelationCheck, type RelationInfo } from '../api';
import { useAction } from '../action';
import { guardLeave } from '../confirm';
import type { FormValue } from '../fields';
import { article, confirmRelationChange, limitsSay, purposeOf, relationLabel, removeRelation, sayRelation, sourceOf, type RelationPurpose } from '../relations';
import { screenTitle, screenTrail } from '../screen';
import { toast } from '../toast';
import { keyOf } from '../type-form';
import { canCreateTypes, labelsOf, loadTypes, profileType, refreshTypes, types } from '../types';

const route  = useRoute();
const router = useRouter();

const name     = computed(() => typeof route.params.name === 'string' ? route.params.name : '');
const creating = computed(() => name.value === '');
// The type it was opened from, which a new one is stored on.
const fromType = computed(() => typeof route.query.type === 'string' ? route.query.type : '');

const relation = ref<RelationInfo | null>(null);
const loaded   = ref(false);
const failed   = ref('');

const purpose      = ref<RelationPurpose>('classify');
const terms        = ref('');
const from         = ref<string[]>([]);
const source       = ref('');
const target       = ref('');
const key          = ref('');
const called       = ref('');
const singular     = ref('');
const create       = ref(true);
const multiple     = ref(true);
const ordered      = ref(false);
const symmetric    = ref(false);
const minimum      = ref('0');
const maximum      = ref('');
const control      = ref('');
const translations = ref<'fallback' | 'add' | 'own'>('fallback');
const page         = ref(false);
const archived     = ref(false);
const word         = ref('');
const sideLabel    = ref('');
const sideMax      = ref('');

// What was there when it opened, to tell a change.
const initial = ref('');
const saved   = ref(false);

const { busy, error, run } = useAction();
const { error: removal, run: runRemoval } = useAction();

// Every type but profiles, which credits credit (D-602).
const choices = computed<ContentTypeSummary[]>(() => types.value.filter((item) => item.kind !== 'profiles'));
const options = computed(() => choices.value.map((item) => ({ value: item.name, label: item.labels.plural })));

// A new set of terms files under a type nothing files under yet.
const termOptions = computed(() => creating.value
	? [{ value: '', label: 'Choose a type' }, ...choices.value.filter((item) => !item.terms).map((item) => ({ value: item.name, label: item.labels.plural }))]
	: options.value);
const fileable = computed(() => choices.value.filter((item) => item.name !== terms.value));

// What it points at, whichever its purpose.
const pointsAt = computed(() => purpose.value === 'classify' ? terms.value : (purpose.value === 'credit' ? profileType.value ?? '' : target.value));

// The types storing it.
const stores = computed(() => purpose.value === 'reference' ? (source.value === '' ? [] : [source.value]) : from.value);

// The key it's written under: the one typed, else one made from what it
// points at, or `authors` for a credit.
const referenceKey = computed(() => keyOf(key.value) || (purpose.value === 'credit' ? 'authors' : keyOf(labelsOf(target.value).plural)));
const writtenKey   = computed(() => purpose.value === 'classify' ? keyOf(key.value) || terms.value : referenceKey.value);

// Its file's name: its own, or, for a new one, the one it will take.
const fileName   = computed(() => relation.value?.name ?? (purpose.value === 'classify' ? terms.value : (pointsAt.value === '' && key.value === '' ? '' : referenceKey.value)));
const label      = computed(() => called.value.trim() || (pointsAt.value === '' ? '' : labelsOf(pointsAt.value).plural));
const oneCalled  = computed(() => singular.value.trim() || (label.value === '' ? '' : (pointsAt.value !== '' && label.value === labelsOf(pointsAt.value).plural ? labelsOf(pointsAt.value).singular : label.value.replace(/s$/, ''))));
const sentence   = computed(() => sayRelation(purpose.value, stores.value, pointsAt.value, called.value.trim()));
const storedItem = computed(() => stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').item : 'entry');
const storedName = computed(() => stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').singular : 'Entry');

const ready = computed(() => purpose.value === 'classify'
	? terms.value !== ''
	: (purpose.value === 'credit' ? profileType.value !== null : source.value !== '' && target.value !== '') && referenceKey.value !== '');

// A tree only for terms that nest; none for one value, always a select.
const nests          = computed(() => purpose.value === 'classify' && types.value.find((item) => item.name === terms.value)?.hierarchical === true);
const shapeControl   = computed(() => purpose.value === 'classify' ? (nests.value ? 'a tree of checkboxes' : 'chips') : (purpose.value === 'credit' ? 'people' : 'cards'));
const controlOptions = computed(() => [
	{ value: '', label: `As its shape says (${shapeControl.value})` },
	...(nests.value ? [{ value: 'tree', label: 'A tree of checkboxes' }] : []),
	{ value: 'tokens', label: 'Chips, typed to search' },
	{ value: 'cards', label: 'Chips, with results shown as cards' }
]);

const ruleOptions = [
	{ value: 'fallback', label: 'Fall Back', help: 'A translation with none of its own uses its original\'s.' },
	{ value: 'add', label: 'Add to the Original', help: 'A translation has its original\'s and its own, as a translator credited beside the author.' },
	{ value: 'own', label: 'Keep Their Own', help: 'A translation has only its own.' }
] as const;

const canBeSymmetric = computed(() => purpose.value === 'reference' && source.value !== '' && source.value === target.value && multiple.value);

// Changed only where it's defined: one from config or a plugin is shown.
const locked = computed(() => !canCreateTypes.value || (relation.value !== null && !relation.value.editable));

function count(text: string): number | null {
	const number = Number.parseInt(text, 10);

	return Number.isNaN(number) ? null : number;
}

const limits = computed(() => {
	const target = pointsAt.value === '' ? null : labelsOf(pointsAt.value);

	return limitsSay({
		item: storedItem.value,
		items: stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').items : 'entries',
		singular: oneCalled.value.toLowerCase() || 'one',
		plural: label.value.toLowerCase() || 'of them',
		target: target?.item ?? 'target',
		multiple: multiple.value,
		min: count(minimum.value) ?? 0,
		max: multiple.value ? count(maximum.value) : 1,
		inverseMax: count(sideMax.value)
	});
});

function fill(found: RelationInfo | null): void {
	if (found !== null) {
		const archive = found.inverse === false ? false : found.inverse.archive;

		purpose.value      = purposeOf(found);
		terms.value        = found.to[0] ?? '';
		from.value         = [...found.from];
		source.value       = found.from[0] ?? '';
		target.value       = found.to[0] ?? '';
		key.value          = found.field;
		called.value       = found.label;
		singular.value     = found.definition.singular === undefined || found.definition.singular === null ? '' : found.singular;
		create.value       = found.create;
		multiple.value     = found.multiple;
		ordered.value      = found.ordered;
		symmetric.value    = found.symmetric;
		minimum.value      = String(found.min);
		maximum.value      = found.multiple && found.max !== null ? String(found.max) : '';
		control.value      = found.control ?? '';
		translations.value = found.translations;
		page.value         = found.inverse !== false && found.inverse.page;
		archived.value     = archive !== false;
		word.value         = archive === false ? '' : archive;
		sideLabel.value    = found.inverse === false ? '' : found.inverse.label;
		sideMax.value      = found.inverse === false || found.inverse.max === null ? '' : String(found.inverse.max);

		return;
	}

	// A new one, from the type it was opened on, if any.
	const here = types.value.find((item) => item.name === fromType.value);

	purpose.value      = here?.terms === true ? 'reference' : 'classify';
	terms.value        = '';
	from.value         = here && here.terms !== true ? [here.name] : [];
	source.value       = here?.name ?? '';
	target.value       = '';
	key.value          = '';
	called.value       = '';
	singular.value     = '';
	create.value       = true;
	multiple.value     = true;
	ordered.value      = false;
	symmetric.value    = false;
	minimum.value      = '0';
	maximum.value      = '';
	control.value      = '';
	translations.value = 'fallback';
	page.value         = here?.terms !== true;
	archived.value     = false;
	word.value         = '';
	sideLabel.value    = '';
	sideMax.value      = '';
}

async function load(): Promise<void> {
	loaded.value = false;
	failed.value = '';
	saved.value  = false;

	try {
		await loadTypes();

		if (!creating.value) {
			const answer = await request<{ relations: RelationInfo[] }>('GET', '/relations');

			relation.value = answer.relations.find((item) => item.name === name.value) ?? null;

			if (relation.value === null) {
				failed.value = `There's no “${name.value}” relationship.`;

				return;
			}
		} else {
			relation.value = null;
		}

		fill(relation.value);
		initial.value = JSON.stringify(definition());
		loaded.value  = true;
	} catch (caught) {
		failed.value = errorMessage(caught, 'The relationship couldn\'t be loaded.');
	}
}

watch(name, () => void load(), { immediate: true });

watch(() => [relation.value, creating.value] as const, ([found, isNew]) => {
	screenTitle.value = isNew ? 'New Relationship' : (found ? relationLabel(found) : null);
}, { immediate: true });

screenTrail.value = [{ label: 'Relationships', to: { name: 'relations' } }];

// A new link's terms have pages; a credit's people have their profile's
// page and archives under each type; a new link to other entries has
// none, until it's chosen.
watch(purpose, (now) => {
	if (creating.value && loaded.value) {
		page.value     = now !== 'reference';
		archived.value = now === 'credit';
		create.value   = now === 'classify';
		from.value     = now !== 'reference' && fromType.value !== '' && types.value.find((item) => item.name === fromType.value)?.terms !== true ? [fromType.value] : [];
	}
});

// Purpose and Stored On are the shared radio buttons and checkboxes
// (`FieldInput`), as the Settings screens draw them (D-404).
const purposeField = computed<FieldDescription>(() => ({
	name: 'purpose',
	type: 'choice',
	control: 'radios',
	required: true,
	options: ['classify', 'reference', ...(profileType.value !== null ? ['credit'] : [])],
	choices: { classify: 'Files Entries Under Terms', reference: 'Links Entries to Other Entries', credit: 'Credits People' },
	details: {
		classify: { text: 'Courses, ingredients, cuisines.', code: '' },
		reference: { text: 'Pairs with, a movie\'s cast.', code: '' },
		credit: { text: 'Authors, cooks, photographers.', code: '' }
	}
}));

const purposeValue = computed<FormValue>({
	get: () => purpose.value,
	set: (value) => {
		if (value === 'classify' || value === 'reference' || value === 'credit') {
			purpose.value = value;
		}
	}
});

const storable    = computed(() => purpose.value === 'classify' ? fileable.value : choices.value);
const storedField = computed<FieldDescription>(() => ({
	name: 'from',
	type: 'list',
	control: 'checks',
	item: { name: 'type', type: 'choice', options: storable.value.map((item) => item.name), choices: Object.fromEntries(storable.value.map((item) => [item.name, item.labels.plural])) }
}));

// The checkboxes' state holds one type per line.
const storedValue = computed<FormValue>({
	get: () => from.value.join('\n'),
	set: (value) => {
		from.value = typeof value === 'string' ? value.split('\n').filter((line) => line !== '') : [];
	}
});

/**
 * The definition to send: the relation's as written, with this screen's
 * choices over it.
 */
function definition(): Record<string, unknown> {
	const base        = relation.value?.definition ?? {};
	const inverseBase = typeof base.inverse === 'object' && base.inverse !== null ? base.inverse : {};
	const classify    = purpose.value === 'classify';
	const fallback    = classify ? terms.value : referenceKey.value;
	const relationName = classify ? terms.value : relation.value?.name ?? referenceKey.value;
	const field        = relation.value === null ? '' : keyOf(key.value);

	return {
		...base,
		name: relationName,
		field: field !== '' && field !== relationName ? field : null,
		kind: purpose.value,
		from: purpose.value === 'reference' ? [source.value] : from.value,
		to: [pointsAt.value],
		label: called.value.trim(),
		singular: singular.value.trim() || null,
		multiple: multiple.value,
		ordered: multiple.value && ordered.value,
		min: count(minimum.value) ?? 0,
		max: multiple.value ? count(maximum.value) : null,
		create: create.value,
		symmetric: canBeSymmetric.value && symmetric.value,
		translations: translations.value,
		control: multiple.value && control.value !== '' ? control.value : null,
		inverse: {
			...inverseBase,
			page: page.value,
			archive: archived.value ? keyOf(word.value) || fallback : false,
			label: sideLabel.value.trim(),
			max: count(sideMax.value)
		}
	};
}

const changed = computed(() => loaded.value && !locked.value && JSON.stringify(definition()) !== initial.value);

guardLeave(() => changed.value && !saved.value);

// Where Cancel and a save go: the type it was opened from, else the list.
const back = computed(() => fromType.value !== '' ? { name: 'content-type', params: { name: fromType.value } } : { name: 'relations' });

async function save(): Promise<void> {
	if (locked.value || !ready.value) {
		return;
	}

	await run('The relationship couldn\'t be saved.', async () => {
		let   sent    = definition();
		const current = relation.value;
		const sentName = String(sent.name);

		if (current !== null) {
			const check  = await request<RelationCheck>('POST', `/relations/${encodeURIComponent(sentName)}/check`, sent);
			const answer = await confirmRelationChange(check, {
				name: current.name,
				label: relationLabel(current),
				singular: current.singular,
				field: current.field,
				newField: keyOf(key.value) || current.name,
				from: current.from,
				oldMax: current.max,
				newMax: count(maximum.value),
				newMin: count(minimum.value) ?? 0,
				inverseMax: count(sideMax.value),
				target: current.to[0] ?? '',
				go: (to) => void router.push(to)
			});

			if (answer === 'keep') {
				// Back to what it was: the setting refused.
				if (check.refused === 'one') {
					multiple.value = true;
				} else {
					target.value = current.to[0] ?? '';
				}

				return;
			}

			if (answer === null) {
				return;
			}

			sent = { ...sent, ...answer };
		}

		await (current === null
			? request('POST', '/relations', sent)
			: request('PATCH', `/relations/${encodeURIComponent(sentName)}`, sent));

		saved.value = true;
		refreshTypes();
		toast(current === null ? `Added ${label.value || 'the relationship'}` : `Saved ${label.value || 'the relationship'}`);
		await router.push(back.value);
	});
}

async function remove(): Promise<void> {
	const current = relation.value;

	if (current === null) {
		return;
	}

	await runRemoval('The relationship couldn\'t be removed.', async () => {
		if (await removeRelation(current)) {
			saved.value = true;
			await router.push(back.value);
		}
	});
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="back"><AdminIcon name="chevron-left" />{{ fromType ? labelsOf(fromType).plural : 'All Relationships' }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ creating ? 'New Relationship' : (relation ? relationLabel(relation) : 'Relationship') }}</h1>
			<p v-if="loaded" class="page-header__hint" aria-live="polite">{{ sentence }}</p>
		</div>
		<div v-if="loaded && !locked" class="page-header__actions">
			<RouterLink class="button" :to="back">Cancel</RouterLink>
			<button type="submit" form="relation-page" class="button button--primary" :disabled="busy || !ready">{{ creating ? 'Add Relationship' : 'Save Relationship' }}</button>
		</div>
	</header>

	<p v-if="failed" class="notice notice--error" role="alert">{{ failed }}</p>

	<template v-else-if="loaded">
		<p v-if="relation && !relation.editable" class="notice"><AdminIcon name="info" /><span>Defined in {{ sourceOf(relation).toLowerCase() }}, so it's changed there and shown here.</span></p>
		<p v-else class="notice"><AdminIcon name="info" /><span>Saved in <code>user/data/relations{{ fileName ? `/${fileName}.json` : '' }}</code>. Changing it over entries that already use it asks first.</span></p>
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<form id="relation-page" @submit.prevent="save">
			<fieldset class="setting-panels" :disabled="locked || busy">
				<section class="panel" aria-labelledby="relation-purpose">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-purpose">Purpose</h2>
						<p class="panel__hint">What the link means</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><span id="relation-purpose-label">Purpose</span></div>
							<div class="field setting__control">
								<FieldInput v-if="creating" id="relation-purpose-input" v-model="purposeValue" :field="purposeField" labelled-by="relation-purpose-label" described-by="relation-purpose-help" />
								<p v-else class="setting__value">{{ { classify: 'Files entries under terms', reference: 'Links entries to other entries', credit: 'Credits people' }[purpose] }}</p>
							</div>
							<div id="relation-purpose-help" class="setting__help"><p>{{ creating ? 'It decides the endpoints below and the suggested picker.' : 'Chosen when it was made. For another purpose, make a new relationship.' }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-endpoints">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-endpoints">Endpoints</h2>
						<p class="panel__hint">{{ { classify: 'Which types are filed, and under which terms', reference: 'Which type links, and to which', credit: 'Which types credit, and which profiles they credit' }[purpose] }}</p>
					</header>
					<div class="setting-panels__rows">
						<div v-if="purpose === 'classify'" class="setting">
							<div class="setting__label"><label for="relation-terms">Terms</label></div>
							<div class="field setting__control"><AdminSelect id="relation-terms" v-model="terms" :options="termOptions" :disabled="!creating || locked" described-by="relation-terms-help" /></div>
							<div id="relation-terms-help" class="setting__help"><p>The type whose entries are the terms{{ creating ? ': one nothing files under yet' : '' }}.</p></div>
						</div>

						<div class="setting">
							<div class="setting__label"><label v-if="purpose === 'reference'" for="relation-source">Stored On</label><span v-else id="relation-stored-label">Stored On</span></div>
							<div class="field setting__control">
								<AdminSelect v-if="purpose === 'reference'" id="relation-source" v-model="source" :options="[{ value: '', label: 'Choose a type' }, ...options]" :disabled="!creating || locked" described-by="relation-source-help" />
								<FieldInput v-else id="relation-stored" v-model="storedValue" :field="storedField" labelled-by="relation-stored-label" described-by="relation-source-help" />
							</div>
							<div id="relation-source-help" class="setting__help">
								<p>The {{ purpose === 'reference' ? 'type' : 'types' }} whose files carry the key{{ creating ? ': the one being edited when the choice is made' : '' }}.</p>
								<p v-if="purpose !== 'reference' && from.length === 0">None chosen, so every type {{ purpose === 'classify' ? 'can be filed under them' : 'credits them' }}.</p>
							</div>
						</div>

						<div v-if="purpose === 'reference'" class="setting">
							<div class="setting__label"><label for="relation-target">Points At</label></div>
							<div class="field setting__control"><AdminSelect id="relation-target" v-model="target" :options="[{ value: '', label: 'Choose a type' }, ...options]" :disabled="locked" described-by="relation-target-help" /></div>
							<div id="relation-target-help" class="setting__help"><p>The type its entries name. Once entries have values, it can't point at another type.</p></div>
						</div>
						<div v-else-if="purpose === 'credit'" class="setting">
							<div class="setting__label"><span>Points At</span></div>
							<div class="field setting__control"><p class="setting__value">{{ profileType ? labelsOf(profileType).plural : 'No profiles type' }}</p></div>
							<div class="setting__help"><p>Credits always name profiles, the public side of accounts.</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-names">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-names">Names</h2>
						<p class="panel__hint">What's written in each file, and what people read</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-key">Front Matter Key</label></div>
							<div class="field setting__control">
								<input v-if="purpose !== 'classify' || !creating" id="relation-key" v-model="key" class="mono" :placeholder="purpose === 'classify' ? terms : referenceKey" autocomplete="off" spellcheck="false" aria-describedby="relation-key-help">
								<p v-else class="setting__value mono">{{ terms || '…' }}</p>
							</div>
							<div id="relation-key-help" class="setting__help"><p>Entries name {{ purpose === 'classify' ? 'their terms' : `their ${label.toLowerCase() || 'targets'}` }} under <code>{{ writtenKey || '…' }}</code>, by slug. Lowercase letters, digits, and underscores. Changing it later keeps an alias or rewrites the files; you choose when you save.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-called">Called</label></div>
							<div class="field setting__control"><input id="relation-called" v-model="called" :placeholder="pointsAt ? labelsOf(pointsAt).plural : ''" autocomplete="off" aria-describedby="relation-called-help"></div>
							<div id="relation-called-help" class="setting__help"><p>The heading in the editor, and its name everywhere in the admin.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-singular">One Is Called</label></div>
							<div class="field setting__control"><input id="relation-singular" v-model="singular" :placeholder="oneCalled" autocomplete="off" aria-describedby="relation-singular-help"></div>
							<div id="relation-singular-help" class="setting__help"><p>“Add {{ article(oneCalled) }} {{ oneCalled.toLowerCase() || '…' }}” in the picker, and “{{ oneCalled || '…' }}: Sam” on the site.</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-options">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-options">Options</h2>
						<p class="panel__hint">How many, and in what order</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><span id="relation-takes-label">Each {{ storedName }} Takes</span></div>
							<div class="field setting__control">
								<div class="segmented" role="group" aria-labelledby="relation-takes-label">
									<button type="button" :aria-pressed="multiple" @click="multiple = true">Several</button>
									<button type="button" :aria-pressed="!multiple" @click="multiple = false">Exactly One</button>
								</div>
							</div>
							<div class="setting__help"><p>{{ multiple ? 'Picked from a list that grows with search.' : 'Picked from a select.' }}</p></div>
						</div>
						<div v-if="multiple" class="setting">
							<div class="setting__label"><span>Order Matters</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="ordered" label="Keep the order" described-by="relation-ordered-help" :locked="locked" @change="ordered = $event" /></div></div>
							<div id="relation-ordered-help" class="setting__help"><p>Kept on the site, and the first is the lead.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>Created as Typed</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="create" label="Allow new ones from the picker" described-by="relation-create-help" :locked="locked" @change="create = $event" /></div></div>
							<div id="relation-create-help" class="setting__help"><p>A name that matches nothing becomes a new {{ pointsAt ? labelsOf(pointsAt).item : 'entry' }} on save.</p></div>
						</div>
						<div v-if="canBeSymmetric" class="setting">
							<div class="setting__label"><span>Both Ways</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="symmetric" label="A link counts both ways" described-by="relation-symmetric-help" :locked="locked" @change="symmetric = $event" /></div></div>
							<div id="relation-symmetric-help" class="setting__help"><p>Linking one {{ storedItem }} to another links it back, so each names the other.</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-limits">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-limits">Limits</h2>
						<p class="panel__hint">Gate publishing, never saving</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-min">At Least</label></div>
							<div class="field setting__control"><input id="relation-min" v-model="minimum" type="number" min="0" step="1" aria-describedby="relation-min-help"></div>
							<div id="relation-min-help" class="setting__help"><p>How many a {{ storedItem }} needs to be published. 0 for none.</p></div>
						</div>
						<div v-if="multiple" class="setting">
							<div class="setting__label"><label for="relation-max">At Most</label></div>
							<div class="field setting__control"><input id="relation-max" v-model="maximum" type="number" min="1" step="1" placeholder="No limit" aria-describedby="relation-max-help"></div>
							<div id="relation-max-help" class="setting__help"><p>Empty for no limit.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-side-max">Per {{ pointsAt ? labelsOf(pointsAt).singular : 'Target' }}, At Most</label></div>
							<div class="field setting__control"><input id="relation-side-max" v-model="sideMax" type="number" min="1" step="1" placeholder="No limit" aria-describedby="relation-side-max-help"></div>
							<div id="relation-side-max-help" class="setting__help"><p>How many {{ stores.length === 1 ? labelsOf(stores[0] ?? '').items : 'entries' }} can point at one of them, such as one season for an episode. Drafts count; the trash doesn't.</p></div>
						</div>
					</div>
					<p class="setting-panels__foot" aria-live="polite">{{ limits }} Limits are checked when an entry is published; a draft always saves.</p>
				</section>

				<section class="panel" aria-labelledby="relation-editing">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-editing">Editing</h2>
						<p class="panel__hint">How it's picked, and what translations do</p>
					</header>
					<div class="setting-panels__rows">
						<div v-if="multiple" class="setting">
							<div class="setting__label"><label for="relation-control">Picker</label></div>
							<div class="field setting__control"><AdminSelect id="relation-control" v-model="control" :options="controlOptions" :disabled="locked" described-by="relation-control-help" /></div>
							<div id="relation-control-help" class="setting__help"><p>Only the pickers that fit these settings are offered.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span id="relation-translations-label">Translations</span></div>
							<div class="field setting__control">
								<div class="segmented" role="group" aria-labelledby="relation-translations-label">
									<button v-for="rule in ruleOptions" :key="rule.value" type="button" :aria-pressed="translations === rule.value" @click="translations = rule.value">{{ rule.label }}</button>
								</div>
							</div>
							<div class="setting__help"><p>{{ ruleOptions.find((rule) => rule.value === translations)?.help }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-side">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-side">What Links to It</h2>
						<p class="panel__hint">How the other side shows it</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-side-label">The List Is Called</label></div>
							<div class="field setting__control"><input id="relation-side-label" v-model="sideLabel" :placeholder="stores.length === 1 ? labelsOf(stores[0] ?? '').plural : 'Acted in'" autocomplete="off" aria-describedby="relation-side-label-help"></div>
							<div id="relation-side-label-help" class="setting__help"><p>Its heading on the other side, such as “Acted in” for a person's movies.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>Own Page</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="page" label="Show the list on its own page" described-by="relation-page-help" :locked="locked" @change="page = $event" /></div></div>
							<div id="relation-page-help" class="setting__help"><p>Under the body of each {{ purpose === 'classify' ? 'term' : (pointsAt ? labelsOf(pointsAt).item : 'target') }}'s page.</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>Archive</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="archived" label="Give it an archive" described-by="relation-archive-help" :locked="locked" @change="archived = $event" /></div></div>
							<div id="relation-archive-help" class="setting__help"><p>A page per {{ purpose === 'classify' ? 'term' : (pointsAt ? labelsOf(pointsAt).item : 'target') }} listing what links to it, under each storing type's address. Paged, with a feed when the type has feeds. Turning it off never deletes a page written for it.</p></div>
						</div>
						<div v-if="archived" class="setting">
							<div class="setting__label"><label for="relation-word">Under the Word</label></div>
							<div class="field setting__control"><input id="relation-word" v-model="word" class="mono" :placeholder="purpose === 'classify' ? terms : referenceKey" autocomplete="off" spellcheck="false" aria-describedby="relation-word-help"></div>
							<div id="relation-word-help" class="setting__help"><p>Each archive is at <code>…/{{ keyOf(word) || writtenKey || '…' }}/its-slug</code>, introduced by <code>_{{ keyOf(word) || writtenKey || '…' }}.md</code> in that type's folder, when there is one.</p></div>
						</div>
					</div>
				</section>
			</fieldset>
		</form>

		<DangerZone v-if="relation && relation.editable && canCreateTypes" class="relation-remove" :error="removal">
			The picker leaves the editor and its links stop showing on the site. Entries keep their values unless you strip them, so adding it again brings them back.
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />Remove Relationship</button></template>
		</DangerZone>
	</template>

	<div v-else class="setting-panels" aria-hidden="true">
		<div v-for="index in 3" :key="index" class="panel"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>
	</div>
</template>

<style scoped>
.relation-remove {
	margin-top: var(--s-5);
}
</style>
