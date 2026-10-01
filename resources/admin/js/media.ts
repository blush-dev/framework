/**
 * Media files as the editor finds them from what content writes: library
 * files by their address (`GET media/{path}`), each once; shared, so an image can be shown and its
 * library alt text offered (D-272).
 */

import { request, type MediaItem } from './api';
import { config } from './config';
import { formatSize } from './format';

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
