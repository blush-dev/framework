<script setup lang="ts">
/**
 * Drafts and scheduled entries the account may edit: an author's own, or
 * everyone's for an editor. Scheduled entries go live on their own at
 * their published time.
 */

import { onMounted, ref } from 'vue';
import { ApiError, request, type EntryList, type EntrySummary } from '../api';
import EntryTable from '../components/EntryTable.vue';

const drafts    = ref<EntrySummary[] | null>(null);
const scheduled = ref<EntrySummary[] | null>(null);
const error     = ref('');

onMounted(async () => {
	try {
		const [draftList, scheduledList] = await Promise.all([
			request<EntryList>('GET', '/entries?status=draft'),
			request<EntryList>('GET', '/entries?status=scheduled')
		]);

		drafts.value    = draftList.entries;
		scheduled.value = scheduledList.entries;
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The entries couldn\'t be loaded.';
	}
});
</script>

<template>
	<h1 tabindex="-1">Drafts</h1>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<p v-else-if="drafts === null" aria-live="polite">Loading…</p>

	<template v-else>
		<section class="panel" aria-labelledby="drafts-heading">
			<h2 id="drafts-heading">Drafts</h2>
			<EntryTable v-if="drafts.length" :entries="drafts" labelledby="drafts-heading" date-label="Last changed" date-key="updated" />
			<p v-else>No drafts.</p>
		</section>

		<section id="scheduled" class="panel" aria-labelledby="scheduled-heading">
			<h2 id="scheduled-heading">Scheduled</h2>
			<p class="panel__intro">These go live on their own at their publish time.</p>
			<EntryTable v-if="scheduled?.length" :entries="scheduled" labelledby="scheduled-heading" date-label="Goes live" date-key="published" />
			<p v-else>Nothing is scheduled.</p>
		</section>
	</template>
</template>
