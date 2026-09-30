<script setup lang="ts">
/**
 * One file in the media library (D-251): a preview, its alt text and
 * caption (D-269), its facts, and what to write to use it.
 *
 * The alt text and caption are the library's own, kept in `user/data`
 * (`PATCH media/{path}`); the editor fills them in when the file is
 * inserted as an image (D-272: pages don't read them). What an entry
 * writes is its own, and editing it there never changes the library's.
 * They're saved when asked, and leaving with changes unsaved asks first.
 */

import { computed, ref, watch } from 'vue';
import { onBeforeRouteLeave, RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type MediaItem } from '../api';
import { attributeText, imageText } from '../markdown';
import { formatDate, formatSize } from '../format';
import { forgetFile } from '../media';
import { screenTitle } from '../screen';
import { toast } from '../toast';

const route = useRoute();
const file  = ref<MediaItem | null>(null);
const error = ref('');

const path = computed(() => {
	const segments = route.params.path;

	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
});

const address = computed(() => `/media/${path.value.split('/').map(encodeURIComponent).join('/')}`);

// The fields, as typed.
const alt     = ref('');
const caption = ref('');
const saving  = ref(false);
const failure = ref('');

const changed = computed(() => file.value !== null && (alt.value.trim() !== file.value.alt || caption.value.trim() !== file.value.caption));

function fill(item: MediaItem): void {
	file.value    = item;
	alt.value     = item.alt;
	caption.value = item.caption;
}

watch(path, async () => {
	file.value    = null;
	error.value   = '';
	failure.value = '';

	try {
		fill(await request<MediaItem>('GET', address.value));
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The file couldn\'t be loaded.';
	}
}, { immediate: true });

watch(file, (value) => {
	screenTitle.value = value?.name ?? null;
});

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	saving.value  = true;
	failure.value = '';

	try {
		fill(await request<MediaItem>('PATCH', address.value, { alt: alt.value, caption: caption.value }));
		forgetFile(file.value?.reference ?? '');
		toast('Saved');
	} catch (caught) {
		failure.value = caught instanceof ApiError ? caught.message : 'It couldn\'t be saved.';
	} finally {
		saving.value = false;
	}
}

onBeforeRouteLeave(() => !changed.value || window.confirm('Leave without saving? Your changes will be lost.'));

// What an entry would write to show it: an image is Markdown, with the
// library's alt text and caption (D-267, D-268); the rest are components.
const snippet = computed(() => {
	const item = file.value;

	if (item === null) {
		return '';
	}

	if (item.kind === 'image') {
		return imageText(item.reference, item.alt, item.caption).text;
	}

	const name = item.kind === 'video' ? 'video' : (item.kind === 'audio' ? 'audio' : 'file');

	return `::blush/${name}{${attributeText('src', item.reference)}}`;
});

async function copy(text: string, what: string): Promise<void> {
	try {
		await navigator.clipboard.writeText(text);
		toast(`Copied the ${what}`);
	} catch {
		toast(`The ${what} couldn't be copied`);
	}
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">{{ file?.name ?? 'File' }}</h1>
			<p v-if="file" class="page-header__hint">
				{{ file.mime }}{{ file.width !== null && file.height !== null ? ` · ${file.width} × ${file.height}` : '' }} · {{ formatSize(file.size) }}
			</p>
		</div>
		<div class="page-header__actions">
			<RouterLink class="button" :to="{ name: 'media' }"><AdminIcon name="arrow-left" />All media</RouterLink>
			<a v-if="file" class="button" :href="file.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />Open<span class="visually-hidden"> the file (new tab)</span></a>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="file" class="detail">
		<section class="panel preview" aria-label="Preview">
			<img v-if="file.kind === 'image'" :src="file.url" :alt="`Preview of ${file.name}`">
			<video v-else-if="file.kind === 'video'" :src="file.url" controls preload="metadata" />
			<audio v-else-if="file.kind === 'audio'" :src="file.url" controls preload="metadata" />
			<AdminIcon v-else name="file" />
		</section>

		<div class="detail__side">
			<form class="panel" aria-labelledby="text-heading" @submit.prevent="save">
				<header class="panel__header">
					<h2 id="text-heading">Details</h2>
				</header>
				<div class="panel__body text">
					<div class="field">
						<label for="media-alt">Alt text</label>
						<textarea id="media-alt" v-model="alt" rows="3" placeholder="What it shows, for anyone who can't see it." aria-describedby="media-alt-help" />
						<p v-if="file.kind === 'image' && alt.trim() === ''" id="media-alt-help" class="field__help text__warn"><AdminIcon name="triangle-alert" />No alt text. Images inserted from the library start without a description.</p>
						<p v-else id="media-alt-help" class="field__help">Filled in when it's inserted as an image. Changing it here doesn't change what entries already wrote.</p>
					</div>
					<div class="field">
						<label for="media-caption">Caption</label>
						<input id="media-caption" v-model="caption" autocomplete="off" aria-describedby="media-caption-help">
						<p id="media-caption-help" class="field__help">Shown under the image where it's inserted, as its quoted title.</p>
					</div>
					<p v-if="failure" class="field__error" role="alert">{{ failure }}</p>
					<div class="text__actions">
						<button type="submit" class="button button--primary button--small" :disabled="!changed || saving">{{ saving ? 'Saving…' : 'Save' }}</button>
						<span class="field__help">Kept in <code>user/data/media</code>, not in the file.</span>
					</div>
				</div>
			</form>

			<section class="panel" aria-labelledby="details-heading">
				<header class="panel__header">
					<h2 id="details-heading">File</h2>
				</header>
				<dl class="panel__body facts">
					<div><dt>Folder</dt><dd class="mono">user/media/{{ file.folder }}</dd></div>
					<div><dt>Type</dt><dd class="mono">{{ file.mime }}</dd></div>
					<div><dt>Size</dt><dd>{{ formatSize(file.size) }}</dd></div>
					<div v-if="file.width !== null && file.height !== null"><dt>Dimensions</dt><dd>{{ file.width }} × {{ file.height }}</dd></div>
					<div><dt>Changed</dt><dd>{{ formatDate(file.modified) }}</dd></div>
				</dl>
			</section>

			<section class="panel" aria-labelledby="use-heading">
				<header class="panel__header">
					<h2 id="use-heading">Use It</h2>
				</header>
				<div class="panel__body use">
					<p class="field__help">In the editor, the media button inserts it. In Markdown or front matter, it's:</p>
					<div class="use__row">
						<code>{{ file.reference }}</code>
						<button type="button" class="button button--small" @click="copy(file.reference, 'address')"><AdminIcon name="copy" />Copy</button>
					</div>
					<div class="use__row">
						<code>{{ snippet }}</code>
						<button type="button" class="button button--small" @click="copy(snippet, file.kind === 'image' ? 'Markdown' : 'component')"><AdminIcon name="copy" />Copy</button>
					</div>
				</div>
			</section>
		</div>
	</div>
</template>

<style scoped>
.detail {
	display: grid;
	grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
	align-items: start;
	gap: 16px;
}

.detail__side {
	display: grid;
	gap: 16px;
}

.preview {
	display: grid;
	place-items: center;
	min-height: 240px;
	padding: 16px;
	background: var(--surface-2);
	color: var(--fg-3);
}

.preview img,
.preview video {
	max-width: 100%;
	max-height: 60vh;
	border-radius: var(--r-1);
}

.preview audio {
	width: 100%;
}

.preview > :deep(svg) {
	width: 48px;
	height: 48px;
	stroke-width: 1.25;
}

.facts {
	display: grid;
	gap: 8px;
	margin: 0;
}

.facts > * + * {
	margin-top: 0;
}

.facts div {
	display: flex;
	justify-content: space-between;
	gap: 12px;
}

.facts dt {
	color: var(--fg-2);
}

.facts dd {
	margin: 0;
	text-align: right;
	overflow-wrap: anywhere;
}

.text {
	display: grid;
	gap: var(--s-4);
}

.text__warn {
	display: flex;
	align-items: flex-start;
	gap: 6px;
	color: var(--warn);
}

.text__warn :deep(svg) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 1px;
}

.text__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--s-3);
}

.use__row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.use__row code {
	flex: 1;
	min-width: 0;
	padding: 5px 8px;
	overflow-x: auto;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	font-size: var(--text-sm);
	white-space: nowrap;
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
