/**
 * Files dragged in from outside the browser (D-505): `dragging` while
 * they're over the element the handlers are on, and `files` with what's
 * dropped. Anything else dragged (text, an element) is left alone, and
 * nothing's taken while `enabled` says no.
 */

import { ref } from 'vue';

export function hasFiles(event: DragEvent): boolean {
	return event.dataTransfer?.types.includes('Files') ?? false;
}

export function useFileDrop(files: (dropped: File[]) => void, enabled: () => boolean = () => true) {
	const dragging = ref(false);

	function over(event: DragEvent): void {
		if (enabled() && hasFiles(event)) {
			event.preventDefault();
			dragging.value = true;
		}
	}

	// Leaving for one of its own children isn't leaving.
	function leave(event: DragEvent): void {
		if (!(event.currentTarget instanceof Node) || !(event.relatedTarget instanceof Node) || !event.currentTarget.contains(event.relatedTarget)) {
			dragging.value = false;
		}
	}

	function drop(event: DragEvent): void {
		dragging.value = false;

		const dropped = [...(event.dataTransfer?.files ?? [])];

		if (enabled() && dropped.length > 0) {
			event.preventDefault();
			files(dropped);
		}
	}

	return { dragging, over, leave, drop };
}
