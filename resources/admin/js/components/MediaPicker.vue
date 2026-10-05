<script setup lang="ts">
/**
 * The media picker (admin.md §8, The inserters and The media library;
 * D-247, D-265, D-268): a library in a modal, with the room a browsing
 * screen needs, and the same picker wherever a file is chosen (the
 * editor's media menu, media fields and options, an image's Replace, and
 * the Media screen's Upload). It lists the library, newest first (`GET media`), with
 * a search, kind filters as a segmented control, and more on request.
 * Thumbnails are one 4:3 box, cropped to fill it; only a file that isn't
 * an image says its kind.
 *
 * For an account that may upload, it has two tabs, **Library** and
 * **Upload**: uploading is choosing, one step earlier. The Upload tab is
 * a drop zone and a button, with the size limit and the types the library
 * takes stated first; a file dropped anywhere on the modal uploads (the
 * tab switches as the drag comes in). Each file goes up on its own
 * (`POST media`), lands at the top of the library, and is selected, so
 * the primary button finishes the job; the tab keeps a receipt of what
 * was added. A search that finds nothing offers the Upload tab. An
 * `uploadOnly` picker (the Media screen's Upload) has no tabs and no
 * library: only the Upload panel, since the library is the screen behind it.
 *
 * A `locked` picker is for one kind of file (D-314): a video's file, a
 * poster image, a gallery's images. It has no kind filter, its file
 * chooser takes only that kind, and a file of another kind dropped on it
 * is refused, with why, rather than uploaded and then hidden.
 *
 * Choosing a file selects it (a ring and a tick); the primary button,
 * labeled for the errand, or a double click uses it. Escape, **Cancel**,
 * or the close button leave without one. It's a native dialog, so focus
 * stays inside while it's open and returns afterwards.
 */

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AdminIcon from './AdminIcon.vue';
import { mediaFacts, mediaName } from '../media';
import { ApiError, request, upload, type MediaItem, type MediaList } from '../api';
import { formatSize, plural } from '../format';
import type { IconName } from '../icons';
import { canUpload } from '../session';

type Kind = 'any' | 'image' | 'video' | 'audio' | 'document' | 'file';

const props = defineProps<{
	title?: string;
	action?: string;
	// Which tab it opens on, and which kind the library shows first.
	tab?: 'library' | 'upload';
	kind?: Kind;
	// Whether it takes only that kind.
	locked?: boolean;
	// Whether it only uploads, with no Library tab.
	uploadOnly?: boolean;
}>();

const emit = defineEmits<{
	choose: [file: MediaItem];
	close: [];
}>();

const dialog   = ref<HTMLDialogElement | null>(null);
const input    = ref<HTMLInputElement | null>(null);
const searchEl = ref<HTMLInputElement | null>(null);
const search   = ref('');
const kind     = ref<Kind>(props.kind ?? 'any');
const files    = ref<MediaItem[]>([]);
const total    = ref(0);
const page     = ref(1);
const pages    = ref(1);
const loading  = ref(true);
const error    = ref('');
const selected = ref<MediaItem | null>(null);

// Uploading: whether the account may, what the server says it takes, the
// tab showing, a drag over the modal, and this visit's uploads.
const uploads  = canUpload();
const accepts  = ref<MediaList['upload']>(null);
const tabbed   = uploads && !props.uploadOnly;
const tab      = ref<'library' | 'upload'>(uploads ? (props.uploadOnly ? 'upload' : props.tab ?? 'library') : 'library');
const dragging = ref(false);
const added    = ref<{ key: number; name: string; state: 'sending' | 'done' | 'failed'; file?: MediaItem; message?: string }[]>([]);

const KINDS = [
	{ key: 'any', label: 'All' },
	{ key: 'image', label: 'Images' },
	{ key: 'video', label: 'Video' },
	{ key: 'audio', label: 'Audio' },
	{ key: 'document', label: 'Documents' },
	{ key: 'file', label: 'Files' }
] as const;

// What a file that isn't an image, video, or audio is.
// What kind of file a name claims to be, by its extension, so a locked
// picker can refuse the wrong kind before uploading it.
const EXTENSIONS: Record<Exclude<Kind, 'any' | 'file'>, string[]> = {
	image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'],
	video: ['mp4', 'm4v', 'webm', 'mov', 'ogv'],
	audio: ['mp3', 'm4a', 'oga', 'ogg', 'wav', 'flac', 'aac', 'opus'],
	document: ['pdf', 'epub', 'rtf', 'doc', 'xls', 'ppt', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'txt', 'csv', 'md']
};

function kindOfName(name: string): Exclude<Kind, 'any'> {
	const extension = name.split('.').pop()?.toLowerCase() ?? '';

	return (Object.keys(EXTENSIONS) as (keyof typeof EXTENSIONS)[]).find((key) => EXTENSIONS[key].includes(extension)) ?? 'file';
}

const KIND_NAMES: Record<Exclude<Kind, 'any'>, string> = { image: 'an image', video: 'a video', audio: 'an audio file', document: 'a document', file: 'a file' };

function isKind(file: MediaItem, key: string): boolean {
	return key === 'any' || (key === 'file' ? !['image', 'video', 'audio', 'document'].includes(file.kind) : file.kind === key);
}

let latest = 0;

async function load(more = false): Promise<void> {
	const ask = ++latest;
	const params = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

	if (search.value.trim() !== '') {
		params.set('search', search.value.trim());
	}

	loading.value = true;
	error.value   = '';

	try {
		const answer = await request<MediaList>('GET', `/media?${params.toString()}`);

		if (ask !== latest) {
			return;
		}

		files.value   = more ? [...files.value, ...answer.files] : answer.files;
		total.value   = answer.total;
		page.value    = answer.page;
		pages.value   = answer.pages;
		accepts.value = answer.upload;
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
	return file.kind === 'video' ? 'film' : (file.kind === 'audio' ? 'music' : (file.kind === 'document' ? 'file-text' : 'file'));
}

function details(file: MediaItem): string {
	return mediaFacts(file);
}

// "Up to 64 MB each · JPG, PNG, WebP …", once the server has said.
const limits = computed(() => {
	const info = accepts.value;

	if (info === null) {
		return '';
	}

	const types = info.extensions.filter((extension) => !['jpeg', 'oga', 'm4v'].includes(extension)).map((extension) => extension.toUpperCase());

	return [info.limit === null ? '' : `Up to ${formatSize(info.limit)} each`, types.join(', ')].filter((part) => part !== '').join(' · ');
});

const accept = computed(() => accepts.value?.extensions.filter((extension) => !props.locked || kind.value === 'any' || kindOfName(`x.${extension}`) === kind.value).map((extension) => `.${extension}`).join(',') ?? '');

let counter = 0;

/**
 * Uploads files one at a time. Each lands at the top of the library and
 * is selected; a file the server refuses says why in the receipt.
 */
async function send(list: FileList | File[] | null | undefined): Promise<void> {
	const chosen = [...(list ?? [])];

	if (!uploads || chosen.length === 0) {
		return;
	}

	tab.value = 'upload';

	for (const file of chosen) {
		const row = { key: ++counter, name: file.name, state: 'sending' as const };

		added.value = [row, ...added.value];

		if (props.locked && kind.value !== 'any' && kindOfName(file.name) !== kind.value) {
			replace(row.key, { ...row, state: 'failed', message: `It isn't ${KIND_NAMES[kind.value]}, and only ${KIND_NAMES[kind.value].replace(/^an? /, '')} files go here.` });

			continue;
		}

		try {
			const item = await upload<MediaItem>('/media', file);

			replace(row.key, { ...row, name: item.name, state: 'done', file: item });

			if (isKind(item, kind.value)) {
				files.value = [item, ...files.value.filter((other) => other.reference !== item.reference)];
				total.value++;
			}

			selected.value = item;
		} catch (caught) {
			replace(row.key, { ...row, state: 'failed', message: caught instanceof ApiError ? caught.message : 'It couldn\'t be uploaded.' });
		}
	}
}

function replace(key: number, row: (typeof added.value)[number]): void {
	added.value = added.value.map((item) => item.key === key ? row : item);
}

function browse(event: Event): void {
	const element = event.target as HTMLInputElement;

	void send(element.files);
	element.value = '';
}

// A drag of files over the modal: switch to the Upload tab and take them
// wherever they're dropped.
function hasFiles(event: DragEvent): boolean {
	return event.dataTransfer?.types.includes('Files') ?? false;
}

function dragOver(event: DragEvent): void {
	if (!uploads || !hasFiles(event)) {
		return;
	}

	event.preventDefault();
	dragging.value = true;
	tab.value      = 'upload';
}

function dragLeave(event: DragEvent): void {
	if (!(event.relatedTarget instanceof Node) || !dialog.value?.contains(event.relatedTarget)) {
		dragging.value = false;
	}
}

function drop(event: DragEvent): void {
	if (!uploads || !hasFiles(event)) {
		return;
	}

	event.preventDefault();
	dragging.value = false;
	void send(event.dataTransfer?.files);
}

async function showTab(next: 'library' | 'upload'): Promise<void> {
	tab.value = next;
	await nextTick();
	document.getElementById(`media-tab-${next}`)?.focus();
}

// Arrow keys move between the tabs.
function tabKey(event: KeyboardEvent): void {
	if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
		event.preventDefault();
		void showTab(tab.value === 'library' ? 'upload' : 'library');
	}
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

	if (tab.value === 'library') {
		searchEl.value?.focus();
	}
});

onBeforeUnmount(() => {
	clearTimeout(typing);
});
</script>

<template>
	<dialog
		ref="dialog"
		class="modal picker"
		:class="{ 'is-dragging': dragging }"
		aria-labelledby="media-picker-heading"
		@close="emit('close')"
		@keydown.esc.prevent.stop="dialog?.close()"
		@dragenter="dragOver"
		@dragover="dragOver"
		@dragleave="dragLeave"
		@drop="drop"
	>
		<div class="modal__head">
			<h2 id="media-picker-heading">{{ title ?? 'Insert Media' }}</h2>
			<button type="button" class="button button--ghost button--icon" @click="dialog?.close()">
				<AdminIcon name="x" />
				<span class="visually-hidden">Close</span>
			</button>
		</div>

		<div v-if="tabbed" class="picker__tabs" role="tablist" aria-label="Source" @keydown="tabKey">
			<button id="media-tab-library" type="button" class="picker__tab" role="tab" aria-controls="media-panel-library" :aria-selected="tab === 'library'" :tabindex="tab === 'library' ? 0 : -1" @click="tab = 'library'">
				<AdminIcon name="image" />Library
			</button>
			<button id="media-tab-upload" type="button" class="picker__tab" role="tab" aria-controls="media-panel-upload" :aria-selected="tab === 'upload'" :tabindex="tab === 'upload' ? 0 : -1" @click="tab = 'upload'">
				<AdminIcon name="upload" />Upload
			</button>
		</div>

		<div v-if="!uploadOnly" v-show="tab === 'library'" id="media-panel-library" class="picker__panel" :role="tabbed ? 'tabpanel' : undefined" :aria-labelledby="tabbed ? 'media-tab-library' : undefined">
			<div class="modal__bar" :class="{ 'picker__bar--tabbed': tabbed }">
				<label class="search-field">
					<AdminIcon name="search" />
					<input ref="searchEl" v-model="search" type="search" placeholder="Search file names…" aria-label="Search media" autocomplete="off">
				</label>
				<div v-if="!locked" class="segmented" role="group" aria-label="Kind">
					<button v-for="item in KINDS" :key="item.key" type="button" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
				</div>
			</div>

			<div class="modal__body picker__body">
				<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

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
								<span class="picker__name" :title="file.name">{{ mediaName(file) }}</span>
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
							<p class="empty__heading">Nothing Matches</p>
							<p class="empty__text">No file in the library matches that name or kind.</p>
						</template>
						<template v-else>
							<p class="empty__heading">The Library Is Empty</p>
							<p class="empty__text">Images, video, audio, and documents you upload land here, and any entry can use them.</p>
						</template>
						<button v-if="uploads" type="button" class="button" @click="showTab('upload')">
							<AdminIcon name="upload" />{{ search || kind !== 'any' ? 'Upload one instead' : 'Upload a file' }}
						</button>
					</div>
					<p v-if="page < pages" class="picker__more">
						<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? 'Loading…' : 'Show more' }}</button>
					</p>
				</section>
			</div>
		</div>

		<div v-if="uploads" v-show="tab === 'upload'" id="media-panel-upload" class="picker__panel" :role="tabbed ? 'tabpanel' : undefined" :aria-labelledby="tabbed ? 'media-tab-upload' : undefined">
			<div class="modal__body picker__body" :class="{ 'picker__upload--tabbed': tabbed }">
				<div class="picker__drop">
					<AdminIcon name="upload" />
					<p class="picker__drop-heading">Drag Files Here</p>
					<p class="picker__drop-text">Images, video, audio, and documents. They land in the library, so any entry can use them afterwards.</p>
					<button type="button" class="button button--primary" @click="input?.click()">Choose files</button>
					<p v-if="limits" class="picker__drop-hint">{{ limits }}</p>
				</div>
				<input ref="input" type="file" multiple hidden :accept="accept" @change="browse">

				<section v-if="added.length" class="picker__added" aria-labelledby="media-added">
					<h3 id="media-added" class="modal__count">
						<span>Added to the Library</span>
						<button v-if="tabbed" type="button" class="picker__show" @click="showTab('library')">Show in library</button>
					</h3>
					<ul class="picker__receipt" aria-live="polite">
						<li v-for="row in added" :key="row.key" class="picker__row" :class="`picker__row--${row.state}`">
							<AdminIcon :name="row.state === 'done' ? 'circle-check' : (row.state === 'failed' ? 'triangle-alert' : 'upload')" />
							<span class="picker__row-name">{{ row.name }}</span>
							<span class="picker__row-sub mono">
								<template v-if="row.state === 'sending'">Uploading…</template>
								<template v-else-if="row.file">{{ details(row.file) }}</template>
								<template v-else>{{ row.message }}</template>
							</span>
						</li>
					</ul>
				</section>
			</div>
		</div>

		<div class="modal__foot">
			<p class="modal__selected" aria-live="polite">
				<template v-if="selected"><b>{{ mediaName(selected) }}</b> · {{ details(selected) }}</template>
				<template v-else>{{ uploadOnly ? 'Upload a file.' : 'Choose a file.' }}</template>
			</p>
			<button type="button" class="button" @click="dialog?.close()">Cancel</button>
			<button type="button" class="button button--primary" :disabled="selected === null" @click="use(selected)">{{ action ?? 'Insert' }}</button>
		</div>
	</dialog>
</template>

<style scoped>
/* The tabs sit where the filter bar would, flush with the modal's left
   edge like the editor drawer's. */
.picker__tabs {
	display: flex;
	flex: none;
	gap: var(--s-1);
	padding: 0 var(--s-5);
	border-bottom: 1px solid var(--border);
}

.picker__tab {
	display: flex;
	align-items: center;
	gap: 7px;
	margin-bottom: -1px;
	padding: 12px 12px 11px;
	border: 0;
	border-bottom: 2px solid transparent;
	background: none;
	color: var(--fg-2);
	font-size: var(--text-sm);
	cursor: pointer;
}

.picker__tab:first-child {
	padding-left: 0;
}

.picker__tab svg {
	width: 13px;
	height: 13px;
}

.picker__tab:hover {
	color: var(--fg);
}

.picker__tab[aria-selected="true"] {
	border-bottom-color: var(--accent);
	color: var(--fg);
	font-weight: 500;
}

.picker__panel {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-height: 0;
}

.picker__bar--tabbed {
	padding-top: var(--s-4);
}

.picker__body {
	display: grid;
	align-content: start;
	gap: var(--s-6);
}

.picker__upload--tabbed {
	border-top: 0;
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

/* The drop zone, then the button, with the limits stated before anyone
   tries. The button has no icon: the large one above already says it. */
.picker__drop {
	display: grid;
	justify-items: center;
	gap: var(--s-3);
	padding: 64px var(--s-6);
	border: 1.5px dashed var(--border-strong);
	border-radius: var(--r-3);
	background: var(--bg);
	text-align: center;
	transition: border-color 120ms, background 120ms;
}

.picker__drop > :deep(svg) {
	width: 28px;
	height: 28px;
	color: var(--fg-3);
}

.picker__drop-heading {
	font-family: var(--font-display);
	font-size: var(--h2);
	font-weight: 600;
}

.picker__drop-text {
	max-width: 46ch;
	color: var(--fg-3);
	line-height: 1.6;
}

.picker__drop .button {
	margin-top: var(--s-2);
}

.picker__drop-hint {
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.picker.is-dragging .picker__drop {
	border-color: var(--accent);
	background: var(--accent-soft);
}

.picker.is-dragging .picker__drop > :deep(svg) {
	color: var(--accent);
}

.picker__added .modal__count {
	align-items: center;
}

.picker__show {
	padding: 0;
	border: 0;
	background: none;
	color: var(--accent);
	font-size: var(--text-sm);
	font-weight: 400;
	letter-spacing: normal;
	text-transform: none;
	cursor: pointer;
}

.picker__show:hover {
	text-decoration: underline;
}

.picker__receipt {
	display: grid;
	gap: var(--s-2);
	margin: 0;
	padding: 0;
	list-style: none;
}

.picker__row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	padding: 12px var(--s-4);
	border: 1px solid var(--border);
	border-radius: var(--r-2);
	background: var(--surface);
	font-size: var(--text-sm);
}

.picker__row > :deep(svg) {
	flex: none;
	color: var(--fg-3);
}

.picker__row--done > :deep(svg) {
	color: var(--good);
}

.picker__row--failed > :deep(svg) {
	color: var(--danger);
}

.picker__row-name {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.picker__row-sub {
	flex: none;
	max-width: 60%;
	overflow: hidden;
	color: var(--fg-3);
	font-size: var(--text-xs);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.picker__row--failed .picker__row-sub {
	flex: 1 1 auto;
	color: var(--danger);
	font-family: var(--font-ui);
	white-space: normal;
}

@media (prefers-reduced-motion: reduce) {
	.picker__tick,
	.picker__drop {
		transition: none;
	}
}
</style>
