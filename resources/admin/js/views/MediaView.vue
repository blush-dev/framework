<script setup lang="ts">
/**
 * Media (D-251): the library in `user/media`, newest first, from the
 * media index (D-288), as a grid of files with the entries list's shape
 * (D-474): **All** and **Mine** tabs (the account's own uploads, D-407)
 * with their counts, then a toolbar with a search (names and details) and
 * a kind filter, then a panel headed by the tab; then a screen for each
 * file (admin.md §8, List, then detail). **Upload** opens the media picker
 * with only its Upload panel (D-268, D-473): one uploader, one set of
 * rules about what a file may be (shown to an account that may upload
 * some kind); **Open** goes to the file's screen.
 */

import { computed, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AdminSelect from '../components/AdminSelect.vue';
import { mediaFacts, mediaName } from '../media';
import MediaPicker from '../components/MediaPicker.vue';
import { ApiError, request, type MediaItem, type MediaList } from '../api';
import { plural } from '../format';
import type { IconName } from '../icons';
import { canUpload } from '../session';

type Kind = 'any' | 'image' | 'video' | 'audio' | 'document' | 'file';

const files   = ref<MediaItem[]>([]);
const total   = ref(0);
const page    = ref(1);
const pages   = ref(1);
const search  = ref('');
const kind    = ref<Kind>('any');
const loading = ref(true);
const error   = ref('');

const route  = useRoute();
const router = useRouter();

// The tab: every file, or only the account's own uploads (D-407).
const mine = computed(() => route.query.mine === '1');

const TABS = [
	{ key: 'all', label: 'All' },
	{ key: 'mine', label: 'Mine' }
] as const;

// Each tab's count, whatever the search and kind.
const counts = ref<{ all?: number; mine?: number }>({});

const KINDS: { value: Kind; label: string }[] = [
	{ value: 'any', label: 'All kinds' },
	{ value: 'image', label: 'Images' },
	{ value: 'video', label: 'Video' },
	{ value: 'audio', label: 'Audio' },
	{ value: 'document', label: 'Documents' },
	{ value: 'file', label: 'Other files' }
];

const kindValue = computed({
	get: () => kind.value as string,
	set: (value: string) => {
		kind.value = KINDS.find((item) => item.value === value)?.value ?? 'any';
	}
});

let latest = 0;

async function load(more = false): Promise<void> {
	const ask    = ++latest;
	const params = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

	if (search.value.trim() !== '') {
		params.set('search', search.value.trim());
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

watch([kind, mine], () => void load());

const filtered = computed(() => search.value !== '' || kind.value !== 'any');

function clear(): void {
	search.value = '';
	kind.value   = 'any';
}

async function count(): Promise<void> {
	try {
		const [all, own] = await Promise.all([
			request<MediaList>('GET', '/media?per=1'),
			request<MediaList>('GET', '/media?per=1&mine=1')
		]);

		counts.value = { all: all.total, mine: own.total };
	} catch {
		counts.value = {};
	}
}

void load();
void count();

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

const uploading = ref(false);

function uploaded(file: MediaItem): void {
	void router.push({ name: 'media-file', params: { path: path(file) } });
}

// Files uploaded and left in the picker are new to the list.
function closed(): void {
	uploading.value = false;
	void load();
	void count();
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

	<nav class="status-tabs" aria-label="Owner">
		<RouterLink v-for="tab in TABS" :key="tab.key" class="status-tabs__tab" :to="{ query: tab.key === 'mine' ? { mine: '1' } : {} }" :aria-current="(tab.key === 'mine') === mine ? 'page' : undefined">
			{{ tab.label }}
			<span v-if="counts[tab.key] !== undefined" class="status-tabs__count">{{ counts[tab.key] }}</span>
		</RouterLink>
	</nav>

	<div class="toolbar" role="search">
		<label class="search-field media-search">
			<AdminIcon name="search" />
			<span class="visually-hidden">Search names and details</span>
			<input v-model="search" type="search" placeholder="Search names and details" autocomplete="off">
		</label>
		<div class="media-filter">
			<label class="visually-hidden" for="media-kind">Kind</label>
			<AdminSelect id="media-kind" v-model="kindValue" :options="KINDS" />
		</div>
		<button v-if="filtered" type="button" class="button button--ghost" @click="clear">Clear filters</button>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<section class="panel" aria-labelledby="media-heading" :aria-busy="loading">
		<header class="panel__header">
			<h2 id="media-heading">{{ mine ? 'Mine' : 'All' }}</h2>
			<p class="panel__hint" aria-live="polite">{{ loading && !files.length ? 'Loading…' : plural(total, 'file', 'files') }}</p>
		</header>

		<div class="panel__body">
			<ul v-if="files.length" class="grid">
				<li v-for="file in files" :key="file.reference">
					<RouterLink class="card" :to="{ name: 'media-file', params: { path: path(file) } }">
						<span class="card__thumb">
							<img v-if="file.kind === 'image'" :src="file.url" alt="" loading="lazy">
							<template v-else><AdminIcon :name="icon(file)" /><span class="card__kind mono">{{ file.kind }}</span></template>
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
				<AdminIcon :name="filtered ? 'search' : 'image'" />
				<p class="empty__heading">{{ filtered ? 'No Files Match' : (mine ? 'No Files of Yours' : 'The Library Is Empty') }}</p>
				<p class="empty__text">{{ filtered ? 'Try another name or kind.' : (mine ? 'Files you upload show up here. The All tab shows everyone\'s.' : 'Images, video, audio, and documents you upload land here, and any entry can use them.') }}</p>
				<button v-if="filtered" type="button" class="button" @click="clear">Clear filters</button>
				<button v-else-if="canUpload()" type="button" class="button button--primary" @click="uploading = true"><AdminIcon name="upload" />Upload {{ mine && counts.all ? 'a file' : 'your first file' }}</button>
			</div>
			<p v-if="page < pages" class="more">
				<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
			</p>
		</div>
	</section>

	<MediaPicker v-if="uploading" title="Upload to the Library" action="Open" upload-only @choose="uploaded" @close="closed" />
</template>

<style scoped>
.media-search {
	flex: 1 1 16rem;
	max-width: 24rem;
}

/* A filter's select is as wide as it needs, not the row (§7, Selects). */
.media-filter {
	flex: none;
	width: auto;
	min-width: 9rem;
	max-width: 16rem;
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
