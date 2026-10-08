<script setup lang="ts">
/**
 * Shows the admin's confirmations (`confirm.ts`, D-373), one at a time,
 * in an `AdminModal`.
 */

import { computed } from 'vue';
import AdminModal from './AdminModal.vue';
import StatusPill from './StatusPill.vue';
import { answerConfirm, emphasis, pendingConfirms } from '../confirm';

const current = computed(() => pendingConfirms.value[0] ?? null);

function parts(text: string | string[] | undefined): ReturnType<typeof emphasis>[] {
	return (Array.isArray(text) ? text : (text === undefined ? [] : [text])).map(emphasis);
}

const paragraphs = computed(() => parts(current.value?.body));
const after      = computed(() => parts(current.value?.after));

// A list's link answers Cancel, then goes where the rest are.
function follow(): void {
	const showing = current.value;

	if (showing?.action) {
		answerConfirm(showing.id, false);
		showing.action.run();
	}
}
</script>

<template>
	<AdminModal v-if="current" :key="current.id" open :title="current.title" @close="answerConfirm(current.id, false)">
		<p v-for="(parts, index) in paragraphs" :key="index">
			<template v-for="(part, at) in parts" :key="at"><strong v-if="part.strong">{{ part.text }}</strong><template v-else>{{ part.text }}</template></template>
		</p>
		<ul v-if="current.items?.length" class="confirm-list">
			<li v-for="(item, index) in current.items" :key="index">
				<span class="confirm-list__title">{{ item.title || 'Untitled' }}</span>
				<span v-if="item.meta || (item.status && item.status !== 'published')" class="confirm-list__meta">{{ item.meta }}<StatusPill v-if="item.status && item.status !== 'published'" :status="item.status" /></span>
				<span v-if="item.count" class="confirm-list__count mono">{{ item.count }}</span>
			</li>
			<li v-if="current.more || current.action" class="confirm-list__more">
				{{ current.more }}
				<button v-if="current.action" type="button" class="lnk" @click="follow">{{ current.action.label }}</button>
			</li>
		</ul>
		<p v-for="(text, index) in after" :key="`after-${index}`">
			<template v-for="(part, at) in text" :key="at"><strong v-if="part.strong">{{ part.text }}</strong><template v-else>{{ part.text }}</template></template>
		</p>
		<div v-if="current.check" class="confirm-check">
			<label class="checkbox"><input v-model="current.checked" type="checkbox"> {{ current.check }}</label>
			<p v-if="current.checkHelp" class="field__help">{{ current.checkHelp }}</p>
		</div>
		<template #footer>
			<button type="button" class="button" :autofocus="current.danger || undefined" @click="answerConfirm(current.id, false)">{{ current.cancel ?? 'Cancel' }}</button>
			<button type="button" class="button" :class="current.danger ? 'button--danger' : 'button--primary'" :autofocus="!current.danger || undefined" @click="answerConfirm(current.id, true)">{{ current.confirm }}</button>
		</template>
	</AdminModal>
</template>
