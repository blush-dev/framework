<script setup lang="ts">
/**
 * One file in the media library (D-251), drawn as the media detail
 * sketch has it (D-551): the file, how it's used, and where it lives on
 * the left; its details, the one part that saves, and what the file says
 * about itself on the right.
 *
 * The heading is the file's title, else its file name (D-290), and
 * follows the Title field as it's typed; with a title, the file name
 * leads the line under it. The trail's last crumb says **Editing**, as
 * the editor's does (D-317).
 *
 * The details are the library's own, kept in `user/data` (`PATCH
 * media/{path}`): the fields its kind has (D-287), title first, as a
 * form built from their definitions, as an entry's are (`FieldControl`),
 * with keys the fields don't declare shown as they are and what doesn't
 * fit under its field. What the file says about itself (D-289: its
 * EXIF, IPTC, ID3, and the rest) that fits a field is offered under it
 * (a title into the title, a creator or credit into the credit; captions
 * only ever come from the library, D-290), with **Use It**, or **Fill
 * from the File** for every empty one; the rest is Metadata, which says
 * when the file carries where it was taken, without where. An image
 * without alt text says so under its field. Alt text and a caption are
 * filled in when the file is inserted as an image (D-269), and a page's
 * image without alt text uses the library's (D-270); what an entry
 * writes is its own. Changes are counted in the save bar (D-508) and
 * saved when asked, only the fields that changed, or put back with
 * **Revert**; leaving with changes unsaved asks first.
 *
 * Who may change a file's details, or delete it, is by whose it is
 * (D-407): the file says who uploaded it, and its details are read-only
 * to someone who may not change them. Usage gives its address and what
 * an entry writes to show it, and the entries that use it, the first
 * five of a long list until asked for all; deleting it asks first,
 * saying how many.
 *
 * A sound or video has Artwork (D-581): a library image, by id, shown
 * in its preview and by the site's audio card and video poster, else the
 * picture it carries. **Add to Library** makes the carried picture an
 * image of its own (or finds the one with its bytes), **Choose Image**
 * picks or uploads one in the media picker, and **Remove** takes the
 * link off, leaving the image. Each waits in the save bar with the
 * details, and saves with them (D-583); Revert puts it back. An image's Usage names the files that
 * show it, and deleting it says so.
 *
 * An image lists its other sizes (D-488), such as resized copies brought
 * from another system, which go with it when it's deleted. A size's
 * screen says whose it is, and shows that image's details, which it
 * goes by, read-only.
 */

import { computed, ref, watch } from 'vue';
import { confirmAction, guardLeave } from '../confirm';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import AdminIcon from '../components/AdminIcon.vue';
import AudioPlayer from '../components/AudioPlayer.vue';
import VideoPlayer from '../components/VideoPlayer.vue';
import FieldControl from '../components/FieldControl.vue';
import MediaPicker from '../components/MediaPicker.vue';
import RawValues from '../components/RawValues.vue';
import SaveBar from '../components/SaveBar.vue';
import { ApiError, entryRoute, errorMessage, request, type FieldDescription, type MediaDetail, type MediaItem } from '../api';
import { useAction } from '../action';
import { config } from '../config';
import { fromForm, toForm, type FormValue } from '../fields';
import { attributeText, imageText, linkText } from '../markdown';
import { formatDate, formatSize, plural } from '../format';
import { embeddedText, forgetFile, formatDuration, mediaFacts, mediaIcon, mediaName } from '../media';
import { screenCrumb, screenTitle } from '../screen';
import { copyText, toast } from '../toast';

const route  = useRoute();
const router = useRouter();
const file  = ref<MediaDetail | null>(null);
const error = ref('');

const path = computed(() => {
	const segments = route.params.path;

	return Array.isArray(segments) ? segments.join('/') : String(segments ?? '');
});

const address = computed(() => `/media/${path.value.split('/').map(encodeURIComponent).join('/')}`);

// The fields, as typed, and as loaded.
const form    = ref<Record<string, FormValue>>({});
const initial = ref<Record<string, FormValue>>({});
const invalid = ref<{ field: string; message: string } | null>(null);

const fields  = computed<FieldDescription[]>(() => file.value?.fields ?? []);

const { busy: saving, error: failure, run } = useAction();

// The field sets attached to the kind (D-341), each under its label after
// the built-in fields.
const inSets    = computed(() => new Set((file.value?.sets ?? []).flatMap((set) => set.fields)));
const ownFields = computed(() => fields.value.filter((field) => !inSets.value.has(field.name)));
const setGroups = computed(() => (file.value?.sets ?? []).map((set) => ({
	...set,
	fields: set.fields.flatMap((name) => fields.value.filter((field) => field.name === name))
})).filter((set) => set.fields.length > 0));
const changes = computed(() => fields.value.filter((field) => form.value[field.name] !== initial.value[field.name]));
const changed = computed(() => changes.value.length > 0 || artChange.value !== null);
const altText = computed(() => typeof form.value.alt === 'string' ? form.value.alt.trim() : '');
const extra   = computed(() => Object.entries(file.value?.extra ?? {}));

// What the file says about itself, labeled, with the field each value
// can fill, if the file's kind has it.
const EMBEDDED: Record<string, { label: string; field?: string }> = {
	title: { label: 'Title', field: 'title' },
	description: { label: 'Description', field: 'description' },
	creator: { label: 'Creator', field: 'credit' },
	album: { label: 'Album' },
	track: { label: 'Track' },
	genre: { label: 'Genre' },
	credit: { label: 'Credit', field: 'credit' },
	copyright: { label: 'Copyright' },
	keywords: { label: 'Keywords' },
	created: { label: 'Made' },
	camera: { label: 'Camera' },
	lens: { label: 'Lens' },
	focalLength: { label: 'Focal length' },
	aperture: { label: 'Aperture' },
	exposure: { label: 'Exposure' },
	iso: { label: 'ISO' },
	orientation: { label: 'Orientation' },
	artwork: { label: 'Artwork' },
	format: { label: 'Format' },
	bitrate: { label: 'Bitrate' },
	sampleRate: { label: 'Sample rate' },
	channels: { label: 'Channels' },
	frameRate: { label: 'Frame rate' },
	audioFormat: { label: 'Audio' },
	pageSize: { label: 'Page size' },
	software: { label: 'Software' },
	producer: { label: 'Producer' }
};

// What `format` is: a video's picture, a document's version, or a
// sound's codec.
const FORMAT: Record<string, string> = { video: 'Video', document: 'Version' };

const embedded = computed(() => Object.entries(file.value?.embedded.values ?? {}).filter(([key]) => EMBEDDED[key] !== undefined).map(([key, value]) => ({
	key,
	label: key === 'format' ? FORMAT[file.value?.kind ?? ''] ?? 'Format' : EMBEDDED[key]?.label ?? key,
	text: embeddedText(key, value),
	field: EMBEDDED[key]?.field
})));

// The value offered under each field the file has one for: the first
// that fits it. A file whose details can't be changed offers none.
const offers = computed(() => {
	const found = new Map<string, string>();

	if (file.value?.may.edit !== true || file.value.original !== null) {
		return found;
	}

	for (const item of embedded.value) {
		if (item.field !== undefined && !found.has(item.field) && fields.value.some((field) => field.name === item.field)) {
			found.set(item.field, item.text);
		}
	}

	return found;
});

// The rest of what it says, as Metadata. A video's sound is one row:
// its codec, bit rate, and channels.
const SOUND    = ['bitrate', 'sampleRate', 'channels'];
const metadata = computed(() => {
	const rows = embedded.value.filter((item) => item.field === undefined || offers.value.get(item.field) !== item.text);

	if (file.value?.kind !== 'video') {
		return rows;
	}

	const sound = rows.filter((item) => item.key === 'bitrate' || item.key === 'channels').map((item) => item.text);

	return rows.filter((item) => !SOUND.includes(item.key)).map((item) => item.key === 'audioFormat' ? { ...item, text: [item.text, ...sound].join(', ') } : item);
});

// A document's pages, when it says (a PDF), and its page's shape, for
// the drawn page.
const pages = computed(() => {
	const value = file.value?.embedded.values.pages;

	return typeof value === 'number' ? value : null;
});

const pageShape = computed(() => {
	const size  = file.value?.embedded.values.pageSize;
	const found = typeof size === 'string' ? /^([\d.]+) × ([\d.]+)/.exec(size) : null;

	return found === null ? '8.5 / 11' : `${found[1]} / ${found[2]}`;
});

// Where a sound's or video's artwork is changed, and the picture it
// carries is read (D-551).
const artworkAddress = computed(() => address.value.replace(/^\/media\//, '/media-artwork/'));
const carried        = computed(() => file.value?.embedded.values.artwork === undefined ? null : config.api + artworkAddress.value);

// Its artwork (D-581) waits in the save bar, as the details do: an
// image chosen, the picture it carries to add to the library, or none.
type ArtChange = { set: 'image'; image: MediaItem } | { set: 'file' } | { set: 'none' };

const artChange   = ref<ArtChange | null>(null);
const choosingArt = ref(false);

// The library image it names, as saved, then as it will be.
const savedImage = computed(() => file.value?.artwork?.image ?? null);
const linked     = computed(() => {
	const change = artChange.value;

	if (change === null) {
		return savedImage.value;
	}

	return change.set === 'image' ? { path: change.image.folder === '' ? change.image.name : `${change.image.folder}/${change.image.name}`, url: change.image.url, name: change.image.name, title: change.image.title } : null;
});

// What it shows: the library image, else what it carries.
const artwork = computed(() => linked.value?.url ?? carried.value);

// Whether it will name an image once saved: one chosen, the one it
// carries to add, or the one it names now, left as it is.
const willLink = computed(() => artChange.value === null ? (file.value?.artwork ?? null) !== null : artChange.value.set !== 'none');

function chooseArtwork(files: MediaItem[]): void {
	choosingArt.value = false;

	const image = files[0];

	if (image !== undefined && image.id !== '') {
		artChange.value = image.id === file.value?.artwork?.id ? null : { set: 'image', image };
	}
}

function addArtwork(): void {
	artChange.value = { set: 'file' };
}

// Takes it off, or puts back that it had none.
function removeArtwork(): void {
	artChange.value = file.value?.artwork ? { set: 'none' } : null;
}

// What the panel says under the artwork's name while a change waits.
const artNote = computed(() => {
	switch (artChange.value?.set) {
		case 'image':
			return 'Chosen from the library; saved with the details';
		case 'file':
			return 'Added to the library when you save';
		case 'none':
			return 'Removed when you save; the image stays in the library';
		default:
			return null;
	}
});

// The offers whose fields are empty, which Fill from the File fills.
const fillable = computed(() => [...offers.value].filter(([field]) => String(form.value[field] ?? '').trim() === ''));

// Copies a value into a field, as typing it would; saving is still asked.
function use(field: string, text: string): void {
	form.value[field] = text;
	document.getElementById(`media-${field}`)?.focus();
}

function fillFromFile(): void {
	for (const [field, text] of fillable.value) {
		form.value[field] = text;
	}
}

// The heading: the title as typed, else the file name.
const heading = computed(() => {
	const title = typeof form.value.title === 'string' ? form.value.title.trim() : '';

	return title !== '' ? title : (file.value?.name ?? 'File');
});

// The line under the heading: its type, size in pixels or length, size
// on disk, and folder.
const summary = computed(() => file.value === null ? '' : [file.value.mime, ...(pages.value === null ? [] : [plural(pages.value, 'page')]), mediaFacts(file.value)].join(' · '));

// An image without alt text, which its field says.
const missingAlt = computed(() => file.value?.kind === 'image' && fields.value.some((field) => field.name === 'alt') && altText.value === '');

// What's playing, for a sound: its title, and who made it, from what.
const track = computed(() => {
	const values = file.value?.embedded.values ?? {};
	const text   = (key: string): string => {
		const value = values[key];

		return value === undefined ? '' : (Array.isArray(value) ? value.join(', ') : String(value));
	};

	return {
		title: text('title') || heading.value,
		by: [text('creator'), text('album'), text('track') === '' ? '' : `Track ${text('track')}`].filter((part) => part !== '').join(' · ')
	};
});

// A file's extension, for one that can't be shown.
const extension = computed(() => file.value?.name.includes('.') ? file.value.name.split('.').pop()?.toUpperCase() ?? '' : '');

// The entries that use it: the first five of a long list, until all are
// asked for.
const LONG     = 6;
const showAll  = ref(false);
const longList = computed(() => (file.value?.usedIn.length ?? 0) > LONG);
const shownUses = computed(() => longList.value && !showAll.value ? file.value?.usedIn.slice(0, 5) ?? [] : file.value?.usedIn ?? []);

function fieldLabel(name: string): string {
	const found = fields.value.find((field) => field.name === name);

	return found?.label ?? name;
}

function fill(item: MediaDetail): void {
	file.value    = item;
	form.value    = Object.fromEntries(item.fields.map((field) => [field.name, toForm(field, item.values[field.name])]));
	initial.value = { ...form.value };
	invalid.value = null;
}

// What's wrong with a field: what the last save refused, or what the
// file holds that doesn't fit it.
function errorFor(field: FieldDescription): string | undefined {
	if (invalid.value?.field === field.name) {
		return invalid.value.message;
	}

	const found = file.value?.violations.find((violation) => violation.field === field.name && violation.severity === 'error');

	return found === undefined ? undefined : `${found.message.charAt(0).toUpperCase()}${found.message.slice(1)}`;
}

watch(path, async () => {
	file.value      = null;
	artChange.value = null;
	showAll.value   = false;
	error.value   = '';
	failure.value = '';

	try {
		fill(await request<MediaDetail>('GET', address.value));
	} catch (caught) {
		error.value = errorMessage(caught, 'The file couldn\'t be loaded.');
	}
}, { immediate: true });

watch(file, (value) => {
	screenTitle.value = value === null ? null : mediaName(value);
	screenCrumb.value = value?.may.edit === true && value.original === null ? 'Editing' : null;
});

async function save(): Promise<void> {
	if (!changed.value || saving.value) {
		return;
	}

	invalid.value = null;

	const set: Record<string, unknown> = {};
	const remove: string[] = [];

	for (const field of changes.value) {
		const value = fromForm(field, form.value[field.name] ?? '', file.value?.values[field.name]);

		if (value === null) {
			remove.push(field.name);
		} else {
			set[field.name] = value;
		}
	}

	await run('It couldn\'t be saved.', async () => {
		// The details first, so a refused artwork leaves them saved.
		if (changes.value.length > 0) {
			fill(await request<MediaDetail>('PATCH', address.value, { set, remove }));
		}

		const change = artChange.value;

		if (change !== null) {
			const answer = change.set === 'none'
				? await request<MediaDetail>('DELETE', artworkAddress.value)
				: await request<MediaDetail>('PUT', artworkAddress.value, change.set === 'image' ? { image: change.image.id } : { from: 'file' });

			artChange.value = null;
			fill(answer);
		}

		forgetFile(file.value?.reference ?? '');
		toast('Saved');
	}, (caught) => {
		// A field the server blamed says so under it, not above the form.
		if (caught instanceof ApiError && caught.field !== null) {
			invalid.value = { field: caught.field, message: caught.message };
			failure.value = '';
		}
	});
}

// Puts the details and the artwork back as they were saved.
function revert(): void {
	form.value      = { ...initial.value };
	artChange.value = null;
	invalid.value   = null;
	failure.value   = '';
}

guardLeave(() => changed.value);

const deleting = ref(false);

// Deletes the file, saying first which entries use it.
async function remove(): Promise<void> {
	const item = file.value;

	if (item === null || deleting.value) {
		return;
	}

	const used  = item.usedIn.length;
	const shows = item.artworkFor.length;
	const goes  = item.sizes.length
		? `The file and its **${plural(item.sizes.length, 'other size')}** will be removed from the library and from disk.`
		: 'The file will be removed from the library and from disk.';
	const body  = [
		goes,
		used === 0
			? 'It isn\'t used in any entry. This can\'t be undone.'
			: `**${plural(used, 'entry', 'entries')} ${used === 1 ? 'uses' : 'use'} it**, and will be left pointing at an address that no longer resolves. This can't be undone.`,
		...(shows === 0 ? [] : [`It's the artwork of **${plural(shows, 'file')}**, which will have none of their own.`])
	];

	if (!await confirmAction({ title: `Delete ${mediaName(item)}?`, body, confirm: 'Delete the File', danger: true })) {
		return;
	}

	deleting.value = true;

	try {
		await request('DELETE', address.value);
		forgetFile(item.reference);
		initial.value = { ...form.value };
		toast(`Deleted ${mediaName(item)}`);
		await router.push({ name: 'media' });
	} catch (caught) {
		toast(errorMessage(caught, 'The file couldn\'t be deleted.'), { kind: 'warn' });
	} finally {
		deleting.value = false;
	}
}

// What an entry writes to show it: an image is Markdown, with the
// library's alt text and caption (D-267, D-268); a sound or video is its
// block, which the editor's media button inserts; anything else is a
// link, its title the link's text.
const snippet = computed(() => {
	const item = file.value;

	if (item === null) {
		return { text: '', label: '', note: '', what: '' };
	}

	if (item.kind === 'image') {
		return { text: imageText(item.reference, item.alt, item.caption).text, label: 'In Markdown', note: 'the alt text is filled in for you', what: 'the Markdown' };
	}

	if (item.kind === 'video' || item.kind === 'audio') {
		return { text: `::blush/${item.kind}{${attributeText('src', item.reference)}}`, label: 'In the editor', note: 'inserted by the media button', what: 'the block' };
	}

	return { text: linkText(mediaName(item), item.reference), label: 'In Markdown', note: 'the link text is the title', what: 'the Markdown' };
});
</script>

<template>
	<header class="page-header">
		<RouterLink class="page-back" :to="{ name: 'media' }"><AdminIcon name="chevron-left" />All media</RouterLink>
		<div class="page-header__text">
			<h1 tabindex="-1">{{ heading }}</h1>
			<p v-if="file" class="page-header__hint">
				<template v-if="heading !== file.name"><span class="mono">{{ file.name }}</span> · </template>{{ summary }} · <span class="mono head__folder">user/media/{{ file.folder }}</span>
			</p>
		</div>
		<div class="page-header__actions">
			<a v-if="file" class="button" :href="file.url" target="_blank" rel="noopener"><AdminIcon name="external-link" />Open<span class="visually-hidden"> the file (new tab)</span></a>
			<button v-if="file?.may.delete" type="button" class="button button--danger" :disabled="deleting" @click="remove"><AdminIcon name="trash-2" />Delete</button>
		</div>
	</header>

	<p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

	<div v-if="file" class="detail">
		<div class="detail__column">
			<section class="panel preview" aria-label="Preview">
				<img v-if="file.kind === 'image'" class="preview__image" :src="file.url" :alt="`Preview of ${mediaName(file)}`">
				<VideoPlayer v-else-if="file.kind === 'video'" class="preview__video" :src="file.url" :poster="artwork" />
				<AudioPlayer v-else-if="file.kind === 'audio'" class="preview__audio" :src="file.url" :title="track.title" :by="track.by" />
				<div v-else-if="pages !== null" class="preview__page">
					<div class="preview__sheet" :style="{ aspectRatio: pageShape }" aria-hidden="true">
						<i class="preview__line preview__line--head" /><i class="preview__line" /><i class="preview__line preview__line--short" /><i class="preview__line" />
						<i class="preview__line preview__line--end" /><i class="preview__line" /><i class="preview__line preview__line--short" /><i class="preview__line" /><i class="preview__line preview__line--end" />
						<span class="preview__pages mono">{{ plural(pages, 'page') }}</span>
					</div>
				</div>
				<div v-else class="preview__none">
					<AdminIcon :name="mediaIcon(file)" />
					<span v-if="extension" class="preview__ext mono">{{ extension }}</span>
					<span>No preview for this kind of file</span>
				</div>
			</section>

			<section v-if="file.kind === 'audio' || file.kind === 'video'" class="panel" aria-labelledby="artwork-heading">
				<header class="panel__header">
					<h2 id="artwork-heading">Artwork</h2>
					<span class="panel__hint">{{ file.kind === 'video' ? 'Its poster, where a page names none' : 'Shown with it on the site' }}</span>
				</header>
				<div class="panel__body art">
					<div class="art__row">
						<img v-if="artwork" class="art__image" :src="artwork" alt="">
						<span v-else class="art__image art__image--none" aria-hidden="true"><AdminIcon :name="file.kind === 'video' ? 'film' : 'music'" /></span>
						<div class="art__text">
							<template v-if="linked">
								<RouterLink :to="{ name: 'media-file', params: { path: linked.path.split('/') } }">{{ linked.title || linked.name }}</RouterLink>
								<span class="art__note">{{ artNote ?? 'An image in the library' }}</span>
							</template>
							<template v-else-if="carried">
								<span>Carried in the file</span>
								<span class="art__note">{{ artNote ?? embeddedText('artwork', file.embedded.values.artwork ?? '') }}</span>
							</template>
							<template v-else>
								<span>None</span>
								<span class="art__note">{{ artNote ?? (file.kind === 'video' ? 'It shows its first frame until it plays.' : 'Its card shows the title alone.') }}</span>
							</template>
						</div>
					</div>
					<p v-if="artChange === null && file.artwork && !linked" class="field__warn"><AdminIcon name="triangle-alert" /><span>The image it named is no longer in the library{{ carried ? ', so what it carries is shown' : '' }}.</span></p>
					<div v-if="file.may.edit" class="art__actions">
						<button v-if="!linked && artChange?.set !== 'file' && file.may.addArtwork" type="button" class="button button--small" :disabled="saving" @click="addArtwork"><AdminIcon name="plus" />Add to Library</button>
						<button type="button" class="button button--small" :disabled="saving" @click="choosingArt = true"><AdminIcon name="image" />{{ linked ? 'Change Image' : 'Choose Image' }}</button>
						<button v-if="willLink" type="button" class="button button--small button--ghost" :disabled="saving" @click="removeArtwork"><AdminIcon name="x" />Remove</button>
					</div>
					<p v-if="file.may.edit" class="field__help">A link to a library image, with its own alt text and sizes; the file itself isn't changed.</p>
				</div>
			</section>

			<section class="panel" aria-labelledby="usage-heading">
				<header class="panel__header">
					<h2 id="usage-heading">Usage</h2>
					<span class="panel__hint">Copy it, and see where it already is</span>
				</header>
				<div class="panel__body usage">
					<div class="usage__copy">
						<p class="usage__label"><span>Address</span> the file itself</p>
						<div class="usage__row">
							<code>{{ file.reference }}</code>
							<button type="button" class="button button--icon" aria-label="Copy the address" title="Copy" @click="copyText(file.reference, 'the address')"><AdminIcon name="copy" /></button>
						</div>
					</div>
					<div class="usage__copy">
						<p class="usage__label"><span>{{ snippet.label }}</span> {{ snippet.note }}</p>
						<div class="usage__row">
							<code>{{ snippet.text }}</code>
							<button type="button" class="button button--icon" :aria-label="`Copy ${snippet.what}`" title="Copy" @click="copyText(snippet.text, snippet.what)"><AdminIcon name="copy" /></button>
						</div>
					</div>
				</div>
				<div class="panel__body usage__uses">
					<p class="eyebrow">{{ file.usedIn.length === 0 ? 'Not used yet' : `Used in ${plural(file.usedIn.length, 'entry', 'entries')}` }}</p>
					<p v-if="file.usedIn.length === 0" class="field__help">No entry uses it, by any of its addresses.</p>
					<template v-else>
						<ul class="used" :class="{ 'used--scroll': longList && showAll }">
							<li v-for="entry in shownUses" :key="entry.path">
								<RouterLink :to="entryRoute(entry)">{{ entry.title }}</RouterLink>
								<span v-if="entry.typeLabel" class="used__type">{{ entry.typeLabel }}</span>
							</li>
						</ul>
						<button v-if="longList" type="button" class="button button--ghost button--small" :aria-expanded="showAll" @click="showAll = !showAll"><AdminIcon :name="showAll ? 'chevron-up' : 'chevron-down'" />{{ showAll ? 'Show Fewer' : `Show All ${file.usedIn.length}` }}</button>
					</template>
				</div>
				<div v-if="file.artworkFor.length" class="panel__body usage__uses">
					<p class="eyebrow">Artwork for {{ plural(file.artworkFor.length, 'file') }}</p>
					<ul class="used" :class="{ 'used--scroll': file.artworkFor.length > LONG }">
						<li v-for="shown in file.artworkFor" :key="shown.path">
							<RouterLink :to="{ name: 'media-file', params: { path: shown.path.split('/') } }">{{ shown.title || shown.name }}</RouterLink>
							<span class="used__type">{{ shown.kind === 'video' ? 'Video' : 'Sound' }}</span>
						</li>
					</ul>
				</div>
			</section>

			<section class="panel" aria-labelledby="storage-heading">
				<header class="panel__header">
					<h2 id="storage-heading">Storage</h2>
					<span class="panel__hint">Where it lives</span>
				</header>
				<dl class="panel__body facts">
					<div><dt>Folder</dt><dd class="mono">user/media/{{ file.folder }}</dd></div>
					<div><dt>Type</dt><dd class="mono">{{ file.mime }}</dd></div>
					<div><dt>Size</dt><dd class="mono">{{ formatSize(file.size) }}</dd></div>
					<div v-if="file.width !== null && file.height !== null"><dt>Dimensions</dt><dd class="mono">{{ file.width }} × {{ file.height }}</dd></div>
					<div v-if="file.duration !== null"><dt>Length</dt><dd class="mono">{{ formatDuration(file.duration) }}</dd></div>
					<div v-if="pages !== null"><dt>Pages</dt><dd class="mono">{{ pages }}</dd></div>
					<div><dt>Changed</dt><dd>{{ formatDate(file.modified) }}</dd></div>
					<div><dt>Uploaded by</dt><dd :class="{ 'facts__none': file.uploader === null }">{{ file.uploader?.name ?? 'Not recorded' }}</dd></div>
				</dl>
			</section>

			<section v-if="file.sizes.length" class="panel" aria-labelledby="sizes-heading">
				<header class="panel__header">
					<h2 id="sizes-heading">Other Sizes</h2>
					<span class="panel__hint">{{ plural(file.sizes.length, 'other size') }}</span>
				</header>
				<ul class="panel__body sizes">
					<li v-for="size in file.sizes" :key="size.path">
						<RouterLink class="mono" :to="{ name: 'media-file', params: { path: size.path.split('/') } }">{{ size.name }}</RouterLink>
						<span class="sizes__facts">{{ mediaFacts({ width: size.width, height: size.height, duration: null, size: size.size }) }}</span>
					</li>
				</ul>
			</section>
		</div>

		<div class="detail__column">
			<form class="panel" aria-labelledby="text-heading" @submit.prevent="save">
				<header class="panel__header">
					<h2 id="text-heading">Details</h2>
					<span class="panel__hint">{{ file.kind === 'audio' || file.kind === 'video' ? 'Saved with the artwork' : 'The only part of this screen that saves' }}</span>
					<div v-if="fillable.length" class="panel__actions">
						<button type="button" class="button button--small" @click="fillFromFile"><AdminIcon name="import" />Fill from the File</button>
					</div>
				</header>
				<fieldset class="panel__body text" :disabled="!file.may.edit">
					<p v-if="file.original" class="field__help text__note"><AdminIcon name="info" />A size of <RouterLink :to="{ name: 'media-file', params: { path: file.original.path.split('/') } }">{{ file.original.title || file.original.name }}</RouterLink>, whose details it goes by. Change them there.</p>
					<p v-else-if="!file.may.edit" class="field__help text__note"><AdminIcon name="info" />{{ file.uploader === null ? 'No one\'s recorded as uploading this file, so only someone who may change anyone\'s files can change its details.' : `Only ${file.uploader.name}, or someone who may change anyone's files, can change its details.` }}</p>
					<FieldControl
						v-for="field in ownFields"
						:key="`${file.reference}-${field.name}`"
						:field="field"
						id-prefix="media-"
						:model-value="form[field.name] ?? ''"
						:error="errorFor(field)"
						:warn="field.name === 'alt' && missingAlt"
						@update:model-value="form[field.name] = $event"
					>
						<template v-if="offers.has(field.name)" #after>
							<p v-if="form[field.name] === offers.get(field.name)" class="offer offer--taken"><AdminIcon name="check" />From the file</p>
							<p v-else class="offer"><AdminIcon name="file" />The file says <q>{{ offers.get(field.name) }}</q><button type="button" class="button button--ghost button--small" @click="use(field.name, offers.get(field.name) ?? '')">Use It<span class="visually-hidden"> as the {{ fieldLabel(field.name).toLowerCase() }}</span></button></p>
						</template>
						<template v-if="field.name === 'alt' && missingAlt" #help="{ id }">
							<p :id="id" class="field__warn"><AdminIcon name="triangle-alert" /><span><strong>No alt text.</strong> It's what the image shows, for anyone who can't see it; images inserted from the library start with it, and pages use it where they have none.</span></p>
						</template>
					</FieldControl>
					<div v-for="set in setGroups" :key="`${file.reference}-set-${set.name}`" class="text__set">
						<p class="text__set-heading">{{ set.label }}</p>
						<p v-if="set.description" class="field__help">{{ set.description }}</p>
						<FieldControl
							v-for="field in set.fields"
							:key="`${file.reference}-${field.name}`"
							:field="field"
							id-prefix="media-"
							:model-value="form[field.name] ?? ''"
							:error="errorFor(field)"
							@update:model-value="form[field.name] = $event"
						/>
					</div>
					<div v-if="extra.length" class="text__extra">
						<p class="field__help">Also in its metadata file, kept as they are:</p>
						<RawValues :entries="extra" />
					</div>
					<p v-if="file.may.edit" class="field__help">Kept in <code>user/data/media</code>, not written back into the file.</p>
				</fieldset>

				<SaveBar v-if="file.may.edit" :count="changes.length + (artChange === null ? 0 : 1)" :failure="failure" :saving="saving" @revert="revert" />
			</form>

			<section class="panel" aria-labelledby="metadata-heading">
				<header class="panel__header">
					<h2 id="metadata-heading">Metadata</h2>
					<span class="panel__hint">What the file says about itself</span>
				</header>
				<div class="panel__body">
					<p v-if="file.embedded.location" class="field__warn"><AdminIcon name="triangle-alert" /><span>This file carries where it was taken (GPS). The site never shows it, but anyone who downloads the file can read it.</span></p>
					<dl v-if="metadata.length" class="facts">
						<div v-for="item in metadata" :key="item.key">
							<dt>{{ item.label }}</dt>
							<dd>{{ item.text }}</dd>
						</div>
					</dl>
					<p v-else-if="!file.embedded.location" class="field__help">{{ embedded.length ? 'Nothing more than the details above.' : 'None in this file.' }}</p>
				</div>
			</section>
		</div>
	</div>

	<MediaPicker v-if="choosingArt" title="Choose Artwork" action="Use as Artwork" kind="image" locked @choose="chooseArtwork" @close="choosingArt = false" />
</template>

<style scoped>
/* Two columns (D-551): the file on the left, at most 440px; its details
   on the right. One column below 1100px. */
.detail {
	display: grid;
	grid-template-columns: minmax(0, 440px) minmax(0, 1fr);
	align-items: start;
	gap: var(--s-4);
}

.detail__column {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--s-4);
	min-width: 0;
}

@media (width <= 1100px) {
	.detail {
		grid-template-columns: minmax(0, 1fr);
	}
}

.head__folder {
	color: var(--fg-3);
}

/* The preview: an image or video fills its card; a sound is its art,
   what's playing, and a player; anything else says it can't be shown. */
.preview {
	overflow: hidden;
}

.preview__image {
	display: block;
	width: 100%;
	height: auto;
	max-height: 70vh;
	object-fit: contain;
	background: var(--surface-2);
}

.preview__video {
	max-height: 70vh;
}

.preview__audio {
	border: 0;
	border-radius: 0;
	background: none;
}

.preview__none {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--s-2);
	padding: var(--s-7) var(--s-5);
	background: var(--surface-2);
	color: var(--fg-3);
	font-size: var(--text-sm);
	text-align: center;
}

.preview__none :deep(svg) {
	width: 26px;
	height: 26px;
}

.preview__ext {
	padding: 2px 8px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	color: var(--fg-2);
	font-size: var(--text-xs);
	letter-spacing: .08em;
}

/* A document that says how many pages it has: a page, drawn, in its
   shape, with the count at its corner. */
.preview__page {
	display: grid;
	place-items: center;
	padding: var(--s-5);
	background: var(--surface-2);
}

.preview__sheet {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 7px;
	width: min(248px, 62%);
	padding: var(--s-5) var(--s-4);
	border: 1px solid var(--border-strong);
	border-radius: 3px;
	background: var(--surface);
}

.preview__line {
	display: block;
	height: 5px;
	border-radius: 2px;
	background: var(--surface-3);
}

.preview__line--head {
	width: 62%;
	height: 9px;
	margin-bottom: 5px;
	background: var(--border-strong);
}

.preview__line--short {
	width: 84%;
}

.preview__line--end {
	width: 46%;
}

.preview__pages {
	position: absolute;
	right: -9px;
	bottom: -9px;
	padding: 2px 7px;
	border: 1px solid var(--border-strong);
	border-radius: var(--r-1);
	background: var(--surface);
	color: var(--fg-2);
	font-size: var(--text-xs);
}

/* Artwork (D-581): the picture beside what it is, then what can be
   done with it. */
.art {
	display: grid;
	gap: var(--s-3);
}

.art > * + * {
	margin-top: 0;
}

.art__row {
	display: flex;
	align-items: center;
	gap: var(--s-3);
	min-width: 0;
}

.art__image {
	flex: none;
	width: 72px;
	height: 72px;
	border: 1px solid var(--border);
	border-radius: var(--r-1);
	background: var(--surface-2);
	object-fit: cover;
}

.art__image--none {
	display: grid;
	place-items: center;
	color: var(--fg-3);
}

.art__image--none :deep(svg) {
	width: 22px;
	height: 22px;
}

.art__text {
	display: grid;
	gap: 2px;
	min-width: 0;
	overflow-wrap: anywhere;
}

.art__note {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.art__actions {
	display: flex;
	flex-wrap: wrap;
	gap: var(--s-2);
}

/* Usage: what to copy, then the entries that use it. */
/* One column at the card's width, so a long address scrolls inside its
   box rather than widening the card. */
.usage {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: var(--s-4);
}

.usage__copy {
	min-width: 0;
}

.usage > * + * {
	margin-top: 0;
}

.usage__label {
	display: flex;
	align-items: baseline;
	gap: var(--s-2);
	margin: 0 0 6px;
	color: var(--fg-3);
	font-size: var(--text-xs);
}

.usage__label span {
	color: var(--fg-2);
	font-size: var(--text-sm);
	font-weight: 500;
}

.usage__row {
	display: flex;
	gap: 6px;
}

.usage__row code {
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

.usage__row .button {
	flex: none;
}

.usage__uses {
	border-top: 1px solid var(--border);
}

.usage__uses > * + * {
	margin-top: var(--s-3);
}

.used {
	display: grid;
	gap: var(--s-3);
	margin: 0;
	padding: 0;
	list-style: none;
}

/* All of a long list, kept from making the column a page long. */
.used--scroll {
	max-height: 232px;
	padding-right: var(--s-1);
	overflow-y: auto;
}

.used li {
	display: flex;
	align-items: baseline;
	gap: var(--s-3);
	min-width: 0;
}

.used a {
	min-width: 0;
	font-family: var(--font-title);
	font-size: var(--title-size);
	font-weight: var(--title-weight);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.used__type {
	flex: none;
	margin-left: auto;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.facts dd.mono {
	font-size: var(--text-sm);
}

.facts__none {
	color: var(--fg-3);
}

/* A size's file name, and its facts under it. */
.sizes {
	display: grid;
	gap: var(--s-3);
	margin: 0;
	list-style: none;
}

.sizes > * + * {
	margin-top: 0;
}

.sizes li {
	display: grid;
	gap: 3px;
}

.sizes a {
	font-size: var(--text-sm);
	overflow-wrap: anywhere;
}

.sizes__facts {
	color: var(--fg-3);
	font-size: var(--text-sm);
}

/* The form's controls, read-only as one when they can't be changed. */
fieldset.text {
	display: grid;
	gap: var(--s-4);
	min-width: 0;
	margin: 0;
	border: 0;
}

.text > * + * {
	margin-top: 0;
}

.text__note {
	display: flex;
	gap: var(--s-2);
}

.text__note :deep(.icon) {
	flex: none;
	width: 14px;
	height: 14px;
	margin-top: 2px;
}

/* A value the file offers for a field, under it, or that it's taken. */
.offer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px;
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-sm);
}

.offer :deep(.icon) {
	flex: none;
	width: 13px;
	height: 13px;
}

.offer q {
	color: var(--fg-2);
	overflow-wrap: anywhere;
}

.offer .button {
	margin-block: -4px;
}

.offer--taken {
	color: var(--good);
}

.offer--taken :deep(.icon) {
	color: var(--good-dot);
}

/* A field set's fields, under its label (D-341), as the editor's panel
   groups them. */
.text__set {
	display: grid;
	gap: var(--s-4);
	padding-top: var(--s-4);
	border-top: 1px solid var(--border);
}

.text__set > .field__help {
	margin-top: calc(var(--s-4) * -1 + 4px);
}

.text__set-heading {
	margin: 0;
	color: var(--fg-3);
	font-size: var(--text-xs);
	font-weight: 600;
	letter-spacing: .07em;
	text-transform: uppercase;
}

.text__extra dl {
	margin: 6px 0 0;
}
</style>
