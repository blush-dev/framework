<script setup lang="ts">
/**
 * A field set's settings (the fields sketch's Settings dialog, D-766),
 * and a new set's (New Field Set): its label, its key (a new set's
 * follows the label until it's typed, and is fixed once the set exists:
 * it's the file's name), its help, the content types it adds its fields
 * to (D-767: entries only), and its position among a type's sets in the
 * editor's panel (D-768). A target that isn't a content type on this
 * site (one turned off) is kept and shown as such.
 *
 * A set's changes are kept with its fields and saved with **Update**; a
 * new set is created with **Create**.
 */

import { computed, ref, watch } from 'vue';
import type { FieldSetTargetOption } from '../api';
import { folderOf } from '../type-form';
import AdminModal from './AdminModal.vue';

export interface FieldSetSettingsValue {
	name: string;
	label: string;
	description: string;
	targets: string[];
	position: number;
}

const props = defineProps<{
	value: FieldSetSettingsValue;
	// Whether it's a new set, which names its key.
	fresh?: boolean;
	// The content types a set can be added to.
	options: FieldSetTargetOption[];
	// The sets' keys, which a new one can't take.
	names: string[];
	busy?: boolean;
	error?: string;
}>();

const emit = defineEmits<{ done: [value: FieldSetSettingsValue]; close: [] }>();

const form  = ref<FieldSetSettingsValue>({ ...props.value, targets: [...props.value.targets] });
const keyed = ref(!props.fresh);
const tried = ref(false);

watch(() => form.value.label, (label) => {
	if (!keyed.value) {
		form.value.name = folderOf(label);
	}
});

const types   = computed(() => props.options.filter((option) => option.kind === 'type'));
const missing = computed(() => form.value.targets.filter((target) => !types.value.some((option) => option.key === target)));

const keyError = computed(() => {
	if (!props.fresh) {
		return '';
	}

	if (form.value.name === '') {
		return 'Give the set a key.';
	}

	if (!/^[a-z0-9][a-z0-9_-]*$/.test(form.value.name)) {
		return 'A key uses lowercase letters, digits, hyphens, and underscores, and starts with a letter or digit.';
	}

	return props.names.includes(form.value.name) ? `Another set already uses “${form.value.name}”.` : '';
});

function toggle(key: string, on: boolean): void {
	const rest = form.value.targets.filter((target) => target !== key);

	form.value.targets = on ? [...rest, key] : rest;
}

function submit(): void {
	tried.value = true;

	if (form.value.label.trim() === '' || keyError.value !== '') {
		return;
	}

	emit('done', { ...form.value, label: form.value.label.trim(), description: form.value.description.trim() });
}
</script>

<template>
	<AdminModal open :title="fresh ? 'New Field Set' : 'Field Set Settings'" @close="emit('close')">
		<form id="field-set-settings" class="form-stack" novalidate @submit.prevent="submit">
			<p v-if="!fresh" class="field__help">Saved with the fields when you press Update.</p>

			<div class="field">
				<label for="field-set-label">Label</label>
				<input id="field-set-label" v-model="form.label" autocomplete="off" autofocus placeholder="Recipe Details" :aria-invalid="tried && form.label.trim() === '' ? 'true' : undefined" aria-describedby="field-set-label-help">
				<p v-if="tried && form.label.trim() === ''" id="field-set-label-help" class="field__error">Give the set a label.</p>
				<p v-else id="field-set-label-help" class="field__help">Heads its fields in the editor.</p>
			</div>

			<div class="field">
				<label for="field-set-key">Key</label>
				<input v-if="fresh" id="field-set-key" v-model="form.name" class="mono" autocomplete="off" spellcheck="false" placeholder="recipe-details" :aria-invalid="tried && keyError !== '' ? 'true' : undefined" aria-describedby="field-set-key-help" @input="keyed = true">
				<p v-else id="field-set-key" class="mono">{{ form.name }}</p>
				<p v-if="tried && keyError" id="field-set-key-help" class="field__error">{{ keyError }}</p>
				<p v-else id="field-set-key-help" class="field__help">Its file's name in <code>user/data/fields</code>{{ fresh ? '. Fixed once it\'s created.' : '.' }}</p>
			</div>

			<div class="field">
				<label for="field-set-help">Help</label>
				<input id="field-set-help" v-model="form.description" autocomplete="off" aria-describedby="field-set-help-help">
				<p id="field-set-help-help" class="field__help">Shown under the label in the editor.</p>
			</div>

			<fieldset class="fieldset">
				<legend>Used on</legend>
				<div class="field-set-settings__types">
					<label v-for="option in types" :key="option.key" class="checkbox">
						<input type="checkbox" :checked="form.targets.includes(option.key)" @change="toggle(option.key, ($event.target as HTMLInputElement).checked)">
						{{ option.label }}
					</label>
					<label v-for="target in missing" :key="target" class="checkbox">
						<input type="checkbox" checked @change="toggle(target, ($event.target as HTMLInputElement).checked)">
						<span><span class="mono">{{ target }}</span> <span class="field__help">(not on this site)</span></span>
					</label>
				</div>
				<p class="field__help">Its fields are added to every entry of these types. A set can't use a key one of them already has.</p>
			</fieldset>

			<div class="field">
				<label for="field-set-position">Position</label>
				<input id="field-set-position" v-model.number="form.position" type="number" step="1" class="field-set-settings__position" aria-describedby="field-set-position-help">
				<p id="field-set-position-help" class="field__help">Where it sits among a type's sets in the editor's panel: lowest first, then by label.</p>
			</div>

			<p v-if="error" class="field__error" role="alert">{{ error }}</p>
		</form>

		<template #footer>
			<button type="button" class="button" @click="emit('close')">Cancel</button>
			<button type="submit" form="field-set-settings" class="button button--primary" :disabled="busy">{{ busy ? 'Creating…' : (fresh ? 'Create' : 'Done') }}</button>
		</template>
	</AdminModal>
</template>

<style scoped>
.field-set-settings__types {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr));
	gap: var(--s-2) var(--s-4);
}

.field-set-settings__position {
	max-width: 8rem;
}
</style>
