/**
 * Putting a short list in order by hand (D-599): an item is dragged onto
 * another's place, or moved one step with ⌥ and an arrow key while it
 * has the focus (↑ and ↓ in a list, ← and → too in a row of chips), as
 * the editor moves elements (D-314). Moving it keeps the focus on it,
 * since the item keeps its key.
 */

import { ref } from 'vue';

/**
 * Returns a list with an item moved from one place to another.
 */
export function moved<T>(list: readonly T[], from: number, to: number): T[] {
	const next = [...list];
	const [item] = next.splice(from, 1);

	if (item !== undefined) {
		next.splice(Math.max(0, Math.min(to, next.length)), 0, item);
	}

	return next;
}

/**
 * Handlers for a list's items: `item(index)` is bound to each item
 * (`v-bind`), which needs to be focusable for the keys. `move` writes the
 * list with an item moved; `count` is how many items there are.
 */
export function useReorder(count: () => number, move: (from: number, to: number) => void) {
	const dragging = ref<number | null>(null);
	const over     = ref<number | null>(null);

	function item(index: number): Record<string, unknown> {
		return {
			draggable: 'true',
			'aria-roledescription': 'movable item',
			'aria-keyshortcuts': 'Alt+ArrowUp Alt+ArrowDown',
			class: { 'is-dragging': dragging.value === index, 'is-drop': over.value === index && dragging.value !== index },
			onDragstart: (event: DragEvent) => {
				dragging.value = index;
				event.dataTransfer?.setData('text/plain', String(index));

				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
				}
			},
			onDragover: (event: DragEvent) => {
				if (dragging.value !== null) {
					event.preventDefault();
					over.value = index;
				}
			},
			onDragleave: () => {
				if (over.value === index) {
					over.value = null;
				}
			},
			onDrop: (event: DragEvent) => {
				event.preventDefault();

				if (dragging.value !== null && dragging.value !== index) {
					move(dragging.value, index);
				}

				dragging.value = null;
				over.value     = null;
			},
			onDragend: () => {
				dragging.value = null;
				over.value     = null;
			},
			onKeydown: (event: KeyboardEvent) => {
				if (!event.altKey || event.target !== event.currentTarget) {
					return;
				}

				const step = event.key === 'ArrowUp' || event.key === 'ArrowLeft' ? -1 : (event.key === 'ArrowDown' || event.key === 'ArrowRight' ? 1 : 0);
				const to   = index + step;

				if (step !== 0) {
					event.preventDefault();

					if (to >= 0 && to < count()) {
						move(index, to);
					}
				}
			}
		};
	}

	return { item };
}
