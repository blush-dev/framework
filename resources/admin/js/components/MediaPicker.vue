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
 * labeled for the errand, or a double click uses it. A `multiple` picker
 * (inserting into the text, D-526) takes several: choosing a file adds
 * it to the selection or takes it out, each card numbered in the order
 * chosen, which is the order they go in; an upload joins the selection,
 * and a double click uses the selection with that file in it. Where one
 * file is wanted (a field, an option, an image's Replace) it takes one. Escape, **Cancel**,
 * or the close button leave without one. It's a native dialog, so focus
 * stays inside while it's open and returns afterwards.
 */

import { computed, nextTick, ref } from 'vue';
import AdminIcon from './AdminIcon.vue';
import EmptyState from './EmptyState.vue';
import MediaCard from './MediaCard.vue';
import TabBar from './TabBar.vue';
import { mediaFacts, mediaName, useMediaList } from '../media';
import { errorMessage, request, upload, type MediaItem, type MediaList } from '../api';
import { useModalDialog } from '../dialog';
import { hasFiles, useFileDrop } from '../drop';
import { formatSize } from '../format';
import type { IconName } from '../icons';
import { canUpload } from '../session';
import { t } from '../messages';
import MessageText from './MessageText.vue';

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
	// Whether it takes several files.
	multiple?: boolean;
}>();

const emit = defineEmits<{
	choose: [files: MediaItem[]];
	close: [];
}>();

const input    = ref<HTMLInputElement | null>(null);
const searchEl = ref<HTMLInputElement | null>(null);
const search   = ref('');
const kind     = ref<Kind>(props.kind ?? 'any');
const selected = ref<MediaItem[]>([]);

// Uploading: whether the account may, what the server says it takes, the
// tab showing, a drag over the modal, and this visit's uploads.
const uploads  = canUpload();
const accepts  = ref<MediaList['upload']>(null);
const tabbed   = uploads && !props.uploadOnly;
const tab      = ref<'library' | 'upload'>(uploads ? (props.uploadOnly ? 'upload' : props.tab ?? 'library') : 'library');
const added    = ref<{ key: number; name: string; state: 'sending' | 'done' | 'failed'; file?: MediaItem; message?: string }[]>([]);

const TABS: { key: 'library' | 'upload'; label: string; icon: IconName }[] = [
	{ key: 'library', label: t('media_picker.library', 'Library'), icon: 'image' },
	{ key: 'upload', label: t('media_picker.upload', 'Upload'), icon: 'upload' }
];

const KINDS = [
	{ key: 'any', label: t('media_picker.kinds.any', 'All') },
	{ key: 'image', label: t('media_picker.kinds.image', 'Images') },
	{ key: 'video', label: t('media_picker.kinds.video', 'Video') },
	{ key: 'audio', label: t('media_picker.kinds.audio', 'Audio') },
	{ key: 'document', label: t('media_picker.kinds.document', 'Documents') },
	{ key: 'file', label: t('media_picker.kinds.file', 'Files') }
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

// Why a file of another kind is refused, by the kind the picker takes.
const KIND_REFUSED: Record<Exclude<Kind, 'any'>, string> = {
	image: t('media_picker.refused.image', 'It isn\'t an image, and only image files go here.'),
	video: t('media_picker.refused.video', 'It isn\'t a video, and only video files go here.'),
	audio: t('media_picker.refused.audio', 'It isn\'t an audio file, and only audio file files go here.'),
	document: t('media_picker.refused.document', 'It isn\'t a document, and only document files go here.'),
	file: t('media_picker.refused.file', 'It isn\'t a file, and only file files go here.')
};

function isKind(file: MediaItem, key: string): boolean {
	return key === 'any' || (key === 'file' ? !['image', 'video', 'audio', 'document'].includes(file.kind) : file.kind === key);
}

const { dialog, close } = useModalDialog(() => {
	void load();

	if (tab.value === 'library') {
		searchEl.value?.focus();
	}
});

const { files, total, page, pages, loading, error, load } = useMediaList(kind, search, undefined, (answer) => {
	accepts.value = answer.upload;
});

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

	return [info.limit === null ? '' : t('media_picker.limit', 'Up to {size} each', { size: formatSize(info.limit) }), types.join(', ')].filter((part) => part !== '').join(' · ');
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
			replace(row.key, { ...row, state: 'failed', message: KIND_REFUSED[kind.value] });

			continue;
		}

		try {
			const item = await upload<MediaItem>('/media', file);

			replace(row.key, { ...row, name: item.name, state: 'done', file: item });

			if (isKind(item, kind.value)) {
				files.value = [item, ...files.value.filter((other) => other.reference !== item.reference)];
				total.value++;
			}

			selected.value = props.multiple ? [...selected.value.filter((other) => other.reference !== item.reference), item] : [item];
		} catch (caught) {
			replace(row.key, { ...row, state: 'failed', message: errorMessage(caught, t('media_picker.errors.upload', 'It couldn\'t be uploaded.')) });
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
const { dragging, over, leave: dragLeave, drop } = useFileDrop((dropped) => void send(dropped), () => uploads);

function dragOver(event: DragEvent): void {
	over(event);

	if (uploads && hasFiles(event)) {
		tab.value = 'upload';
	}
}

async function showTab(next: 'library' | 'upload'): Promise<void> {
	tab.value = next;
	await nextTick();
	document.getElementById(`media-tab-${next}`)?.focus();
}

// Where a file is in the selection, from 1, or 0 when it isn't.
function order(file: MediaItem): number {
	return selected.value.findIndex((other) => other.reference === file.reference) + 1;
}

// Choosing a file: one picker swaps it in; a multiple one adds it to the
// selection, or takes it out.
function choose(file: MediaItem): void {
	if (!props.multiple) {
		selected.value = [file];
	} else if (order(file) > 0) {
		selected.value = selected.value.filter((other) => other.reference !== file.reference);
	} else {
		selected.value = [...selected.value, file];
	}
}

// A double click uses the file, with the rest of a multiple selection.
function chooseNow(file: MediaItem): void {
	use(props.multiple && order(file) === 0 ? [...selected.value, file] : (props.multiple ? selected.value : [file]));
}

// "Insert", or "Insert 3 Images" for several.
const actionLabel = computed(() => {
	const count = selected.value.length;

	if (count < 2) {
		return props.action ?? t('media_picker.insert', 'Insert');
	}

	const action = props.action ?? t('media_picker.insert', 'Insert');

	return props.locked && kind.value === 'image'
		? t('media_picker.action_images', '{action} {count} Images', { action, count })
		: t('media_picker.action_files', '{action} {count} Files', { action, count });
});

// The dialog closes first: while it's open and modal, nothing outside it
// can take focus, so the editor couldn't insert at its caret.
function use(chosen: MediaItem[]): void {
	if (chosen.length > 0) {
		close();
		emit('choose', chosen);
	}
}
</script>

<template>
	<dialog
		ref="dialog"
		class="modal picker"
		:class="{ 'is-dragging': dragging }"
		aria-labelledby="media-picker-heading"
		@close="emit('close')"
		@keydown.esc.prevent.stop="close()"
		@dragenter="dragOver"
		@dragover="dragOver"
		@dragleave="dragLeave"
		@drop="drop"
	>
		<div class="panel__header" :class="{ 'picker__head--tabbed': tabbed }">
			<h2 id="media-picker-heading">{{ title ?? t('media_picker.title', 'Insert Media') }}</h2>
			<div class="panel__actions">
				<button type="button" class="button button--ghost button--small button--icon" @click="close()">
					<AdminIcon name="x" />
					<span class="visually-hidden">{{ t('common.close', 'Close') }}</span>
				</button>
			</div>
			<TabBar v-if="tabbed" v-model="tab" class="picker__tabs" :tabs="TABS" :label="t('media_picker.source', 'Source')" prefix="media" />
		</div>

		<div v-if="!uploadOnly" v-show="tab === 'library'" id="media-panel-library" class="picker__panel" :role="tabbed ? 'tabpanel' : undefined" :aria-labelledby="tabbed ? 'media-tab-library' : undefined">
			<div class="modal__bar">
				<label class="search-field">
					<AdminIcon name="search" />
					<input ref="searchEl" v-model="search" type="search" :placeholder="t('media_picker.search_placeholder', 'Search file names…')" :aria-label="t('media_picker.search', 'Search media')" autocomplete="off">
				</label>
				<div v-if="!locked" class="segmented" role="group" :aria-label="t('media_picker.kind', 'Kind')">
					<button v-for="item in KINDS" :key="item.key" type="button" :aria-pressed="kind === item.key" @click="kind = item.key">{{ item.label }}</button>
				</div>
			</div>

			<div class="modal__body picker__body">
				<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

				<section aria-labelledby="media-library">
					<h3 id="media-library" class="modal__count">
						<span>{{ t('media_picker.library', 'Library') }}</span>
						<span aria-live="polite">{{ loading && !files.length ? t('common.loading', 'Loading…') : t('media_picker.count', '{count, plural, one {# file} other {# files} }', { count: total }) }}</span>
					</h3>
					<div v-if="files.length" class="picker__grid">
						<MediaCard v-for="file in files" :key="file.reference" :file="file" :details="details(file)" :selected="order(file) > 0" :order="multiple ? order(file) : undefined" @click="choose(file)" @dblclick="chooseNow(file)" />
					</div>
					<div v-else-if="loading" class="picker__grid" aria-hidden="true">
						<span v-for="card in 8" :key="card" class="skeleton picker__skeleton" />
					</div>
					<EmptyState
						v-else-if="!error"
						:icon="search || kind !== 'any' ? 'search' : 'image'"
						:heading="search || kind !== 'any' ? t('media_picker.no_matches', 'Nothing Matches') : t('media_picker.empty', 'The Library Is Empty')"
						:text="search || kind !== 'any' ? t('media_picker.no_matches_text', 'No file in the library matches that name or kind.') : t('media_picker.empty_text', 'Images, video, audio, and documents you upload land here, and any entry can use them.')"
					>
						<template #actions>
							<button v-if="uploads" type="button" class="button" @click="showTab('upload')">
								<AdminIcon name="upload" />{{ search || kind !== 'any' ? t('media_picker.upload_instead', 'Upload One Instead') : t('media_picker.upload_file', 'Upload a File') }}
							</button>
						</template>
					</EmptyState>
					<p v-if="page < pages" class="picker__more">
						<button type="button" class="button" :disabled="loading" @click="load(true)">{{ loading ? t('common.loading', 'Loading…') : t('media_picker.show_more', 'Show More') }}</button>
					</p>
				</section>
			</div>
		</div>

		<div v-if="uploads" v-show="tab === 'upload'" id="media-panel-upload" class="picker__panel" :role="tabbed ? 'tabpanel' : undefined" :aria-labelledby="tabbed ? 'media-tab-upload' : undefined">
			<div class="modal__body picker__body picker__upload">
				<div class="picker__drop">
					<AdminIcon name="upload" />
					<p class="picker__drop-heading">{{ t('media_picker.drag_here', 'Drag Files Here') }}</p>
					<p class="picker__drop-text">{{ t('media_picker.drop_text', 'Images, video, audio, and documents. They land in the library, so any entry can use them afterwards.') }}</p>
					<button type="button" class="button button--primary" @click="input?.click()">{{ t('media_picker.choose_files', 'Choose Files') }}</button>
					<p v-if="limits" class="picker__drop-hint">{{ limits }}</p>
				</div>
				<input ref="input" type="file" multiple hidden :accept="accept" @change="browse">

				<section v-if="added.length" class="picker__added" aria-labelledby="media-added">
					<h3 id="media-added" class="modal__count">
						<span>{{ t('media_picker.added', 'Added to the Library') }}</span>
						<button v-if="tabbed" type="button" class="lnk picker__show" @click="showTab('library')">{{ t('media_picker.show_in_library', 'Show in library') }}</button>
					</h3>
					<ul class="picker__receipt" aria-live="polite">
						<li v-for="row in added" :key="row.key" class="picker__row" :class="`picker__row--${row.state}`">
							<AdminIcon :name="row.state === 'done' ? 'circle-check' : (row.state === 'failed' ? 'triangle-alert' : 'upload')" />
							<span class="picker__row-name">{{ row.name }}</span>
							<span class="picker__row-sub mono">
								<template v-if="row.state === 'sending'">{{ t('media_picker.uploading', 'Uploading…') }}</template>
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
				<template v-if="selected.length > 1">
					<MessageText :text="t('media_picker.chosen', '{chosen} · in the order they go in ·')"><template #chosen><b>{{ t('media_picker.files_chosen', '{count, plural, one {# file chosen} other {# files chosen} }', { count: selected.length }) }}</b></template></MessageText>
					<button type="button" class="lnk" @click="selected = []">{{ t('media_picker.clear', 'Clear') }}</button>
				</template>
				<template v-else-if="selected[0]"><b>{{ mediaName(selected[0]) }}</b> · {{ details(selected[0]) }}</template>
				<template v-else>{{ uploadOnly ? t('media_picker.prompt_upload', 'Upload a file.') : (multiple ? t('media_picker.prompt_multiple', 'Choose one or more files.') : t('media_picker.prompt_one', 'Choose a file.')) }}</template>
			</p>
			<button type="button" class="button" @click="close()">{{ t('common.cancel', 'Cancel') }}</button>
			<button type="button" class="button button--primary" :disabled="selected.length === 0" @click="use(selected)">{{ actionLabel }}</button>
		</div>
	</dialog>
</template>

<style scoped>
/* The tabs sit where the filter bar would, flush with the modal's left
   edge like the editor drawer's. */
/* The tabs are the head's last row, their marker on its hairline. */
.picker__head--tabbed {
	padding-bottom: 0;
}

.picker__tabs {
	flex-basis: 100%;
}

.picker__panel {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-height: 0;
}

.picker__body {
	display: grid;
	align-content: start;
	gap: var(--s-6);
}

/* Right under the head, whose hairline it already has. The drop zone
   fills the body, less its padding, with any receipt under it. */
.picker__upload {
	grid-template-rows: minmax(min-content, 1fr);
	align-content: stretch;
	border-top: 0;
}

.picker__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(188px, 1fr));
	gap: var(--s-4);
}

.picker__skeleton {
	display: block;
	height: auto;
	aspect-ratio: 188 / 190;
	border-radius: var(--border-radius-medium);
}

.picker__more {
	margin-top: var(--s-5);
	text-align: center;
}

/* The drop zone, then the button, with the limits stated before anyone
   tries. The button has no icon: the large one above already says it. */
.picker__drop {
	display: grid;
	align-content: center;
	justify-items: center;
	gap: var(--s-3);
	padding: 64px var(--s-6);
	border: 1.5px dashed var(--border-color-strong);
	border-radius: var(--border-radius-large);
	background: var(--color-background);
	text-align: center;
	transition: border-color 120ms, background 120ms;
}

.picker__drop > :deep(svg) {
	width: 28px;
	height: 28px;
	color: var(--color-text-muted);
}

.picker__drop-heading {
	font-family: var(--font-family-display);
	font-size: var(--font-size-panel-title);
	font-weight: var(--font-weight-display);
}

.picker__drop-text {
	max-width: 46ch;
	color: var(--color-text-muted);
	line-height: 1.6;
}

.picker__drop .button {
	margin-top: var(--s-2);
}

.picker__drop-hint {
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
}

.picker.is-dragging .picker__drop {
	border-color: var(--color-accent);
	background: var(--color-accent-subtle);
}

.picker.is-dragging .picker__drop > :deep(svg) {
	color: var(--color-accent);
}

.picker__added .modal__count {
	align-items: center;
}

.picker__show {
	font-size: var(--font-size-small);
	font-weight: 400;
	letter-spacing: normal;
	text-transform: none;
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
	border: 1px solid var(--border-color);
	border-radius: var(--border-radius-medium);
	background: var(--color-surface);
	font-size: var(--font-size-small);
}

.picker__row > :deep(svg) {
	flex: none;
	color: var(--color-text-muted);
}

.picker__row--done > :deep(svg) {
	color: var(--color-success);
}

.picker__row--failed > :deep(svg) {
	color: var(--color-danger);
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
	color: var(--color-text-muted);
	font-size: var(--font-size-xsmall);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.picker__row--failed .picker__row-sub {
	flex: 1 1 auto;
	color: var(--color-danger);
	font-family: var(--font-family-ui);
	white-space: normal;
}

@media (prefers-reduced-motion: reduce) {
	.picker__tick,
	.picker__drop {
		transition: none;
	}
}
</style>
