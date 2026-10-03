<script setup lang="ts">
/**
 * A switch (the extensions sketch's, D-385): a setting that takes effect
 * the moment it moves, so it carries a word (On, Off) as well as a fill,
 * and the page says what happened in a toast. It's a checkbox with the
 * `switch` role, so it's announced as on or off.
 *
 * In a form (`form`, the settings sketch's, D-404) it's a value saved
 * with the rest, as a checkbox is, so it fills with the accent rather
 * than the color of something running.
 *
 * A `locked` switch shows a state that's real but can't be changed here;
 * its `reason` is its tooltip and is read with it. A `busy` one is
 * saving, and can't be moved again until it's done.
 */

import { useId } from 'vue';

const props = defineProps<{
	checked: boolean;
	// Its accessible name: "Word Count".
	label: string;
	locked?: boolean;
	busy?: boolean;
	reason?: string | null;
	form?: boolean;
	// Help elsewhere on the page that describes it.
	describedBy?: string;
}>();

const emit = defineEmits<{
	change: [checked: boolean];
}>();

const reasonId = useId();

function changed(event: Event): void {
	const input = event.target as HTMLInputElement;

	emit('change', input.checked);

	// The page decides: until it says so, the switch stays as it was.
	input.checked = props.checked;
}
</script>

<template>
	<label class="switch" :class="{ 'switch--form': form, 'is-locked': locked, 'is-busy': busy }" :title="reason ?? undefined">
		<input
			type="checkbox"
			role="switch"
			:checked="checked"
			:disabled="locked || busy"
			:aria-label="label"
			:aria-describedby="[reason ? reasonId : '', describedBy ?? ''].join(' ').trim() || undefined"
			@change="changed"
		>
		<span class="switch__track" aria-hidden="true"><span class="switch__knob" /></span>
		<span class="switch__word" aria-hidden="true">{{ checked ? 'On' : 'Off' }}</span>
		<span v-if="reason" :id="reasonId" class="visually-hidden">{{ reason }}</span>
	</label>
</template>

<style scoped>
.switch {
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: var(--s-2);
	cursor: pointer;
	user-select: none;
}

.switch input {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
}

.switch__track {
	position: relative;
	flex: none;
	width: 34px;
	height: 20px;
	border: 1px solid var(--border-strong);
	border-radius: 999px;
	background: var(--surface-3);
	transition: background .14s ease-out, border-color .14s ease-out;
}

.switch__knob {
	position: absolute;
	top: 2px;
	left: 2px;
	width: 14px;
	height: 14px;
	border-radius: 50%;
	background: var(--surface);
	box-shadow: var(--shadow-2);
	transition: transform .14s ease-out;
}

.switch input:checked + .switch__track {
	border-color: var(--good-dot);
	background: var(--good-dot);
}

.switch input:checked + .switch__track .switch__knob {
	transform: translateX(14px);
}

.switch input:focus-visible + .switch__track {
	outline: 2px solid var(--accent);
	outline-offset: 2px;
}

.switch__word {
	width: 22px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
}

.switch input:checked ~ .switch__word {
	color: var(--good);
}

.switch--form input:checked + .switch__track {
	border-color: var(--accent);
	background: var(--accent);
}

.switch--form input:checked + .switch__track .switch__knob {
	background: var(--accent-fg);
}

.switch--form input:checked ~ .switch__word {
	color: var(--fg-2);
}

.switch.is-locked {
	cursor: default;
}

.switch.is-locked .switch__track {
	opacity: .5;
}

.switch.is-locked .switch__word {
	opacity: .6;
}

.switch.is-busy {
	cursor: progress;
	opacity: .72;
}

@media (prefers-reduced-motion: reduce) {
	.switch__track,
	.switch__knob {
		transition: none;
	}
}
</style>
