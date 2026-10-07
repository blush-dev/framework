/**
 * The audio player (D-553): a `<blush-audio-player>` around a native
 * `<audio>`, drawn as the media detail sketch draws it: a round play
 * button, a track to seek along, the time, "0:02 / 0:12", and the
 * volume (D-576), a mute button with a slider over it. It's plain DOM,
 * without Vue, so the admin and the site both use it; without its
 * script, the browser's own controls stay. In a card (`.audio-card`,
 * `player.css`, D-575), it sits beside the artwork, under the title.
 *
 *     <blush-audio-player>
 *         <audio src="/media/song.mp3" controls preload="metadata"></audio>
 *     </blush-audio-player>
 *
 * Its labels are English unless `label-play`, `label-pause`,
 * `label-seek`, `label-mute`, `label-unmute`, and `label-volume` say
 * otherwise. Its colors are the host's, by custom
 * properties (`player.css`).
 */

import { listen, transport, volume } from './controls';

export class AudioPlayer extends HTMLElement {
	private audio: HTMLAudioElement | null = null;

	connectedCallback(): void {
		if (this.audio !== null) {
			listen(this.audio, true);

			return;
		}

		const audio = this.querySelector('audio');

		if (audio === null) {
			return;
		}

		const { play, seek, time } = transport(this, audio);
		const level = volume(this, audio);

		this.audio = audio;
		this.classList.add('player', 'audio-player');
		this.append(play, seek, time, level.control);
		listen(audio, true);
	}

	disconnectedCallback(): void {
		if (this.audio !== null) {
			listen(this.audio, false);
		}
	}
}

/**
 * Defines the element, once.
 */
export function defineAudioPlayer(): void {
	if (customElements.get('blush-audio-player') === undefined) {
		customElements.define('blush-audio-player', AudioPlayer);
	}
}
