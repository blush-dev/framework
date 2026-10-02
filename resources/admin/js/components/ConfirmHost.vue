<script setup lang="ts">
/**
 * Shows the admin's confirmations (`confirm.ts`, D-373), one at a time,
 * in an `AdminModal`.
 */

import { computed } from 'vue';
import AdminModal from './AdminModal.vue';
import { answerConfirm, emphasis, pendingConfirms } from '../confirm';

const current    = computed(() => pendingConfirms.value[0] ?? null);
const paragraphs = computed(() => {
	const body = current.value?.body ?? [];

	return (Array.isArray(body) ? body : [body]).map(emphasis);
});
</script>

<template>
	<AdminModal v-if="current" :key="current.id" open :title="current.title" @close="answerConfirm(current.id, false)">
		<p v-for="(parts, index) in paragraphs" :key="index">
			<template v-for="(part, at) in parts" :key="at"><strong v-if="part.strong">{{ part.text }}</strong><template v-else>{{ part.text }}</template></template>
		</p>
		<template #footer>
			<button type="button" class="button" :autofocus="current.danger || undefined" @click="answerConfirm(current.id, false)">{{ current.cancel ?? 'Cancel' }}</button>
			<button type="button" class="button" :class="current.danger ? 'button--danger' : 'button--primary'" :autofocus="!current.danger || undefined" @click="answerConfirm(current.id, true)">{{ current.confirm }}</button>
		</template>
	</AdminModal>
</template>
