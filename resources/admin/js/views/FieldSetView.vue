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
import { t } from '../messages';
import MessageText from '../components/MessageText.vue';
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
		error.value = errorMessage(caught, t('field_set_view.load_failed', 'The field set couldn\'t be loaded.'));
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
const noun = computed(() => typeNames.value.length === 1 ? (typeNames.value[0] ?? t('field_set_view.entries', 'entries')).toLowerCase() : t('field_set_view.entries_of_types', 'entries of its types'));

// What a save says, with the values it moved along with fields put
// somewhere new (D-780), and those it couldn't.
function savedMessage(saved: FieldSetDetail): string {
	const moved  = saved.moved;
	const failed = Object.keys(moved?.failed ?? {}).length;
	const parts  = [t('field_set_view.saved', 'Saved {label}', { label: saved.label })];

	if (moved !== undefined && moved.entries > 0) {
		parts.push(t('field_set_view.moved', 'Moved values in {count, plural, one {# entry} other {# entries}}', { count: moved.entries }));
	}

	if (moved !== undefined && moved.kept > 0) {
		parts.push(t('field_set_view.kept', '{count, plural, one {# entry} other {# entries}} already had a value at the new place, so the old one stays', { count: moved.kept }));
	}

	if (failed > 0) {
		parts.push(t('field_set_view.failed', '{count, plural, one {# entry} other {# entries}} couldn\'t be changed: {reason}', { count: failed, reason: Object.values(moved?.failed ?? {})[0] }));
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
		failure.value = errorMessage(caught, t('field_set_view.save_failed', 'The field set couldn\'t be saved.'));
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	if (set.value !== null) {
		show(set.value);
		toast(t('field_set_view.reverted', 'Changes reverted'), { kind: 'info' });
	}
}

async function remove(): Promise<void> {
	const detail = set.value;

	if (detail === null || !await confirmAction({ title: t('field_set_view.delete_title', 'Delete {label}?', { label: detail.label }), body: [t('field_set_view.delete_body', 'Its file in user/data/fields is removed, and its fields leave the types it\'s used on.'), t('field_set_view.delete_values', 'Entries keep their values in their files.')], confirm: t('field_set_view.delete_confirm', 'Delete Field Set'), danger: true })) {
		return;
	}

	try {
		await request('DELETE', `/fields/sets/${encodeURIComponent(detail.name)}`);
		leaving = true;
		refreshTypes();
		toast(t('field_set_view.deleted', 'Deleted {label}', { label: detail.label }), { kind: 'danger' });
		await router.push({ name: 'fields' });
	} catch (caught) {
		failure.value = errorMessage(caught, t('field_set_view.delete_failed', 'The field set couldn\'t be deleted.'));
	}
}

function applySettings(value: FieldSetSettingsValue): void {
	settled.value  = value;
	settings.value = false;
}

guardLeave(() => !leaving && set.value?.editable === true && changed.value, () => confirmLeave(t('field_set_view.leave', 'The fields and settings you changed haven\'t been saved.')));
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'fields' }"><AdminIcon name="chevron-left" />{{ t('field_set_view.back', 'All field sets') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ settled?.label ?? t('field_set_view.title', 'Field Set') }}</h1>
			<div v-if="set && settled" class="page-facts">
				<span class="page-facts__item"><AdminIcon name="group" /><span class="mono">{{ set.name }}</span></span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">{{ t('field_set_view.fields', '{count, plural, one {# field} other {# fields} }', { count: fields.length }) }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon name="file-text" />{{ typeNames.length ? typeNames.join(', ') : t('field_set_view.no_types', 'No types') }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><MessageText :text="t('field_set_view.position', 'Position {position}')"><template #position><span class="mono">{{ settled.position }}</span></template></MessageText></span>
				<template v-if="!set.editable">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item"><AdminIcon name="lock" />{{ t('field_set_view.read_only', 'Read only') }}</span>
				</template>
			</div>
		</div>
		<div v-if="set?.editable" class="page-header__actions">
			<button type="button" class="button" aria-haspopup="dialog" @click="settings = true"><AdminIcon name="settings" />{{ t('field_set_view.settings', 'Settings') }}</button>
			<button type="button" class="button button--primary" :disabled="!changed || saving" @click="save">{{ saving ? t('field_set_view.saving', 'Saving…') : (changed ? t('field_set_view.update', 'Update') : t('field_set_view.saved_button', 'Saved')) }}</button>
			<MenuButton button-class="button button--icon" :label="t('field_set_view.more', 'More actions')" align="end">
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<button type="button" class="menu-item" :disabled="!changed" @click="revert"><AdminIcon name="undo-2" />{{ t('field_set_view.revert', 'Revert changes') }}</button>
				<div class="menu-divider" />
				<button type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('field_set_view.delete', 'Delete field set') }}</button>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
	<p v-for="problem in set?.problems ?? []" :key="problem" class="notice notice--warn"><span>{{ t('field_set_view.problem', '{problem} It\'s left off there until one of them is renamed.', { problem }) }}</span></p>

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
			<p class="panel__note"><AdminIcon name="lock" /> <MessageText :text="t('field_set_view.locked', '{config} turns off sets in {folder}, so its fields can\'t be changed here.')"><template #config><code>config/fields.php</code></template><template #folder><code>user/data/fields</code></template></MessageText></p>
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
