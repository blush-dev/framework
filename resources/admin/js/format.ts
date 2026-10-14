/**
 * Formatting shared by screens, in the admin's language (the account's,
 * D-833), as its messages are. A date read as a date is in the site's
 * formats instead (`dates.ts`, D-693).
 */

import { locale, t } from './messages';

const dates = new Intl.DateTimeFormat(locale(), { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Formats an ISO 8601 date and time compactly, for a list's column. A
 * date read as a date is in the site's formats (`dates.ts`, D-693).
 */
export function formatDate(iso: string): string {
	return dates.format(new Date(iso));
}

/**
 * Formats a calendar day (`2026-10-05`) compactly, for a row's details.
 */
export function formatDay(day: string): string {
	const [year, month, date] = day.split('-').map(Number);

	return fullDays.format(new Date(year ?? 0, (month ?? 1) - 1, date ?? 1));
}

/**
 * Returns text with its first letter a capital, to start a sentence.
 */
export function capitalized(text: string): string {
	return text.replace(/^./u, (letter) => letter.toUpperCase());
}

/**
 * Returns `formatWhen()`'s answer worded to sit mid-sentence: "ran today,
 * 9:00", "queued 5 minutes ago". Its own words are messages of their own
 * (a language may capitalize them anyway), and a month's or a weekday's
 * name is as the language writes it.
 */
export function formatWhenInline(iso: string, now = new Date()): string {
	return when(iso, now, true);
}

const numbers = new Intl.NumberFormat(locale());

/**
 * Formats a count for reading, as the admin's language groups digits:
 * "1,024". A number given to `t()` is formatted the same way.
 */
export function formatNumber(count: number): string {
	return numbers.format(count);
}

/**
 * Names in a sentence, joined as the admin's language joins a list: "A",
 * "A and B", "A, B, and C".
 */
export function series(names: string[]): string {
	return new Intl.ListFormat(locale(), { type: 'conjunction' }).format(names);
}

const sizes = new Intl.NumberFormat(locale(), { maximumFractionDigits: 1 });

/**
 * A file size for reading: "840 bytes", "12.4 KB", "3.1 MB".
 */
export function formatSize(bytes: number): string {
	if (bytes < 1024) {
		return t('format.bytes', '{count, plural, one {# byte} other {# bytes}}', { count: bytes });
	}

	const units = ['KB', 'MB', 'GB'];
	let value   = bytes / 1024;
	let unit    = 0;

	while (value >= 1024 && unit < units.length - 1) {
		value /= 1024;
		unit++;
	}

	const size = sizes.format(value);

	switch (units[unit]) {
		case 'KB':
			return t('format.size_kb', '{size} KB', { size });
		case 'MB':
			return t('format.size_mb', '{size} MB', { size });
		default:
			return t('format.size_gb', '{size} GB', { size });
	}
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

const times    = new Intl.DateTimeFormat(locale(), { timeStyle: 'short' });
const weekdays = new Intl.DateTimeFormat(locale(), { weekday: 'long' });
const days     = new Intl.DateTimeFormat(locale(), { month: 'long', day: 'numeric' });
const shortDay = new Intl.DateTimeFormat(locale(), { month: 'short', day: 'numeric' });
const fullDays = new Intl.DateTimeFormat(locale(), { dateStyle: 'medium' });

/**
 * When something happened or will, as a list says it (D-538): "3 hours
 * ago" today, a weekday within the week, "October 1" this year, and the
 * full date before. A time to come names its day and time: "Today, 4:00
 * PM", "Oct 7, 9:00 AM".
 */
export function formatWhen(iso: string, now = new Date()): string {
	return when(iso, now, false);
}

/**
 * Returns when something happened or will, to start a sentence or stand
 * alone, or to sit inside one (`inline`).
 */
function when(iso: string, now: Date, inline: boolean): string {
	const date  = new Date(iso);
	const start = (day: Date): number => new Date(day.getFullYear(), day.getMonth(), day.getDate()).getTime();
	const apart = Math.round((start(date) - start(now)) / 86_400_000);

	if (date.getTime() > now.getTime()) {
		const time = times.format(date);

		if (apart === 0) {
			return inline ? t('format.inline.today_at', 'today, {time}', { time }) : t('format.today_at', 'Today, {time}', { time });
		}

		if (apart === 1) {
			return inline ? t('format.inline.tomorrow_at', 'tomorrow, {time}', { time }) : t('format.tomorrow_at', 'Tomorrow, {time}', { time });
		}

		return t('format.day_at', '{day}, {time}', { day: shortDay.format(date), time });
	}

	const minutes = Math.round((now.getTime() - date.getTime()) / 60_000);

	if (minutes < 1) {
		return t('format.just_now', 'just now');
	}

	if (apart === 0) {
		return minutes < 60
			? t('format.minutes_ago', '{count, plural, one {# minute} other {# minutes}} ago', { count: minutes })
			: t('format.hours_ago', '{count, plural, one {# hour} other {# hours}} ago', { count: Math.round(minutes / 60) });
	}

	if (apart > -7) {
		if (apart === -1) {
			return inline ? t('format.inline.yesterday', 'yesterday') : t('format.yesterday', 'Yesterday');
		}

		return weekdays.format(date);
	}

	return date.getFullYear() === now.getFullYear() ? days.format(date) : fullDays.format(date);
}
