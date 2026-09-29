/**
 * Toasts (admin.md §7): what just happened, in the past tense ("Inserted a
 * callout"), one at a time at the bottom right, gone after about 2.6
 * seconds. A new one replaces the one showing. They confirm actions; a
 * problem gets a notice where it happened instead.
 */

import { ref } from 'vue';

export const currentToast = ref<{ id: number; message: string } | null>(null);

let next  = 0;
let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * Shows a toast.
 */
export function toast(message: string): void {
	clearTimeout(timer);
	currentToast.value = { id: ++next, message };
	timer = setTimeout(() => {
		currentToast.value = null;
	}, 2600);
}
