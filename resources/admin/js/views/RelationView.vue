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
import MessageText from '../components/MessageText.vue';
import ToggleSwitch from '../components/ToggleSwitch.vue';
import { errorMessage, request, type ContentTypeSummary, type FieldDescription, type RelationCheck, type RelationInfo } from '../api';
import { useAction } from '../action';
import { guardLeave } from '../confirm';
import type { FormValue } from '../fields';
import { t } from '../messages';
import { confirmRelationChange, limitsSay, purposeOf, relationLabel, removeRelation, sayRelation, sourceOf, type RelationPurpose } from '../relations';
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
	? [{ value: '', label: t('relation_view.choose_type', 'Choose a type') }, ...choices.value.filter((item) => !item.taxonomy).map((item) => ({ value: item.name, label: item.labels.plural }))]
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
const storedItem = computed(() => stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').item : t('relation_view.entry', 'entry'));
const storedName = computed(() => stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').singular : t('relation_view.entry_title', 'Entry'));

const ready = computed(() => purpose.value === 'classify'
	? terms.value !== ''
	: (purpose.value === 'credit' ? profileType.value !== null : source.value !== '' && target.value !== '') && referenceKey.value !== '');

// A tree only for terms that nest; none for one value, always a select.
const nests          = computed(() => purpose.value === 'classify' && types.value.find((item) => item.name === terms.value)?.hierarchical === true);
const shapeControl   = computed(() => purpose.value === 'classify' ? (nests.value ? t('relation_view.shape_tree', 'a tree of checkboxes') : t('relation_view.shape_chips', 'chips')) : (purpose.value === 'credit' ? t('relation_view.shape_people', 'people') : t('relation_view.shape_cards', 'cards')));
const controlOptions = computed(() => [
	{ value: '', label: t('relation_view.control_shape', 'As its shape says ({shape})', { shape: shapeControl.value }) },
	...(nests.value ? [{ value: 'tree', label: t('relation_view.control_tree', 'A tree of checkboxes') }] : []),
	{ value: 'tokens', label: t('relation_view.control_tokens', 'Chips, typed to search') },
	{ value: 'cards', label: t('relation_view.control_cards', 'Chips, with results shown as cards') }
]);

const ruleOptions = [
	{ value: 'fallback', label: t('relation_view.rule_fallback', 'Fall Back'), help: t('relation_view.rule_fallback_help', 'A translation with none of its own uses its original\'s.') },
	{ value: 'add', label: t('relation_view.rule_add', 'Add to the Original'), help: t('relation_view.rule_add_help', 'A translation has its original\'s and its own, as a translator credited beside the author.') },
	{ value: 'own', label: t('relation_view.rule_own', 'Keep Their Own'), help: t('relation_view.rule_own_help', 'A translation has only its own.') }
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
		items: stores.value.length === 1 ? labelsOf(stores.value[0] ?? '').items : t('relation_view.entries', 'entries'),
		singular: oneCalled.value.toLowerCase() || t('relation_view.limits_one', 'one'),
		plural: label.value.toLowerCase() || t('relation_view.limits_of_them', 'of them'),
		target: target?.item ?? t('relation_view.target', 'target'),
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

	purpose.value      = here?.taxonomy === true ? 'reference' : 'classify';
	terms.value        = '';
	from.value         = here && here.taxonomy !== true ? [here.name] : [];
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
	page.value         = here?.taxonomy !== true;
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
				failed.value = t('relation_view.not_found', 'There\'s no “{name}” relationship.', { name: name.value });

				return;
			}
		} else {
			relation.value = null;
		}

		fill(relation.value);
		initial.value = JSON.stringify(definition());
		loaded.value  = true;
	} catch (caught) {
		failed.value = errorMessage(caught, t('relation_view.load_failed', 'The relationship couldn\'t be loaded.'));
	}
}

watch(name, () => void load(), { immediate: true });

watch(() => [relation.value, creating.value] as const, ([found, isNew]) => {
	screenTitle.value = isNew ? t('relation_view.new_relationship', 'New Relationship') : (found ? relationLabel(found) : null);
}, { immediate: true });

screenTrail.value = [{ label: t('relation_view.relationships', 'Relationships'), to: { name: 'relations' } }];

// A new link's terms have pages; a credit's people have their profile's
// page and archives under each type; a new link to other entries has
// none, until it's chosen.
watch(purpose, (now) => {
	if (creating.value && loaded.value) {
		page.value     = now !== 'reference';
		archived.value = now === 'credit';
		create.value   = now === 'classify';
		from.value     = now !== 'reference' && fromType.value !== '' && types.value.find((item) => item.name === fromType.value)?.taxonomy !== true ? [fromType.value] : [];
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
	choices: { classify: t('relation_view.purpose_classify', 'Files Entries Under Terms'), reference: t('relation_view.purpose_reference', 'Links Entries to Other Entries'), credit: t('relation_view.purpose_credit', 'Credits People') },
	details: {
		classify: { text: t('relation_view.purpose_classify_example', 'Courses, ingredients, cuisines.'), code: '' },
		reference: { text: t('relation_view.purpose_reference_example', 'Pairs with, a movie\'s cast.'), code: '' },
		credit: { text: t('relation_view.purpose_credit_example', 'Authors, cooks, photographers.'), code: '' }
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
	control: 'checkboxes',
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

	await run(t('relation_view.save_failed', 'The relationship couldn\'t be saved.'), async () => {
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
		toast(current === null ? t('relation_view.added', 'Added {label}', { label: label.value || t('relation_view.the_relationship', 'the relationship') }) : t('relation_view.saved', 'Saved {label}', { label: label.value || t('relation_view.the_relationship', 'the relationship') }));
		await router.push(back.value);
	});
}

async function remove(): Promise<void> {
	const current = relation.value;

	if (current === null) {
		return;
	}

	await runRemoval(t('relation_view.remove_failed', 'The relationship couldn\'t be removed.'), async () => {
		if (await removeRelation(current)) {
			saved.value = true;
			await router.push(back.value);
		}
	});
}
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="back"><AdminIcon name="chevron-left" />{{ fromType ? labelsOf(fromType).plural : t('relation_view.all_relationships', 'All Relationships') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ creating ? t('relation_view.new_relationship', 'New Relationship') : (relation ? relationLabel(relation) : t('relation_view.relationship', 'Relationship')) }}</h1>
			<p v-if="loaded" class="page-header__hint" aria-live="polite">{{ sentence }}</p>
		</div>
		<div v-if="loaded && !locked" class="page-header__actions">
			<RouterLink class="button" :to="back">{{ t('common.cancel', 'Cancel') }}</RouterLink>
			<button type="submit" form="relation-page" class="button button--primary" :disabled="busy || !ready">{{ creating ? t('relation_view.add_relationship', 'Add Relationship') : t('relation_view.save_relationship', 'Save Relationship') }}</button>
		</div>
	</header>

	<p v-if="failed" class="notice notice--error" role="alert">{{ failed }}</p>

	<template v-else-if="loaded">
		<p v-if="relation && !relation.editable" class="notice"><AdminIcon name="info" /><span>{{ t('relation_view.defined_in', 'Defined in {source}, so it\'s changed there and shown here.', { source: sourceOf(relation).toLowerCase() }) }}</span></p>
		<p v-else class="notice"><AdminIcon name="info" /><span><MessageText :text="t('relation_view.saved_in', 'Saved in {file}. Changing it over entries that already use it asks first.')"><template #file><code>user/data/relations{{ fileName ? `/${fileName}.json` : '' }}</code></template></MessageText></span></p>
		<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

		<form id="relation-page" @submit.prevent="save">
			<fieldset class="setting-panels" :disabled="locked || busy">
				<section class="panel" aria-labelledby="relation-purpose">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-purpose">{{ t('relation_view.purpose', 'Purpose') }}</h2>
						<p class="panel__hint">{{ t('relation_view.purpose_hint', 'What the link means') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><span id="relation-purpose-label">{{ t('relation_view.purpose', 'Purpose') }}</span></div>
							<div class="field setting__control">
								<FieldInput v-if="creating" id="relation-purpose-input" v-model="purposeValue" :field="purposeField" labelled-by="relation-purpose-label" described-by="relation-purpose-help" />
								<p v-else class="setting__value">{{ { classify: t('relation_view.purpose_classify_value', 'Files entries under terms'), reference: t('relation_view.purpose_reference_value', 'Links entries to other entries'), credit: t('relation_view.purpose_credit_value', 'Credits people') }[purpose] }}</p>
							</div>
							<div id="relation-purpose-help" class="setting__help"><p>{{ creating ? t('relation_view.purpose_help_new', 'It decides the endpoints below and the suggested picker.') : t('relation_view.purpose_help', 'Chosen when it was made. For another purpose, make a new relationship.') }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-endpoints">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-endpoints">{{ t('relation_view.endpoints', 'Endpoints') }}</h2>
						<p class="panel__hint">{{ { classify: t('relation_view.endpoints_classify', 'Which types are filed, and under which terms'), reference: t('relation_view.endpoints_reference', 'Which type links, and to which'), credit: t('relation_view.endpoints_credit', 'Which types credit, and which profiles they credit') }[purpose] }}</p>
					</header>
					<div class="setting-panels__rows">
						<div v-if="purpose === 'classify'" class="setting">
							<div class="setting__label"><label for="relation-terms">{{ t('relation_view.taxonomy', 'Taxonomy') }}</label></div>
							<div class="field setting__control"><AdminSelect id="relation-terms" v-model="terms" :options="termOptions" :disabled="!creating || locked" described-by="relation-terms-help" /></div>
							<div id="relation-terms-help" class="setting__help"><p>{{ creating ? t('relation_view.taxonomy_help_new', 'The taxonomy entries are filed under: one nothing files under yet.') : t('relation_view.taxonomy_help', 'The taxonomy entries are filed under.') }}</p></div>
						</div>

						<div class="setting">
							<div class="setting__label"><label v-if="purpose === 'reference'" for="relation-source">{{ t('relation_view.stored_on', 'Stored On') }}</label><span v-else id="relation-stored-label">{{ t('relation_view.stored_on', 'Stored On') }}</span></div>
							<div class="field setting__control">
								<AdminSelect v-if="purpose === 'reference'" id="relation-source" v-model="source" :options="[{ value: '', label: t('relation_view.choose_type', 'Choose a type') }, ...options]" :disabled="!creating || locked" described-by="relation-source-help" />
								<FieldInput v-else id="relation-stored" v-model="storedValue" :field="storedField" labelled-by="relation-stored-label" described-by="relation-source-help" />
							</div>
							<div id="relation-source-help" class="setting__help">
								<p>{{ t('relation_view.stored_help', '{reference, select, yes {The type} other {The types} } whose files carry the key{creating, select, yes {: the one being edited when the choice is made} other {} }.', { reference: purpose === 'reference' ? 'yes' : 'no', creating: creating ? 'yes' : 'no' }) }}</p>
								<p v-if="purpose !== 'reference' && from.length === 0">{{ purpose === 'classify' ? t('relation_view.none_chosen_classify', 'None chosen, so every type can be filed under them.') : t('relation_view.none_chosen_credit', 'None chosen, so every type credits them.') }}</p>
							</div>
						</div>

						<div v-if="purpose === 'reference'" class="setting">
							<div class="setting__label"><label for="relation-target">{{ t('relation_view.points_at', 'Points At') }}</label></div>
							<div class="field setting__control"><AdminSelect id="relation-target" v-model="target" :options="[{ value: '', label: t('relation_view.choose_type', 'Choose a type') }, ...options]" :disabled="locked" described-by="relation-target-help" /></div>
							<div id="relation-target-help" class="setting__help"><p>{{ t('relation_view.target_help', 'The type its entries name. Once entries have values, it can\'t point at another type.') }}</p></div>
						</div>
						<div v-else-if="purpose === 'credit'" class="setting">
							<div class="setting__label"><span>{{ t('relation_view.points_at', 'Points At') }}</span></div>
							<div class="field setting__control"><p class="setting__value">{{ profileType ? labelsOf(profileType).plural : t('relation_view.no_profiles', 'No profiles type') }}</p></div>
							<div class="setting__help"><p>{{ t('relation_view.credit_help', 'Credits always name profiles, the public side of accounts.') }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-names">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-names">{{ t('relation_view.names', 'Names') }}</h2>
						<p class="panel__hint">{{ t('relation_view.names_hint', 'What\'s written in each file, and what people read') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-key">{{ t('relation_view.key', 'Front Matter Key') }}</label></div>
							<div class="field setting__control">
								<input v-if="purpose !== 'classify' || !creating" id="relation-key" v-model="key" class="mono" :placeholder="purpose === 'classify' ? terms : referenceKey" autocomplete="off" spellcheck="false" aria-describedby="relation-key-help">
								<p v-else class="setting__value mono">{{ terms || '…' }}</p>
							</div>
							<div id="relation-key-help" class="setting__help"><p><MessageText :text="purpose === 'classify' ? t('relation_view.key_help_terms', 'Entries name their terms under {key}, by slug. Lowercase letters, digits, and underscores. Changing it later keeps an alias or rewrites the files; you choose when you save.') : t('relation_view.key_help', 'Entries name their {targets} under {key}, by slug. Lowercase letters, digits, and underscores. Changing it later keeps an alias or rewrites the files; you choose when you save.', { targets: label.toLowerCase() || t('relation_view.targets', 'targets') })"><template #key><code>{{ writtenKey || '…' }}</code></template></MessageText></p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-called">{{ t('relation_view.called', 'Called') }}</label></div>
							<div class="field setting__control"><input id="relation-called" v-model="called" :placeholder="pointsAt ? labelsOf(pointsAt).plural : ''" autocomplete="off" aria-describedby="relation-called-help"></div>
							<div id="relation-called-help" class="setting__help"><p>{{ t('relation_view.called_help', 'The heading in the editor, and its name everywhere in the admin.') }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-singular">{{ t('relation_view.singular', 'One Is Called') }}</label></div>
							<div class="field setting__control"><input id="relation-singular" v-model="singular" :placeholder="oneCalled" autocomplete="off" aria-describedby="relation-singular-help"></div>
							<div id="relation-singular-help" class="setting__help"><p>{{ t('relation_view.singular_help', '“Add {one}…” in the picker, and “{name}: Sam” on the site.', { one: oneCalled.toLowerCase() || '…', name: oneCalled || '…' }) }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-options">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-options">{{ t('relation_view.options', 'Options') }}</h2>
						<p class="panel__hint">{{ t('relation_view.options_hint', 'How many, and in what order') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><span id="relation-takes-label">{{ t('relation_view.takes', 'Each {name} Takes', { name: storedName }) }}</span></div>
							<div class="field setting__control">
								<div class="segmented" role="group" aria-labelledby="relation-takes-label">
									<button type="button" :aria-pressed="multiple" @click="multiple = true">{{ t('relation_view.several', 'Several') }}</button>
									<button type="button" :aria-pressed="!multiple" @click="multiple = false">{{ t('relation_view.exactly_one', 'Exactly One') }}</button>
								</div>
							</div>
							<div class="setting__help"><p>{{ multiple ? t('relation_view.takes_several_help', 'Picked from a list that grows with search.') : t('relation_view.takes_one_help', 'Picked from a select.') }}</p></div>
						</div>
						<div v-if="multiple" class="setting">
							<div class="setting__label"><span>{{ t('relation_view.ordered', 'Order Matters') }}</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="ordered" :label="t('relation_view.ordered_label', 'Keep the order')" described-by="relation-ordered-help" :locked="locked" @change="ordered = $event" /></div></div>
							<div id="relation-ordered-help" class="setting__help"><p>{{ t('relation_view.ordered_help', 'Kept on the site, and the first is the lead.') }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>{{ t('relation_view.create', 'Created as Typed') }}</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="create" :label="t('relation_view.create_label', 'Allow new ones from the picker')" described-by="relation-create-help" :locked="locked" @change="create = $event" /></div></div>
							<div id="relation-create-help" class="setting__help"><p>{{ t('relation_view.create_help', 'A name that matches nothing becomes a new {item} on save.', { item: pointsAt ? labelsOf(pointsAt).item : t('relation_view.entry', 'entry') }) }}</p></div>
						</div>
						<div v-if="canBeSymmetric" class="setting">
							<div class="setting__label"><span>{{ t('relation_view.symmetric', 'Both Ways') }}</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="symmetric" :label="t('relation_view.symmetric_label', 'A link counts both ways')" described-by="relation-symmetric-help" :locked="locked" @change="symmetric = $event" /></div></div>
							<div id="relation-symmetric-help" class="setting__help"><p>{{ t('relation_view.symmetric_help', 'Linking one {item} to another links it back, so each names the other.', { item: storedItem }) }}</p></div>
						</div>
					</div>
				</section>

				<section class="panel" aria-labelledby="relation-limits">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-limits">{{ t('relation_view.limits', 'Limits') }}</h2>
						<p class="panel__hint">{{ t('relation_view.limits_hint', 'Gate publishing, never saving') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-min">{{ t('relation_view.min', 'At Least') }}</label></div>
							<div class="field setting__control"><input id="relation-min" v-model="minimum" type="number" min="0" step="1" aria-describedby="relation-min-help"></div>
							<div id="relation-min-help" class="setting__help"><p>{{ t('relation_view.min_help', 'How many a {item} needs to be published. 0 for none.', { item: storedItem }) }}</p></div>
						</div>
						<div v-if="multiple" class="setting">
							<div class="setting__label"><label for="relation-max">{{ t('relation_view.max', 'At Most') }}</label></div>
							<div class="field setting__control"><input id="relation-max" v-model="maximum" type="number" min="1" step="1" :placeholder="t('relation_view.no_limit', 'No limit')" aria-describedby="relation-max-help"></div>
							<div id="relation-max-help" class="setting__help"><p>{{ t('relation_view.max_help', 'Empty for no limit.') }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><label for="relation-side-max">{{ t('relation_view.side_max', 'Per {target}, At Most', { target: pointsAt ? labelsOf(pointsAt).singular : t('relation_view.target_title', 'Target') }) }}</label></div>
							<div class="field setting__control"><input id="relation-side-max" v-model="sideMax" type="number" min="1" step="1" :placeholder="t('relation_view.no_limit', 'No limit')" aria-describedby="relation-side-max-help"></div>
							<div id="relation-side-max-help" class="setting__help"><p>{{ t('relation_view.side_max_help', 'How many {items} can point at one of them, such as one season for an episode. Drafts count; the trash doesn\'t.', { items: stores.length === 1 ? labelsOf(stores[0] ?? '').items : t('relation_view.entries', 'entries') }) }}</p></div>
						</div>
					</div>
					<p class="setting-panels__foot" aria-live="polite">{{ t('relation_view.limits_foot', '{limits} Limits are checked when an entry is published; a draft always saves.', { limits }) }}</p>
				</section>

				<section class="panel" aria-labelledby="relation-editing">
					<header class="panel__header setting-panels__header">
						<h2 id="relation-editing">{{ t('relation_view.editing', 'Editing') }}</h2>
						<p class="panel__hint">{{ t('relation_view.editing_hint', 'How it\'s picked, and what translations do') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div v-if="multiple" class="setting">
							<div class="setting__label"><label for="relation-control">{{ t('relation_view.picker', 'Picker') }}</label></div>
							<div class="field setting__control"><AdminSelect id="relation-control" v-model="control" :options="controlOptions" :disabled="locked" described-by="relation-control-help" /></div>
							<div id="relation-control-help" class="setting__help"><p>{{ t('relation_view.picker_help', 'Only the pickers that fit these settings are offered.') }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span id="relation-translations-label">{{ t('relation_view.translations', 'Translations') }}</span></div>
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
						<h2 id="relation-side">{{ t('relation_view.side', 'What Links to It') }}</h2>
						<p class="panel__hint">{{ t('relation_view.side_hint', 'How the other side shows it') }}</p>
					</header>
					<div class="setting-panels__rows">
						<div class="setting">
							<div class="setting__label"><label for="relation-side-label">{{ t('relation_view.side_label', 'The List Is Called') }}</label></div>
							<div class="field setting__control"><input id="relation-side-label" v-model="sideLabel" :placeholder="stores.length === 1 ? labelsOf(stores[0] ?? '').plural : t('relation_view.side_label_placeholder', 'Acted in')" autocomplete="off" aria-describedby="relation-side-label-help"></div>
							<div id="relation-side-label-help" class="setting__help"><p>{{ t('relation_view.side_label_help', 'Its heading on the other side, such as “Acted in” for a person\'s movies.') }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>{{ t('relation_view.page', 'Own Page') }}</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="page" :label="t('relation_view.page_label', 'Show the list on its own page')" described-by="relation-page-help" :locked="locked" @change="page = $event" /></div></div>
							<div id="relation-page-help" class="setting__help"><p>{{ t('relation_view.page_help', 'Under the body of each {item}\'s page.', { item: purpose === 'classify' ? t('relation_view.term', 'term') : (pointsAt ? labelsOf(pointsAt).item : t('relation_view.target', 'target')) }) }}</p></div>
						</div>
						<div class="setting">
							<div class="setting__label"><span>{{ t('relation_view.archive', 'Archive') }}</span></div>
							<div class="field setting__control"><div class="setting__switch"><ToggleSwitch form :checked="archived" :label="t('relation_view.archive_label', 'Give it an archive')" described-by="relation-archive-help" :locked="locked" @change="archived = $event" /></div></div>
							<div id="relation-archive-help" class="setting__help"><p>{{ t('relation_view.archive_help', 'A page per {item} listing what links to it, under each storing type\'s address. Paged, with a feed when the type has feeds. Turning it off never deletes a page written for it.', { item: purpose === 'classify' ? t('relation_view.term', 'term') : (pointsAt ? labelsOf(pointsAt).item : t('relation_view.target', 'target')) }) }}</p></div>
						</div>
						<div v-if="archived" class="setting">
							<div class="setting__label"><label for="relation-word">{{ t('relation_view.word', 'Under the Word') }}</label></div>
							<div class="field setting__control"><input id="relation-word" v-model="word" class="mono" :placeholder="purpose === 'classify' ? terms : referenceKey" autocomplete="off" spellcheck="false" aria-describedby="relation-word-help"></div>
							<div id="relation-word-help" class="setting__help"><p><MessageText :text="t('relation_view.word_help', 'Each archive is at {path}, introduced by {file} in that type\'s folder, when there is one.')"><template #path><code>…/{{ keyOf(word) || writtenKey || '…' }}/its-slug</code></template><template #file><code>_{{ keyOf(word) || writtenKey || '…' }}.md</code></template></MessageText></p></div>
						</div>
					</div>
				</section>
			</fieldset>
		</form>

		<DangerZone v-if="relation && relation.editable && canCreateTypes" class="relation-remove" :error="removal">
			{{ t('relation_view.remove_body', 'The picker leaves the editor and its links stop showing on the site. Entries keep their values unless you strip them, so adding it again brings them back.') }}
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('relation_view.remove', 'Remove Relationship') }}</button></template>
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
