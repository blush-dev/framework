<script setup lang="ts">
/**
 * A select on the editor's side panel (D-509): its label for screen
 * readers only (the group's heading names it), the choices, and a line
 * of help under it (`help`, or the `help` slot for one that depends on
 * the choice).
 */

import AdminSelect, { type SelectOption } from './AdminSelect.vue';

defineProps<{
	id: string;
	label: string;
	options: SelectOption[];
	help?: string;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
	<div class="field">
		<label class="visually-hidden" :for="id">{{ label }}</label>
		<AdminSelect :id="id" v-model="model" :options="options" :described-by="`${id}-help`" />
		<p :id="`${id}-help`" class="field__help"><slot name="help">{{ help }}</slot></p>
	</div>
</template>
