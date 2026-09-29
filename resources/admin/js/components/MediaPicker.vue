<script setup lang="ts">
/**
 * The media picker (admin.md §8, The inserters; D-247): a modal, since a
 * file is chosen once and then it's over, and the same picker wherever a
 * file is chosen (the editor's media button, and media fields and
 * options). It lists the files beside the entry, when it's a page
 * bundle, then the library, newest first (`GET media`), with a search,
 * filters by kind, and more on request.
 *
 * Choosing a file selects it; **Insert** (or a double click) uses it.
 * Escape, **Cancel**, or the close button leave without one. It's a
 * native dialog, so focus stays inside while it's open and returns
 * afterwards.
 */

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { ApiError, request, type MediaItem, type MediaList } from '../api';
import { formatSize } from '../format';
import type { IconName } from '../icons';

const props = defineProps<{
	// The entry being edited, for the files beside it.
	entry?: string;
	title?: string;
	action?: string;
}>();

const emit = defineEmits<{
	choose: [file: MediaItem];
	close: [];
}>();

const dialog   = ref<HTMLDialogElement | null>(null);
const search   = ref('');
const kind     = ref<'any' | 'image' | 'video' | 'audio'>('any');
const files    = ref<MediaItem[]>([]);
const beside   = ref<MediaItem[] | null>(null);
const total    = ref(0);
const page     = ref(1);
const pages    = ref(1);
const loading  = ref(true);
const error    = ref('');
const selected = ref<MediaItem | null>(null);

const KINDS = [
	{ key: 'any', label: 'All' },
	{ key: 'image', label: 'Images' },
	{ key: 'video', label: 'Video' },
	{ key: 'audio', label: 'Audio' }
] as const;

// Only the files beside the entry that pass the filters.
const besideShown = computed(() => (beside.value ?? []).filter((file) => (kind.value === 'any' || file.kind === kind.value)
	&& (search.value.trim() === '' || file.name.toLowerCase().includes(search.value.trim().toLowerCase()))));

let latest = 0;

async function load(more = false): Promise<void> {
	const ask = ++latest;
	const params = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

	if (search.value.trim() !== '') {
		params.set('search', search.value.trim());
	}

	if (props.entry !== undefined && props.entry !== '') {
		params.set('entry', props.entry);
	}

	loading.value = true;
	error.value   = '';

	try {
		const answer = await request<MediaList>('GET', `/media?${params.toString()}`);

		if (ask !== latest) {
			return;
		}

		files.value  = more ? [...files.value, ...answer.files] : answer.files;
		beside.value = answer.beside;
		total.value  = answer.total;
		page.value   = answer.page;
		pages.value  = answer.pages;
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

// A search waits for a pause in typing.
let typing: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
	clearTimeout(typing);
	typing = setTimeout(() => void load(), 250);
});

watch(kind, () => void load());

function icon(file: MediaItem): IconName {
	return file.kind === 'video' ? 'film' : (file.kind === 'audio' ? 'music' : 'file');
}

function details(file: MediaItem): string {
	const size = formatSize(file.size);

	return file.width !== null && file.height !== null ? `${file.width} × ${file.height} · ${size}` : size;
}

function use(file: MediaItem | null): void {
	if (file !== null) {
		emit('choose', file);
	}
}

onMounted(() => {
	dialog.value?.showModal();
	void load();
});

onBeforeUnmount(() => {
	clearTimeout(typing);
});
</script>

<template>
	<dialog ref="dialog" class="picker" aria-labelledby="media-picker-heading" @close="emit('close')" @keydown.esc.prevent.stop="dialog?.close()">
		<div class="picker__head">
			<h2 id="media-picker-heading">{{ title ?? 'Insert media' }}</h2>
			<button type="button" class="button button--ghost button--icon" @click="dialog?.close()">
				<AdminIcon name="x" />
				<span class="visually-hidden">Close</span>
			</button>
		</div>

		<div class="picker__tools">
			<label class="picker__search">
				<AdminIcon name="search" />
				<input v-model="search" type="search" placeholder="Search file names…" aria-label="Search media" autocomplete="off">
			</label>
			<div class="picker__kinds" role="group" aria-label="Kind">
				<button v-for="item in KINDS" :key="item.key" type="button" class="picker__kind" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
			</div>
		</div>

		<div class="picker__body">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

			<section v-if="besideShown.length" aria-labelledby="media-beside">
				<h3 id="media-beside" class="picker__heading">Beside this entry</h3>
				<div class="picker__grid">
					<button
						v-for="file in besideShown"
						:key="`beside-${file.reference}`"
						type="button"
						class="picker__card"
						:aria-pressed="selected?.url === file.url"
						@click="selected = file"
						@dblclick="use(file)"
					>
						<span class="picker__thumb">
							<img v-if="file.kind === 'image'" :src="file.url" alt="" loading="lazy">
							<AdminIcon v-else :name="icon(file)" />
						</span>
						<span class="picker__name">{{ file.name }}</span>
						<span class="picker__meta">{{ details(file) }}</span>
					</button>
				</div>
			</section>

			<section aria-labelledby="media-library">
				<h3 id="media-library" class="picker__heading">
					Library
					<span class="picker__count" aria-live="polite">{{ loading && !files.length ? 'Loading…' : `${total.toLocaleString()} ${total === 1 ? 'file' : 'files'}` }}</span>
				</h3>
				<div v-if="files.length" class="picker__grid">
					<button
						v-for="file in files"
						:key="file.reference"
						type="button"
						class="picker__card"
						:aria-pressed="selected?.url === file.url"
						@click="selected = file"
						@dblclick="use(file)"
					>
						<span class="picker__thumb">
							<img v-if="file.kind === 'image'" :src="file.url" alt="" loading="lazy">
							<AdminIcon v-else :name="icon(file)" />
						</span>
						<span class="picker__name">{{ file.name }}</span>
						<span class="picker__meta">{{ file.folder || 'media' }} · {{ details(file) }}</span>
					</button>
				</div>
				<div v-else-if="loading" class="picker__grid" aria-hidden="true">
					<span v-for="card in 8" :key="card" class="skeleton picker__skeleton" />
				</div>
				<p v-else-if="!error" class="picker__empty">
					<template v-if="search || kind !== 'any'">No files match. Try another name or kind.</template>
					<template v-else>The library is empty. Put files in <code>user/media</code> to use them here.</template>
				</p>
				<p v-if="page < pages" class="picker__more">
					<button type="button" class="button button--small" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
				</p>
			</section>
		</div>

		<div class="picker__foot">
			<p class="picker__selected">
				<template v-if="selected"><code>{{ selected.reference }}</code> · {{ details(selected) }}</template>
				<template v-else>Choose a file.</template>
			</p>
			<button type="button" class="button" @click="dialog?.close()">Cancel</button>
			<button type="button" class="button button--primary" :disabled="selected === null" @click="use(selected)">{{ action ?? 'Insert' }}</button>
		</div>
	</dialog>
</template>

<style scoped>
.picker {
	width: min(860px, calc(100vw - 32px));
	max-height: min(720px, calc(100vh - 48px));
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-3);
	background: var(--surface);
	box-shadow: var(--shadow-3);
	color: var(--fg);
}

.picker[open] {
	display: flex;
	flex-direction: column;
}

.picker::backdrop {
	background: color-mix(in srgb, var(--fg) 30%, transparent);
}

.picker__head {
	display: flex;
	flex: none;
	align-items: center;
	gap: 8px;
	padding: 10px 10px 10px 18px;
	border-bottom: 1px solid var(--border);
}

.picker__head h2 {
	flex: 1;
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
}

.picker__tools {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	align-items: center;
	gap: 10px;
	padding: 10px 18px;
	border-bottom: 1px solid var(--border);
}

.picker__search {
	display: flex;
	flex: 1 1 220px;
	align-items: center;
	gap: 8px;
	height: 32px;
	padding: 0 10px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--bg);
	color: var(--fg-3);
}

.picker__search:focus-within {
	border-color: var(--accent);
}

.picker__search input {
	flex: 1;
	min-width: 0;
	border: 0;
	background: none;
	color: var(--fg);
	outline: none;
}

.picker__kinds {
	display: flex;
	gap: 5px;
}

.picker__kind {
	padding: 3px 10px;
	border: 1px solid var(--border);
	border-radius: 99px;
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.picker__kind[aria-pressed="true"] {
	border-color: var(--accent-line);
	background: var(--accent-soft);
	color: var(--accent);
	font-weight: 500;
}

.picker__body {
	display: grid;
	flex: 1;
	align-content: start;
	gap: 18px;
	min-height: 240px;
	padding: 14px 18px 18px;
	overflow-y: auto;
}

.picker__heading {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	margin-bottom: 10px;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.picker__count {
	font-weight: 400;
	letter-spacing: normal;
	text-transform: none;
}

.picker__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
	gap: 10px;
}

.picker__card {
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
	padding: 0 0 8px;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	text-align: left;
	cursor: pointer;
}

.picker__card:hover {
	border-color: var(--border-strong);
}

.picker__card[aria-pressed="true"] {
	border-color: var(--accent);
	box-shadow: 0 0 0 2px var(--accent-soft);
}

.picker__thumb {
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	margin-bottom: 5px;
	background: var(--surface-2);
	color: var(--fg-3);
}

.picker__thumb img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.picker__thumb :deep(svg) {
	width: 28px;
	height: 28px;
	stroke-width: 1.5;
}

.picker__name,
.picker__meta {
	overflow: hidden;
	padding: 0 9px;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.picker__name {
	font-size: var(--text-sm);
	font-weight: 500;
}

.picker__meta {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.picker__skeleton {
	display: block;
	aspect-ratio: 1;
	border-radius: var(--r-2);
}

.picker__empty {
	padding: 30px 10px;
	color: var(--fg-3);
	text-align: center;
}

.picker__more {
	margin-top: 12px;
	text-align: center;
}

.picker__foot {
	display: flex;
	flex: none;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	padding: 10px 18px;
	border-top: 1px solid var(--border);
	background: var(--bg);
}

.picker__selected {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>
