<script setup lang="ts">
/**
 * Media (D-251): the library in `user/media`, newest first, as a grid of
 * files with a search and filters by kind, then a screen for each file
 * (admin.md §8, List, then detail). Uploading comes later.
 */

import { ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import { ApiError, request, type MediaItem, type MediaList } from '../api';
import { formatSize } from '../format';
import type { IconName } from '../icons';

const files   = ref<MediaItem[]>([]);
const total   = ref(0);
const page    = ref(1);
const pages   = ref(1);
const search  = ref('');
const kind    = ref<'any' | 'image' | 'video' | 'audio'>('any');
const loading = ref(true);
const error   = ref('');

const KINDS = [
	{ key: 'any', label: 'All' },
	{ key: 'image', label: 'Images' },
	{ key: 'video', label: 'Video' },
	{ key: 'audio', label: 'Audio' }
] as const;

let latest = 0;

async function load(more = false): Promise<void> {
	const ask    = ++latest;
	const params = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

	if (search.value.trim() !== '') {
		params.set('search', search.value.trim());
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

watch(kind, () => void load());

void load();

function icon(file: MediaItem): IconName {
	return file.kind === 'video' ? 'film' : (file.kind === 'audio' ? 'music' : 'file');
}

function details(file: MediaItem): string {
	const size = formatSize(file.size);

	return file.width !== null && file.height !== null ? `${file.width} × ${file.height} · ${size}` : size;
}

// A library file's screen is at its path under `user/media`.
function path(file: MediaItem): string[] {
	return [file.folder, file.name].filter((part) => part !== '').join('/').split('/');
}
</script>

<template>
	<header class="page-header">
		<div class="page-header__text">
			<h1 tabindex="-1">Media</h1>
			<p class="page-header__hint">Files every entry can use</p>
		</div>
	</header>

	<p class="notice notice--warn"><span>Uploading comes later. Put files in <code>user/media</code>, or beside an entry in its own folder, and they're here and in the editor's media button.</span></p>

	<section class="panel" aria-labelledby="media-heading" :aria-busy="loading">
		<header class="panel__header toolbar-row">
			<h2 id="media-heading" class="visually-hidden">Library</h2>
			<div class="kinds" role="group" aria-label="Kind">
				<button v-for="item in KINDS" :key="item.key" type="button" class="kinds__kind" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
			</div>
			<p class="panel__hint" aria-live="polite">{{ loading && !files.length ? 'Loading…' : `${total.toLocaleString()} ${total === 1 ? 'file' : 'files'}` }}</p>
			<label class="search">
				<AdminIcon name="search" />
				<span class="visually-hidden">Search file names</span>
				<input v-model="search" type="search" placeholder="Search file names…" autocomplete="off">
			</label>
		</header>

		<div class="panel__body">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
			<ul v-if="files.length" class="grid">
				<li v-for="file in files" :key="file.reference">
					<RouterLink class="card" :to="{ name: 'media-file', params: { path: path(file) } }">
						<span class="card__thumb">
							<img v-if="file.kind === 'image'" :src="file.url" alt="" loading="lazy">
							<AdminIcon v-else :name="icon(file)" />
						</span>
						<span class="card__name">{{ file.name }}</span>
						<span class="card__meta">{{ file.folder || 'media' }} · {{ details(file) }}</span>
					</RouterLink>
				</li>
			</ul>
			<div v-else-if="loading" class="grid" aria-hidden="true">
				<span v-for="card in 10" :key="card" class="skeleton card__skeleton" />
			</div>
			<div v-else-if="!error" class="empty">
				<AdminIcon name="image" />
				<p class="empty__heading">{{ search || kind !== 'any' ? 'No files match' : 'The library is empty' }}</p>
				<p class="empty__text">{{ search || kind !== 'any' ? 'Try another name or kind.' : 'Put images, video, and audio in user/media to use them in entries.' }}</p>
				<button v-if="search || kind !== 'any'" type="button" class="button" @click="search = ''; kind = 'any'">Clear filters</button>
			</div>
			<p v-if="page < pages" class="more">
				<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
			</p>
		</div>
	</section>
</template>

<style scoped>
.toolbar-row {
	flex-wrap: wrap;
}

.kinds {
	display: flex;
	gap: 5px;
}

.kinds__kind {
	padding: 3px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.kinds__kind[aria-pressed="true"] {
	border-color: var(--accent-line);
	background: var(--accent-soft);
	color: var(--accent);
	font-weight: 500;
}

.search {
	display: flex;
	align-items: center;
	gap: 7px;
	height: 30px;
	margin-left: auto;
	padding: 0 9px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	color: var(--fg-3);
}

.search:focus-within {
	border-color: var(--accent);
}

.search input {
	width: 12rem;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}

.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(158px, 1fr));
	gap: 12px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.card {
	display: flex;
	flex-direction: column;
	gap: 3px;
	padding-bottom: 9px;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	box-shadow: var(--shadow-1);
	color: var(--fg);
	text-decoration: none;
}

.card:hover {
	border-color: var(--border-strong);
	color: var(--fg);
}

.card__thumb {
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	margin-bottom: 5px;
	background: var(--surface-2);
	color: var(--fg-3);
}

.card__thumb img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.card__thumb :deep(svg) {
	width: 28px;
	height: 28px;
	stroke-width: 1.5;
}

.card__name,
.card__meta {
	overflow: hidden;
	padding: 0 10px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.card__name {
	font-size: var(--text-sm);
	font-weight: 500;
}

.card__meta {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.card__skeleton {
	display: block;
	aspect-ratio: 1;
	border-radius: var(--r-2);
}

.more {
	margin-top: 14px;
	text-align: center;
}
</style>
