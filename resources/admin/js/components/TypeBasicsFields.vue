<script setup lang="ts">
/**
 * A content type's names, description, and icon (D-311), for the type
 * editor and the new-type wizard. The icon is chosen from the site's
 * icons (`IconField`); clearing it uses the kind's.
 */

import IconField from './IconField.vue';
import type { TypeForm, TypeKind } from '../type-form';

defineProps<{
	idPrefix: string;
	kind: TypeKind;
}>();

const form = defineModel<TypeForm>({ required: true });
</script>

<template>
	<div class="form-stack">
		<div class="field-pair">
			<div class="field">
				<label :for="`${idPrefix}plural`">{{ t('type_basics_fields.name_plural', 'Name (plural)') }}</label>
				<input :id="`${idPrefix}plural`" v-model="form.plural" :placeholder="{ tree: t('type_basics_fields.placeholder_tree_plural', 'Docs'), collection: t('type_basics_fields.placeholder_collection_plural', 'Recipes') }[kind]" autocomplete="off">
			</div>
			<div class="field">
				<label :for="`${idPrefix}singular`">{{ t('type_basics_fields.name_singular', 'Name (singular)') }}</label>
				<input :id="`${idPrefix}singular`" v-model="form.singular" :placeholder="{ tree: t('type_basics_fields.placeholder_tree_singular', 'Doc'), collection: t('type_basics_fields.placeholder_collection_singular', 'Recipe') }[kind]" autocomplete="off">
			</div>
		</div>
		<div class="field">
			<label :for="`${idPrefix}description`">{{ t('type_basics_fields.description', 'Description') }}</label>
			<input :id="`${idPrefix}description`" v-model="form.description" autocomplete="off" :aria-describedby="`${idPrefix}description-help`">
			<p :id="`${idPrefix}description-help`" class="field__help">{{ t('type_basics_fields.description_help', 'What it\'s for, in a sentence. Shown on its list while it\'s empty.') }}</p>
		</div>
		<div class="field">
			<label :for="`${idPrefix}icon`">{{ t('type_basics_fields.icon', 'Icon') }}</label>
			<IconField :id="`${idPrefix}icon`" v-model="form.icon" :described-by="`${idPrefix}icon-help`" :preview="(icon) => `icon: ${icon.name}`" />
			<p :id="`${idPrefix}icon-help`" class="field__help">{{ t('type_basics_fields.icon_help', 'Without one, its kind\'s icon.') }}</p>
		</div>
	</div>
</template>
