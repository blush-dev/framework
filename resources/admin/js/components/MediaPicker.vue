<script setup lang="ts">
/**
 * The media picker (admin.md §8, The inserters and The media library;
 * D-247, D-265): a library in a modal, with the room a browsing screen
 * needs, and the same picker wherever a file is chosen (the editor's
 * media button, and media fields and options). It lists the files beside
 * the entry, when it's a page bundle, then the library, newest first
 * (`GET media`), with a search, kind filters as a segmented control, and
 * more on request. Thumbnails are one 4:3 box, cropped to fill it; only
 * a file that isn't an image says its kind.
 *
 * Choosing a file selects it (a ring and a tick); the primary button,
 * labeled for the errand, or a double click uses it. Escape, **Cancel**,
 * or the close button leave without one. It's a native dialog, so focus
 * stays inside while it's open and returns afterwards.
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
const kind     = ref<'any' | 'image' | 'video' | 'audio' | 'file'>('any');
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
	{ key: 'audio', label: 'Audio' },
	{ key: 'file', label: 'Files' }
] as const;

// What a file that isn't an image, video, or sound is.
function isKind(file: MediaItem, key: string): boolean {
	return key === 'any' || (key === 'file' ? !['image', 'video', 'audio'].includes(file.kind) : file.kind === key);
}

// Only the files beside the entry that pass the filters.
const besideShown = computed(() => (beside.value ?? []).filter((file) => isKind(file, kind.value)
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

// The dialog closes first: while it's open and modal, nothing outside it
// can take focus, so the editor couldn't insert at its caret.
function use(file: MediaItem | null): void {
	if (file !== null) {
		dialog.value?.close();
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
	<dialog ref="dialog" class="modal" aria-labelledby="media-picker-heading" @close="emit('close')" @keydown.esc.prevent.stop="dialog?.close()">
		<div class="modal__head">
			<h2 id="media-picker-heading">{{ title ?? 'Insert media' }}</h2>
			<button type="button" class="button button--ghost button--icon" @click="dialog?.close()">
				<AdminIcon name="x" />
				<span class="visually-hidden">Close</span>
			</button>
		</div>

		<div class="modal__bar">
			<label class="search-field">
				<AdminIcon name="search" />
				<input v-model="search" type="search" placeholder="Search file names…" aria-label="Search media" autocomplete="off">
			</label>
			<div class="segmented" role="group" aria-label="Kind">
				<button v-for="item in KINDS" :key="item.key" type="button" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
			</div>
		</div>

		<div class="modal__body picker__body">
			<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

			<section v-if="besideShown.length" aria-labelledby="media-beside">
				<h3 id="media-beside" class="modal__count"><span>Beside this entry</span><span>{{ besideShown.length === 1 ? '1 file' : `${besideShown.length} files` }}</span></h3>
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
							<template v-else><AdminIcon :name="icon(file)" /><span class="picker__kind mono">{{ file.kind }}</span></template>
							<span class="picker__tick" aria-hidden="true"><AdminIcon name="check" /></span>
						</span>
						<span class="picker__meta">
							<span class="picker__name">{{ file.name }}</span>
							<span class="picker__sub mono">{{ details(file) }}</span>
						</span>
					</button>
				</div>
			</section>

			<section aria-labelledby="media-library">
				<h3 id="media-library" class="modal__count">
					<span>Library</span>
					<span aria-live="polite">{{ loading && !files.length ? 'Loading…' : `${total.toLocaleString()} ${total === 1 ? 'file' : 'files'}` }}</span>
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
							<template v-else><AdminIcon :name="icon(file)" /><span class="picker__kind mono">{{ file.kind }}</span></template>
							<span class="picker__tick" aria-hidden="true"><AdminIcon name="check" /></span>
						</span>
						<span class="picker__meta">
							<span class="picker__name">{{ file.name }}</span>
							<span class="picker__sub mono">{{ details(file) }}</span>
						</span>
					</button>
				</div>
				<div v-else-if="loading" class="picker__grid" aria-hidden="true">
					<span v-for="card in 8" :key="card" class="skeleton picker__skeleton" />
				</div>
				<div v-else-if="!error" class="empty">
					<AdminIcon :name="search || kind !== 'any' ? 'search' : 'image'" />
					<template v-if="search || kind !== 'any'">
						<p class="empty__heading">Nothing matches</p>
						<p class="empty__text">No file in the library matches that name or kind.</p>
					</template>
					<template v-else>
						<p class="empty__heading">The library is empty</p>
						<p class="empty__text">Put files in <code>user/media</code> to use them here.</p>
					</template>
				</div>
				<p v-if="page < pages" class="picker__more">
					<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
				</p>
			</section>
		</div>

		<div class="modal__foot">
			<p class="modal__selected" aria-live="polite">
				<template v-if="selected"><b>{{ selected.name }}</b> · {{ details(selected) }}</template>
				<template v-else>Choose a file.</template>
			</p>
			<button type="button" class="button" @click="dialog?.close()">Cancel</button>
			<button type="button" class="button button--primary" :disabled="selected === null" @click="use(selected)">{{ action ?? 'Insert' }}</button>
		</div>
	</dialog>
</template>

<style scoped>
.picker__body {
	display: grid;
	align-content: start;
	gap: var(--s-6);
}

.picker__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(188px, 1fr));
	gap: var(--s-4);
}

/* Every card the same height: a 4:3 thumbnail, a name on one line, and
   its details on a second. */
.picker__card {
	display: flex;
	flex-direction: column;
	min-width: 0;
	padding: 0;
	overflow: hidden;
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	color: var(--fg);
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
	position: relative;
	display: grid;
	place-items: center;
	aspect-ratio: 4 / 3;
	overflow: hidden;
	background: var(--surface-2);
	color: var(--fg-3);
}

.picker__thumb img {
	display: block;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.picker__thumb > :deep(svg) {
	width: 28px;
	height: 28px;
	stroke-width: 1.5;
}

/* A kind only where the thumbnail is a placeholder. */
.picker__kind {
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

/* Selection is a ring and a tick: a tint alone is lost on an image. */
.picker__tick {
	position: absolute;
	top: 9px;
	right: 9px;
	display: grid;
	place-items: center;
	width: 22px;
	height: 22px;
	border-radius: 50%;
	background: var(--accent);
	color: var(--accent-fg);
	opacity: 0;
	transform: scale(.7);
	transition: opacity 120ms, transform 120ms;
}

.picker__tick :deep(svg) {
	width: 13px;
	height: 13px;
	stroke-width: 2.6;
}

.picker__card[aria-pressed="true"] .picker__tick {
	opacity: 1;
	transform: none;
}

.picker__meta {
	display: grid;
	gap: 4px;
	min-width: 0;
	padding: var(--s-3) var(--s-4);
	border-top: 1px solid var(--border);
}

.picker__name,
.picker__sub {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.picker__name {
	font-size: var(--text-sm);
}

.picker__sub {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.picker__skeleton {
	display: block;
	height: auto;
	aspect-ratio: 188 / 190;
	border-radius: var(--r-2);
}

.picker__more {
	margin-top: var(--s-5);
	text-align: center;
}

@media (prefers-reduced-motion: reduce) {
	.picker__tick {
		transition: none;
	}
}
</style>
