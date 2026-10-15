<script setup lang="ts">
/**
 * Adding a library field to a type (D-858): a modal in the library
 * pickers' shell (`.modal`), its search under the heading, then the
 * library's fields the type doesn't have yet, each a row with its
 * control's glyph, its label and key, and its control. Choosing one
 * closes it (`choose`). **New Field** at its foot makes one in the
 * library instead (`create`), when it's offered (`canCreate`).
 */

import { computed, ref } from 'vue';
import type { LibraryFieldOption } from '../api';
import { controlIcon, controlLabel } from '../controls';
import { useModalDialog } from '../dialog';
import { humanize } from '../fields';
import { loadFieldTypes } from '../field-types';
import { t } from '../messages';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{
	// The fields that can be added: the library's, but the type's own.
	fields: LibraryFieldOption[];
	// Whether New Field is offered.
	canCreate?: boolean;
}>();

const emit = defineEmits<{ choose: [name: string]; create: []; close: [] }>();

void loadFieldTypes().catch(() => undefined);

const input = ref<HTMLInputElement | null>(null);
const query = ref('');

const { dialog, close, backdrop } = useModalDialog(() => input.value?.focus());

const shown = computed(() => {
	const words = query.value.trim().toLowerCase();

	return props.fields.filter((field) => words === '' || `${field.label} ${field.name} ${field.description}`.toLowerCase().includes(words));
});

// The dialog closes first, so focus can go back to the tree.
function choose(name: string): void {
	close();
	emit('choose', name);
}

function create(): void {
	close();
	emit('create');
}
</script>

<template>
	<dialog ref="dialog" class="modal library-field-picker" aria-labelledby="library-field-picker-heading" @close="emit('close')" @click="backdrop" @keydown.esc.prevent.stop="close()">
		<div class="panel__header">
			<div class="library-field-picker__title">
				<h2 id="library-field-picker-heading">{{ t('library_field_picker.title', 'Add a Field') }}</h2>
				<p class="panel__hint">{{ t('library_field_picker.hint', 'From the field library. A field keeps its key and settings on every type that has it.') }}</p>
			</div>
			<div class="panel__actions">
				<button type="button" class="button button--ghost button--small button--icon" @click="close()">
					<AdminIcon name="x" />
					<span class="visually-hidden">{{ t('common.close', 'Close') }}</span>
				</button>
			</div>
		</div>

		<div v-if="fields.length" class="modal__bar">
			<label class="search-field library-field-picker__search">
				<AdminIcon name="search" />
				<input ref="input" v-model="query" type="search" :placeholder="t('library_field_picker.search_placeholder', 'Search fields…')" autocomplete="off" spellcheck="false" :aria-label="t('library_field_picker.search_label', 'Search fields')">
			</label>
		</div>

		<div class="modal__body">
			<p v-if="!fields.length" class="library-field-picker__none">{{ canCreate ? t('library_field_picker.none', 'The library has no field this type doesn\'t have yet. Make a new one.') : t('library_field_picker.none_wizard', 'The library has no field this type doesn\'t have yet. Make new ones under Structure → Fields, or on the type\'s screen once it\'s created.') }}</p>
			<p v-else-if="!shown.length" class="library-field-picker__none">{{ t('library_field_picker.no_match', 'No field matches that.') }}</p>
			<div v-else class="pick-list">
				<button v-for="field in shown" :key="field.name" type="button" class="pick" :title="field.description || undefined" @click="choose(field.name)">
					<AdminIcon :name="controlIcon(field.control)" />
					<span class="pick__text">
						<span class="pick__name">{{ field.label || humanize(field.name) }}</span>
						<span class="pick__meta">{{ field.name }}</span>
					</span>
					<span class="pick__aside">{{ controlLabel(field.control) }}</span>
				</button>
			</div>
		</div>

		<div class="modal__foot">
			<button v-if="canCreate" type="button" class="button" @click="create"><AdminIcon name="plus" />{{ t('library_field_picker.new_field', 'New Field') }}</button>
			<span class="library-field-picker__space" />
			<button type="button" class="button" @click="close()">{{ t('common.cancel', 'Cancel') }}</button>
		</div>
	</dialog>
</template>

<style scoped>
/* A list, not a library: a narrower modal, as tall as it needs to be. */
.library-field-picker {
	width: min(560px, calc(100vw - 32px));
	height: auto;
	max-height: calc(100vh - 48px);
}

.library-field-picker__title {
	display: grid;
	gap: 4px;
}

.library-field-picker__search {
	flex: 1;
}

.library-field-picker .pick-list {
	margin-top: 0;
}

.library-field-picker__none {
	color: var(--color-text-muted);
	font-size: var(--font-size-small);
}

.library-field-picker__space {
	flex: 1;
}
</style>
