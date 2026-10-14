<script setup lang="ts">
/**
 * A media field's file that isn't shown as a picture (a video, a sound,
 * any other file; D-766, D-773): the library's name for the file the
 * field's id names, with **Choose** opening the media picker and
 * **Clear**. A value naming no file in the library says so; an older
 * value, a path, is named by its file too.
 */

import { ref, watch } from 'vue';
import { mediaFile, mediaName } from '../media';
import MessageText from './MessageText.vue';

const props = defineProps<{
	id: string;
	// What the buttons name, for screen readers: "the video".
	noun: string;
	describedBy?: string;
	invalid?: boolean;
	disabled?: boolean;
}>();

const model = defineModel<string>({ required: true });

const emit = defineEmits<{ pick: [] }>();

// The file's name, `null` while it's looked up, `''` for none found.
const name = ref<string | null>('');

watch(model, async (value) => {
	if (value.trim() === '') {
		name.value = '';

		return;
	}

	name.value = null;

	const found = await mediaFile(value);

	if (value === model.value) {
		name.value = found === null ? '' : mediaName(found);
	}
}, { immediate: true });
</script>

<template>
	<div class="media-choice-row">
		<span :id="id" class="media-choice mono" :class="{ 'is-empty': model === '', 'is-missing': model !== '' && name === '' }" role="status" :aria-describedby="describedBy" :aria-invalid="invalid ? 'true' : undefined">
			{{ model === '' ? t('media_choice.none', 'No file chosen') : (name === null ? t('media_choice.looking', 'Looking it up…') : (name === '' ? t('media_choice.missing', 'Not in the library') : name)) }}
		</span>
		<button type="button" class="button button--small" :disabled="disabled" @click="emit('pick')"><MessageText :text="t('media_choice.choose', 'Choose {noun}')"><template #noun><span class="visually-hidden">{{ noun }}</span></template></MessageText></button>
		<button v-if="model !== ''" type="button" class="button button--ghost button--small" :disabled="disabled" @click="model = ''"><MessageText :text="t('media_choice.clear', 'Clear {noun}')"><template #noun><span class="visually-hidden">{{ noun }}</span></template></MessageText></button>
	</div>
</template>

<style scoped>
.media-choice-row {
	display: flex;
	gap: 6px;
}

.media-choice-row .button {
	flex: none;
}

.media-choice {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	align-self: center;
	color: var(--color-text);
	font-size: var(--font-size-small);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.media-choice.is-empty {
	color: var(--color-text-muted);
}

.media-choice.is-missing {
	color: var(--color-danger);
}
</style>
