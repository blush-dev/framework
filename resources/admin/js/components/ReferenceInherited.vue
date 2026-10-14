<script setup lang="ts">
/**
 * What a translation shows from its original in a relation (D-599,
 * D-607), in a dashed band that names the original: beside its own
 * (`add`), or in place of its own until it sets some (`fallback`). The
 * values are the slot's, drawn as the picker draws them, without ×.
 */

import type { InheritedValues } from '../api';
import AdminIcon from './AdminIcon.vue';

defineProps<{ inherited: InheritedValues }>();
</script>

<template>
	<div class="reference__inherited">
		<p class="reference__inherited-head">
			<AdminIcon name="languages" />
			<span v-if="inherited.rule === 'add'">{{ t('reference_inherited.also_shown', 'Also shown, from {title} ({language})', { title: inherited.title, language: inherited.language }) }}</span>
			<span v-else>{{ t('reference_inherited.shown_until', 'Shown from {title} ({language}) until this translation sets its own', { title: inherited.title, language: inherited.language }) }}</span>
		</p>
		<slot />
	</div>
</template>
