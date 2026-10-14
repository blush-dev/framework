<script setup lang="ts">
/**
 * Gets a signed preview link for an entry (D-226), then offers to open it
 * in a new tab or copy it to share. Anyone with the link can see the
 * entry until it expires, so it's made only when asked for.
 */

import { ref } from 'vue';
import { errorMessage, request, type EntryDetail, type PreviewLink } from '../api';
import { siteDateTime } from '../dates';
import { t } from '../messages';
import AdminIcon from './AdminIcon.vue';
import MessageText from './MessageText.vue';

const props = defineProps<{ entry: Pick<EntryDetail, 'id' | 'title'> }>();

// Untitled drafts are named by their file for screen readers.
const name = props.entry.title || t('preview_link_control.this_entry', 'this entry');

const link    = ref<PreviewLink | null>(null);
const busy    = ref(false);
const message = ref('');

async function make(): Promise<void> {
	busy.value    = true;
	message.value = '';

	try {
		link.value    = await request<PreviewLink>('POST', '/previews', { entry: props.entry.id });
		message.value = t('preview_link_control.ready', 'Preview link ready for “{name}”.', { name });
	} catch (caught) {
		message.value = errorMessage(caught, t('preview_link_control.failed', 'The preview link couldn\'t be made.'));
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
		message.value = t('preview_link_control.copied', 'Copied the preview link.');
	} catch {
		message.value = t('preview_link_control.copy_failed', 'Copying failed; open the preview and copy its address instead.');
	}
}
</script>

<template>
	<div class="preview-link">
		<button v-if="!link" type="button" class="button button--ghost button--small" :disabled="busy" :aria-label="t('preview_link_control.get_link_label', 'Get a preview link for {name}', { name })" @click="make">
			<AdminIcon name="link" />
			{{ busy ? t('preview_link_control.getting', 'Getting…') : t('preview_link_control.get_link', 'Get Link') }}
		</button>
		<template v-else>
			<a class="button button--ghost button--small" :href="link.url" target="_blank" rel="noopener noreferrer">
				<AdminIcon name="external-link" />
				{{ t('preview_link_control.open', 'Open') }}<span class="visually-hidden"> {{ t('preview_link_control.open_hidden', 'the preview of {name} (new tab)', { name }) }}</span>
			</a>
			<button type="button" class="button button--ghost button--small" @click="copy">
				<AdminIcon name="copy" />
				{{ t('preview_link_control.copy', 'Copy') }}<span class="visually-hidden"> {{ t('preview_link_control.copy_hidden', 'the preview link for {name}', { name }) }}</span>
			</button>
			<span class="preview-link__note"><MessageText :text="t('preview_link_control.until', 'Until {time}')"><template #time><time :datetime="link.expires">{{ siteDateTime(link.expires) }}</time></template></MessageText></span>
		</template>
		<span class="visually-hidden" aria-live="polite">{{ message }}</span>
		<span v-if="message && !link" class="preview-link__note preview-link__note--error">{{ message }}</span>
	</div>
</template>
