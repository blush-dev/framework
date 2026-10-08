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
 * Returns text with its first letter a capital, to start a sentence.
 */
export function capitalized(text: string): string {
	return text.replace(/^./u, (letter) => letter.toUpperCase());
}

/**
 * Returns a noun with its indefinite article: "an ingredient", "a cook".
 */
export function withArticle(noun: string): string {
	return `${/^[aeiou]/i.test(noun) ? 'an' : 'a'} ${noun}`;
}

/**
 * Returns a count with its noun, plural when it isn't one.
 */
export function plural(count: number, one: string, many = `${one}s`): string {
	return `${count} ${count === 1 ? one : many}`;
}

/**
 * Names in a sentence: "A", "A and B", "A, B, and C".
 */
export function series(names: string[]): string {
	return names.length <= 2 ? names.join(' and ') : `${names.slice(0, -1).join(', ')}, and ${names.at(-1)}`;
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

const times    = new Intl.DateTimeFormat(undefined, { timeStyle: 'short' });
const weekdays = new Intl.DateTimeFormat(undefined, { weekday: 'long' });
const days     = new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric' });
const shortDay = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });
const fullDays = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

/**
 * When something happened or will, as a list says it (D-538): "3 hours
 * ago" today, a weekday within the week, "October 1" this year, and the
 * full date before. A time to come names its day and time: "Today, 4:00
 * PM", "Oct 7, 9:00 AM".
 */
export function formatWhen(iso: string, now = new Date()): string {
	const date  = new Date(iso);
	const start = (day: Date): number => new Date(day.getFullYear(), day.getMonth(), day.getDate()).getTime();
	const apart = Math.round((start(date) - start(now)) / 86_400_000);

	if (date.getTime() > now.getTime()) {
		return `${apart === 0 ? 'Today' : (apart === 1 ? 'Tomorrow' : shortDay.format(date))}, ${times.format(date)}`;
	}

	const minutes = Math.round((now.getTime() - date.getTime()) / 60_000);

	if (minutes < 1) {
		return 'just now';
	}

	if (apart === 0) {
		return minutes < 60 ? `${plural(minutes, 'minute')} ago` : `${plural(Math.round(minutes / 60), 'hour')} ago`;
	}

	if (apart > -7) {
		return apart === -1 ? 'Yesterday' : weekdays.format(date);
	}

	return date.getFullYear() === now.getFullYear() ? days.format(date) : fullDays.format(date);
}
