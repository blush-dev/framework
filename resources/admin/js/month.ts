/**
 * Months drawn as weeks (D-505), Monday first, as the date picker
 * draws them.
 */

import { locale } from './messages';

// The weekdays' short names, Monday first, in the admin's language
// (`Intl`; 1 January 2024 was a Monday).
export const WEEKDAYS = Array.from({ length: 7 }, (_, day) => new Intl.DateTimeFormat(locale(), { weekday: 'short' }).format(new Date(2024, 0, 1 + day)));

// The months' names, long and short, the same way.
export const MONTHS = Array.from({ length: 12 }, (_, month) => new Intl.DateTimeFormat(locale(), { month: 'long' }).format(new Date(2024, month, 1)));
export const SHORT_MONTHS = Array.from({ length: 12 }, (_, month) => new Intl.DateTimeFormat(locale(), { month: 'short' }).format(new Date(2024, month, 1)));

/**
 * A date as `YYYY-MM-DD`, in local time.
 */
export function isoDay(date: Date): string {
	return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/**
 * An hour of the day on a 12-hour clock (0 is 12).
 */
export function hour12(hours: number): number {
	return ((hours + 11) % 12) + 1;
}

/**
 * The days a month is drawn with: six weeks, from the Monday on or
 * before its first day. `out` marks a day of the month before or after.
 */
export function monthDays(year: number, month: number): { date: Date; iso: string; out: boolean }[] {
	const lead = (new Date(year, month, 1).getDay() + 6) % 7;

	return Array.from({ length: 6 * 7 }, (_, offset) => {
		const date = new Date(year, month, 1 - lead + offset);

		return { date, iso: isoDay(date), out: date.getMonth() !== month };
	});
}
