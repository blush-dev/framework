<script setup lang="ts">
/**
 * One field of the field library (D-858), in the frame of the admin's
 * other detail screens: a back link, the title with its facts under it
 * (its key, its control, and the types that have it, each a link to its
 * screen, where a type chooses its fields), and the actions at the right:
 * the save button, and a menu with Revert and Delete. Below is the
 * field's form (`LibraryFieldForm`); an Object's fields are a tree in
 * it. Its key is fixed (D-859).
 *
 * Everything is saved together by the one button: **Update** once
 * something's changed, **Saved** when nothing has. Leaving with changes
 * asks first. While `config/fields.php` turns the library off, the
 * field is only shown.
 *
 * After a save, the server reindexes with the new field
 * (`refreshTypes`), and moves the values of an Object's fields that went
 * somewhere new (D-780).
 *
 * **New Field** is this screen too (`/fields/new`): the control first
 * (`ControlPicker`, unless the address names one), then the field,
 * made with **Create** (`POST fields/library`). Its key follows its
 * label until it's typed, and can't be one the library has. Made from a
 * type's screen (`?type=`), it goes back there with the field added to
 * the type's layout, to save with the type's other changes; otherwise
 * it opens the field.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import ControlPicker from '../components/ControlPicker.vue';
import LibraryFieldForm from '../components/LibraryFieldForm.vue';
import MenuButton from '../components/MenuButton.vue';
import MessageText from '../components/MessageText.vue';
import { errorMessage, request, type FieldDescription, type LibraryFieldDetail, type LibraryFieldList } from '../api';
import { confirmAction, confirmLeave, guardLeave } from '../confirm';
import { blankField, controlIcon, controlLabel } from '../controls';
import { humanize } from '../fields';
import { loadFieldTypes } from '../field-types';
import { t } from '../messages';
import { screenTitle } from '../screen';
import { toast } from '../toast';
import { copy } from '../type-form';
import { refreshTypes } from '../types';

const route  = useRoute();
const router = useRouter();

const detail  = ref<LibraryFieldDetail | null>(null);
const field   = ref<FieldDescription | null>(null);
const form    = ref<{ check: () => boolean } | null>(null);
const error   = ref('');
const failure = ref('');
const saving  = ref(false);
let leaving   = false;

// A new field: whether its control is being chosen, and the library's
// keys, which it can't take.
const fresh   = computed(() => route.name === 'field-new');
const picking = ref(false);
const taken   = ref<Record<string, string>>({});

void loadFieldTypes().catch(() => undefined);

function show(value: LibraryFieldDetail): void {
	detail.value  = value;
	field.value   = copy(value.field);
	failure.value = '';
}

watch(() => route.params.name, async (name) => {
	if (fresh.value) {
		await start();

		return;
	}

	if (name === undefined) {
		return;
	}

	detail.value = null;
	field.value  = null;
	error.value  = '';

	try {
		show(await request<LibraryFieldDetail>('GET', `/fields/library/${encodeURIComponent(String(name))}`));
	} catch (caught) {
		error.value = errorMessage(caught, t('field_view.load_failed', 'The field couldn\'t be loaded.'));
	}
}, { immediate: true });

// A new field starts from the control the address names, else the one
// chosen first.
async function start(): Promise<void> {
	detail.value = null;
	error.value  = '';

	const control = typeof route.query.control === 'string' ? route.query.control : '';

	field.value   = control === '' ? null : blankField(control);
	picking.value = control === '';

	try {
		const list = await request<LibraryFieldList>('GET', '/fields/library');

		taken.value = Object.fromEntries([...list.fields.map((item) => item.name), 'new'].map((name) => [name, t('field_view.the_library', 'The field library')]));
	} catch (caught) {
		error.value = errorMessage(caught, t('field_view.load_failed', 'The field couldn\'t be loaded.'));
	}
}

function chose(key: string): void {
	picking.value = false;
	field.value   = blankField(key);
}

function leavePicker(): void {
	if (field.value === null) {
		void router.push(back.value);
	}
}

// Where the screen goes back to: the type it was opened from, or the
// library.
const back = computed(() => typeof route.query.type === 'string' && fresh.value ? { name: 'content-type', params: { name: route.query.type } } : { name: 'fields' });

const title = computed(() => field.value?.label || (detail.value ? humanize(detail.value.name) : (fresh.value ? t('field_view.new_title', 'New Field') : t('field_view.title', 'Field'))));

watch(title, (value) => {
	screenTitle.value = detail.value || fresh.value ? value : null;
}, { immediate: true });

const changed = computed(() => fresh.value ? field.value !== null : detail.value !== null && field.value !== null && JSON.stringify(field.value) !== JSON.stringify(detail.value.field));

async function create(): Promise<void> {
	if (field.value === null || saving.value || !(form.value?.check() ?? false)) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const made = await request<LibraryFieldDetail>('POST', '/fields/library', { field: field.value });
		const type = typeof route.query.type === 'string' ? route.query.type : null;

		leaving = true;
		refreshTypes();
		toast(t('field_view.created', 'Made {label}', { label: made.label || humanize(made.name) }));
		await router.replace(type === null ? { name: 'field', params: { name: made.name } } : { name: 'content-type', params: { name: type }, query: { add: made.name } });
	} catch (caught) {
		failure.value = errorMessage(caught, t('field_view.create_failed', 'The field couldn\'t be made.'));
	} finally {
		saving.value = false;
	}
}

// What a save says, with the values it moved along with an Object's
// fields put somewhere new (D-780), and those it couldn't.
function savedMessage(saved: LibraryFieldDetail): string {
	const moved  = saved.moved;
	const failed = Object.keys(moved?.failed ?? {}).length;
	const parts  = [t('field_view.saved', 'Saved {label}', { label: saved.label || humanize(saved.name) })];

	if (moved !== undefined && moved.entries > 0) {
		parts.push(t('field_view.moved', 'Moved values in {count, plural, one {# entry} other {# entries}}', { count: moved.entries }));
	}

	if (moved !== undefined && moved.kept > 0) {
		parts.push(t('field_view.kept', '{count, plural, one {# entry} other {# entries}} already had a value at the new place, so the old one stays', { count: moved.kept }));
	}

	if (failed > 0) {
		parts.push(t('field_view.failed', '{count, plural, one {# entry} other {# entries}} couldn\'t be changed: {reason}', { count: failed, reason: Object.values(moved?.failed ?? {})[0] }));
	}

	return parts.join('. ');
}

async function save(): Promise<void> {
	const current = detail.value;

	if (current === null || field.value === null || !changed.value || saving.value || !(form.value?.check() ?? false)) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		const saved = await request<LibraryFieldDetail>('PATCH', `/fields/library/${encodeURIComponent(current.name)}`, { field: field.value });

		refreshTypes();
		show(saved);
		toast(savedMessage(saved), { kind: Object.keys(saved.moved?.failed ?? {}).length > 0 || (saved.moved?.kept ?? 0) > 0 ? 'warn' : undefined });
	} catch (caught) {
		failure.value = errorMessage(caught, t('field_view.save_failed', 'The field couldn\'t be saved.'));
	} finally {
		saving.value = false;
	}
}

function revert(): void {
	if (detail.value !== null) {
		show(detail.value);
		toast(t('field_view.reverted', 'Changes reverted'), { kind: 'info' });
	}
}

async function remove(): Promise<void> {
	const current = detail.value;

	if (current === null) {
		return;
	}

	const types = current.types.map((type) => type.label).join(', ');

	if (!await confirmAction({
		title: t('field_view.delete_title', 'Delete {label}?', { label: title.value }),
		body: [
			current.types.length ? t('field_view.delete_types', 'It comes off {types}.', { types }) : t('field_view.delete_no_types', 'No type has it.'),
			t('field_view.delete_keep', 'Its file in user/data/fields is removed. Entries keep their values in their files.')
		],
		confirm: t('field_view.delete_confirm', 'Delete Field'),
		danger: true
	})) {
		return;
	}

	try {
		await request('DELETE', `/fields/library/${encodeURIComponent(current.name)}`);
		leaving = true;
		refreshTypes();
		toast(t('field_view.deleted', 'Deleted {label}', { label: title.value }), { kind: 'danger' });
		await router.push({ name: 'fields' });
	} catch (caught) {
		failure.value = errorMessage(caught, t('field_view.delete_failed', 'The field couldn\'t be deleted.'));
	}
}

guardLeave(() => !leaving && (fresh.value || detail.value?.editable === true) && changed.value, () => confirmLeave(t('field_view.leave', 'The changes to this field haven\'t been saved.')));
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="back"><AdminIcon name="chevron-left" />{{ back.name === 'fields' ? t('field_view.back', 'All fields') : t('field_view.back_to_type', 'Back to the type') }}</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ title }}</h1>
			<div v-if="detail && field" class="page-facts">
				<span class="page-facts__item"><AdminIcon name="key-round" /><span class="mono">{{ detail.name }}</span></span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item"><AdminIcon :name="controlIcon(field.control ?? 'text')" />{{ controlLabel(field.control ?? 'text') }}</span>
				<span class="page-facts__divider" aria-hidden="true" />
				<span class="page-facts__item">
					<AdminIcon name="layers" />
					<template v-if="detail.types.length">
						<template v-for="(type, index) in detail.types" :key="type.name"><template v-if="index">, </template><RouterLink :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink></template>
					</template>
					<template v-else>{{ t('field_view.no_types', 'No type has it yet') }}</template>
				</span>
				<template v-if="!detail.editable">
					<span class="page-facts__divider" aria-hidden="true" />
					<span class="page-facts__item"><AdminIcon name="lock" />{{ t('field_view.read_only', 'Read only') }}</span>
				</template>
			</div>
		</div>
		<div v-if="fresh && field" class="page-header__actions">
			<RouterLink class="button" :to="back">{{ t('common.cancel', 'Cancel') }}</RouterLink>
			<button type="button" class="button button--primary" :disabled="saving" @click="create">{{ saving ? t('field_view.creating', 'Creating…') : t('field_view.create', 'Create') }}</button>
		</div>
		<div v-if="detail?.editable" class="page-header__actions">
			<button type="button" class="button button--primary" :disabled="!changed || saving" @click="save">{{ saving ? t('field_view.saving', 'Saving…') : (changed ? t('field_view.update', 'Update') : t('field_view.saved_button', 'Saved')) }}</button>
			<MenuButton button-class="button button--icon" :label="t('field_view.more', 'More actions')" align="end">
				<template #button><AdminIcon name="ellipsis-vertical" /></template>
				<button type="button" class="menu-item" :disabled="!changed" @click="revert"><AdminIcon name="undo-2" />{{ t('field_view.revert', 'Revert changes') }}</button>
				<div class="menu-divider" />
				<button type="button" class="menu-item menu-item--danger" @click="remove"><AdminIcon name="trash-2" />{{ t('field_view.delete', 'Delete field') }}</button>
			</MenuButton>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-if="failure" class="notice notice--error" role="alert">{{ failure }}</p>
	<p v-for="problem in detail?.problems ?? []" :key="problem" class="notice notice--warn"><span>{{ t('field_view.problem', '{problem} It\'s left off there until one of them is renamed.', { problem }) }}</span></p>

	<section v-if="fresh && field" class="panel" aria-labelledby="field-heading">
		<header class="panel__header">
			<h2 id="field-heading">{{ t('field_view.field', 'Field') }}</h2>
			<p class="panel__hint">{{ t('field_view.new_hint', 'Made once, in the field library. Each type that needs it adds it on its own screen.') }}</p>
		</header>
		<div class="panel__body">
			<LibraryFieldForm ref="form" v-model="field" :saved="null" :taken="taken" id-prefix="field-" />
		</div>
	</section>

	<section v-else-if="detail && field" class="panel" aria-labelledby="field-heading">
		<header class="panel__header">
			<h2 id="field-heading">{{ t('field_view.field', 'Field') }}</h2>
			<p class="panel__hint">{{ t('field_view.hint', 'The same on every type that has it. A type can change its label, help, and whether it\'s required.') }}</p>
		</header>
		<p v-if="!detail.editable" class="panel__note"><AdminIcon name="lock" /> <MessageText :text="t('field_view.locked', '{config} turns off the field library in {folder}, so it can\'t be changed here.')"><template #config><code>config/fields.php</code></template><template #folder><code>user/data/fields</code></template></MessageText></p>
		<div class="panel__body">
			<LibraryFieldForm ref="form" v-model="field" :saved="detail.name" :counts="detail.counts" :locked="!detail.editable" id-prefix="field-" />
		</div>
	</section>

	<div v-else-if="!error && !picking" class="panel" aria-hidden="true"><div class="panel__body"><span class="skeleton skeleton--heading" /><span class="skeleton" /><span class="skeleton" /></div></div>

	<ControlPicker v-if="picking" @choose="chose" @close="leavePicker" />
</template>
