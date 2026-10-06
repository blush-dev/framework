<script setup lang="ts">
/**
 * One capability in a role's sections (D-359, D-509): a drawn box, its
 * label, and its key, shown when the sections show keys. `from` names
 * what fixes it ("every type"); otherwise `unheld` tells a screen
 * reader it's one the viewer can't give.
 */

import AdminIcon from './AdminIcon.vue';

defineProps<{
	name: string;
	label: string;
	checked: boolean;
	disabled: boolean;
	from?: string;
	unheld?: boolean;
}>();

const emit = defineEmits<{ change: [on: boolean] }>();
</script>

<template>
	<label class="capability" :class="{ 'is-off': !checked, 'is-locked': disabled }">
		<input type="checkbox" class="check-input" :checked="checked" :disabled="disabled" @change="emit('change', ($event.target as HTMLInputElement).checked)">
		<span class="check-box" aria-hidden="true"><AdminIcon name="check" /></span>
		<span class="capability__label">{{ label }}<span v-if="from" class="capability__from"> · {{ from }}</span><span v-else-if="unheld" class="visually-hidden"> (you don't have it, so you can't give it)</span></span>
		<code class="capability__key">{{ name }}</code>
	</label>
</template>

<style scoped>
/* A capability: a drawn box, its label, and its key. */
.capability {
	position: relative;
	display: flex;
	align-items: center;
	gap: 10px;
	min-width: 0;
	padding: 7px 0;
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.capability:hover {
	color: var(--fg);
}

.capability.is-off {
	color: var(--fg-3);
}

.capability.is-locked {
	cursor: default;
}

.capability:not(.is-locked):hover .check-box {
	border-color: var(--accent);
}

/* A capability that's on but can't change stays the accent, faded, not
   the global gray: here it's most often one the role has through every
   type, or one you can't give. */
.check-input:checked:disabled + .check-box {
	border-color: var(--accent);
	background: var(--accent);
	opacity: .45;
}

.capability__label {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.capability__from {
	color: var(--fg-3);
}

.capability__key {
	display: none;
	flex: none;
	margin-left: auto;
	padding-left: var(--s-3);
	color: var(--fg-3);
	font-family: var(--font-mono);
	font-size: var(--text-2xs);
}

.sections--keys .capability__key {
	display: inline;
}
</style>
