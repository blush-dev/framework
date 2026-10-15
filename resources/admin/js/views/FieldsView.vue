<script setup lang="ts">
/**
 * Structure → Fields, the field library (D-858): every field the site
 * makes, one at a time, as a list screen, then a screen for each. A type
 * chooses which of them it carries, on its own screen; a field never
 * chooses its types. Each row says the field's label and key, how it's
 * entered, and the types that have it, with a menu to edit or delete it.
 * **New Field** opens a screen of its own (`FieldView` at `/fields/new`),
 * which asks for its control, then the field.
 */

import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import EmptyState from '../components/EmptyState.vue';
import MenuButton from '../components/MenuButton.vue';
import SkeletonTable from '../components/SkeletonTable.vue';
import { errorMessage, request, type LibraryFieldList, type LibraryFieldSummary } from '../api';
import { confirmAction } from '../confirm';
import { controlIcon, controlLabel } from '../controls';
import { loadFieldTypes } from '../field-types';
import { humanize } from '../fields';
import { t } from '../messages';
import { toast } from '../toast';
import { refreshTypes } from '../types';

const list     = ref<LibraryFieldList | null>(null);
const error    = ref('');

void loadFieldTypes().catch(() => undefined);

async function load(): Promise<void> {
	try {
		list.value = await request<LibraryFieldList>('GET', '/fields/library');
	} catch (caught) {
		error.value = errorMessage(caught, t('fields_view.load_failed', 'The field library couldn\'t be loaded.'));
	}
}

void load();

// Only the site's own fields: those core and plugins register aren't
// listed (D-790).
const fields = computed(() => list.value?.fields ?? []);

async function remove(field: LibraryFieldSummary): Promise<void> {
	const label = field.label || humanize(field.name);
	const types = field.types.map((type) => type.label).join(', ');

	if (!await confirmAction({
		title: t('fields_view.delete_title', 'Delete {label}?', { label }),
		body: [
			field.types.length ? t('fields_view.delete_types', 'It comes off {types}.', { types }) : t('fields_view.delete_no_types', 'No type has it.'),
			t('fields_view.delete_keep', 'Its file in user/data/fields is removed. Entries keep their values in their files.')
		],
		confirm: t('fields_view.delete_confirm', 'Delete Field'),
		danger: true
	})) {
		return;
	}

	try {
		await request('DELETE', `/fields/library/${encodeURIComponent(field.name)}`);
		refreshTypes();
		toast(t('fields_view.deleted', 'Deleted {label}', { label }), { kind: 'danger' });
		await load();
	} catch (caught) {
		toast(errorMessage(caught, t('fields_view.delete_failed', '{label} couldn\'t be deleted.', { label })), { kind: 'warn' });
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ t('fields_view.title', 'Fields') }}</h1>
			<p class="page-header__hint">{{ t('fields_view.hint', 'The field library. Each content type chooses which of these it has, on its own screen.') }}</p>
		</div>
		<div v-if="list?.create" class="page-header__actions">
			<RouterLink class="button button--primary" :to="{ name: 'field-new' }"><AdminIcon name="plus" />{{ t('fields_view.new_field', 'New Field') }}</RouterLink>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<template v-else>
		<section class="panel" aria-labelledby="fields-heading" :aria-busy="list === null">
			<header class="panel__header">
				<h2 id="fields-heading">{{ t('fields_view.library', 'Field Library') }}</h2>
				<p v-if="list" class="panel__hint">{{ t('fields_view.count', '{count, plural, one {# field} other {# fields} }', { count: fields.length }) }}</p>
			</header>

			<SkeletonTable v-if="list === null" :columns="[t('fields_view.field', 'Field'), t('fields_view.control', 'Control'), t('fields_view.used_on', 'Used on')]" :rows="3" :label="t('fields_view.loading', 'Loading the field library…')" />
			<EmptyState v-else-if="!fields.length" icon="group" :heading="t('fields_view.empty_heading', 'No Fields Yet')" :text="t('fields_view.empty_text', 'Make a field once, and give it to every type that needs it: a recipe\'s cook time, or an Object of search engine settings for posts and pages.')">
				<template v-if="list.create" #actions>
					<RouterLink class="button button--primary" :to="{ name: 'field-new' }"><AdminIcon name="plus" />{{ t('fields_view.new_field', 'New Field') }}</RouterLink>
				</template>
			</EmptyState>
			<div v-else class="table-wrap">
				<table class="table library-fields" aria-labelledby="fields-heading">
					<thead>
						<tr>
							<th scope="col">{{ t('fields_view.field', 'Field') }}</th>
							<th scope="col">{{ t('fields_view.control', 'Control') }}</th>
							<th scope="col">{{ t('fields_view.used_on', 'Used on') }}</th>
							<th scope="col"><span class="visually-hidden">{{ t('fields_view.actions', 'Actions') }}</span></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="field in fields" :key="field.name">
							<th scope="row">
								<span class="entry-title">
									<span class="entry-title__text">
										<RouterLink class="entry-title__link" :to="{ name: 'field', params: { name: field.name } }">{{ field.label || humanize(field.name) }}</RouterLink>
									</span>
									<span class="entry-title__path">{{ field.name }}</span>
								</span>
							</th>
							<td><span class="library-fields__control"><AdminIcon :name="controlIcon(field.control)" />{{ controlLabel(field.control) }}</span></td>
							<td>
								<ul v-if="field.types.length" class="chips">
									<li v-for="type in field.types" :key="type.name"><RouterLink :to="{ name: 'content-type', params: { name: type.name } }">{{ type.label }}</RouterLink></li>
								</ul>
								<span v-else class="library-fields__none">{{ t('fields_view.nowhere', 'No type yet') }}</span>
								<span v-if="field.problems.length" class="pill pill--warn" :title="field.problems.join('\n')">{{ t('fields_view.left_off', 'Left Off') }}<span class="visually-hidden">: {{ field.problems.join(' ') }}</span></span>
							</td>
							<td class="table__actions">
								<MenuButton button-class="row-more" :label="t('fields_view.actions_for', 'Actions for {label}', { label: field.label || humanize(field.name) })" floating>
									<template #button><AdminIcon name="ellipsis" /></template>
									<RouterLink class="menu-item" :to="{ name: 'field', params: { name: field.name } }"><AdminIcon :name="field.editable ? 'pen-line' : 'eye'" />{{ field.editable ? t('fields_view.edit', 'Edit field') : t('fields_view.open', 'Open') }}</RouterLink>
									<template v-if="field.editable">
										<div class="menu-divider" />
										<button type="button" class="menu-item menu-item--danger" @click="remove(field)"><AdminIcon name="trash-2" />{{ t('fields_view.delete', 'Delete field') }}</button>
									</template>
								</MenuButton>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</template>
</template>

<style scoped>
.library-fields__none {
	color: var(--color-text-muted);
}

.library-fields__control {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
</style>
