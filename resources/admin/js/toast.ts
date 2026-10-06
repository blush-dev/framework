/**
 * Toasts (the toast sketch, `toast-sketch.html`): what just happened where
 * you weren't looking, in the past tense ("Moved 3 posts to the trash"),
 * at the bottom right. A rule along the bottom edge counts each one down;
 * hovering it or focusing inside it holds the count, and Escape inside
 * one dismisses it. A plain toast replaces the plain one standing, so
 * routine reports don't pile up, but never one carrying an Undo; at three
 * the oldest goes. A problem gets a notice where it happened; a toast
 * reports one only where there's no such place (`warn`).
 */

import { shallowReactive } from 'vue';

/**
 * A toast's kind: `good` confirms (the default), `warn` is a refusal or a
 * state worth noticing, `danger` reports something destroyed or hidden,
 * and `info` says something that isn't a thing that happened.
 */
export type ToastKind = 'good' | 'warn' | 'danger' | 'info';

export interface ToastOptions {
	kind?: ToastKind;

	/**
	 * Puts back exactly what the action changed. Offered only where the
	 * reverse is exact, and run at most once: the offer goes with the toast.
	 */
	undo?: () => void;

	/**
	 * How long it stands, in milliseconds; 2600, or 7000 with an Undo.
	 */
	life?: number;
}

export interface Toast {
	id: number;
	message: string;
	kind: ToastKind;
	undo: (() => void) | null;
	life: number;
	held: boolean;
}

interface Clock {
	left: number;
	ran: number;
	timer: ReturnType<typeof setTimeout> | undefined;
	holds: number;
}

const LIFE      = 2600;
const LIFE_UNDO = 7000;
const MAX       = 3;

/**
 * The toasts standing, oldest first.
 */
export const toasts = shallowReactive<Toast[]>([]);

/**
 * The message the live region reads out: the latest toast's, never the
 * chips themselves, since a live region that gains a button is announced
 * as a button arriving.
 */
export const announcement = shallowReactive({ text: '' });

const clocks = new Map<number, Clock>();
let next     = 0;

/**
 * Shows a toast.
 */
export function toast(message: string, options: ToastOptions = {}): void {
	const undo = options.undo ?? null;
	const life = options.life ?? (undo === null ? LIFE : LIFE_UNDO);

	if (undo === null) {
		toasts.filter((standing) => standing.undo === null).forEach((standing) => dismissToast(standing.id));
	}

	while (toasts.length >= MAX) {
		dismissToast(toasts[0]!.id);
	}

	const id = ++next;

	toasts.push({ id, message, kind: options.kind ?? 'good', undo, life, held: false });
	clocks.set(id, { left: life, ran: Date.now(), timer: setTimeout(() => dismissToast(id), life), holds: 0 });

	announcement.text = undo === null ? message : `${message}. Undo is available`;
}

/**
 * Takes a toast down.
 */
export function dismissToast(id: number): void {
	const index = toasts.findIndex((standing) => standing.id === id);

	if (index !== -1) {
		toasts.splice(index, 1);
	}

	clearTimeout(clocks.get(id)?.timer);
	clocks.delete(id);
}

/**
 * Holds a toast's count or lets it go. The pointer and the keyboard hold
 * independently, so holds are counted: a flag would take the second
 * release as a release of both.
 */
export function holdToast(id: number, on: boolean): void {
	const clock = clocks.get(id);
	const index = toasts.findIndex((standing) => standing.id === id);

	if (clock === undefined || index === -1) {
		return;
	}

	clock.holds = Math.max(0, clock.holds + (on ? 1 : -1));

	const held = clock.holds > 0;

	if (held === toasts[index]!.held) {
		return;
	}

	toasts[index] = { ...toasts[index]!, held };

	if (held) {
		clearTimeout(clock.timer);
		clock.left = Math.max(0, clock.left - (Date.now() - clock.ran));
	} else {
		clock.ran   = Date.now();
		clock.timer = setTimeout(() => dismissToast(id), clock.left);
	}
}

/**
 * Takes the offer: dismisses the toast first, so it isn't left standing
 * over the screen the reverse brings back, then puts the action back.
 */
export function undoToast(id: number): void {
	const undo = toasts.find((standing) => standing.id === id)?.undo ?? null;

	dismissToast(id);
	undo?.();
}

/**
 * Copies text to the clipboard, saying so in a toast (D-505): `what` is
 * what it is, with its article ("the link"), and `shown` what the toast
 * names on success, when that's the text itself ("Copied jane@…").
 * Resolves whether it was copied.
 */
export async function copyText(text: string, what: string, shown = what): Promise<boolean> {
	try {
		await navigator.clipboard.writeText(text);
		toast(`Copied ${shown}`);

		return true;
	} catch {
		toast(`${what.charAt(0).toUpperCase()}${what.slice(1)} couldn't be copied`, { kind: 'warn' });

		return false;
	}
}
