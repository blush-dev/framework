<script setup lang="ts">
/**
 * Several choices from a set, picked from a menu (the multi-select
 * control, D-766): the chosen ones as chips in a box, each with its ×,
 * and **Add** opening a menu of the rest (`MenuButton`). It's drawn with
 * the token picker's box and chips (D-607), since it's the same thing
 * with a fixed set. The state holds one option per line, in the order
 * they were added.
 */

import { computed } from 'vue';
import AdminIcon from './AdminIcon.vue';
import MenuButton from './MenuButton.vue';

const props = defineProps<{
	id: string;
	options: string[];
	// An option's words.
	labelOf: (option: string) => string;
	// The group's name, for the Add button and each chip's ×.
	name: string;
	describedBy?: string;
	labelledBy?: string;
	invalid?: boolean;
	disabled?: boolean;
}>();

const model = defineModel<string>({ required: true });

const chosen = computed(() => model.value.split('\n').filter((line) => props.options.includes(line)));
const left   = computed(() => props.options.filter((option) => !chosen.value.includes(option)));

function add(option: string): void {
	model.value = [...chosen.value, option].join('\n');
}

function remove(option: string): void {
	model.value = chosen.value.filter((item) => item !== option).join('\n');
}
</script>

<template>
	<div :id="id" class="reference__tokens" :class="{ 'is-invalid': invalid }" role="group" :aria-labelledby="labelledBy" :aria-describedby="describedBy">
		<span v-for="option in chosen" :key="option" class="reference__token">
			<span class="reference__token-name">{{ labelOf(option) }}</span>
			<button type="button" :disabled="disabled" @click="remove(option)"><AdminIcon name="x" /><span class="visually-hidden">Remove {{ labelOf(option) }}</span></button>
		</span>
		<MenuButton v-if="left.length" button-class="button button--ghost button--small" :label="`Add to ${name}`" floating>
			<template #button><AdminIcon name="plus" />Add</template>
			<button v-for="option in left" :key="option" type="button" class="menu-item" :disabled="disabled" @click="add(option)">{{ labelOf(option) }}</button>
		</MenuButton>
		<span v-else-if="!chosen.length" class="field__help">No options to pick from.</span>
	</div>
</template>
