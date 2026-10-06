/**
 * The video player (D-554): a `<blush-video-player>` around a native
 * `<video>`, matching the audio player. Until it's first played, a round
 * play button sits over the picture with its length in a corner, as the
 * media detail sketch draws it; then a bar over the picture's foot holds
 * the shared transport (play, seek, time), mute and volume, captions (when the
 * video has caption or subtitle tracks), and fullscreen. The bar hides
 * while it plays and the pointer rests, and comes back on a move, a
 * touch, or focus. Clicking the picture plays or pauses it. It's plain
 * DOM, without Vue, so the admin and, later, the site can both use it;
 * without its script, the browser's own controls stay.
 *
 *     <blush-video-player>
 *         <video src="/media/clip.mp4" controls preload="metadata"></video>
 *     </blush-video-player>
 *
 * Fullscreen takes the whole player, controls and all, where the browser
 * allows; elsewhere (iOS) it's the browser's own. Its labels are English
 * unless `label-{name}` says otherwise: `play`, `pause`, `seek`, `mute`, `volume`,
 * `unmute`, `captions`, `fullscreen`, and `exit-fullscreen`. Its colors
 * are the host's, by custom properties (`player.css`).
 */

import { button, clock, label, listen, toggle, transport, volume } from './controls';

// How long the pointer rests before the bar hides, in milliseconds.
const REST = 2500;

export class VideoPlayer extends HTMLElement {
	private video: HTMLVideoElement | null = null;

	private timer = 0;

	connectedCallback(): void {
		if (this.video !== null) {
			listen(this.video, true);

			return;
		}

		const video = this.querySelector('video');

		if (video === null) {
			return;
		}

		this.video = video;
		video.playsInline = true;

		const start  = button('video-player__start');
		const stamp  = document.createElement('span');
		const bar    = document.createElement('div');
		const mute   = button('video-player__mute');
		const shown  = button('video-player__captions');
		const screen = button('video-player__fullscreen');

		stamp.className = 'video-player__stamp';
		bar.className   = 'video-player__bar';

		const tracks = (): TextTrack[] => [...video.textTracks].filter((track) => track.kind === 'captions' || track.kind === 'subtitles');
		const full   = (): boolean => document.fullscreenElement === this;

		const draw = (): void => {
			const captioned = tracks().some((track) => track.mode === 'showing');

			start.show('play', label(this, 'play', 'Play'));
			stamp.textContent = Number.isFinite(video.duration) ? clock(video.duration) : '';
			mute.show(video.muted ? 'muted' : 'volume', video.muted ? label(this, 'unmute', 'Unmute') : label(this, 'mute', 'Mute'));
			shown.show('captions', label(this, 'captions', 'Captions'));
			shown.button.setAttribute('aria-pressed', String(captioned));
			shown.button.hidden = tracks().length === 0;
			screen.show(full() ? 'minimize' : 'maximize', full() ? label(this, 'exit-fullscreen', 'Exit Fullscreen') : label(this, 'fullscreen', 'Fullscreen'));
			this.toggleAttribute('data-playing', !video.paused);
		};

		const { play, seek, time } = transport(this, video, draw);

		start.show('play', label(this, 'play', 'Play'));
		start.button.addEventListener('click', () => toggle(video));
		video.addEventListener('click', () => toggle(video));
		video.addEventListener('play', () => {
			this.setAttribute('data-started', '');
			this.wake();
		});
		video.addEventListener('pause', () => this.wake());

		const level = volume(this, video);

		mute.button.addEventListener('click', () => {
			// Unmuting at nothing would stay silent: back to half.
			if (video.muted && video.volume === 0) {
				video.volume = 0.5;
			}

			video.muted = !video.muted;
		});

		shown.button.addEventListener('click', () => {
			const all = tracks();
			const on  = all.some((track) => track.mode === 'showing');

			all.forEach((track, index) => {
				track.mode = !on && index === 0 ? 'showing' : 'hidden';
			});
			draw();
		});

		video.textTracks.addEventListener('addtrack', draw);
		video.textTracks.addEventListener('change', draw);

		screen.button.addEventListener('click', () => {
			if (full()) {
				void document.exitFullscreen().catch(() => undefined);
			} else if (document.fullscreenEnabled) {
				void this.requestFullscreen().catch(() => undefined);
			} else {
				(video as HTMLVideoElement & { webkitEnterFullscreen?: () => void }).webkitEnterFullscreen?.();
			}
		});

		document.addEventListener('fullscreenchange', draw);

		for (const event of ['pointermove', 'pointerdown', 'focusin', 'keydown']) {
			this.addEventListener(event, () => this.wake());
		}

		bar.append(play, seek, time, mute.button, level.slider, shown.button, screen.button);
		this.classList.add('player', 'video-player');
		this.append(start.button, stamp, bar);
		listen(video, true);
		draw();
	}

	disconnectedCallback(): void {
		window.clearTimeout(this.timer);

		if (this.video !== null) {
			listen(this.video, false);
		}
	}

	/**
	 * Shows the bar, and hides it again after a rest while it plays and
	 * no control has keyboard focus.
	 */
	private wake(): void {
		this.removeAttribute('data-resting');
		window.clearTimeout(this.timer);

		this.timer = window.setTimeout(() => {
			if (this.video !== null && !this.video.paused && this.querySelector(':focus-visible') === null) {
				this.setAttribute('data-resting', '');
			}
		}, REST);
	}
}

/**
 * Defines the element, once.
 */
export function defineVideoPlayer(): void {
	if (customElements.get('blush-video-player') === undefined) {
		customElements.define('blush-video-player', VideoPlayer);
	}
}
