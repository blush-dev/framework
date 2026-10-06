<script setup lang="ts">
/**
 * An image as the thing itself, not its file name (admin.md §8): a
 * cropped frame (4:3 as the library shows files, or 16:9 for a featured
 * image, the shape it's used in), its address and size on a line under
 * it, and **Replace** and **Remove** on it, over a veil, on hover or
 * keyboard focus. A file that isn't there says so rather than showing a
 * broken frame. With no image, it's a button to choose one. It tells
 * its owner the library's record for the file (`resolved`), which has
 * its alt text and caption.
 */

import { ref, watch } from 'vue';
import type { MediaItem } from '../api';
import { mediaFile } from '../media';
import AdminIcon from './AdminIcon.vue';

const props = withDefaults(defineProps<{
	src: string;
	wide?: boolean;
	// What the buttons name, for screen readers: "the image".
	noun?: string;
}>(), { wide: false, noun: 'the image' });

const emit = defineEmits<{
	pick: [];
	remove: [];
	resolved: [file: MediaItem | null];
}>();

// Where the image is shown from: its library file, or else its
// address; `missing` once it fails to load.
const url     = ref<string | null>(null);
const missing = ref(false);
const size    = ref('');

async function resolve(): Promise<void> {
	const src = props.src;

	missing.value = false;
	size.value    = '';

	if (src === '') {
		url.value = null;
		emit('resolved', null);

		return;
	}

	const found = await mediaFile(src);

	if (src !== props.src) {
		return;
	}

	emit('resolved', found);

	if (found !== null) {
		url.value = found.url;
	} else {
		url.value = src;
	}
}

watch(() => props.src, () => void resolve(), { immediate: true });

function loaded(event: Event): void {
	const element = event.target as HTMLImageElement;

	size.value = `${element.naturalWidth} × ${element.naturalHeight}`;
}
</script>

<template>
	<div v-if="src" class="image-preview" :class="{ 'image-preview--wide': wide }">
		<span class="image-preview__frame">
			<img v-if="url && !missing" :src="url" alt="" @load="loaded" @error="missing = true">
			<span v-else class="image-preview__missing">
				<AdminIcon name="image-off" />
				<span>{{ url === null && !missing ? 'Loading…' : 'Not found' }}</span>
			</span>
		</span>
		<span class="image-preview__veil" aria-hidden="true" />
		<span class="image-preview__actions">
			<button type="button" class="button button--small" @click="emit('pick')">Replace<span class="visually-hidden"> {{ noun }}</span></button>
			<button type="button" class="button button--small button--danger" @click="emit('remove')">Remove<span class="visually-hidden"> {{ noun }}</span></button>
		</span>
	</div>
	<div v-else class="image-preview image-preview--empty" :class="{ 'image-preview--wide': wide }">
		<button type="button" class="button" @click="emit('pick')"><AdminIcon name="image" />Choose an Image</button>
	</div>
	<p v-if="src" class="field__help mono image-preview__about">
		{{ src }}<template v-if="missing"> · not found</template><template v-else-if="size"> · {{ size }}</template>
	</p>
</template>

<style scoped>
/* The same 4:3 crop the library uses, so a file looks the same wherever
   it appears; a featured image is 16:9, the shape it's used in. */
.image-preview {
	position: relative;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface-2);
}

.image-preview__frame {
	display: block;
	aspect-ratio: 4 / 3;
}

.image-preview--wide .image-preview__frame,
.image-preview--wide.image-preview--empty {
	aspect-ratio: 16 / 9;
}

.image-preview__frame img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.image-preview__missing {
	display: grid;
	place-content: center;
	justify-items: center;
	gap: var(--s-2);
	height: 100%;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.image-preview__missing :deep(svg) {
	width: 24px;
	height: 24px;
}

/* The actions sit on the thing they act on, over a veil it still shows
   through, on hover or keyboard focus. */
.image-preview__veil,
.image-preview__actions {
	position: absolute;
	inset: 0;
	opacity: 0;
	transition: opacity 120ms;
}

.image-preview__veil {
	background: var(--surface);
}

.image-preview__actions {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: var(--s-2);
}

.image-preview:hover .image-preview__veil,
.image-preview:focus-within .image-preview__veil {
	opacity: .8;
}

.image-preview:hover .image-preview__actions,
.image-preview:focus-within .image-preview__actions {
	opacity: 1;
}

/* Without hover, the actions stay in view. */
@media (hover: none) {
	.image-preview__actions {
		align-items: flex-end;
		padding: var(--s-3);
		opacity: 1;
	}
}

.image-preview--empty {
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	border-style: dashed;
	background: var(--bg);
}

.image-preview__about {
	overflow-wrap: anywhere;
}

@media (prefers-reduced-motion: reduce) {
	.image-preview__veil,
	.image-preview__actions {
		transition: none;
	}
}
</style>
