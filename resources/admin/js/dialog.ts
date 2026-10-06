/**
 * A modal that's on screen for as long as its component is (D-505), as
 * the inserters and the command palette are: shown when it mounts, and
 * closed by `close()`, Escape, or (where `backdrop` is wired) a click on
 * the dimmed page around it. Closing fires the dialog's `close` event,
 * which the component passes on.
 */

import { onMounted, ref } from 'vue';

export function useModalDialog(opened?: () => void) {
	const dialog = ref<HTMLDialogElement | null>(null);

	function close(): void {
		dialog.value?.close();
	}

	// A click on the dialog itself, not its box, is on the backdrop.
	function backdrop(event: MouseEvent): void {
		if (event.target === dialog.value) {
			close();
		}
	}

	onMounted(() => {
		dialog.value?.showModal();
		opened?.();
	});

	return { dialog, close, backdrop };
}
