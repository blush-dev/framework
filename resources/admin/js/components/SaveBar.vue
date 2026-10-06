<script setup lang="ts">
/**
 * The save bar (D-324, D-508): a screen's unsaved changes, counted, over
 * the bottom of the screen, with a refused save's reason, **Revert**, and
 * **Save changes**, which submits the form it's in. It shows once
 * there's a change or a failure to say.
 */

import { plural } from '../format';

// `ready` defaults to true: Vue reads a boolean prop left out as false.
withDefaults(defineProps<{
	count: number;
	failure: string;
	saving: boolean;
	// Whether Save changes can be pressed, when there's more to it than
	// a change.
	ready?: boolean;
}>(), { ready: true });

const emit = defineEmits<{ revert: [] }>();
</script>

<template>
	<div v-if="count > 0 || failure" class="save-bar" role="region" aria-label="Unsaved changes">
		<span class="save-bar__count" aria-live="polite">{{ plural(count, 'unsaved change') }}</span>
		<span v-if="failure" class="save-bar__error" role="alert">{{ failure }}</span>
		<button type="button" class="button button--ghost button--small" :disabled="saving" @click="emit('revert')">Revert</button>
		<button type="submit" class="button button--primary button--small" :disabled="saving || count === 0 || !ready">{{ saving ? 'Saving…' : 'Save changes' }}</button>
	</div>
</template>
