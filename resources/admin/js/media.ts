/**
 * Media files as the editor finds them from what content writes: library
 * files by their address (`GET media/{path}`), each once; shared, so an image can be shown and its
 * library alt text offered (D-272).
 */

import { ref, watch, type Ref } from 'vue';
import { debounced, latest } from './action';
import { errorMessage, request, type MediaItem, type MediaList } from './api';
import { config } from './config';
import { formatSize } from './format';
import { finish } from './jobs';
import type { IconName } from './icons';

const library = new Map<string, Promise<MediaItem | null>>();

/**
 * The media file an image's address names: a library file by its URL
 * path; `null` for anything else (another site's address) or a file that
 * isn't there.
 */
export async function mediaFile(src: string): Promise<MediaItem | null> {
	const address = src.replace(/[?#].*$/, '');
	const prefix  = `${config.media.url}/`;

	if (address.startsWith(prefix)) {
		const path = address.slice(prefix.length);
		let file   = library.get(path);

		if (file === undefined) {
			file = request<MediaItem>('GET', `/media/${path}`).catch(() => {
				library.delete(path);

				return null;
			});

			library.set(path, file);
		}

		return file;
	}

	return null;
}

/**
 * Forgets a library file, after its alt text or caption changes.
 */
export function forgetFile(reference: string): void {
	library.delete(reference.slice(config.media.url.length + 1));
}

/**
 * What the library calls a file (D-290): its title, else its file name.
 */
export function mediaName(file: Pick<MediaItem, 'title' | 'name'>): string {
	return file.title !== '' ? file.title : file.name;
}

/**
 * A length of time as a player shows it: `3:25`, or `1:02:03`.
 */
export function formatDuration(seconds: number): string {
	const whole = Math.round(seconds);
	const hours = Math.floor(whole / 3600);
	const parts = [Math.floor(whole / 60) % 60, whole % 60].map((part, index) => index === 0 && hours === 0 ? String(part) : String(part).padStart(2, '0'));

	return hours > 0 ? `${hours}:${parts.join(':')}` : parts.join(':');
}

/**
 * A file's facts, for a card: its size in pixels, how long it lasts, and
 * its size on disk.
 */
export function mediaFacts(file: Pick<MediaItem, 'width' | 'height' | 'duration' | 'size'>): string {
	return [
		file.width !== null && file.height !== null ? `${file.width} × ${file.height}` : '',
		file.duration !== null ? formatDuration(file.duration) : '',
		formatSize(file.size)
	].filter((part) => part !== '').join(' · ');
}

const numbers = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });

/**
 * A value a file says about itself (D-289, D-551), as people read it: a
 * bit rate in kbit/s, a sample rate in kHz, channels as mono or stereo, a
 * frame rate in fps, and a list joined with commas.
 */
export function embeddedText(key: string, value: string | number | string[]): string {
	if (Array.isArray(value)) {
		return value.join(', ');
	}

	if (typeof value !== 'number') {
		return value;
	}

	switch (key) {
		case 'bitrate':
			return `${Math.round(value / 1000)} kbps`;
		case 'sampleRate':
			return `${numbers.format(value / 1000)} kHz`;
		case 'channels':
			return value === 1 ? 'mono' : (value === 2 ? 'stereo' : `${value} channels`);
		case 'frameRate':
			return `${numbers.format(value)} fps`;
		default:
			return String(value);
	}
}

/**
 * The glyph for a file shown without a picture of it.
 */
export function mediaIcon(file: Pick<MediaItem, 'kind'>): IconName {
	return file.kind === 'video' ? 'film' : (file.kind === 'audio' ? 'music' : (file.kind === 'document' ? 'file-text' : 'file'));
}

/**
 * The library a page at a time (D-509), as the Media screen and the media
 * picker list it: by `kind`, a `search` (asked a moment after typing
 * stops), and, when `mine` says so, only the account's own uploads. Only
 * the latest answer is shown; `answered` hears every one. `load(true)`
 * adds the next page; the first load is the caller's. While the library
 * is catching up (a rebuild, D-626), it follows the job reading the rest,
 * with its progress in `indexing`, and loads again when it's done.
 */
export function useMediaList(kind: Ref<string>, search: Ref<string>, mine: () => boolean = () => false, answered?: (answer: MediaList) => void) {
	const files   = ref<MediaItem[]>([]);
	const total   = ref(0);
	const page    = ref(1);
	const pages   = ref(1);
	const loading = ref(true);
	const error   = ref('');
	const ask     = latest();
	// How far along reading the library is, while it catches up.
	const indexing = ref<number | null>(null);

	async function load(more = false): Promise<void> {
		const current = ask();
		const params  = new URLSearchParams({ page: String(more ? page.value + 1 : 1), kind: kind.value });

		if (search.value.trim() !== '') {
			params.set('search', search.value.trim());
		}

		if (mine()) {
			params.set('mine', '1');
		}

		loading.value = true;
		error.value   = '';

		try {
			const answer = await request<MediaList>('GET', `/media?${params.toString()}`);

			if (current()) {
				files.value = more ? [...files.value, ...answer.files] : answer.files;
				total.value = answer.total;
				page.value  = answer.page;
				pages.value = answer.pages;
				answered?.(answer);

				if (answer.indexing !== null && indexing.value === null) {
					void catchUp(answer.indexing);
				}
			}
		} catch (caught) {
			if (current()) {
				error.value = errorMessage(caught, 'The media couldn\'t be loaded.');
			}
		} finally {
			if (current()) {
				loading.value = false;
			}
		}
	}

	async function catchUp(job: string): Promise<void> {
		indexing.value = 0;

		try {
			await finish(job, (update) => {
				indexing.value = update.progress ?? 0;
			});
		} catch {
			// What's listed stays; a later visit catches up again.
		} finally {
			indexing.value = null;
		}

		await load();
	}

	watch(search, debounced(() => void load(), 250));
	watch([kind, mine], () => void load());

	return { files, total, page, pages, loading, error, indexing, load };
}
