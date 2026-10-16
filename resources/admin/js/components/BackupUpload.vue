<script setup lang="ts">
/**
 * Upload a Backup (the backups sketch, D-886, D-898): bringing in a
 * backup from another server, or from your own computer, built as the
 * extension uploader is (`InstallModal`): a drop zone and a button with
 * the limit said first, a file dropped anywhere on the modal counting,
 * then the file being sent, what was added, or why nothing was. It joins
 * the list, and nothing about the site changes until it's restored.
 */

import { ref, useTemplateRef, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import AdminModal from './AdminModal.vue';
import UploadDrop from './UploadDrop.vue';
import UploadSlab from './UploadSlab.vue';
import { errorMessage, uploadWithProgress } from '../api';
import { siteDateTime } from '../dates';
import { useFileDrop } from '../drop';
import { formatSize } from '../format';
import { t } from '../messages';
import type { BackupInfo } from '../backups';

const props = defineProps<{
	open: boolean;
	// The largest file the server takes, or `null` when it won't say.
	limit: number | null;
}>();

const emit = defineEmits<{
	close: [];
	added: [backup: BackupInfo];
}>();

type Phase = 'idle' | 'working' | 'done' | 'error';

const input   = useTemplateRef<HTMLInputElement>('input');
const phase   = ref<Phase>('idle');
const file    = ref<File | null>(null);
const sent    = ref(0);
const added   = ref<BackupInfo | null>(null);
const failure = ref('');

// Starts again each time it opens.
watch(() => props.open, (open) => {
	if (open) {
		phase.value   = 'idle';
		file.value    = null;
		added.value   = null;
		failure.value = '';
	}
});

function pick(): void {
	if (input.value !== null) {
		input.value.value = '';
		input.value.click();
	}
}

function picked(event: Event): void {
	const chosen = (event.target as HTMLInputElement).files?.[0];

	if (chosen) {
		void send(chosen);
	}
}

const { dragging, over: dragOver, leave: dragLeave, drop: dropped } = useFileDrop((files) => {
	if (files[0]) {
		void send(files[0]);
	}
}, () => phase.value !== 'working');

async function send(chosen: File): Promise<void> {
	file.value = chosen;

	if (!chosen.name.toLowerCase().endsWith('.zip')) {
		phase.value   = 'error';
		failure.value = t('backup_upload.not_zip', 'A backup is a .zip; {name} isn\'t one.', { name: chosen.name });

		return;
	}

	if (props.limit !== null && chosen.size > props.limit) {
		phase.value   = 'error';
		failure.value = t('backup_upload.too_large', '{name} is larger than the server takes (up to {size}).', { name: chosen.name, size: formatSize(props.limit) });

		return;
	}

	phase.value = 'working';
	sent.value  = 0;

	try {
		const answer = await uploadWithProgress<{ backup: BackupInfo }>('/backups/upload', chosen, {}, (part) => {
			sent.value = part;
		});

		added.value = answer.backup;
		phase.value = 'done';
		emit('added', answer.backup);
	} catch (caught) {
		failure.value = errorMessage(caught, t('backup_upload.failed', '{name} couldn\'t be added.', { name: chosen.name }));
		phase.value   = 'error';
	}
}
</script>

<template>
	<AdminModal :open="open" :title="t('backup_upload.title', 'Upload a Backup')" @close="phase !== 'working' && emit('close')" @dragover="dragOver" @dragleave="dragLeave" @drop="dropped">
		<p v-if="phase === 'idle'">{{ t('backup_upload.intro', 'Bring in a backup from another server, or from your own computer. It joins the list, and nothing changes until you restore it.') }}</p>

		<UploadDrop v-if="phase === 'idle'" :heading="t('backup_upload.drop', 'Drop a backup here')" :hint="limit !== null ? t('backup_upload.limit', 'Up to {size} · ZIP', { size: formatSize(limit) }) : null" :over="dragging">
			{{ t('backup_upload.drop_text', 'A .zip made by Blush\'s backups, as it was downloaded.') }}
			<template #button><button type="button" class="button button--primary" @click="pick">{{ t('backup_upload.choose', 'Choose File') }}</button></template>
		</UploadDrop>

		<UploadSlab v-else-if="phase === 'working' && file" role="status" :progress="sent">
			<template #head><span class="spin" aria-hidden="true" />{{ sent < 1 ? t('backup_upload.uploading', 'Uploading {name}…', { name: file.name }) : t('backup_upload.checking', 'Checking it…') }}</template>
			<p><b>{{ file.name }}</b> · {{ formatSize(file.size) }}</p>
		</UploadSlab>

		<UploadSlab v-else-if="phase === 'done' && added" kind="good" role="status">
			<template #head><AdminIcon name="circle-check" />{{ t('backup_upload.added', 'Added the backup from {date}', { date: siteDateTime(added.created) }) }}</template>
			<p>{{ t('backup_upload.added_text', 'It\'s in the list. Nothing about the site changed: restore it to use it.') }}</p>
			<dl>
				<dt>{{ t('backup_upload.file', 'File') }}</dt>
				<dd class="mono">{{ added.file }}</dd>
				<dt>{{ t('backup_upload.size', 'Size') }}</dt>
				<dd>{{ formatSize(added.size) }}</dd>
			</dl>
		</UploadSlab>

		<UploadSlab v-else-if="phase === 'error'" kind="bad" role="alert">
			<template #head><AdminIcon name="triangle-alert" />{{ t('backup_upload.nothing', 'Nothing was added') }}</template>
			<p>{{ failure }}</p>
		</UploadSlab>

		<input ref="input" type="file" accept=".zip,application/zip" hidden @change="picked">

		<template #footer>
			<button v-if="phase === 'error'" type="button" class="button" @click="pick">{{ t('backup_upload.another', 'Choose Another File') }}</button>
			<button type="button" class="button" :class="{ 'button--primary': phase === 'done' }" :disabled="phase === 'working'" @click="emit('close')">{{ phase === 'done' ? t('common.done', 'Done') : t('common.close', 'Close') }}</button>
		</template>
	</AdminModal>
</template>
