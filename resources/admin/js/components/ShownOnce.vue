<script setup lang="ts">
/**
 * A secret shown once (D-312, D-749): a notice saying what it's for (the
 * slot), the secret in a read-only field that selects itself, and a copy
 * button. A password link and a new API token are shown with it; only a
 * hash of either is kept, so it's never shown again.
 */

import { ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { copyText } from '../toast';
import type { IconName } from '../icons';

const props = defineProps<{
	value: string;
	// The field's name, for screen readers: "Password link".
	label: string;
	// What's copied, in a sentence: "the link".
	noun: string;
	// The button's label: "Copy Link".
	copy: string;
	icon?: IconName;
}>();

const copied = ref('');

async function copyValue(): Promise<void> {
	copied.value = await copyText(props.value, props.noun) ? `Copied ${props.noun}.` : `Copying failed; select ${props.noun} and copy it instead.`;
}

function selectAll(event: Event): void {
	(event.target as HTMLInputElement).select();
}
</script>

<template>
	<div class="notice">
		<AdminIcon :name="icon ?? 'key-round'" />
		<span class="notice__text shown-once">
			<span><slot /></span>
			<span class="shown-once__row">
				<input class="input mono shown-once__value" :value="value" readonly :aria-label="label" @focus="selectAll">
				<button type="button" class="button button--small" @click="copyValue"><AdminIcon name="copy" />{{ copy }}</button>
			</span>
			<span class="visually-hidden" aria-live="polite">{{ copied }}</span>
		</span>
	</div>
</template>

<style scoped>
.shown-once {
	display: grid;
	gap: var(--s-2);
}

.shown-once__row {
	display: flex;
	align-items: center;
	gap: var(--s-2);
}

.shown-once__value {
	flex: 1;
	min-width: 0;
	font-size: var(--font-size-xsmall);
}
</style>
