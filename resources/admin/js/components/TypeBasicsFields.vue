<script setup lang="ts">
/**
 * A content type's names, description, and icon (D-311), for the type
 * editor and the new-type wizard. The icon is chosen from the site's
 * icons (`IconPicker`) or typed by name; clearing it uses the kind's.
 */

import { ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import IconPicker from './IconPicker.vue';
import type { TypeForm, TypeKind } from '../type-form';
import type { SiteIcon } from '../site-icons';

defineProps<{
	idPrefix: string;
	kind: TypeKind;
}>();

const form = defineModel<TypeForm>({ required: true });

const picking = ref(false);

function choose(icon: SiteIcon): void {
	form.value.icon = icon.name;
	picking.value   = false;
}
</script>

<template>
	<div class="form-stack">
		<div class="field-pair">
			<div class="field">
				<label :for="`${idPrefix}plural`">Name (plural)</label>
				<input :id="`${idPrefix}plural`" v-model="form.plural" :placeholder="{ taxonomy: 'Cuisines', tree: 'Docs', collection: 'Recipes' }[kind]" autocomplete="off">
			</div>
			<div class="field">
				<label :for="`${idPrefix}singular`">Name (singular)</label>
				<input :id="`${idPrefix}singular`" v-model="form.singular" :placeholder="{ taxonomy: 'Cuisine', tree: 'Doc', collection: 'Recipe' }[kind]" autocomplete="off">
			</div>
		</div>
		<div class="field">
			<label :for="`${idPrefix}description`">Description</label>
			<input :id="`${idPrefix}description`" v-model="form.description" autocomplete="off" :aria-describedby="`${idPrefix}description-help`">
			<p :id="`${idPrefix}description-help`" class="field__help">What it's for, in a sentence. Shown on its list while it's empty.</p>
		</div>
		<div class="field">
			<label :for="`${idPrefix}icon`">Icon</label>
			<div class="type-fields__icon">
				<input :id="`${idPrefix}icon`" v-model="form.icon" class="mono" placeholder="Its kind's" autocomplete="off" spellcheck="false">
				<button type="button" class="button button--small" @click="picking = true"><AdminIcon name="shapes" />Choose</button>
			</div>
		</div>
		<IconPicker v-if="picking" :preview="(icon) => `icon: ${icon.name}`" @choose="choose" @close="picking = false" />
	</div>
</template>

<style scoped>
.type-fields__icon {
	display: flex;
	gap: var(--s-2);
}

.type-fields__icon input {
	flex: 1;
	min-width: 0;
}
</style>
