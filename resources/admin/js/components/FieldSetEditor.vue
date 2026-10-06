<script setup lang="ts">
/**
 * A field set from `user/data/fields`, edited, or a new one (D-337):
 * General (label, key, help), Added To (the kind of place, D-347, then
 * the places of that kind it adds its fields to, and the slot it's in
 * when the kind offers more than one), and Fields (`FieldListEditor`),
 * created with **Create Field Set** (`POST fields/sets`), or, for an
 * existing set, saved together from the save bar (D-508) with **Save
 * changes** (`PATCH fields/sets/{name}`, only what changed) or put back
 * with **Revert**; leaving with changes unsaved asks first. An existing set
 * has a Danger Zone that deletes its file; entries keep their values for
 * its fields.
 *
 * A new set's key follows its label until it's typed, and is fixed once
 * the set exists: it's the file's name. A target the site doesn't have
 * (a type that's turned off, say) is kept, and shown as such.
 *
 * After a change, it asks the server to reindex with the new fields
 * (`POST types/refresh`) and loads the types again.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import DangerZone from './DangerZone.vue';
import FieldListEditor from './FieldListEditor.vue';
import SaveBar from './SaveBar.vue';
import { request, type FieldDescription, type FieldKindDescription, type FieldSetDetail, type FieldSetTargetOption } from '../api';
import { useAction } from '../action';
import { humanize } from '../fields';
import { toast } from '../toast';
import { copy, folderOf } from '../type-form';
import { refreshTypes, types } from '../types';

const props = defineProps<{
	// The set, or `null` for a new one.
	set: FieldSetDetail | null;
	// Every place a set can be added to, and each kind with its slots.
	options: FieldSetTargetOption[];
	kinds: FieldKindDescription[];
}>();

const emit = defineEmits<{ saved: [set: FieldSetDetail] }>();

interface SetForm {
	name: string;
	label: string;
	description: string;
	kind: string;
	slot: string;
	targets: string[];
	fields: FieldDescription[];
}

// A kind's default slot: its first.
function defaultSlot(kind: string): string {
	return props.kinds.find((item) => item.kind === kind)?.slots[0]?.name ?? '';
}

function formOf(set: FieldSetDetail | null): SetForm {
	const kind = set?.kind ?? props.kinds[0]?.kind ?? '';

	return {
		name: set?.name ?? '',
		label: set?.label ?? '',
		description: set?.description ?? '',
		kind,
		slot: set?.slot ?? defaultSlot(kind),
		targets: set?.targets.map((target) => target.key) ?? [],
		fields: copy(set?.fields ?? [])
	};
}

const router     = useRouter();
const form       = ref<SetForm>(formOf(props.set));
const initial    = ref<SetForm>(formOf(props.set));
const keyTouched = ref(props.set !== null);
const fresh      = computed(() => props.set === null);
// Set once the set is created or deleted, so leaving doesn't ask.
const done       = ref(false);

const { busy: saving, error: failure, run } = useAction();
const { error: removal, run: runRemoval }   = useAction();

watch(() => props.set, (set) => {
	form.value    = formOf(set);
	initial.value = formOf(set);
});

watch(() => form.value.label, (label) => {
	if (!keyTouched.value) {
		form.value.name = folderOf(label);
	}
});

// The label a set's name gives it, which the file leaves out.
const madeLabel = computed(() => humanize(form.value.name));

// What's sent: every key for a new set, only the changed ones for a set.
const changes = computed(() => {
	const all: Record<string, unknown> = {
		label: form.value.label.trim() === '' || form.value.label.trim() === madeLabel.value ? null : form.value.label.trim(),
		description: form.value.description.trim() === '' ? null : form.value.description.trim(),
		slot: form.value.slot === defaultSlot(form.value.kind) ? null : form.value.slot,
		targets: form.value.targets,
		fields: form.value.fields
	};

	if (fresh.value) {
		return all;
	}

	const before: Record<string, unknown> = {
		label: initial.value.label === humanize(initial.value.name) ? null : initial.value.label,
		description: initial.value.description === '' ? null : initial.value.description,
		slot: initial.value.slot === defaultSlot(initial.value.kind) ? null : initial.value.slot,
		targets: initial.value.targets,
		fields: initial.value.fields
	};

	return Object.fromEntries(Object.entries(all).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(before[key])));
});

// What an existing set's save bar counts: each setting changed.
const count   = computed(() => fresh.value ? 0 : Object.keys(changes.value).length);
const changed = computed(() => fresh.value
	? form.value.label.trim() !== '' || form.value.targets.length > 0 || form.value.fields.length > 0
	: count.value > 0);

const keyError = computed(() => {
	if (!fresh.value) {
		return '';
	}

	if (form.value.name === '') {
		return 'Give the set a key.';
	}

	return /^[a-z0-9][a-z0-9_-]*$/.test(form.value.name) ? '' : 'A key uses lowercase letters, digits, hyphens, and underscores, and starts with a letter or digit.';
});

// The kinds with places to add to, the chosen kind's places, and its
// slots (D-347). A set's targets are all one kind.
const kinds  = computed(() => props.kinds.filter((item) => props.options.some((option) => option.kind === item.kind)));
const places = computed(() => props.options.filter((option) => option.kind === form.value.kind));
const slots  = computed(() => props.kinds.find((item) => item.kind === form.value.kind)?.slots ?? []);

// Choosing another kind starts its places over, in its default slot.
function chooseKind(kind: string): void {
	form.value.kind    = kind;
	form.value.targets = form.value.targets.filter((target) => target.startsWith(`${kind}:`));
	form.value.slot    = defaultSlot(kind);
}

// Targets the site doesn't have, kept as they are.
const missing = computed(() => (props.set?.targets ?? []).filter((target) => !target.found && form.value.targets.includes(target.key)));

function toggle(key: string, on: boolean): void {
	const rest = form.value.targets.filter((target) => target !== key);

	form.value.targets = on ? [...rest, key] : rest;
}

async function save(): Promise<void> {
	if (!changed.value || saving.value || keyError.value !== '') {
		return;
	}

	await run('The field set couldn\'t be saved.', async () => {
		const saved = fresh.value
			? await request<FieldSetDetail>('POST', '/fields/sets', { name: form.value.name, set: changes.value })
			: await request<FieldSetDetail>('PATCH', `/fields/sets/${encodeURIComponent(props.set?.name ?? '')}`, { set: changes.value });

		refreshTypes();

		if (fresh.value) {
			done.value = true;
			toast(`Created ${saved.label}`);
			await router.replace({ name: 'field-set', params: { name: saved.name } });

			return;
		}

		emit('saved', saved);
		toast(`Saved ${saved.label}`);
	});
}

function revert(): void {
	form.value    = formOf(props.set);
	failure.value = '';
}

async function remove(): Promise<void> {
	const set = props.set;

	removal.value = '';

	if (set === null || !await confirmAction({ title: `Delete the ${set.label} Field Set?`, body: ['Its file in user/data/fields is removed, and its fields leave the types it\'s added to.', 'Entries keep their values in their files.'], confirm: 'Delete the field set', danger: true })) {
		return;
	}

	await runRemoval('The field set couldn\'t be deleted.', async () => {
		await request('DELETE', `/fields/sets/${encodeURIComponent(set.name)}`);
		done.value = true;
		refreshTypes();
		toast(`Deleted the ${set.label} field set`, { kind: 'danger' });
		await router.push({ name: 'fields' });
	});
}

guardLeave(() => !done.value && changed.value);
</script>

<template>
	<form class="form-stack" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">General</h2>
				<p v-if="set?.file" class="panel__hint">In <code>{{ set.file }}</code></p>
			</header>
			<div class="panel__body form-stack">
				<div class="field-pair set-editor__row">
					<div class="field">
						<label for="set-label">Label</label>
						<input id="set-label" v-model="form.label" placeholder="Search Engines" autocomplete="off" aria-describedby="set-label-help">
						<p id="set-label-help" class="field__help">Heads its fields in the editor.</p>
					</div>
					<div v-if="fresh" class="field">
						<label for="set-key">Key</label>
						<input id="set-key" v-model="form.name" class="mono" placeholder="search-engines" autocomplete="off" spellcheck="false" :aria-invalid="keyError !== '' && form.label !== '' ? 'true' : undefined" :aria-describedby="keyError && form.label !== '' ? 'set-key-error' : 'set-key-help'" @input="keyTouched = true">
						<p v-if="keyError && form.label !== ''" id="set-key-error" class="field__error">{{ keyError }}</p>
						<p v-else id="set-key-help" class="field__help">Its file's name in <code>user/data/fields</code>. Fixed once it's created.</p>
					</div>
					<dl v-else class="facts facts--inline set-editor__facts">
						<div><dt>Key</dt><dd class="mono">{{ set?.name }}</dd></div>
					</dl>
				</div>
				<div class="field">
					<label for="set-description">Help</label>
					<input id="set-description" v-model="form.description" autocomplete="off" placeholder="Shown under the label in the editor">
				</div>
			</div>
		</section>

		<section class="panel" aria-labelledby="targets-heading">
			<header class="panel__header">
				<h2 id="targets-heading">Added To</h2>
				<p class="panel__hint">The places that get these fields</p>
			</header>
			<div class="panel__body">
				<fieldset v-if="kinds.length > 1" class="fieldset set-editor__group">
					<legend>Kind of place</legend>
					<div class="set-editor__targets">
						<label v-for="item in kinds" :key="item.kind" class="checkbox">
							<input type="radio" name="set-kind" :value="item.kind" :checked="form.kind === item.kind" @change="chooseKind(item.kind)">
							{{ item.label }}
						</label>
					</div>
					<p class="field__help">A set's places are all one kind: a field means one thing on an entry, another in the site's settings.</p>
				</fieldset>
				<fieldset class="fieldset set-editor__group">
					<legend>{{ kinds.find((item) => item.kind === form.kind)?.label ?? 'Places' }}</legend>
					<div class="set-editor__targets">
						<label v-for="option in places" :key="option.key" class="checkbox">
							<input type="checkbox" :checked="form.targets.includes(option.key)" @change="toggle(option.key, ($event.target as HTMLInputElement).checked)">
							{{ option.label }}
						</label>
					</div>
				</fieldset>
				<fieldset v-if="missing.length" class="fieldset set-editor__group">
					<legend>Not on this site</legend>
					<div class="set-editor__targets">
						<label v-for="target in missing" :key="target.key" class="checkbox">
							<input type="checkbox" checked @change="toggle(target.key, ($event.target as HTMLInputElement).checked)">
							<span class="mono">{{ target.key }}</span>
						</label>
					</div>
					<p class="field__help">Kept for when the site has them, such as a type that's turned off.</p>
				</fieldset>
				<fieldset v-if="slots.length > 1" class="fieldset set-editor__group set-editor__slots">
					<legend>These fields are</legend>
					<label v-for="slot in slots" :key="slot.name" class="checkbox set-editor__slot">
						<input v-model="form.slot" type="radio" name="set-slot" :value="slot.name">
						<span><strong>{{ slot.label }}</strong> <span class="field__help">{{ slot.description }}</span></span>
					</label>
				</fieldset>
				<p class="field__help">A place's own fields come first, then each set's. A set can't use a field name a place it's added to already has.</p>
			</div>
		</section>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
			</header>
			<FieldListEditor v-model="form.fields" :types="types" id-prefix="field-" />
		</section>

		<div v-if="fresh" class="submit-row submit-row--tight">
			<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
			<button type="submit" class="button button--primary" :disabled="!changed || saving || keyError !== ''">{{ saving ? 'Saving…' : 'Create Field Set' }}</button>
		</div>

		<DangerZone v-if="set" :error="removal">
			Removes its file, and its fields from the types it's added to. Entries keep their values, listed as other front matter.
			<template #action><button type="button" class="button button--danger" @click="remove"><AdminIcon name="x" />Delete this field set</button></template>
		</DangerZone>

		<SaveBar v-if="!fresh" :count="count" :failure="failure" :saving="saving" @revert="revert" />
	</form>
</template>

<style scoped>
.set-editor__row {
	align-items: start;
}

/* Down to sit level with the field beside it. */
.set-editor__facts {
	padding-top: 26px;
}

.set-editor__slot {
	align-items: baseline;
}

.set-editor__group {
	min-width: 0;
	margin-bottom: var(--s-4);
}

.set-editor__targets {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
	gap: var(--s-2) var(--s-4);
}
</style>
