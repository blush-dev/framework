/**
 * The playlist (D-708): a `<blush-playlist>` around one audio player
 * (`<blush-audio-player>`, plain or in an audio card) and its tracks, an
 * `<ol>` of rows (`.playlist__track`) whose links are their files.
 * Choosing a row plays it in the player, or plays and pauses it when
 * it's the one playing; when a track ends, the next plays, and after
 * the last, the first again with `loop`. The playing row is
 * `aria-current`. In a card, the title, who made it, and the artwork
 * (the row's `data-art`) follow the track. Without its script, the
 * player plays the first track and each row links to its file.
 *
 *     <blush-playlist loop>
 *         <blush-audio-player><audio src="/media/one.mp3" controls></audio></blush-audio-player>
 *         <ol class="playlist__tracks">
 *             <li class="playlist__track" data-art=""><a class="playlist__link" href="/media/one.mp3">…</a></li>
 *         </ol>
 *     </blush-playlist>
 */

import { toggle } from './controls';

export class Playlist extends HTMLElement {
	private audio: HTMLAudioElement | null = null;
	private rows: HTMLElement[] = [];
	private current = 0;

	connectedCallback(): void {
		if (this.audio !== null) {
			return;
		}

		const audio = this.querySelector('audio');
		const rows  = [...this.querySelectorAll<HTMLElement>('.playlist__track')];

		if (audio === null || rows.length === 0) {
			return;
		}

		this.audio = audio;
		this.rows  = rows;

		rows.forEach((row, index) => {
			row.querySelector('a')?.addEventListener('click', (event) => {
				event.preventDefault();
				this.choose(index);
			});
		});

		audio.addEventListener('ended', () => this.next());
		this.mark();
	}

	/**
	 * Plays a track, or plays and pauses the one already in the player.
	 */
	private choose(index: number): void {
		const audio = this.audio;
		const row   = this.rows[index];
		const link  = row?.querySelector('a');

		if (audio === null || row === undefined || link === null || link === undefined) {
			return;
		}

		if (index === this.current) {
			toggle(audio);

			return;
		}

		this.current = index;
		audio.src    = link.href;
		this.show(row);
		this.mark();
		void audio.play().catch(() => undefined);
	}

	/**
	 * Plays the next track, or the first after the last with `loop`.
	 */
	private next(): void {
		const next = this.current + 1;

		if (next < this.rows.length) {
			this.choose(next);
		} else if (this.hasAttribute('loop') && this.rows.length > 1) {
			this.choose(0);
		} else if (this.hasAttribute('loop') && this.audio !== null) {
			void this.audio.play().catch(() => undefined);
		}
	}

	/**
	 * Marks the row in the player as the current one.
	 */
	private mark(): void {
		this.rows.forEach((row, index) => {
			if (index === this.current) {
				row.setAttribute('aria-current', 'true');
			} else {
				row.removeAttribute('aria-current');
			}
		});
	}

	/**
	 * Shows a track in the card, if there's one: its title, who made it,
	 * and its artwork.
	 */
	private show(row: HTMLElement): void {
		const card = this.querySelector<HTMLElement>('.audio-card');

		if (card === null) {
			return;
		}

		const title = card.querySelector('.audio-card__title');
		const by    = card.querySelector<HTMLElement>('.audio-card__by');
		const text  = row.querySelector('.playlist__by')?.textContent ?? '';
		const art   = row.dataset.art ?? '';
		let image   = card.querySelector<HTMLImageElement>('.audio-card__art');

		if (title !== null) {
			// The row's own title, as the server rendered it.
			title.innerHTML = row.querySelector('.playlist__title')?.innerHTML ?? '';
		}

		if (by !== null) {
			by.textContent = text;
			by.hidden      = text === '';
		}

		if (art === '') {
			image?.remove();

			return;
		}

		if (image === null) {
			image           = document.createElement('img');
			image.className = 'audio-card__art';
			image.alt       = '';
			image.decoding  = 'async';
			card.prepend(image);
		}

		image.src = art;
	}
}

/**
 * Defines the element, once.
 */
export function definePlaylist(): void {
	if (customElements.get('blush-playlist') === undefined) {
		customElements.define('blush-playlist', Playlist);
	}
}
