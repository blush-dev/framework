/**
 * What the audio and video players share (D-553, D-554): their glyphs,
 * the time as a player shows it, and the transport: a play button, a
 * track to seek along, and the time, "0:02 / 0:12", kept in step with
 * the media. Playing one player pauses any other.
 *
 * Glyphs are Lucide's (ISC license, as in `resources/icons/blush/LICENSE`),
 * play and pause filled, the rest stroked.
 */

import './player.css';

export const GLYPHS = {
	play: '<path d="M7 4.5 19.5 12 7 19.5Z"/>',
	pause: '<rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/>',
	volume: '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/>',
	muted: '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="m22 9-6 6"/><path d="m16 9 6 6"/>',
	captions: '<rect width="18" height="14" x="3" y="5" rx="2" ry="2"/><path d="M7 15h4M15 15h2M7 11h2M13 11h4"/>',
	maximize: '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
	minimize: '<path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/>'
} as const;

export type Glyph = keyof typeof GLYPHS;

const players = new Set<HTMLMediaElement>();

/**
 * A length of time as a player shows it: `3:25`, or `1:02:03`.
 */
export function clock(seconds: number): string {
	const whole = Number.isFinite(seconds) ? Math.max(0, Math.floor(seconds)) : 0;
	const hours = Math.floor(whole / 3600);
	const parts = [Math.floor(whole / 60) % 60, whole % 60].map((part, index) => index === 0 && hours === 0 ? String(part) : String(part).padStart(2, '0'));

	return hours > 0 ? `${hours}:${parts.join(':')}` : parts.join(':');
}

/**
 * A button with a glyph, its label for screen readers.
 */
export function button(className: string): { button: HTMLButtonElement; show: (glyph: Glyph, label: string) => void } {
	const element = document.createElement('button');
	const icon    = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

	element.type      = 'button';
	element.className = `player__button ${className}`;
	icon.setAttribute('viewBox', '0 0 24 24');
	icon.setAttribute('aria-hidden', 'true');
	element.append(icon);

	return {
		button: element,
		show: (glyph, label) => {
			if (element.dataset.glyph !== glyph) {
				icon.innerHTML        = GLYPHS[glyph];
				element.dataset.glyph = glyph;
			}

			element.setAttribute('aria-label', label);
		}
	};
}

/**
 * A label, from the host's `label-{name}` attribute or the English.
 */
export function label(host: HTMLElement, name: string, english: string): string {
	return host.getAttribute(`label-${name}`) ?? english;
}

/**
 * Plays the media, or pauses it.
 */
export function toggle(media: HTMLMediaElement): void {
	if (media.paused) {
		void media.play().catch(() => undefined);
	} else {
		media.pause();
	}
}

/**
 * The transport for a media element: its play button, seek track, and
 * time, and `draw()`, which the media's events call (then `onDraw`, for
 * what else the player draws).
 */
export function transport(host: HTMLElement, media: HTMLMediaElement, onDraw: () => void = () => undefined): { play: HTMLButtonElement; seek: HTMLInputElement; time: HTMLSpanElement; draw: () => void } {
	const play = button('player__button--play');
	const seek = document.createElement('input');
	const time = document.createElement('span');

	seek.type      = 'range';
	seek.className = 'player__seek';
	seek.min       = '0';
	seek.step      = 'any';
	seek.value     = '0';
	seek.setAttribute('aria-label', label(host, 'seek', 'Seek'));
	time.className = 'player__time';

	const draw = (): void => {
		const length = Number.isFinite(media.duration) ? media.duration : 0;
		const now    = media.currentTime;

		play.show(media.paused ? 'play' : 'pause', media.paused ? label(host, 'play', 'Play') : label(host, 'pause', 'Pause'));
		seek.max      = String(length);
		seek.value    = String(now);
		seek.disabled = length === 0;
		seek.setAttribute('aria-valuetext', `${clock(now)} / ${clock(length)}`);
		seek.style.setProperty('--player-progress', `${length === 0 ? 0 : now / length * 100}%`);
		time.textContent = length === 0 ? clock(now) : `${clock(now)} / ${clock(length)}`;
		onDraw();
	};

	play.button.addEventListener('click', () => toggle(media));

	seek.addEventListener('input', () => {
		media.currentTime = Number(seek.value);
		draw();
	});

	media.addEventListener('play', () => {
		for (const other of players) {
			if (other !== media && !other.paused) {
				other.pause();
			}
		}
	});

	for (const event of ['loadedmetadata', 'durationchange', 'timeupdate', 'play', 'pause', 'ended', 'emptied', 'volumechange']) {
		media.addEventListener(event, draw);
	}

	media.controls = false;
	draw();

	return { play: play.button, seek, time, draw };
}

/**
 * Counts a player's media among those that pause when another plays,
 * or stops counting it (when it leaves the page).
 */
export function listen(media: HTMLMediaElement, on: boolean): void {
	if (on) {
		players.add(media);
	} else {
		players.delete(media);
	}
}
