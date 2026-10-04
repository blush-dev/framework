<?php

/**
 * Date and time formats.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;

/**
 * The site's date and time formats (D-445): each is a style the language
 * defines (`full`, `long`, `medium`, or `short`), so the order, words,
 * and punctuation follow the site's language, or an ICU pattern
 * (`MMMM d, y`, `HH:mm`), fixed whatever the language, though month and
 * day names are still in it. A style may be given as a `DateStyle`
 * (D-447) wherever a format is taken. Themes show dates with them through the
 * template's `date()`, `time()`, and `datetime()`.
 *
 * A pattern is checked more closely than ICU checks it, since ICU
 * quietly drops letters it doesn't know: every letter outside quotes
 * must be one of its fields, quotes must close, and there must be one
 * field at least.
 */
final class DateFormat
{
	/**
	 * Where ICU's pattern letters are explained (D-446).
	 */
	public const string REFERENCE = 'https://unicode-org.github.io/icu/userguide/format_parse/datetime/#datetime-format-syntax';

	/**
	 * The letters an ICU pattern's fields are written with.
	 */
	private const string FIELDS = 'GyYuUrQqMLwWdDFgEecabBhHkKmsSAzZOvVXx';

	/**
	 * The longest a pattern may be.
	 */
	private const int MAX = 100;

	/**
	 * The patterns the admin offers beside the styles, by kind.
	 *
	 * @var array{date: list<string>, time: list<string>}
	 */
	private const array PRESETS = [
		'date' => ['MMMM d, y', 'd MMMM y', 'MMM d, y', 'EEEE, MMMM d, y', 'y-MM-dd', 'MM/dd/y', 'dd/MM/y', 'dd.MM.y'],
		'time' => ['h:mm a', 'HH:mm', 'h:mm:ss a', 'HH:mm:ss']
	];

	/**
	 * Whether a format is a style the language defines.
	 */
	public static function isStyle(string $format): bool
	{
		return DateStyle::tryFrom($format) !== null;
	}

	/**
	 * Returns what's wrong with a format, or `null` when it's fine.
	 */
	public static function problem(string $format): ?string
	{
		if (self::isStyle($format)) {
			return null;
		}

		if (trim($format) === '') {
			return 'A format can\'t be empty; use full, long, medium, or short, or a pattern such as MMMM d, y.';
		}

		if (mb_strlen($format) > self::MAX) {
			return sprintf('A format can be at most %d characters.', self::MAX);
		}

		if (preg_match('/[\p{Cc}\p{Zl}\p{Zp}]/u', $format) !== 0) {
			return 'A format is one line, without control characters.';
		}

		$quoted = false;
		$fields = 0;

		foreach (mb_str_split($format) as $character) {
			if ($character === "'") {
				$quoted = ! $quoted;
			} elseif (! $quoted && preg_match('/^[A-Za-z]$/', $character) === 1) {
				if (! str_contains(self::FIELDS, $character)) {
					return sprintf('"%s" isn\'t a date or time field; put words in single quotes, such as \'at\'.', $character);
				}

				$fields++;
			}
		}

		return match (true) {
			$quoted      => 'A quote isn\'t closed; write two (\'\') for a quote itself.',
			$fields === 0 => 'A format needs a field at least, such as d, MMMM, or y.',
			default      => null
		};
	}

	/**
	 * Formats a date with a date format, a time format, or both, in a
	 * language and time zone. Both styles are joined as the language
	 * joins them (`October 4, 2026 at 2:30 PM`); a pattern takes the
	 * place of the style it's beside in that join. A format ICU can't
	 * use falls back to the date as `Y-m-d` (and the time as `H:i`).
	 */
	public static function format(DateTimeInterface $date, string $locale, DateTimeZone|string $timezone, DateStyle|string|null $dateFormat, DateStyle|string|null $timeFormat = null): string
	{
		$dateFormat = $dateFormat instanceof DateStyle ? $dateFormat->value : $dateFormat;
		$timeFormat = $timeFormat instanceof DateStyle ? $timeFormat->value : $timeFormat;
		$pattern    = self::pattern($locale, $dateFormat, $timeFormat);
		$formatter  = new IntlDateFormatter(
			$locale,
			$pattern === null ? DateStyle::tryFrom($dateFormat ?? '')?->icu() ?? IntlDateFormatter::NONE : IntlDateFormatter::NONE,
			$pattern === null ? DateStyle::tryFrom($timeFormat ?? '')?->icu() ?? IntlDateFormatter::NONE : IntlDateFormatter::NONE,
			$timezone,
			null,
			$pattern
		);

		$formatted = $formatter->format($date);

		return $formatted === false || $formatted === ''
			? trim(($dateFormat === null ? '' : $date->format('Y-m-d')) . ' ' . ($timeFormat === null ? '' : $date->format('H:i')))
			: $formatted;
	}

	/**
	 * The menu of formats the admin offers for dates or times: the
	 * styles, then the presets whose result no style already gives, each
	 * labeled by how it shows `$now` and hinted by its name or pattern.
	 *
	 * @param  'date'|'time' $kind
	 * @return list<array{value: string, label: string, hint: string, group: string}>
	 */
	public static function options(string $kind, DateTimeInterface $now, string $locale, DateTimeZone|string $timezone): array
	{
		$options = [];
		$seen    = [];

		foreach (array_column(DateStyle::cases(), 'value') as $style) {
			$label = $kind === 'date' ? self::format($now, $locale, $timezone, $style) : self::format($now, $locale, $timezone, null, $style);

			if (isset($seen[$label])) {
				continue;
			}

			$seen[$label] = true;
			$options[]    = ['value' => $style, 'label' => $label, 'hint' => ucfirst($style), 'group' => 'From the language'];
		}

		foreach (self::PRESETS[$kind] as $preset) {
			$label = $kind === 'date' ? self::format($now, $locale, $timezone, $preset) : self::format($now, $locale, $timezone, null, $preset);

			if (isset($seen[$label])) {
				continue;
			}

			$seen[$label] = true;
			$options[]    = ['value' => $preset, 'label' => $label, 'hint' => $preset, 'group' => 'Fixed'];
		}

		return $options;
	}

	/**
	 * The pattern to format with, or `null` when the styles alone do.
	 */
	private static function pattern(string $locale, ?string $dateFormat, ?string $timeFormat): ?string
	{
		$dateStyle = $dateFormat === null || self::isStyle($dateFormat);
		$timeStyle = $timeFormat === null || self::isStyle($timeFormat);

		if ($dateStyle && $timeStyle) {
			return null;
		}

		if ($dateFormat === null || $timeFormat === null) {
			return $dateFormat ?? $timeFormat;
		}

		// The language's join for these styles (a pattern joins as
		// `medium` does), with each side swapped for the format.
		$date     = ($dateStyle ? DateStyle::from($dateFormat) : DateStyle::Medium)->icu();
		$time     = ($timeStyle ? DateStyle::from($timeFormat) : DateStyle::Medium)->icu();
		$joined   = new IntlDateFormatter($locale, $date, $time)->getPattern();
		$datePart = new IntlDateFormatter($locale, $date, IntlDateFormatter::NONE)->getPattern();
		$timePart = new IntlDateFormatter($locale, IntlDateFormatter::NONE, $time)->getPattern();

		if ($joined === false || $datePart === false || $timePart === false || ! str_contains($joined, $datePart) || ! str_contains($joined, $timePart)) {
			return "{$dateFormat} {$timeFormat}";
		}

		return strtr($joined, [
			$datePart => $dateStyle ? $datePart : $dateFormat,
			$timePart => $timeStyle ? $timePart : $timeFormat
		]);
	}
}
