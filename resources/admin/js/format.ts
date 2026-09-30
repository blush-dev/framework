/**
 * Formatting shared by screens, in the browser's language.
 */

const dates = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Formats an ISO 8601 date and time for reading.
 */
export function formatDate(iso: string): string {
	return dates.format(new Date(iso));
}

/**
 * Returns a count with its noun, plural when it isn't one.
 */
export function plural(count: number, one: string, many = `${one}s`): string {
	return `${count} ${count === 1 ? one : many}`;
}

const sizes = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });

/**
 * A file size for reading: "840 bytes", "12.4 KB", "3.1 MB".
 */
export function formatSize(bytes: number): string {
	if (bytes < 1024) {
		return `${bytes} ${bytes === 1 ? 'byte' : 'bytes'}`;
	}

	const units = ['KB', 'MB', 'GB'];
	let value   = bytes / 1024;
	let unit    = 0;

	while (value >= 1024 && unit < units.length - 1) {
		value /= 1024;
		unit++;
	}

	return `${sizes.format(value)} ${units[unit] ?? 'GB'}`;
}

// Words Title Case leaves lowercase unless they lead.
const SMALL = new Set(['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into', 'nor', 'of', 'on', 'or', 'the', 'to', 'with']);

/**
 * A name in Title Case (admin.md §10, Copy): "Choose an Image", "Nothing
 * Links to This File". Only first letters change, so a name already
 * capitalized, or with capitals inside it, keeps them.
 */
export function titleCase(text: string): string {
	return text.split(' ').map((word, index) => {
		const lower = word.toLowerCase();

		return index > 0 && SMALL.has(lower) ? lower : word.charAt(0).toUpperCase() + word.slice(1);
	}).join(' ');
}
