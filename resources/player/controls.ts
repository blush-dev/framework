/**
 * What the audio and video players share (D-553, D-554): their glyphs,
 * the time as a player shows it, the transport (a play button, a track
 * to seek along, and the time, "0:02 / 0:12", kept in step with the
 * media), and the volume, shared by every player on the page and
 * remembered (D-576). Playing one player pauses any other.
 *
 * Glyphs are Lucide's (ISC license, as in `resources/icons/blush/LICENSE`),
 * play and pause filled, the rest stroked.
 */

// @ts-ignore
import './player.css';

export const GLYPHS = {
	play: '<path d="M7 4.5 19.5 12 7 19.5Z"/>',
	pause: '<rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/>',
	volume: '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/><path d="M19.364 18.364a9 9 0 0 0 0-12.728"/>',
	quiet: '<path d="M11 4.702a.705.705 0 0 0-1.203-.498L6.413 7.587A1.4 1.4 0 0 1 5.416 8H3a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h2.416a1.4 1.4 0 0 1 .997.413l3.383 3.384A.705.705 0 0 0 11 19.298z"/><path d="M16 9a5 5 0 0 1 0 6"/>',
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
	seek.className = 'player__range player__seek';
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
 * The volume every player on the page shares, and remembers across
 * visits (one `localStorage` key): nobody means "this clip is loud",
 * they mean "this site is loud". `last` is the level to unmute to.
 */
const STORE = 'blush-player-volume';

const shared = { volume: 1, muted: false, last: 1 };

try {
	const saved: unknown = JSON.parse(window.localStorage.getItem(STORE) ?? 'null');

	if (typeof saved === 'object' && saved !== null) {
		const { volume, muted, last } = saved as Record<string, unknown>;

		shared.volume = typeof volume === 'number' ? Math.min(1, Math.max(0, volume)) : 1;
		shared.muted  = muted === true;
		shared.last   = typeof last === 'number' && last > 0 ? Math.min(1, last) : 1;
	}
} catch {
	// Storage is off, or what's there isn't ours: the defaults stand.
}

/**
 * Gives a media element the shared volume. One that starts silent
 * (`muted` in its markup) keeps that until it's unmuted.
 */
function adopt(media: HTMLMediaElement): void {
	media.volume = shared.volume;

	if (!media.defaultMuted) {
		media.muted = shared.muted;
	}
}

/**
 * Sets the shared volume on every player, and remembers it. Nothing
 * is muted; a level above nothing is the one to unmute to.
 */
function setVolume(volume: number, muted: boolean): void {
	shared.volume = Math.min(1, Math.max(0, volume));
	shared.muted  = muted || shared.volume === 0;

	if (shared.volume > 0) {
		shared.last = shared.volume;
	}

	for (const media of players) {
		media.volume = shared.volume;
		media.muted  = shared.muted;
	}

	try {
		window.localStorage.setItem(STORE, JSON.stringify(shared));
	} catch {
		// Not remembered, but still shared on this page.
	}
}

/**
 * The volume control for a media element, as the audio player sketch
 * draws it: a mute button whose glyph follows the level (loud, quiet,
 * muted), and a slider from 0 to 1 that opens above it on hover and
 * keyboard focus. Dragging to nothing mutes; unmuting goes back to the
 * last level. The level is shared by every player and remembered.
 * Where a page can't set the volume (iOS, where it's the device's),
 * only the mute button shows.
 */
export function volume(host: HTMLElement, media: HTMLMediaElement): { control: HTMLSpanElement; draw: () => void } {
	const control = document.createElement('span');
	const mute    = button('player__button--mute');
	const pop     = document.createElement('span');
	const card    = document.createElement('span');
	const slider  = document.createElement('input');
	const level   = (): number => media.muted ? 0 : media.volume;

	control.className = 'player__volume';
	pop.className     = 'player__volume-pop';
	card.className    = 'player__volume-card';
	slider.type       = 'range';
	slider.className  = 'player__range player__volume-range';
	slider.min        = '0';
	slider.max        = '1';
	slider.step       = '0.05';
	slider.setAttribute('aria-label', label(host, 'volume', 'Volume'));

	card.append(slider);
	pop.append(card);
	control.append(mute.button);

	if (settable(media)) {
		control.append(pop);
	}

	const draw = (): void => {
		const now = level();

		mute.show(now === 0 ? 'muted' : (now < 0.5 ? 'quiet' : 'volume'), now === 0 ? label(host, 'unmute', 'Unmute') : label(host, 'mute', 'Mute'));
		slider.value = String(now);
		slider.setAttribute('aria-valuetext', `${Math.round(now * 100)}%`);
		slider.style.setProperty('--player-progress', `${now * 100}%`);
	};

	mute.button.addEventListener('click', () => {
		if (level() === 0) {
			setVolume(shared.last, false);
		} else {
			setVolume(media.volume, true);
		}
	});

	slider.addEventListener('input', () => setVolume(Number(slider.value), false));
	media.addEventListener('volumechange', draw);
	adopt(media);
	draw();

	return { control, draw };
}

/**
 * Whether a page can set a media element's volume: iOS keeps it at 1.
 */
function settable(media: HTMLMediaElement): boolean {
	const was = media.volume;

	media.volume = was === 0.5 ? 0.4 : 0.5;

	const moved = media.volume !== was;

	media.volume = was;

	return moved;
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
