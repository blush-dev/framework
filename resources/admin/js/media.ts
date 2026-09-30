/**
 * Media files as the editor finds them from what content writes: the
 * files beside an entry in a page bundle (`GET media`'s `beside`), loaded
 * once per entry, and library files by their address (`GET
 * media/{path}`), each once; shared, so an image can be shown and its
 * library alt text offered (D-272).
 */

import { request, type MediaItem, type MediaList } from './api';
import { config } from './config';

const loaded = new Map<string, Promise<MediaItem[]>>();

/**
 * The files beside an entry: none when it isn't a bundle, or when they
 * couldn't be loaded (tried again next time).
 */
export function besideFiles(entry: string): Promise<MediaItem[]> {
	let files = loaded.get(entry);

	if (files === undefined) {
		files = request<MediaList>('GET', `/media?${new URLSearchParams({ entry, per: '1' }).toString()}`).then(
			(answer) => answer.beside ?? [],
			() => {
				loaded.delete(entry);

				return [];
			}
		);

		loaded.set(entry, files);
	}

	return files;
}

/**
 * Forgets an entry's files, after one is added beside it.
 */
export function forgetBeside(entry: string): void {
	loaded.delete(entry);
}

const library = new Map<string, Promise<MediaItem | null>>();

/**
 * The media file an image's address names: a library file by its URL
 * path, or a file beside the entry by its name; `null` for anything
 * else (another site's address) or a file that isn't there.
 */
export async function mediaFile(src: string, entry?: string): Promise<MediaItem | null> {
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

	if (entry === undefined || /^([a-z][a-z0-9+.-]*:)?\/\//i.test(address) || address.startsWith('/')) {
		return null;
	}

	return (await besideFiles(entry)).find((item) => item.reference === address || item.name === address) ?? null;
}

/**
 * Forgets a library file, after its alt text or caption changes.
 */
export function forgetFile(reference: string): void {
	library.delete(reference.slice(config.media.url.length + 1));
}
