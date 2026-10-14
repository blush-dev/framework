<script setup lang="ts">
/**
 * The two fields every object on the element tab has (D-268), since
 * the syntax is the same everywhere: **Classes** (space separated, dots
 * optional) and **ID**. They report each change as typed; what's written
 * is up to the panel.
 */

import { classNames } from '../markdown';
import MessageText from './MessageText.vue';

defineProps<{
	classes: string[];
	id: string;
	// Keeps the fields' ids apart from other panels'.
	idPrefix: string;
}>();

const emit = defineEmits<{
	change: [classes: string[], id: string];
}>();
</script>

<template>
	<div class="field">
		<label :for="`${idPrefix}classes`">{{ t('attribute_fields.classes', 'Classes') }}</label>
		<input
			:id="`${idPrefix}classes`"
			class="mono"
			:value="classes.join(' ')"
			placeholder="lead wide"
			autocomplete="off"
			spellcheck="false"
			:aria-describedby="`${idPrefix}classes-help`"
			@input="emit('change', classNames(($event.target as HTMLInputElement).value), id)"
		>
		<p :id="`${idPrefix}classes-help`" class="field__help">{{ t('attribute_fields.classes_help', 'Space separated, without the dots.') }}</p>
	</div>
	<div class="field">
		<label :for="`${idPrefix}id`">{{ t('attribute_fields.id', 'ID') }}</label>
		<input
			:id="`${idPrefix}id`"
			class="mono"
			:value="id"
			placeholder="section-two"
			autocomplete="off"
			spellcheck="false"
			:aria-describedby="`${idPrefix}id-help`"
			@input="emit('change', classes, ($event.target as HTMLInputElement).value.trim())"
		>
		<p :id="`${idPrefix}id-help`" class="field__help"><MessageText :text="t('attribute_fields.id_help', 'The anchor this can be linked to, as {example}.')"><template #example><code>#section-two</code></template></MessageText></p>
	</div>
</template>
