/**
 * Moving through a grid of choices from the keyboard (admin.md §8, The
 * inserters): up and down move a row, left and right a cell. In a search
 * field with text in it, left and right edit the text instead.
 */

/**
 * The index a key moves to, or `null` when the key isn't the grid's.
 */
export function gridMove(key: string, index: number, count: number, columns: number, editingText: boolean): number | null {
	const last = Math.max(0, count - 1);

	switch (key) {
		case 'ArrowDown':
			return Math.min(last, index + columns);
		case 'ArrowUp':
			return Math.max(0, index - columns);
		case 'ArrowRight':
			return editingText ? null : Math.min(last, index + 1);
		case 'ArrowLeft':
			return editingText ? null : Math.max(0, index - 1);
		default:
			return null;
	}
}
