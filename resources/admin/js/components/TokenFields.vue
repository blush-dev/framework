<script setup lang="ts">
/**
 * A new API token's fields (D-749): its name, how long it lasts (30
 * days, 90, a year, or never; 90 at first), and what it can do, a
 * preset (Read, Write drafts, Same as the account) or, where `choose`
 * offers it, capabilities picked one by one (`chosen`, which the parent
 * draws). On an account's Tokens panel and the New Account screen.
 */

import { useId } from 'vue';
import { TOKEN_PRESETS, type TokenPreset } from '../people';

defineProps<{
	// Whether "Choose" is offered, and whose capabilities it picks from.
	choose?: string;
	invalid?: 'name' | 'expires' | 'capabilities' | null;
}>();

const name  = defineModel<string>('name', { required: true });
const days  = defineModel<number | null>('days', { required: true });
const scope = defineModel<TokenPreset | 'chosen'>('scope', { required: true });

const id = useId();

const LIFETIMES: { value: number | null; label: string }[] = [
	{ value: 30, label: '30 Days' },
	{ value: 90, label: '90 Days' },
	{ value: 365, label: 'A Year' },
	{ value: null, label: 'Never' }
];
</script>

<template>
	<div class="field">
		<label :for="`${id}-name`">Name</label>
		<input :id="`${id}-name`" v-model="name" autocomplete="off" maxlength="100" :aria-invalid="invalid === 'name' || undefined" :aria-describedby="`${id}-name-help`">
		<span :id="`${id}-name-help`" class="field__help">What it's for, such as “Astro front end” or “Claude”.</span>
	</div>
	<div class="field">
		<span :id="`${id}-expires`" class="field__label">Expires</span>
		<div class="segmented" role="group" :aria-labelledby="`${id}-expires`">
			<button v-for="lifetime in LIFETIMES" :key="String(lifetime.value)" type="button" :aria-pressed="days === lifetime.value" @click="days = lifetime.value">{{ lifetime.label }}</button>
		</div>
		<span class="field__help">{{ days === null ? 'It works until it\'s revoked.' : `It stops working ${days === 365 ? 'a year' : `${days} days`} from now, with a reminder on the dashboard a week before.` }}</span>
	</div>
	<fieldset class="fieldset" :aria-invalid="invalid === 'capabilities' || undefined">
		<legend>It can</legend>
		<div class="choices choices--detailed">
			<label v-for="preset in TOKEN_PRESETS" :key="preset.key" class="checkbox">
				<input v-model="scope" type="radio" :name="`${id}-scope`" :value="preset.key">
				<span class="choice">
					<span class="choice__name">{{ preset.label }}</span>
					<span class="choice__text">{{ preset.text }}</span>
				</span>
			</label>
			<label v-if="choose" class="checkbox">
				<input v-model="scope" type="radio" :name="`${id}-scope`" value="chosen">
				<span class="choice">
					<span class="choice__name">Choose</span>
					<span class="choice__text">Some of what {{ choose }} may do, picked one by one.</span>
				</span>
			</label>
		</div>
	</fieldset>
</template>
