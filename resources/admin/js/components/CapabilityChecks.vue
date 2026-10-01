<script setup lang="ts">
/**
 * A role's capabilities as checkboxes in groups by their first word
 * (D-312), each with its label and name. You can't give a role a
 * capability you don't have, so those are shown but can't be ticked.
 */

import { computed } from 'vue';
import { capabilityGroups, type CapabilityInfo } from '../people';
import { can } from '../session';

const props = defineProps<{
	capabilities: CapabilityInfo[];
	idPrefix: string;
}>();

const model = defineModel<string[]>({ required: true });

const groups = computed(() => capabilityGroups(props.capabilities));

function toggle(name: string, on: boolean): void {
	model.value = on ? [...model.value, name] : model.value.filter((item) => item !== name);
}
</script>

<template>
	<fieldset v-for="group in groups" :key="group.name" class="capability-checks">
		<legend>{{ group.name }}</legend>
		<label v-for="capability in group.capabilities" :key="capability.name" class="capability-check" :class="{ 'capability-check--locked': !can(capability.name) }">
			<input :id="`${idPrefix}${capability.name}`" type="checkbox" :checked="model.includes(capability.name)" :disabled="!can(capability.name)" @change="toggle(capability.name, ($event.target as HTMLInputElement).checked)">
			<span>{{ capability.label }}<span v-if="!can(capability.name)" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
			<code>{{ capability.name }}</code>
		</label>
	</fieldset>
</template>

<style scoped>
.capability-checks {
	display: grid;
	gap: 4px;
	margin: 0;
	padding: 12px var(--pad-x);
	border: 0;
	border-top: 1px solid var(--border);
}

.capability-checks legend {
	float: left;
	width: 100%;
	margin-bottom: 6px;
	padding: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.capability-check {
	display: flex;
	align-items: center;
	gap: 8px;
	min-height: 26px;
	font-size: var(--text-sm);
	cursor: pointer;
}

.capability-check input {
	flex: none;
	width: 15px;
	height: 15px;
	margin: 0;
	accent-color: var(--accent);
}

.capability-check code {
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.capability-check--locked {
	color: var(--fg-3);
	cursor: default;
}
</style>
