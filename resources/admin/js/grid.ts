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

/**
 * The index an arrow moves to in a list: down and up a row, wrapping
 * past either end when `wrap` says so; `null` when the key isn't the
 * list's or it's empty.
 */
export function listMove(key: string, index: number, count: number, wrap = true): number | null {
	if ((key !== 'ArrowDown' && key !== 'ArrowUp') || count === 0) {
		return null;
	}

	const step = key === 'ArrowDown' ? 1 : -1;

	return wrap ? (index + step + count) % count : Math.max(0, Math.min(count - 1, index + step));
}

/**
 * How many columns a grid is drawn with, read off it, since it reflows
 * with its width.
 */
export function gridColumns(grid: Element | null | undefined): number {
	return grid === null || grid === undefined ? 1 : Math.max(1, getComputedStyle(grid).gridTemplateColumns.split(' ').length);
}

export interface Section<T> {
	heading: string;
	cells: { index: number; item: T }[];
}

/**
 * Choices under headings, each numbered in keyboard order across them
 * all; a heading with nothing under it is left out.
 */
export function numbered<T>(groups: { heading: string; items: T[] }[]): Section<T>[] {
	let index = 0;

	return groups.filter((group) => group.items.length > 0).map((group) => ({ heading: group.heading, cells: group.items.map((item) => ({ index: index++, item })) }));
}
