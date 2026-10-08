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
import EmptyState from '../components/EmptyState.vue';
import MediaCard from '../components/MediaCard.vue';
import MediaPicker from '../components/MediaPicker.vue';
import { mediaFacts, useMediaList } from '../media';
import { request, type MediaItem, type MediaList } from '../api';
import { loadCounts } from '../counts';
import { plural } from '../format';
import { canUpload } from '../session';

type Kind = 'any' | 'image' | 'video' | 'audio' | 'document' | 'file';

const search = ref('');
const kind   = ref<Kind>('any');

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

const { files, total, page, pages, loading, error, indexing, load } = useMediaList(kind, search, () => mine.value);

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

// Once the library has caught up (D-626), its counts here and in the
// panel are counted again.
watch(indexing, (now, before) => {
	if (now === null && before !== null) {
		void count();
		void loadCounts();
	}
});

// Its facts, and how many sizes an image has (D-488), which aren't
// cards of their own.
function details(file: MediaItem): string {
	return [mediaFacts(file), file.sizeCount ? plural(file.sizeCount, 'size') : ''].filter((part) => part !== '').join(' · ');
}

// A library file's screen is at its path under `user/media`.
function path(file: MediaItem): string[] {
	return [file.folder, file.name].filter((part) => part !== '').join('/').split('/');
}

const uploading = ref(false);

function uploaded([file]: MediaItem[]): void {
	if (file !== undefined) {
		void router.push({ name: 'media-file', params: { path: path(file) } });
	}
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
		<label class="search-field toolbar__search">
			<AdminIcon name="search" />
			<span class="visually-hidden">Search names and details</span>
			<input v-model="search" type="search" placeholder="Search names and details" autocomplete="off">
		</label>
		<div class="toolbar__filter">
			<label class="visually-hidden" for="media-kind">Kind</label>
			<AdminSelect id="media-kind" v-model="kindValue" :options="KINDS" />
		</div>
		<button v-if="filtered" type="button" class="button button--ghost" @click="clear">Clear Filters</button>
	</div>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
	<div v-if="indexing !== null" class="notice" aria-live="polite">
		<AdminIcon name="refresh-cw" />
		<span class="notice__text">Reading the library's files ({{ indexing }}%). Files not read yet aren't listed, or show their details from before; the list fills in when it's done.</span>
	</div>

	<section class="panel" aria-labelledby="media-heading" :aria-busy="loading">
		<header class="panel__header">
			<h2 id="media-heading">{{ mine ? 'Mine' : 'All' }}</h2>
			<p class="panel__hint" aria-live="polite">{{ loading && !files.length ? 'Loading…' : plural(total, 'file', 'files') }}</p>
		</header>

		<div class="panel__body">
			<ul v-if="files.length" class="grid">
				<li v-for="file in files" :key="file.reference">
					<MediaCard :file="file" :details="details(file)" :to="{ name: 'media-file', params: { path: path(file) } }" />
				</li>
			</ul>
			<div v-else-if="loading" class="grid" aria-hidden="true">
				<span v-for="card in 10" :key="card" class="skeleton card__skeleton" />
			</div>
			<EmptyState v-else-if="!error" :icon="filtered ? 'search' : 'image'" :heading="filtered ? 'No Files Match' : (mine ? 'No Files of Yours' : 'The Library Is Empty')" :text="filtered ? 'Try another name or kind.' : (mine ? 'Files you upload show up here. The All tab shows everyone\'s.' : 'Images, video, audio, and documents you upload land here, and any entry can use them.')">
				<template #actions>
					<button v-if="filtered" type="button" class="button" @click="clear">Clear Filters</button>
					<button v-else-if="canUpload()" type="button" class="button button--primary" @click="uploading = true"><AdminIcon name="upload" />Upload {{ mine && counts.all ? 'a file' : 'your first file' }}</button>
				</template>
			</EmptyState>
			<p v-if="page < pages" class="more">
				<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show More' }}</button>
			</p>
		</div>
	</section>

	<MediaPicker v-if="uploading" title="Upload to the Library" action="Open" upload-only @choose="uploaded" @close="closed" />
</template>

<style scoped>
.grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(172px, 1fr));
	gap: var(--s-4);
	margin: 0;
	padding: 0;
	list-style: none;
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
