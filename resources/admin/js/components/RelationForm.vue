<script setup lang="ts">
/**
 * Adds or changes one relationship (D-593), in a modal over a type's
 * screen: what it's for, then what that purpose asks.
 *
 * - **Files entries under terms** (a classify relation, named after its
 *   terms' type): the terms' type, and the types it files (none for
 *   every type).
 * - **Links to other entries** (a reference): the type that holds it,
 *   the type it points at, and the front matter key.
 *
 * Then what both share (D-599's form): what it's called; one or several,
 * in order, created as typed, and both ways (a link to its own type);
 * its limits (`min`, `max`, the inverse's `max`); its picker (`control`)
 * and translations rule; and where what links to a target is listed (the
 * inverse's `page` and `archive` word, either or both, D-602) and what it's
 * called there.
 *
 * A change starts from the relation's definition as written, so options
 * this form doesn't show (aliases, a term page's listing) are kept, and
 * sends it whole (`PATCH relations/{name}`); a new one is `POST
 * relations`. Then the routes and index are refreshed, and `saved` asks
 * the screen to load the type again.
 */

import { computed, ref, watch } from 'vue';
import AdminModal from './AdminModal.vue';
import AdminSelect from './AdminSelect.vue';
import { request, type ContentTypeSummary, type RelationInfo } from '../api';
import { confirmAction, confirmChecked } from '../confirm';
import { plural } from '../format';
import { useAction } from '../action';
import { keyOf } from '../type-form';
import { toast } from '../toast';
import { profileType, refreshTypes, types } from '../types';

const props = defineProps<{
	open: boolean;
	// The type whose screen it's on.
	type: string;
	// The relation to change, or `null` to add one.
	relation: RelationInfo | null;
}>();

const emit = defineEmits<{
	close: [];
	saved: [];
}>();

type Purpose = 'classify' | 'reference' | 'credit';

type Rule = 'fallback' | 'add' | 'own';

const purpose      = ref<Purpose>('classify');
const terms        = ref('');
const from         = ref<string[]>([]);
const source       = ref('');
const target       = ref('');
const key          = ref('');
const called       = ref('');
const create       = ref(true);
const multiple     = ref(true);
const ordered      = ref(false);
const symmetric    = ref(false);
const minimum      = ref('0');
const maximum      = ref('');
const control      = ref('');
const translations = ref<Rule>('fallback');
// Where what links to an entry is listed (D-602): on its own page, in
// archives under the linking type's address by a word, both, or neither
// (a template lists it).
const page         = ref(false);
const archived     = ref(false);
const word         = ref('');
const sideLabel    = ref('');
const sideMax      = ref('');

const { busy, error, run } = useAction();

// Every type but profiles, which credit relations credit (D-602).
const choices = computed<ContentTypeSummary[]>(() => types.value.filter((item) => item.kind !== 'profiles'));
const options = computed(() => choices.value.map((item) => ({ value: item.name, label: item.labels.plural })));
const labelOf = (name: string): string => types.value.find((item) => item.name === name)?.labels.plural ?? name;
const itemOf  = (name: string): string => types.value.find((item) => item.name === name)?.labels.singular.toLowerCase() ?? name;

// The types a new classify relation can file entries under: those nothing
// files under yet (one relation each, named after it); and the types it
// can file: any but the terms' own.
const termOptions = computed(() => props.relation !== null
	? options.value
	: [{ value: '', label: 'Choose a type' }, ...choices.value.filter((item) => !item.terms).map((item) => ({ value: item.name, label: item.labels.plural }))]);
const fileable    = computed(() => choices.value.filter((item) => item.name !== terms.value));
const ready       = computed(() => purpose.value === 'classify'
	? terms.value !== ''
	: (purpose.value === 'credit' ? profileType.value !== null : target.value !== '') && referenceKey.value !== '');

// The types a credit is from (D-602): any but profiles.
const creditable  = computed(() => choices.value);

// What it points at, whichever its purpose.
const pointsAt = computed(() => purpose.value === 'classify' ? terms.value : (purpose.value === 'credit' ? profileType.value ?? '' : target.value));

// The pickers it may choose over its shape's (D-599): a tree only for
// terms that nest; none for one value, which is always a select.
const nests          = computed(() => purpose.value === 'classify' && types.value.find((item) => item.name === terms.value)?.hierarchical === true);
const shapeControl   = computed(() => purpose.value === 'classify' ? (nests.value ? 'a tree of checkboxes' : 'chips') : 'cards');
const controlOptions = computed(() => [
	{ value: '', label: `As its shape says (${shapeControl.value})` },
	...(nests.value ? [{ value: 'tree', label: 'A tree of checkboxes' }] : []),
	{ value: 'tokens', label: 'Chips, typed to search' },
	{ value: 'cards', label: 'Chips, with results shown as cards' }
]);

const ruleOptions = [
	{ value: 'fallback', label: 'Its own, else its original\'s' },
	{ value: 'add', label: 'Its original\'s, and its own' },
	{ value: 'own', label: 'Only its own' }
];

// A whole number from a field, or `null` when it's empty.
function count(text: string): number | null {
	const number = Number.parseInt(text, 10);

	return Number.isNaN(number) ? null : number;
}

watch(() => [props.open, props.relation] as const, ([open, relation]) => {
	if (!open) {
		return;
	}

	error.value = '';

	if (relation !== null) {
		const archive = relation.inverse === false ? false : relation.inverse.archive;

		purpose.value      = relation.kind === 'classify' ? 'classify' : (relation.kind === 'credit' ? 'credit' : 'reference');
		terms.value        = relation.to[0] ?? '';
		from.value         = [...relation.from];
		source.value       = relation.from[0] ?? props.type;
		target.value       = relation.to[0] ?? '';
		key.value          = relation.field;
		called.value       = relation.label;
		create.value       = relation.create;
		multiple.value     = relation.multiple;
		ordered.value      = relation.ordered;
		symmetric.value    = relation.symmetric;
		minimum.value      = String(relation.min);
		maximum.value      = relation.multiple && relation.max !== null ? String(relation.max) : '';
		control.value      = relation.control ?? '';
		translations.value = relation.translations;
		page.value         = relation.inverse !== false && relation.inverse.page;
		archived.value     = archive !== false;
		word.value         = archive === false ? '' : archive;
		sideLabel.value    = relation.inverse === false ? '' : relation.inverse.label;
		sideMax.value      = relation.inverse === false || relation.inverse.max === null ? '' : String(relation.inverse.max);

		return;
	}

	// A new one, from this type's side.
	const here   = types.value.find((item) => item.name === props.type);
	const others = choices.value.filter((item) => item.name !== props.type);

	// A type of terms is already filed under by its relation, so what it
	// gains is usually a link; anything else, a set of terms.
	purpose.value      = here?.terms === true ? 'reference' : 'classify';
	terms.value        = '';
	from.value         = here?.terms === true ? [] : [props.type];
	source.value       = props.type;
	target.value       = others[0]?.name ?? '';
	key.value          = '';
	called.value       = '';
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
}, { immediate: true });

// A new link's terms have pages; a credit's people have their profile's
// page and archives under each type; a new link to other entries has
// none, until it's chosen.
watch(purpose, (now) => {
	if (props.relation === null) {
		page.value     = now !== 'reference';
		archived.value = now === 'credit';
		create.value   = now === 'classify';
		from.value     = now === 'reference' ? from.value : [props.type];
	}
});

function filed(name: string, on: boolean): void {
	from.value = on ? [...from.value, name] : from.value.filter((item) => item !== name);
}

// The key a reference or credit is written under: the one typed, else
// one made from the target's plural name ("Actors" → `actors`), or
// `authors` for a credit.
const referenceKey = computed(() => keyOf(key.value) || (purpose.value === 'credit' ? 'authors' : keyOf(labelOf(target.value))));

// A link both ways needs it to point at its own type.
const canBeSymmetric = computed(() => purpose.value === 'reference' && source.value !== '' && source.value === target.value && multiple.value);

/**
 * The definition to send: the relation's as written, with this form's
 * choices over it.
 */
function definition(): Record<string, unknown> {
	const base        = props.relation?.definition ?? {};
	const inverseBase = typeof base.inverse === 'object' && base.inverse !== null ? base.inverse : {};
	const classify    = purpose.value === 'classify';
	const fallback    = classify ? terms.value : referenceKey.value;

	const name = classify ? terms.value : props.relation?.name ?? referenceKey.value;
	const field = props.relation === null ? '' : keyOf(key.value);

	return {
		...base,
		name,
		field: field !== '' && field !== name ? field : null,
		kind: purpose.value,
		from: purpose.value === 'reference' ? [source.value] : from.value,
		to: [pointsAt.value],
		label: called.value.trim(),
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

/**
 * What a change does to the entries using the relation (D-600), from
 * `POST relations/{name}/check`.
 */
interface RelationCheck {
	refusal: string | null;
	warnings: string[];
	uses: number;
	moved: number;
	unfiled: string[];
	stripped: number;
}

/**
 * Asks about what a change does to entries: limits it puts them out
 * of, a new key's values (kept as an alias, or moved), and the values of
 * types it no longer files (kept, or removed). Resolves the options to
 * send, or `null` to stop.
 */
async function confirmChange(check: RelationCheck, was: string): Promise<{ rewrite: boolean; strip: boolean } | null> {
	const field   = props.relation === null ? '' : keyOf(key.value);
	let   rewrite = false;
	let   strip   = false;

	if (check.moved > 0) {
		const answer = await confirmChecked({
			title: 'Change the Key?',
			body: [...check.warnings, `**${plural(check.moved, 'entry has', 'entries have')} values under \`${was}\`.** Left in place, \`${was}\` is still read, as another name for \`${field}\`.`],
			check: `Rewrite ${check.moved === 1 ? 'its file' : `their ${check.moved} files`} to \`${field}\``,
			checked: false,
			confirm: 'Save Relationship'
		});

		if (answer === null) {
			return null;
		}

		rewrite = answer;
	}

	if (check.stripped > 0) {
		const answer = await confirmChecked({
			title: 'Stop Filing These Types?',
			body: [...(check.moved > 0 ? [] : check.warnings), `**${plural(check.stripped, 'entry', 'entries')} of ${check.unfiled.map(labelOf).join(' and ')} ${check.stripped === 1 ? 'has' : 'have'} values in it.** Kept, they stay in their files, but aren't read as links.`],
			check: `Also remove them from ${check.stripped === 1 ? 'that entry' : `those ${check.stripped} entries`}`,
			checked: false,
			confirm: 'Save Relationship',
			danger: true
		});

		if (answer === null) {
			return null;
		}

		strip = answer;
	} else if (check.moved === 0 && check.warnings.length > 0) {
		if (!await confirmAction({ title: 'Save the Relationship?', body: check.warnings, confirm: 'Save Relationship' })) {
			return null;
		}
	}

	return { rewrite, strip };
}

async function save(): Promise<void> {
	await run('The relationship couldn\'t be saved.', async () => {
		let   sent = definition();
		const name = String(sent.name);

		if (props.relation !== null) {
			const check = await request<RelationCheck>('POST', `/relations/${encodeURIComponent(name)}/check`, sent);

			if (check.refusal !== null) {
				error.value = check.refusal;

				return;
			}

			const options = await confirmChange(check, props.relation.field);

			if (options === null) {
				return;
			}

			sent = { ...sent, ...options };
		}

		await (props.relation === null
			? request('POST', '/relations', sent)
			: request('PATCH', `/relations/${encodeURIComponent(name)}`, sent));

		refreshTypes();
		toast(props.relation === null ? 'Added the relationship' : 'Saved the relationship');
		emit('saved');
		emit('close');
	});
}
</script>

<template>
	<AdminModal :open="open" :title="relation === null ? 'Add a Relationship' : 'Edit the Relationship'" @close="emit('close')">
		<form id="relation-form" class="form-stack" @submit.prevent="save">
			<fieldset v-if="relation === null" class="fieldset">
				<legend>What it does</legend>
				<label class="checkbox"><input v-model="purpose" type="radio" value="classify" name="relation-purpose"> Files entries under terms, such as categories or tags</label>
				<label class="checkbox"><input v-model="purpose" type="radio" value="reference" name="relation-purpose"> Links entries to other entries, such as a movie's actors</label>
				<label v-if="profileType !== null" class="checkbox"><input v-model="purpose" type="radio" value="credit" name="relation-purpose"> Credits people, such as a post's authors or a recipe's cooks</label>
			</fieldset>

			<template v-if="purpose === 'classify'">
				<div class="field">
					<label for="relation-terms">Terms</label>
					<AdminSelect id="relation-terms" v-model="terms" :options="termOptions" :disabled="relation !== null" described-by="relation-terms-help" />
					<p id="relation-terms-help" class="field__help">The type whose entries are the terms; one nothing files under yet. Entries name them in <code>{{ terms || '…' }}</code>.</p>
				</div>
				<fieldset class="fieldset">
					<legend>Files</legend>
					<label v-for="item in fileable" :key="item.name" class="checkbox"><input type="checkbox" :checked="from.includes(item.name)" @change="filed(item.name, ($event.target as HTMLInputElement).checked)"> {{ item.labels.plural }}</label>
					<p class="field__help">{{ from.length === 0 ? 'None chosen, so it files every type.' : 'Entries of these types can be filed under its terms.' }}</p>
				</fieldset>
			</template>

			<template v-else-if="purpose === 'credit'">
				<fieldset class="fieldset">
					<legend>Credited by</legend>
					<label v-for="item in creditable" :key="item.name" class="checkbox"><input type="checkbox" :checked="from.includes(item.name)" @change="filed(item.name, ($event.target as HTMLInputElement).checked)"> {{ item.labels.plural }}</label>
					<p class="field__help">{{ from.length === 0 ? 'None chosen, so every type credits them.' : 'Entries of these types credit people.' }} Each credits a profile, one of the {{ labelOf(profileType ?? '') }}.</p>
				</fieldset>
				<div class="field">
					<label for="relation-credit-key">Key</label>
					<input id="relation-credit-key" v-model="key" class="mono" placeholder="authors" autocomplete="off" spellcheck="false" aria-describedby="relation-credit-key-help">
					<p id="relation-credit-key-help" class="field__help">Front matter names the people under <code>{{ referenceKey || '…' }}</code>, by their profile's slug, in order: the first is the lead.</p>
				</div>
			</template>

			<template v-else>
				<div class="field">
					<label for="relation-source">Entries of</label>
					<AdminSelect id="relation-source" v-model="source" :options="options" :disabled="relation !== null" />
				</div>
				<div class="field">
					<label for="relation-target">Link to</label>
					<AdminSelect id="relation-target" v-model="target" :options="options" />
				</div>
				<div class="field">
					<label for="relation-key">Key</label>
					<input id="relation-key" v-model="key" class="mono" :placeholder="keyOf(labelOf(target))" autocomplete="off" spellcheck="false" aria-describedby="relation-key-help">
					<p id="relation-key-help" class="field__help">Front matter names the entries under <code>{{ referenceKey || '…' }}</code>, by slug.</p>
				</div>
			</template>

			<div v-if="purpose === 'classify' && relation !== null" class="field">
				<label for="relation-term-key">Key</label>
				<input id="relation-term-key" v-model="key" class="mono" :placeholder="terms" autocomplete="off" spellcheck="false" aria-describedby="relation-term-key-help">
				<p id="relation-term-key-help" class="field__help">Front matter names the terms under <code>{{ keyOf(key) || terms }}</code>, by slug.</p>
			</div>

			<div class="field">
				<label for="relation-called">Called</label>
				<input id="relation-called" v-model="called" :placeholder="labelOf(pointsAt)" autocomplete="off" aria-describedby="relation-called-help">
				<p id="relation-called-help" class="field__help">What the editor calls it, such as “Cast” for a movie's actors.</p>
			</div>

			<fieldset class="fieldset">
				<legend>Options</legend>
				<label class="checkbox"><input v-model="multiple" type="checkbox"> Several, not just one</label>
				<label v-if="multiple" class="checkbox"><input v-model="ordered" type="checkbox"> Their order matters</label>
				<label class="checkbox"><input v-model="create" type="checkbox"> Writers can add a new {{ itemOf(pointsAt) }} as they type it</label>
				<label v-if="canBeSymmetric" class="checkbox"><input v-model="symmetric" type="checkbox"> A link counts both ways, so each names the other</label>
			</fieldset>

			<fieldset class="fieldset">
				<legend>Limits</legend>
				<div class="field-pair">
					<div class="field">
						<label for="relation-min">Needed to publish</label>
						<input id="relation-min" v-model="minimum" type="number" min="0" step="1">
					</div>
					<div v-if="multiple" class="field">
						<label for="relation-max">At most</label>
						<input id="relation-max" v-model="maximum" type="number" min="1" step="1" placeholder="No limit">
					</div>
				</div>
				<div class="field">
					<label for="relation-side-max">Each {{ itemOf(pointsAt) }} named by at most</label>
					<input id="relation-side-max" v-model="sideMax" type="number" min="1" step="1" placeholder="Any number of entries" aria-describedby="relation-side-max-help">
					<p id="relation-side-max-help" class="field__help">How many entries may link to one, such as one season for an episode. Drafts count; the trash doesn't.</p>
				</div>
			</fieldset>

			<fieldset class="fieldset">
				<legend>Editing</legend>
				<div v-if="multiple" class="field">
					<label for="relation-control">Picked with</label>
					<AdminSelect id="relation-control" v-model="control" :options="controlOptions" />
				</div>
				<div class="field">
					<label for="relation-translations">A translation's</label>
					<AdminSelect id="relation-translations" v-model="translations" :options="ruleOptions" described-by="relation-translations-help" />
					<p id="relation-translations-help" class="field__help">Which a translation uses: credits usually add a translator to the original's.</p>
				</div>
			</fieldset>

			<fieldset class="fieldset">
				<legend>What links to {{ purpose === 'classify' ? 'a term' : `a ${itemOf(pointsAt)}` }}</legend>
				<label class="checkbox"><input v-model="page" type="checkbox"> On each {{ purpose === 'classify' ? 'term' : itemOf(pointsAt) }}'s own page</label>
				<label class="checkbox"><input v-model="archived" type="checkbox"> In archives under the {{ purpose === 'reference' ? `${labelOf(source)}` : 'types\'' }} address</label>
				<div v-if="archived" class="field">
					<label for="relation-word">Archive word</label>
					<input id="relation-word" v-model="word" class="mono" :placeholder="purpose === 'classify' ? terms : referenceKey" autocomplete="off" spellcheck="false" aria-describedby="relation-word-help">
					<p id="relation-word-help" class="field__help">Each archive is at <code>…/{{ keyOf(word) || (purpose === 'classify' ? terms : referenceKey) || '…' }}/its-slug</code>, with an introduction from <code>_{{ keyOf(word) || (purpose === 'classify' ? terms : referenceKey) || '…' }}.md</code> in that type's folder, when there is one.</p>
				</div>
				<p class="field__help">Each list is paged, with a feed when the type has feeds. Neither, and a template can still list them.</p>
				<div class="field">
					<label for="relation-side-label">Called there</label>
					<input id="relation-side-label" v-model="sideLabel" :placeholder="purpose === 'classify' ? labelOf(from[0] ?? '') : 'Acted in'" autocomplete="off" aria-describedby="relation-side-label-help">
					<p id="relation-side-label-help" class="field__help">What the list is called on the other side, such as “Acted in” for a person's movies.</p>
				</div>
			</fieldset>

			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		</form>
		<template #footer>
			<button type="button" class="button" @click="emit('close')">Cancel</button>
			<button type="submit" form="relation-form" class="button button--primary" :disabled="busy || !ready">{{ relation === null ? 'Add Relationship' : 'Save Relationship' }}</button>
		</template>
	</AdminModal>
</template>
