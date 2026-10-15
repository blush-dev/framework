<script setup lang="ts">
/**
 * A path pattern's box (D-406, D-871): what it's under as a lead, then
 * the pattern. While it has focus, it shows the path the pattern would
 * make and its tokens; a token lands at the caret with the slashes it
 * needs, never two of them. A token the path must hold is marked with a
 * dot, and a token the pattern holds is pressed. A problem shows under
 * it. Media's upload paths and a type's route paths are drawn with it.
 */

import { computed, nextTick, ref } from 'vue';
import { t } from '../messages';
import AdminIcon from './AdminIcon.vue';

export interface PatternToken {
	name: string;
	// Whether the pattern must hold it.
	required?: boolean;
}

const props = withDefaults(defineProps<{
	id: string;
	// What the pattern is under, such as `user/media/` or `/blog/`.
	lead?: string;
	placeholder?: string;
	tokens?: PatternToken[];
	// The path the pattern makes, shown while it has focus, or always.
	example?: string;
	alwaysExample?: boolean;
	label: string;
	disabled?: boolean;
	problem?: string;
}>(), { lead: '', placeholder: '', tokens: () => [], example: '', alwaysExample: false, disabled: false, problem: '' });

const value = defineModel<string>({ required: true });

const focused = ref(false);
const input   = ref<HTMLInputElement | null>(null);
const root    = ref<HTMLElement | null>(null);

const held = computed(() => new Set([...(value.value || props.placeholder).matchAll(/\{([a-z_]+)\}/g)].map((match) => match[1])));

function leave(event: FocusEvent): void {
	if (!(event.relatedTarget instanceof Node && root.value?.contains(event.relatedTarget))) {
		focused.value = false;
	}
}

function braced(token: string): string {
	return `{${token}}`;
}

async function insert(token: string): Promise<void> {
	const box = input.value;

	if (box === null || box.disabled) {
		return;
	}

	const current = box.value;
	const start   = box.selectionStart ?? current.length;
	const end     = box.selectionEnd ?? start;
	const before  = current.slice(0, start);
	const after   = current.slice(end);
	let add       = braced(token);

	if (before !== '' && !before.endsWith('/')) {
		add = `/${add}`;
	}

	if (after !== '' && !after.startsWith('/')) {
		add = `${add}/`;
	}

	value.value = before + add + after;
	await nextTick();
	box.focus();
	box.setSelectionRange(before.length + add.length, before.length + add.length);
}
</script>

<template>
	<div ref="root" class="pattern-field" @focusin="focused = true" @focusout="leave">
		<div class="pattern-field__box" :class="{ 'is-disabled': disabled, 'is-invalid': problem !== '' }">
			<span v-if="lead" class="pattern-field__lead" aria-hidden="true">{{ lead }}</span>
			<input
				:id="id"
				ref="input"
				v-model="value"
				class="mono"
				spellcheck="false"
				autocomplete="off"
				:placeholder="placeholder"
				:disabled="disabled"
				:aria-label="label"
				:aria-invalid="problem !== '' ? 'true' : undefined"
				:aria-describedby="problem !== '' ? `${id}-problem` : undefined"
			>
		</div>
		<p v-if="example && (alwaysExample || (focused && !disabled))" class="pattern-field__example">→ {{ example }}</p>
		<template v-if="focused && !disabled">
			<div v-if="tokens.length" class="pattern-field__tokens" role="group" :aria-label="t('pattern_field.tokens', 'Tokens for {label}', { label })">
				<button
					v-for="token in tokens"
					:key="token.name"
					type="button"
					class="mono"
					:class="{ 'is-required': token.required }"
					:aria-pressed="held.has(token.name) ? 'true' : 'false'"
					:title="token.required ? t('pattern_field.required', 'Required: insert {token}', { token: braced(token.name) }) : t('pattern_field.optional', 'Optional: insert {token}', { token: braced(token.name) })"
					@mousedown.prevent
					@click="insert(token.name)"
					v-text="braced(token.name)"
				/>
			</div>
		</template>
		<p v-if="problem" :id="`${id}-problem`" class="pattern-field__problem"><AdminIcon name="triangle-alert" />{{ problem }}</p>
	</div>
</template>

<style scoped>
.pattern-field {
	display: grid;
	gap: var(--s-2);
	max-width: 780px;
}

/* An input with what it's under before it. */
.pattern-field__box {
	display: flex;
	align-items: stretch;
	min-height: var(--ctl);
	overflow: hidden;
	border: 1px solid var(--border-color-strong);
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
}

.pattern-field__box:hover {
	border-color: var(--color-text-muted);
}

.pattern-field__box:focus-within {
	border-color: var(--color-accent);
	outline: 2px solid var(--color-accent);
}

.pattern-field__box.is-invalid {
	border-color: var(--color-danger);
}

.pattern-field__box.is-disabled {
	border-color: var(--border-color);
	background: var(--color-surface-subtle);
}

.pattern-field__lead {
	display: flex;
	align-items: center;
	padding: 0 var(--s-2) 0 var(--s-3);
	border-right: 1px solid var(--border-color);
	background: var(--color-surface-subtle);
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-small);
	white-space: nowrap;
}

.pattern-field__box input {
	flex: 1;
	min-width: 0;
	padding: 0 var(--s-3);
	border: 0;
	outline: 0;
	background: none;
	color: var(--color-text);
	font-size: var(--font-size-small);
}

.pattern-field__box input::placeholder {
	color: var(--color-text-muted);
}

.pattern-field__box input:disabled {
	cursor: not-allowed;
}

.pattern-field__example {
	margin: 0;
	color: var(--color-text-muted);
	font-family: var(--font-mono);
	font-size: var(--font-size-xsmall);
	overflow-wrap: anywhere;
}

.pattern-field__tokens {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

.pattern-field__tokens button {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	height: 24px;
	padding: 0 var(--s-2);
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-small);
	background: var(--color-surface);
	color: var(--color-text-secondary);
	font-size: var(--font-size-xsmall);
	cursor: pointer;
}

/* A token the pattern must hold has a dot before it. */
.pattern-field__tokens button.is-required::before {
	content: "";
	width: 5px;
	height: 5px;
	border-radius: 50%;
	background: currentcolor;
}

.pattern-field__tokens button[aria-pressed="true"] {
	color: var(--color-text);
}

.pattern-field__tokens button:hover {
	border-color: var(--color-accent-border);
	background: var(--color-accent-subtle);
	color: var(--color-accent);
}

.pattern-field__problem {
	display: flex;
	gap: var(--s-1);
	margin: 0;
	color: var(--color-danger);
	font-size: var(--font-size-small);
}

.pattern-field__problem :deep(.icon) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}
</style>
