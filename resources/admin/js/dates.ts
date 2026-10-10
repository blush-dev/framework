/**
 * Dates read as dates, in the site's formats (D-446, D-693): an entry's
 * dates in a sentence, a file's details, an account's last sign-in.
 * Compact and structural dates (list columns, pickers, "3 hours ago")
 * stay `format.ts`'s. Each format is a style the language defines
 * (`full`, `long`, `medium`, `short`) or an ICU pattern (`MMMM d, y`),
 * as `DateFormat` has them, read here in the site's language and the
 * reader's time zone, the one every other time in the admin is in.
 */

import { config } from './config';

type Style = 'full' | 'long' | 'medium' | 'short';

const STYLES: readonly string[] = ['full', 'long', 'medium', 'short'];

const isStyle = (format: string): format is Style => STYLES.includes(format);

// The site's language as the browser knows it, or the browser's own
// when it doesn't.
const locale = ((): string | undefined => {
	try {
		return Intl.DateTimeFormat.supportedLocalesOf(config.dates.locale)[0];
	} catch {
		return undefined;
	}
})();

const pad = (value: number, length: number): string => String(value).padStart(length, '0');

/**
 * One part of a date by a single `Intl` option: a month's or weekday's
 * name, an era, a time zone, or a day period.
 */
function part(date: Date, type: Intl.DateTimeFormatPartTypes, options: Intl.DateTimeFormatOptions): string {
	return new Intl.DateTimeFormat(locale, options).formatToParts(date).find((item) => item.type === type)?.value ?? '';
}

/**
 * A time zone's offset: `-0800`, `-08:00`, or `-08`, with `Z` for none
 * when `zulu`.
 */
function offset(date: Date, style: 'basic' | 'extended' | 'hours', zulu: boolean): string {
	const minutes = -date.getTimezoneOffset();

	if (minutes === 0 && zulu) {
		return 'Z';
	}

	const sign  = minutes < 0 ? '-' : '+';
	const hours = pad(Math.floor(Math.abs(minutes) / 60), 2);
	const rest  = Math.abs(minutes) % 60;

	if (style === 'hours') {
		return `${sign}${hours}${rest === 0 ? '' : pad(rest, 2)}`;
	}

	return `${sign}${hours}${style === 'extended' ? ':' : ''}${pad(rest, 2)}`;
}

/**
 * A text field's width by how many letters write it: short, long, or
 * narrow.
 */
const width = (count: number): 'short' | 'long' | 'narrow' => (count <= 3 ? 'short' : (count === 4 ? 'long' : 'narrow'));

/**
 * One field of a pattern, `count` letters long.
 */
function field(date: Date, letter: string, count: number): string {
	const hours = date.getHours();

	switch (letter) {
		case 'G':
			return part(date, 'era', { era: width(count), year: 'numeric' });
		case 'y':
		case 'Y':
		case 'u':
		case 'U':
		case 'r':
			return count === 2 ? pad(date.getFullYear() % 100, 2) : pad(date.getFullYear(), count);
		case 'Q':
		case 'q': {
			const quarter = Math.floor(date.getMonth() / 3) + 1;

			return count <= 2 ? pad(quarter, count) : `Q${quarter}`;
		}
		case 'M':
		case 'L':
			return count <= 2 ? pad(date.getMonth() + 1, count) : part(date, 'month', { month: width(count) });
		case 'd':
			return pad(date.getDate(), count);
		case 'D': {
			const day = Math.round((new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime() - new Date(date.getFullYear(), 0, 1).getTime()) / 86_400_000) + 1;

			return pad(day, count);
		}
		case 'F':
			return pad(Math.floor((date.getDate() - 1) / 7) + 1, count);
		case 'E':
			return part(date, 'weekday', { weekday: count === 6 ? 'short' : width(count) });
		case 'e':
		case 'c':
			return count <= 2 ? pad(date.getDay() === 0 ? 7 : date.getDay(), count) : part(date, 'weekday', { weekday: width(count) });
		case 'a':
		case 'b':
			return part(date, 'dayPeriod', { hour: 'numeric', hourCycle: 'h12' });
		case 'B':
			return part(date, 'dayPeriod', { dayPeriod: width(count), hour: 'numeric' });
		case 'h':
			return pad(hours % 12 === 0 ? 12 : hours % 12, count);
		case 'H':
			return pad(hours, count);
		case 'k':
			return pad(hours === 0 ? 24 : hours, count);
		case 'K':
			return pad(hours % 12, count);
		case 'm':
			return pad(date.getMinutes(), count);
		case 's':
			return pad(date.getSeconds(), count);
		case 'S':
			return pad(date.getMilliseconds(), 3).padEnd(count, '0').slice(0, count);
		case 'A':
			return pad(((hours * 60 + date.getMinutes()) * 60 + date.getSeconds()) * 1000 + date.getMilliseconds(), count);
		case 'z':
			return part(date, 'timeZoneName', { timeZoneName: count === 4 ? 'long' : 'short' });
		case 'O':
			return part(date, 'timeZoneName', { timeZoneName: count === 4 ? 'longOffset' : 'shortOffset' });
		case 'v':
			return part(date, 'timeZoneName', { timeZoneName: count === 4 ? 'longGeneric' : 'shortGeneric' });
		case 'V':
			return count === 2 ? Intl.DateTimeFormat().resolvedOptions().timeZone : part(date, 'timeZoneName', { timeZoneName: 'shortGeneric' });
		case 'Z':
			return count === 4 ? part(date, 'timeZoneName', { timeZoneName: 'longOffset' }) : offset(date, count === 5 ? 'extended' : 'basic', count === 5);
		case 'X':
		case 'x':
			return offset(date, count === 1 ? 'hours' : (count % 2 === 0 ? 'basic' : 'extended'), letter === 'X');
		default:
			// Weeks of the year and month (`w`, `W`) and the rest ICU
			// knows: the number the browser can't give is left out.
			return '';
	}
}

/**
 * A date written with an ICU pattern: runs of one letter are fields,
 * text in single quotes is as written (`''` is a quote), and anything
 * else is as it is.
 */
function pattern(date: Date, format: string): string {
	let text   = '';
	let quoted = false;
	let index  = 0;

	while (index < format.length) {
		const character = format.charAt(index);

		if (character === '\'') {
			if (format.charAt(index + 1) === '\'') {
				text += '\'';
				index += 2;
			} else {
				quoted = !quoted;
				index++;
			}

			continue;
		}

		if (!quoted && /[A-Za-z]/.test(character)) {
			let count = 1;

			while (format.charAt(index + count) === character) {
				count++;
			}

			text += field(date, character, count);
			index += count;
			continue;
		}

		text += character;
		index++;
	}

	return text;
}

const DATE_PARTS = new Set(['era', 'year', 'relatedYear', 'month', 'day', 'weekday']);

/**
 * A date and time each in their format, joined as the language joins
 * them: a style's join, with a pattern joining as `medium` does, as
 * `DateFormat` joins them on the server.
 */
function joined(date: Date, dateFormat: string, timeFormat: string): string {
	if (isStyle(dateFormat) && isStyle(timeFormat)) {
		return new Intl.DateTimeFormat(locale, { dateStyle: dateFormat, timeStyle: timeFormat }).format(date);
	}

	const parts = new Intl.DateTimeFormat(locale, {
		dateStyle: isStyle(dateFormat) ? dateFormat : 'medium',
		timeStyle: isStyle(timeFormat) ? timeFormat : 'medium'
	}).formatToParts(date);

	const kind = (type: string): 'date' | 'time' | null => (type === 'literal' ? null : (DATE_PARTS.has(type) ? 'date' : 'time'));
	const sides = parts.map((item) => kind(item.type)).filter((side) => side !== null);
	const first = sides[0];
	const flips = sides.filter((side, index) => index > 0 && side !== sides[index - 1]).length;

	if (first === undefined || flips !== 1) {
		return `${formatted(date, dateFormat, null)} ${formatted(date, null, timeFormat)}`;
	}

	// The literal between the last part of one side and the first of
	// the other is the language's join.
	const next = parts.findIndex((item) => kind(item.type) !== null && kind(item.type) !== first);
	let last   = next - 1;

	while (last > 0 && kind(parts[last]?.type ?? 'literal') === null) {
		last--;
	}

	const join  = parts.slice(last + 1, next).map((item) => item.value).join('');
	const day   = formatted(date, dateFormat, null);
	const clock = formatted(date, null, timeFormat);

	return first === 'date' ? `${day}${join}${clock}` : `${clock}${join}${day}`;
}

/**
 * A date in a date format, a time format, or both.
 */
function formatted(date: Date, dateFormat: string | null, timeFormat: string | null): string {
	if (dateFormat !== null && timeFormat !== null) {
		return joined(date, dateFormat, timeFormat);
	}

	const format = dateFormat ?? timeFormat ?? 'medium';

	if (!isStyle(format)) {
		return pattern(date, format);
	}

	return new Intl.DateTimeFormat(locale, dateFormat === null ? { timeStyle: format } : { dateStyle: format }).format(date);
}

/**
 * A date from an ISO 8601 string, a calendar day alone (`2026-10-05`,
 * read as that day where the reader is), a Unix time, or a `Date`.
 */
function toDate(value: string | number | Date): Date {
	if (value instanceof Date) {
		return value;
	}

	if (typeof value === 'number') {
		return new Date(value * 1000);
	}

	const day = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

	return day === null ? new Date(value) : new Date(Number(day[1]), Number(day[2]) - 1, Number(day[3]));
}

/**
 * A date and time in the site's formats: "October 9, 2026 at 3:00 PM".
 */
export function siteDateTime(value: string | number | Date): string {
	return formatted(toDate(value), config.dates.date, config.dates.time);
}

/**
 * A day in the site's date format: "October 9, 2026".
 */
export function siteDate(value: string | number | Date): string {
	return formatted(toDate(value), config.dates.date, null);
}
