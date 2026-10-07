<script setup lang="ts">
/**
 * Adds or changes one relationship (D-593), in a modal over a type's
 * screen: what it's for, then what that purpose asks.
 *
 * - **Files entries under terms** (a classify relation, named after its
 *   terms' type): the terms' type, the types it files (none for every
 *   type), whether writers may add a term as they type, whether an entry
 *   needs one to be published, and whether each term has a page listing
 *   what's filed under it.
 * - **Links to other entries** (a reference): the type that holds it,
 *   the type it points at, the front matter key, one or several, in
 *   order, and whether it's needed to publish.
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
import { useAction } from '../action';
import { keyOf } from '../type-form';
import { toast } from '../toast';
import { refreshTypes, types } from '../types';

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

type Purpose = 'classify' | 'reference';

const purpose  = ref<Purpose>('classify');
const terms    = ref('');
const from     = ref<string[]>([]);
const source   = ref('');
const target   = ref('');
const key      = ref('');
const create   = ref(true);
const required = ref(false);
const archive  = ref(true);
const multiple = ref(true);
const ordered  = ref(false);

const { busy, error, run } = useAction();

// Every type but profiles, which people fields credit (D-351).
const choices = computed<ContentTypeSummary[]>(() => types.value.filter((item) => item.kind !== 'profiles'));
const options = computed(() => choices.value.map((item) => ({ value: item.name, label: item.labels.plural })));
const labelOf = (name: string): string => types.value.find((item) => item.name === name)?.labels.plural ?? name;

// The types a new classify relation can file entries under: those nothing
// files under yet (one relation each, named after it); and the types it
// can file: any but the terms' own.
const termOptions = computed(() => props.relation !== null
	? options.value
	: [{ value: '', label: 'Choose a type' }, ...choices.value.filter((item) => !item.terms).map((item) => ({ value: item.name, label: item.labels.plural }))]);
const fileable    = computed(() => choices.value.filter((item) => item.name !== terms.value));
const ready       = computed(() => purpose.value === 'classify' ? terms.value !== '' : target.value !== '' && referenceKey.value !== '');

watch(() => [props.open, props.relation] as const, ([open, relation]) => {
	if (!open) {
		return;
	}

	error.value = '';

	if (relation !== null) {
		purpose.value  = relation.kind === 'classify' ? 'classify' : 'reference';
		terms.value    = relation.to[0] ?? '';
		from.value     = [...relation.from];
		source.value   = relation.from[0] ?? props.type;
		target.value   = relation.to[0] ?? '';
		key.value      = relation.field;
		create.value   = relation.create;
		required.value = relation.min > 0;
		archive.value  = relation.inverse !== false && relation.inverse.archive === true;
		multiple.value = relation.multiple;
		ordered.value  = relation.ordered;

		return;
	}

	// A new one, from this type's side.
	const here   = types.value.find((item) => item.name === props.type);
	const others = choices.value.filter((item) => item.name !== props.type);

	// A type of terms is already filed under by its relation, so what it
	// gains is usually a link; anything else, a set of terms.
	purpose.value  = here?.terms === true ? 'reference' : 'classify';
	terms.value    = '';
	from.value     = here?.terms === true ? [] : [props.type];
	source.value   = props.type;
	target.value   = others[0]?.name ?? '';
	key.value      = '';
	create.value   = true;
	required.value = false;
	archive.value  = true;
	multiple.value = true;
	ordered.value  = false;
}, { immediate: true });

function filed(name: string, on: boolean): void {
	from.value = on ? [...from.value, name] : from.value.filter((item) => item !== name);
}

// The key a reference is written under: the one typed, else one made
// from the target's plural name ("Actors" → `actors`).
const referenceKey = computed(() => keyOf(key.value) || keyOf(labelOf(target.value)));

/**
 * The definition to send: the relation's as written, with this form's
 * choices over it.
 */
function definition(): Record<string, unknown> {
	const base = props.relation?.definition ?? {};

	if (purpose.value === 'classify') {
		return {
			...base,
			name: terms.value,
			kind: 'classify',
			from: from.value,
			to: [terms.value],
			create: create.value,
			min: required.value ? 1 : 0,
			inverse: archive.value ? { ...(typeof base.inverse === 'object' && base.inverse !== null ? base.inverse : {}), archive: true } : false
		};
	}

	return {
		...base,
		name: props.relation?.name ?? referenceKey.value,
		kind: 'reference',
		from: [source.value],
		to: [target.value],
		multiple: multiple.value,
		ordered: multiple.value && ordered.value,
		min: required.value ? 1 : 0
	};
}

async function save(): Promise<void> {
	await run('The relationship couldn\'t be saved.', async () => {
		const sent = definition();
		const name = String(sent.name);

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
				<fieldset class="fieldset">
					<legend>Options</legend>
					<label class="checkbox"><input v-model="create" type="checkbox"> Writers can add a term as they type it</label>
					<label class="checkbox"><input v-model="required" type="checkbox"> An entry needs one to be published</label>
					<label class="checkbox"><input v-model="archive" type="checkbox"> Each term has a page listing what's filed under it</label>
				</fieldset>
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
				<div v-if="relation === null" class="field">
					<label for="relation-key">Key</label>
					<input id="relation-key" v-model="key" class="mono" :placeholder="keyOf(labelOf(target))" autocomplete="off" spellcheck="false" aria-describedby="relation-key-help">
					<p id="relation-key-help" class="field__help">Front matter names the entries under <code>{{ referenceKey || '…' }}</code>, by slug.</p>
				</div>
				<fieldset class="fieldset">
					<legend>Options</legend>
					<label class="checkbox"><input v-model="multiple" type="checkbox"> Several entries, not just one</label>
					<label v-if="multiple" class="checkbox"><input v-model="ordered" type="checkbox"> Their order matters</label>
					<label class="checkbox"><input v-model="required" type="checkbox"> An entry needs one to be published</label>
				</fieldset>
			</template>

			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
		</form>
		<template #footer>
			<button type="button" class="button" @click="emit('close')">Cancel</button>
			<button type="submit" form="relation-form" class="button button--primary" :disabled="busy || !ready">{{ relation === null ? 'Add Relationship' : 'Save Relationship' }}</button>
		</template>
	</AdminModal>
</template>
