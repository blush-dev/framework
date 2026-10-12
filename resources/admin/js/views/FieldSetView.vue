<script setup lang="ts">
/**
 * One field set (the fields sketch, D-766), in the frame of the admin's
 * other detail screens (Menus): a back link, the title with its facts
 * under it (key, fields, the types it's used on, its position, where
 * it's from), and the actions at the right: Settings
 * (`FieldSetSettings`), the save button, and a menu with Revert and
 * Delete. Below is the field tree (`FieldTree`).
 *
 * Everything is saved together by the one button: **Update** once
 * something's changed (only what changed is sent), **Saved** when
 * nothing has. Leaving with changes asks first. A set from code (config
 * or a plugin) is shown, its fields opened only to read them.
 *
 * After a save, the server reindexes with the new fields
 * (`refreshTypes`).
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import FieldSetSettings, { type FieldSetSettingsValue } from '../components/FieldSetSettings.vue';
import FieldTree from '../components/FieldTree.vue';
import MenuButton from '../components/MenuButton.vue';
import { errorMessage, request, type FieldDescription, type FieldSetDetail } from '../api';
import { confirmAction, confirmLeave, guardLeave } from '../confirm';
import { humanize } from '../fields';
import { loadFieldTypes } from '../field-types';
import { plural } from '../format';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { copy } from '../type-form';
import { refreshTypes } from '../types';

const route  = useRoute();
const router = useRouter();

const set      = ref<FieldSetDetail | null>(null);
const error    = ref('');
const failure  = ref('');
const saving   = ref(false);
const settings = ref(false);
const settled  = ref<FieldSetSettingsValue | null>(null);
const fields   = ref<FieldDescription[]>([]);
let leaving    = false;

void loadFieldTypes().catch(() => undefined);

function settingsOf(detail: FieldSetDetail): FieldSetSettingsValue {
	return { name: detail.name, label: detail.label, description: detail.description, targets: detail.targets.map((target) => target.key), position: detail.position };
}

function show(detail: FieldSetDetail): void {
	set.value     = detail;
	settled.value = settingsOf(detail);
	fields.value  = copy(detail.fields);
	failure.value = '';
}

watch(() => route.params.name, async (name) => {
	if (name === undefined) {
		return;
	}

	set.value   = null;
	error.value = '';

	try {
		const detail = await request<FieldSetDetail>('GET', `/fields/sets/${encodeURIComponent(String(name))}`);

		show(detail);
		settings.value = route.query.settings === '1' && detail.editable;
	} catch (caught) {
		error.value = errorMessage(caught, 'The field set couldn\'t be loaded.');
	}
}, { immediate: true });

watch(() => settled.value?.label, (label) => {
	screenTitle.value = label ?? null;
});

// What's sent: only the keys that changed.
const changes = computed(() => {
	const detail = set.value;
	const now    = settled.value;

	if (detail === null || now === null) {
		return {};
	}

	const before = settingsOf(detail);
	const out: Record<string, unknown> = {};

	if (now.label !== before.label) {
		out.label = now.label === '' || now.label === humanize(detail.name) ? null : now.label;
	}

	if (now.description !== before.description) {
		out.description = now.description === '' ? null : now.description;
	}

	if (JSON.stringify(now.targets) !== JSON.stringify(before.targets)) {
		out.targets = now.targets;
	}

	if (now.position !== before.position) {
		out.position = now.position === 10 ? null : now.position;
	}

	if (JSON.stringify(fields.value) !== JSON.stringify(detail.fields)) {
		out.fields = fields.value;
	}

	return out;
});

const changed = computed(() => Object.keys(changes.value).length > 0);

const typeNames = computed(() => {
	const targets = settled.value?.targets ?? [];

	return targets.map((key) => set.value?.targets.find((target) => target.key === key)?.label ?? set.value?.options.find((option) => option.key === key)?.label ?? key);
});


// The noun an empty tree uses: what the set is added to.
const noun = computed(() => typeNames.value.length === 1 ? (typeNames.value[0] ?? 'entries').toLowerCase() : 'entries of its types');

// What a save says, with the values it moved along with fields put
// somewhere new (D-780), and those it couldn't.
function savedMessage(saved: FieldSetDetail): string {
	const moved  = saved.moved;
	const failed = Object.keys(moved?.failed ?? {}).length;
	const parts  = [`Saved ${saved.label}`];

	if (moved !== undefined && moved.entries > 0) {
		parts.push(`Moved values in ${plural(moved.entries, 'entry', 'entries')}`);
	}

	if (moved !== undefined && moved.kept > 0) {
		parts.push(`${plural(moved.kept, 'entry', 'entries')} already had a value at the new place, so the old one stays`);
	}

	if (failed > 0) {
		parts.push(`${plural(failed, 'entry', 'entries')} couldn't be changed: ${Object.values(moved?.failed ?? {})[0]}`);
	}

	return parts.join('. ');
}

async function save(): Promise<void> {
	const detail = set.value;

	if (detail === null || !changed.value || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const saved = await request<FieldSetDetail>('PATCH', `/fields/sets/${encodeURIComponent(detail.name)}`, { set: changes.value });

		refreshTypes();
		show(saved);
		toast(savedMessage(saved), { kind: Object.keys(saved.moved?.failed ?? {}).length > 0 || (saved.moved?.kept ?? 0) > 0 ? 'warn' : undefined });
	} catch (caught) {
		failure.value = errorMessage(caught, 'The field set couldn\'t be saved.');
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	if (set.value !== null) {
		show(set.value);
		toast('Changes reverted', { kind: 'info' });
	}
}

async function remove(): Promise<void> {
	const detail = set.value;

	if (detail === null || !await confirmAction({ title: `Delete ${detail.label}?`, body: ['Its file in user/data/fields is removed, and its fields leave the types it\'s used on.', 'Entries keep their values in their files.'], confirm: 'Delete Field Set', danger: true })) {
		return;
	}

	try {
		await request('DELETE', `/fields/sets/${encodeURIComponent(detail.name)}`);
		leaving = true;
		refreshTypes();
		toast(`Deleted ${detail.label}`, { kind: 'danger' });
		await router.push({ name: 'fields' });
	} catch (caught) {
		failure.value = errorMessage(caught, 'The field set couldn\'t be deleted.');
	}
}

function applySettings(value: FieldSetSettingsValue): void {
	settled.value  = value;
	settings.value = false;
}

guardLeave(() => !leaving && set.value?.editable === true && changed.value, () => confirmLeave('The fields and settings you changed haven\'t been saved.'));
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'fields' }"><AdminIcon name="chevron-left" />All field sets</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ settled?.label ?? 'Field Set' }}</h1>
			<div v-if="set && settled" class="page-facts">
				<span class="page-facts__item"><AdminIcon name="group" /><span class="mono">{{ set.name }}</span></span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">{{ plural(fields.length, 'field') }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon name="file-text" />{{ typeNames.length ? typeNames.join(', ') : 'No types' }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">Position <span class="mono">{{ settled.position }}</span></span>
				<template v-if="!set.editable">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item"><AdminIcon name="lock" />Read only</span>
				</template>
			</div>
		</div>
		<div v-if="set?.editable" class="page-header__actions">
			<button type="button" class="button" aria-haspopup="dialog" @click="settings = true"><AdminIcon name="settings" />Settings</button>
			<button type="button" class="button button--primary" :disabled="!changed || saving" @click="save">{{ saving ? 'Saving…' : (changed ? 'Update' : 'Saved') }}</button>
			<MenuButton button-class="button button--icon" label="More actions" align="end">
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<button type="button" class="menu-item" :disabled="!changed" @click="revert"><AdminIcon name="undo-2" />Revert changes</button>
				<div class="menu-divider" />
				<button type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />Delete field set</button>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
	<p v-for="problem in set?.problems ?? []" :key="problem" class="notice notice--warn"><span>{{ problem }} It's left off there until one of them is renamed.</span></p>

	<FieldTree
		v-if="set"
		v-model="fields"
		heading-id="set-fields-heading"
		:hint="set.description"
		id-prefix="set-field-"
		:noun="noun"
		:locked="!set.editable"
		:counts="set.counts"
	>
		<template v-if="!set.editable" #notice>
			<p class="panel__note"><AdminIcon name="lock" /> <code>config/fields.php</code> turns off sets in <code>user/data/fields</code>, so its fields can't be changed here.</p>
		</template>
	</FieldTree>

	<div v-else-if="!error" class="panel" aria-hidden="true"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>

	<FieldSetSettings
		v-if="settings && set && settled"
		:value="settled"
		:options="set.options"
		:names="[]"
		@done="applySettings"
		@close="settings = false"
	/>
</template>
