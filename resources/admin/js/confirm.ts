/**
 * Confirmations (D-373): the admin asks before something it can't take
 * back, or that does a lot, in a modal drawn as the profiles sketch's
 * (`ConfirmHost`), never the browser's `confirm()`. `confirmAction()`
 * resolves `true` for the confirming button, `false` for Cancel, Escape,
 * or the backdrop. One shows at a time; a second waits its turn.
 *
 * A paragraph may mark words to stand out with `**`, as `**12 bylines**`;
 * nothing else is markup, so names typed by people are always text.
 *
 * `confirmChecked()` adds a checkbox, checked at first unless `checked`
 * says otherwise, for a choice that goes with confirming ("Remove it
 * from 12 entries", D-598).
 */

import { onBeforeUnmount, onMounted, ref } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';

export interface ConfirmOptions {
	// A question, in title case: "Delete Jane Doe?"
	title: string;
	// What happens, a paragraph each.
	body?: string | string[];
	// The confirming button's words: "Delete the account".
	confirm: string;
	cancel?: string;
	// Whether it destroys something: the button is red, and Cancel has
	// the focus.
	danger?: boolean;
	// A checkbox's words, for `confirmChecked()`, and whether it starts
	// checked (it does unless this says otherwise).
	check?: string;
	checked?: boolean;
}

export interface PendingConfirm extends ConfirmOptions {
	id: number;
	checked: boolean;
	resolve: (answer: boolean, checked: boolean) => void;
}

export const pendingConfirms = ref<PendingConfirm[]>([]);

let next = 0;

export function confirmAction(options: ConfirmOptions): Promise<boolean> {
	return new Promise((resolve) => {
		pendingConfirms.value = [...pendingConfirms.value, { ...options, check: undefined, id: ++next, checked: false, resolve }];
	});
}

/**
 * Asks with a checkbox (`check`, checked at first): resolves whether it
 * was checked when confirmed, or `null` when it wasn't confirmed.
 */
export function confirmChecked(options: ConfirmOptions & { check: string }): Promise<boolean | null> {
	return new Promise((resolve) => {
		pendingConfirms.value = [...pendingConfirms.value, { ...options, id: ++next, checked: options.checked ?? true, resolve: (answer, checked) => resolve(answer ? checked : null) }];
	});
}

/**
 * Answers the confirmation showing, and shows the next.
 */
export function answerConfirm(id: number, answer: boolean): void {
	const found = pendingConfirms.value.find((item) => item.id === id);

	pendingConfirms.value = pendingConfirms.value.filter((item) => item.id !== id);
	found?.resolve(answer, found.checked);
}

/**
 * A paragraph split into plain and `**strong**` parts.
 */
export function emphasis(text: string): { text: string; strong: boolean }[] {
	return text.split(/(\*\*[^*]+\*\*)/).filter((part) => part !== '').map((part) => part.startsWith('**') && part.endsWith('**') && part.length > 4
		? { text: part.slice(2, -2), strong: true }
		: { text: part, strong: false });
}

/**
 * The usual question before leaving unsaved changes.
 */
export function confirmLeave(what = 'Your changes will be lost.'): Promise<boolean> {
	return confirmAction({ title: 'Leave Without Saving?', body: what, confirm: 'Leave', cancel: 'Stay', danger: true });
}

/**
 * Asks before leaving a screen with unsaved changes (D-505): another
 * screen asks `ask` (the usual question, else the screen's own), and
 * closing or reloading the tab gets the browser's own warning.
 */
export function guardLeave(unsaved: () => boolean, ask: () => Promise<boolean> = () => confirmLeave()): void {
	onBeforeRouteLeave(() => !unsaved() || ask());

	const unload = (event: BeforeUnloadEvent): void => {
		if (unsaved()) {
			event.preventDefault();
		}
	};

	onMounted(() => window.addEventListener('beforeunload', unload));
	onBeforeUnmount(() => window.removeEventListener('beforeunload', unload));
}
