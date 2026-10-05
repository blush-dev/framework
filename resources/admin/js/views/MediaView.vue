<script setup lang="ts">
/**
 * Media (D-251): the library in `user/media`, newest first, from the
 * media index (D-288), as a grid of files with a search (names and
 * details), filters by kind, **Missing alt text** for images without
 * it, each marked in the grid, and **My files** for the account's own
 * uploads (D-407); then a screen for each file
 * (admin.md §8, List, then detail). **Upload** opens the media picker on
 * its Upload tab (D-268): one uploader, one set of rules about what a
 * file may be (shown to an account that may upload some kind); **Open**
 * goes to the file's screen.
 */

import { ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { mediaFacts, mediaName } from '../media';
import MediaPicker from '../components/MediaPicker.vue';
import { ApiError, request, type MediaItem, type MediaList } from '../api';
import type { IconName } from '../icons';
import { canUpload } from '../session';

const files   = ref<MediaItem[]>([]);
const total   = ref(0);
const page    = ref(1);
const pages   = ref(1);
const search  = ref('');
const kind    = ref<'any' | 'image' | 'video' | 'audio' | 'document' | 'file'>('any');
const missing = ref(false);
// Only the account's own uploads (D-407).
const mine    = ref(false);
const loading = ref(true);
const error   = ref('');

const KINDS = [
	{ key: 'any', label: 'All' },
	{ key: 'image', label: 'Images' },
	{ key: 'video', label: 'Video' },
	{ key: 'audio', label: 'Audio' },
	{ key: 'document', label: 'Documents' },
	{ key: 'file', label: 'Files' }
] as const;

let latest = 0;

async function load(more = false): Promise<void> {
	const ask    = ++latest;
	const params = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

	if (search.value.trim() !== '') {
		params.set('search', search.value.trim());
	}

	if (missing.value) {
		params.set('missing', 'alt');
	}

	if (mine.value) {
		params.set('mine', '1');
	}

	loading.value = true;
	error.value   = '';

	try {
		const answer = await request<MediaList>('GET', `/media?${params.toString()}`);

		if (ask === latest) {
			files.value = more ? [...files.value, ...answer.files] : answer.files;
			total.value = answer.total;
			page.value  = answer.page;
			pages.value = answer.pages;
		}
	} catch (caught) {
		if (ask === latest) {
			error.value = caught instanceof ApiError ? caught.message : 'The media couldn\'t be loaded.';
		}
	} finally {
		if (ask === latest) {
			loading.value = false;
		}
	}
}

let typing: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
	clearTimeout(typing);
	typing = setTimeout(() => void load(), 250);
});

watch([kind, missing, mine], () => void load());

const filtered = (): boolean => search.value !== '' || kind.value !== 'any' || missing.value || mine.value;

function clear(): void {
	search.value  = '';
	kind.value    = 'any';
	missing.value = false;
	mine.value    = false;
}

void load();

function icon(file: MediaItem): IconName {
	return file.kind === 'video' ? 'film' : (file.kind === 'audio' ? 'music' : (file.kind === 'document' ? 'file-text' : 'file'));
}

function details(file: MediaItem): string {
	return mediaFacts(file);
}

// A library file's screen is at its path under `user/media`.
function path(file: MediaItem): string[] {
	return [file.folder, file.name].filter((part) => part !== '').join('/').split('/');
}

const router    = useRouter();
const uploading = ref(false);

function uploaded(file: MediaItem): void {
	void router.push({ name: 'media-file', params: { path: path(file) } });
}

// Files uploaded and left in the picker are new to the list.
function closed(): void {
	uploading.value = false;
	void load();
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Media</h1>
			<p class="page-header__hint">Files every entry can use</p>
		</div>
		<div class="page-header__actions">
			<button v-if="canUpload()" type="button" class="button button--primary" @click="uploading = true"><AdminIcon name="upload" />Upload</button>
		</div>
	</header>

	<section class="panel" aria-labelledby="media-heading" :aria-busy="loading">
		<header class="panel__header toolbar-row">
			<h2 id="media-heading" class="visually-hidden">Library</h2>
			<div class="segmented" role="group" aria-label="Kind">
				<button v-for="item in KINDS" :key="item.key" type="button" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
			</div>
			<button type="button" class="button button--small toggle" :aria-pressed="missing" @click="missing = !missing"><AdminIcon name="triangle-alert" />Missing alt text</button>
			<button type="button" class="button button--small toggle" :aria-pressed="mine" @click="mine = !mine"><AdminIcon name="circle-user-round" />My files</button>
			<p class="panel__hint" aria-live="polite">{{ loading && !files.length ? 'Loading…' : `${total.toLocaleString()} ${total === 1 ? 'file' : 'files'}` }}</p>
			<label class="search-field search">
				<AdminIcon name="search" />
				<span class="visually-hidden">Search names and details</span>
				<input v-model="search" type="search" placeholder="Search names and details…" autocomplete="off">
			</label>
		</header>

		<div class="panel__body">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<ul v-if="files.length" class="grid">
				<li v-for="file in files" :key="file.reference">
					<RouterLink class="card" :to="{ name: 'media-file', params: { path: path(file) } }">
						<span class="card__thumb">
							<img v-if="file.kind === 'image'" :src="file.url" alt="" loading="lazy">
							<span v-if="file.kind === 'image' && file.alt === ''" class="card__warn" title="No alt text"><AdminIcon name="triangle-alert" /><span class="visually-hidden">No alt text</span></span>
							<template v-if="file.kind !== 'image'"><AdminIcon :name="icon(file)" /><span class="card__kind mono">{{ file.kind }}</span></template>
						</span>
						<span class="card__text">
							<span class="card__name" :title="file.name">{{ mediaName(file) }}</span>
							<span class="card__meta mono">{{ details(file) }}</span>
						</span>
					</RouterLink>
				</li>
			</ul>
			<div v-else-if="loading" class="grid" aria-hidden="true">
				<span v-for="card in 10" :key="card" class="skeleton card__skeleton" />
			</div>
			<div v-else-if="!error" class="empty">
				<AdminIcon name="image" />
				<p class="empty__heading">{{ missing && !search && kind === 'any' ? 'Every Image Has Alt Text' : (filtered() ? 'No Files Match' : 'The Library Is Empty') }}</p>
				<p class="empty__text">{{ missing && !search && kind === 'any' ? 'Nothing left to describe.' : (filtered() ? 'Try another name or kind.' : 'Images, video, audio, and documents you upload land here, and any entry can use them.') }}</p>
				<button v-if="filtered()" type="button" class="button" @click="clear">Clear filters</button>
				<button v-else type="button" class="button button--primary" @click="uploading = true"><AdminIcon name="upload" />Upload your first file</button>
			</div>
			<p v-if="page < pages" class="more">
				<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
			</p>
		</div>
	</section>

	<MediaPicker v-if="uploading" title="Upload to the Library" action="Open" upload-only @choose="uploaded" @close="closed" />
</template>

<style scoped>
.toolbar-row {
	flex-wrap: wrap;
}

.search {
	flex: 0 1 280px;
	margin-left: auto;
}

.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(172px, 1fr));
	gap: var(--s-4);
	margin: 0;
	padding: 0;
	list-style: none;
}

/* Every card the same height: a 4:3 thumbnail, cropped to fill it, a
   name on one line, and its details on a second. */
.card {
	display: flex;
	flex-direction: column;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg);
	text-decoration: none;
}

.card:hover {
	border-color: var(--border-strong);
	color: var(--fg);
}

.card__thumb {
	position: relative;
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	overflow: hidden;
	background: var(--surface-2);
	color: var(--fg-3);
}

.card__thumb img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.card__thumb > :deep(svg) {
	width: 28px;
	height: 28px;
	stroke-width: 1.5;
}

/* A pressed toggle reads as on: accent ink on a soft fill. */
.toggle[aria-pressed="true"] {
	border-color: var(--accent-line);
	background: var(--accent-soft);
	color: var(--accent);
}

/* An image without alt text is marked on its thumbnail, with the words
   read out: never color alone. */
.card__warn {
	position: absolute;
	top: 8px;
	right: 8px;
	display: grid;
	place-items: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: var(--warn-soft);
	color: var(--warn);
}

.card__warn :deep(svg) {
	width: 14px;
	height: 14px;
}

/* A kind only where the thumbnail is a placeholder. */
.card__kind {
	position: absolute;
	top: 9px;
	left: 9px;
	padding: 1px 4px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg-2);
	font-size: var(--text-2xs);
	letter-spacing: .04em;
	text-transform: uppercase;
}

.card__text {
	display: grid;
	gap: 4px;
	min-width: 0;
	padding: var(--s-3) var(--s-4);
	border-top: 1px solid var(--border);
}

.card__name,
.card__meta {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.card__name {
	font-size: var(--text-sm);
}

.card__meta {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.card__skeleton {
	display: block;
	height: auto;
	aspect-ratio: 172 / 180;
	border-radius: var(--r-2);
}

.more {
	margin-top: var(--s-5);
	text-align: center;
}
</style>
