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
