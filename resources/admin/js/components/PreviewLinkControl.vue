<script setup lang="ts">
/**
 * Gets a signed preview link for an entry (D-226), then offers to open it
 * in a new tab or copy it to share. Anyone with the link can see the
 * entry until it expires, so it's made only when asked for.
 */

import { ref } from 'vue';
import { ApiError, request, type EntrySummary, type PreviewLink } from '../api';
import { formatDate } from '../format';
import AdminIcon from './AdminIcon.vue';

const props = defineProps<{ entry: Pick<EntrySummary, 'path' | 'title'> }>();

// Untitled drafts are named by their file for screen readers.
const name = props.entry.title || 'this entry';

const link    = ref<PreviewLink | null>(null);
const busy    = ref(false);
const message = ref('');

async function make(): Promise<void> {
	busy.value    = true;
	message.value = '';

	try {
		link.value    = await request<PreviewLink>('POST', '/previews', { entry: props.entry.path });
		message.value = `Preview link ready for “${name}”.`;
	} catch (caught) {
		message.value = caught instanceof ApiError ? caught.message : 'The preview link couldn\'t be made.';
	} finally {
		busy.value = false;
	}
}

async function copy(): Promise<void> {
	if (link.value === null) {
		return;
	}

	try {
		await navigator.clipboard.writeText(link.value.url);
		message.value = 'Copied the preview link.';
	} catch {
		message.value = 'Copying failed; open the preview and copy its address instead.';
	}
}
</script>

<template>
	<div class="preview-link">
		<button v-if="!link" type="button" class="button button--ghost button--small" :disabled="busy" :aria-label="`Get a preview link for ${name}`" @click="make">
			<AdminIcon name="link" />
			{{ busy ? 'Getting…' : 'Get link' }}
		</button>
		<template v-else>
			<a class="button button--ghost button--small" :href="link.url" target="_blank" rel="noopener noreferrer">
				<AdminIcon name="external-link" />
				Open<span class="visually-hidden"> the preview of {{ name }} (new tab)</span>
			</a>
			<button type="button" class="button button--ghost button--small" @click="copy">
				<AdminIcon name="copy" />
				Copy<span class="visually-hidden"> the preview link for {{ name }}</span>
			</button>
			<span class="preview-link__note">Until <time :datetime="link.expires">{{ formatDate(link.expires) }}</time></span>
		</template>
		<span class="visually-hidden" aria-live="polite">{{ message }}</span>
		<span v-if="message && !link" class="preview-link__note preview-link__note--error">{{ message }}</span>
	</div>
</template>
