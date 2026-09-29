<script setup lang="ts">
/**
 * One file in the media library (D-251): a preview, its facts, and what
 * to write to use it. Its metadata (alt text, captions) comes with D-238.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type MediaItem } from '../api';
import { attributeText } from '../markdown';
import { formatDate, formatSize } from '../format';
import { screenTitle } from '../screen';
import { toast } from '../toast';

const route = useRoute();
const file  = ref<MediaItem | null>(null);
const error = ref('');

const path = computed(() => {
	const segments = route.params.path;

	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
});

watch(path, async (value) => {
	file.value  = null;
	error.value = '';

	try {
		file.value = await request<MediaItem>('GET', `/media/${value.split('/').map(encodeURIComponent).join('/')}`);
	} catch (caught) {
		error.value = caught instanceof ApiError ? caught.message : 'The file couldn\'t be loaded.';
	}
}, { immediate: true });

watch(file, (value) => {
	screenTitle.value = value?.name ?? null;
});

// What an entry would write to show it.
const directive = computed(() => {
	const item = file.value;

	if (item === null) {
		return '';
	}

	const name = item.kind === 'image' ? 'figure' : (item.kind === 'video' ? 'video' : (item.kind === 'audio' ? 'audio' : 'file'));

	return `::blush/${name}[]{${attributeText('src', item.reference)}}`;
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
			<section class="panel" aria-labelledby="details-heading">
				<header class="panel__header">
					<h2 id="details-heading">Details</h2>
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
					<h2 id="use-heading">Use it</h2>
				</header>
				<div class="panel__body use">
					<p class="field__help">In the editor, the media button inserts it. In Markdown or front matter, it's:</p>
					<div class="use__row">
						<code>{{ file.reference }}</code>
						<button type="button" class="button button--small" @click="copy(file.reference, 'address')"><AdminIcon name="copy" />Copy</button>
					</div>
					<div class="use__row">
						<code>{{ directive }}</code>
						<button type="button" class="button button--small" @click="copy(directive, 'component')"><AdminIcon name="copy" />Copy</button>
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
