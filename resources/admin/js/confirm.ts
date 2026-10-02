/**
 * Confirmations (D-373): the admin asks before something it can't take
 * back, or that does a lot, in a modal drawn as the profiles sketch's
 * (`ConfirmHost`), never the browser's `confirm()`. `confirmAction()`
 * resolves `true` for the confirming button, `false` for Cancel, Escape,
 * or the backdrop. One shows at a time; a second waits its turn.
 *
 * A paragraph may mark words to stand out with `**`, as `**12 bylines**`;
 * nothing else is markup, so names typed by people are always text.
 */

import { ref } from 'vue';

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
}

export interface PendingConfirm extends ConfirmOptions {
	id: number;
	resolve: (answer: boolean) => void;
}

export const pendingConfirms = ref<PendingConfirm[]>([]);

let next = 0;

export function confirmAction(options: ConfirmOptions): Promise<boolean> {
	return new Promise((resolve) => {
		pendingConfirms.value = [...pendingConfirms.value, { ...options, id: ++next, resolve }];
	});
}

/**
 * Answers the confirmation showing, and shows the next.
 */
export function answerConfirm(id: number, answer: boolean): void {
	const found = pendingConfirms.value.find((item) => item.id === id);

	pendingConfirms.value = pendingConfirms.value.filter((item) => item.id !== id);
	found?.resolve(answer);
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
