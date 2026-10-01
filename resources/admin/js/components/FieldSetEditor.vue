<script setup lang="ts">
/**
 * A field set from `user/data/fields`, edited, or a new one (D-337):
 * General (label, key, help), Added To (the content types it adds its
 * fields to), and Fields (`FieldListEditor`), saved together with
 * **Save** (`PATCH fields/sets/{name}`, only what changed) or **Create
 * Field Set** (`POST fields/sets`), or put back with **Revert**; leaving
 * with changes unsaved asks first. An existing set has a Danger Zone that
 * deletes its file; entries keep their values for its fields.
 *
 * A new set's key follows its label until it's typed, and is fixed once
 * the set exists: it's the file's name. A target that isn't a content
 * type on this site (one turned off, say) is kept, and shown as such.
 *
 * After a change, it asks the server to reindex with the new fields
 * (`POST types/refresh`) and loads the types again.
 */

import { computed, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRouter } from 'vue-router';
import AdminIcon from './AdminIcon.vue';
import FieldListEditor from './FieldListEditor.vue';
import { ApiError, request, type FieldDescription, type FieldSetDetail, type FieldSetTargetOption } from '../api';
import { humanize } from '../fields';
import { toast } from '../toast';
import { copy, folderOf } from '../type-form';
import { reloadTypes, types } from '../types';

const props = defineProps<{
	// The set, or `null` for a new one.
	set: FieldSetDetail | null;
	// Every place a set can be added to.
	options: FieldSetTargetOption[];
}>();

const emit = defineEmits<{ saved: [set: FieldSetDetail] }>();

interface SetForm {
	name: string;
	label: string;
	description: string;
	targets: string[];
	fields: FieldDescription[];
}

function formOf(set: FieldSetDetail | null): SetForm {
	return {
		name: set?.name ?? '',
		label: set?.label ?? '',
		description: set?.description ?? '',
		targets: set?.targets.map((target) => target.key) ?? [],
		fields: copy(set?.fields ?? [])
	};
}

const router     = useRouter();
const form       = ref<SetForm>(formOf(props.set));
const initial    = ref<SetForm>(formOf(props.set));
const keyTouched = ref(props.set !== null);
const saving     = ref(false);
const failure    = ref('');
const removal    = ref('');
const fresh      = computed(() => props.set === null);
// Set once the set is created or deleted, so leaving doesn't ask.
const done       = ref(false);

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
		targets: form.value.targets,
		fields: form.value.fields
	};

	if (fresh.value) {
		return all;
	}

	const before: Record<string, unknown> = {
		label: initial.value.label === humanize(initial.value.name) ? null : initial.value.label,
		description: initial.value.description === '' ? null : initial.value.description,
		targets: initial.value.targets,
		fields: initial.value.fields
	};

	return Object.fromEntries(Object.entries(all).filter(([key, value]) => JSON.stringify(value) !== JSON.stringify(before[key])));
});

const changed = computed(() => fresh.value
	? form.value.label.trim() !== '' || form.value.targets.length > 0 || form.value.fields.length > 0
	: Object.keys(changes.value).length > 0);

const keyError = computed(() => {
	if (!fresh.value) {
		return '';
	}

	if (form.value.name === '') {
		return 'Give the set a key.';
	}

	return /^[a-z0-9][a-z0-9_-]*$/.test(form.value.name) ? '' : 'A key uses lowercase letters, digits, hyphens, and underscores, and starts with a letter or digit.';
});

// Targets the site doesn't have, kept as they are.
const missing = computed(() => (props.set?.targets ?? []).filter((target) => !target.found && form.value.targets.includes(target.key)));

function toggle(key: string, on: boolean): void {
	const rest = form.value.targets.filter((target) => target !== key);

	form.value.targets = on ? [...rest, key] : rest;
}

// After a change: reindex, then the navigation.
function refresh(): void {
	request('POST', '/types/refresh').catch(() => undefined).finally(() => {
		reloadTypes().catch(() => undefined);
	});
}

async function save(): Promise<void> {
	if (!changed.value || saving.value || keyError.value !== '') {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const saved = fresh.value
			? await request<FieldSetDetail>('POST', '/fields/sets', { name: form.value.name, set: changes.value })
			: await request<FieldSetDetail>('PATCH', `/fields/sets/${encodeURIComponent(props.set?.name ?? '')}`, { set: changes.value });

		refresh();

		if (fresh.value) {
			done.value = true;
			toast(`Created ${saved.label}`);
			await router.replace({ name: 'field-set', params: { name: saved.name } });

			return;
		}

		emit('saved', saved);
		toast(`Saved ${saved.label}`);
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'The field set couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	form.value    = formOf(props.set);
	failure.value = '';
}

async function remove(): Promise<void> {
	const set = props.set;

	removal.value = '';

	if (set === null || !window.confirm(`Delete the ${set.label} field set? Its file in user/data/fields is removed, and its fields leave the types it's added to. Entries keep their values in their files.`)) {
		return;
	}

	try {
		await request('DELETE', `/fields/sets/${encodeURIComponent(set.name)}`);
		done.value = true;
		refresh();
		toast(`Deleted the ${set.label} field set`);
		await router.push({ name: 'fields' });
	} catch (caught) {
		removal.value = caught instanceof ApiError ? caught.message : 'The field set couldn\'t be deleted.';
	}
}

onBeforeRouteLeave(() => done.value || !changed.value || window.confirm('Leave without saving? Your changes will be lost.'));
</script>

<template>
	<form class="set-editor" @submit.prevent="save">
		<section class="panel" aria-labelledby="general-heading">
			<header class="panel__header">
				<h2 id="general-heading">General</h2>
				<p v-if="set?.file" class="panel__hint">In <code>{{ set.file }}</code></p>
			</header>
			<div class="panel__body set-editor__body">
				<div class="set-editor__row">
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
					<dl v-else class="set-editor__facts">
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
				<p class="panel__hint">The content types that get these fields</p>
			</header>
			<div class="panel__body">
				<fieldset class="set-editor__targets" aria-labelledby="targets-heading">
					<label v-for="option in options" :key="option.key" class="checkbox">
						<input type="checkbox" :checked="form.targets.includes(option.key)" @change="toggle(option.key, ($event.target as HTMLInputElement).checked)">
						{{ option.label }}
					</label>
					<label v-for="target in missing" :key="target.key" class="checkbox">
						<input type="checkbox" checked @change="toggle(target.key, ($event.target as HTMLInputElement).checked)">
						<span class="mono">{{ target.key }}</span> <span class="field__help">Not a content type on this site; kept for when it is</span>
					</label>
				</fieldset>
				<p class="field__help">A type's own fields come first, then each set's. A set can't use a field name a type it's added to already has.</p>
			</div>
		</section>

		<section class="panel" aria-labelledby="fields-heading">
			<header class="panel__header">
				<h2 id="fields-heading">Fields</h2>
			</header>
			<FieldListEditor v-model="form.fields" :types="types" id-prefix="field-" />
		</section>

		<div class="set-editor__save">
			<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
			<button type="submit" class="button button--primary" :disabled="!changed || saving || keyError !== ''">{{ saving ? 'Saving…' : (fresh ? 'Create Field Set' : 'Save') }}</button>
			<button v-if="changed && !fresh" type="button" class="button button--ghost" :disabled="saving" @click="revert">Revert</button>
		</div>

		<section v-if="set" class="panel" aria-labelledby="danger-heading">
			<header class="panel__header">
				<h2 id="danger-heading">Danger Zone</h2>
			</header>
			<div class="panel__body set-editor__danger">
				<button type="button" class="button button--danger button--small" @click="remove"><AdminIcon name="x" />Delete this field set</button>
				<p v-if="removal" class="field__error" role="alert">{{ removal }}</p>
				<p class="field__help">Removes its file, and its fields from the types it's added to. Entries keep their values, listed as other front matter.</p>
			</div>
		</section>
	</form>
</template>

<style scoped>
.set-editor {
	display: grid;
	gap: var(--s-4);
}

.set-editor__body {
	display: grid;
	gap: var(--s-4);
}

.set-editor__body > * + * {
	margin-top: 0;
}

.set-editor__row {
	display: grid;
	grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.set-editor__facts {
	display: flex;
	gap: 8px;
	margin: 0;
	padding-top: 26px;
}

.set-editor__facts div {
	display: flex;
	gap: 8px;
}

.set-editor__facts dt {
	color: var(--fg-2);
}

.set-editor__facts dd {
	margin: 0;
}

.set-editor__targets {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
	gap: var(--s-2) var(--s-4);
	margin: 0 0 var(--s-3);
	padding: 0;
	border: 0;
}

.set-editor__save {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-2);
}

.set-editor__save .field__error {
	flex-basis: 100%;
	margin: 0;
}

.set-editor__danger {
	display: grid;
	justify-items: start;
	gap: var(--s-2);
}

@media (width <= 760px) {
	.set-editor__row {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
