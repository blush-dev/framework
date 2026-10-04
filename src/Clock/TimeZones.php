<?php

/**
 * Time zones.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Clock;

use Collator;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlTimeZone;

/**
 * The time zones a site can be in, for the admin's time zone menu
 * (D-444): every IANA zone (`America/Chicago`, still the value saved),
 * named by its city (`Chicago`; `Indianapolis, Indiana` for a zone
 * under another part), grouped by region (`America`) with UTC first,
 * cities in alphabetical order. Each one's hint is its common name in a
 * language (ICU's: `Central Time`) and its offset now (`UTC−5`), which
 * changes with daylight saving time. A search also finds a zone by its
 * name, its abbreviations through the year (`CST`, `CDT`), its offset
 * written as `-05:00` or `UTC-5`, and the old names that point to it
 * (`US/Central`).
 */
final class TimeZones
{
	/**
	 * Returns the time zones as menu options.
	 *
	 * @return list<array{value: string, label: string, hint: ?string, group: ?string, search: string}>
	 */
	public static function options(DateTimeInterface $now, string $in = 'en'): array
	{
		$collator = new Collator($in);
		$aliases  = self::aliases();
		$year     = (int) $now->format('Y');
		$options  = [];

		foreach (DateTimeZone::listIdentifiers() as $id) {
			$zone   = new DateTimeZone($id);
			$parts  = explode('/', str_replace('_', ' ', $id));
			$region = count($parts) > 1 ? array_shift($parts) : null;
			$city   = implode(', ', array_reverse($parts));
			$offset = $zone->getOffset($now);
			$name   = IntlTimeZone::createTimeZone($id)->getDisplayName(false, IntlTimeZone::DISPLAY_LONG_GENERIC, $in);

			// ICU names a zone it doesn't know by its offset (`GMT`).
			$name = str_starts_with($name, 'GMT') ? null : $name;

			$abbreviations = array_filter(
				array_unique([
					new DateTimeImmutable("{$year}-01-01", $zone)->format('T'),
					new DateTimeImmutable("{$year}-07-01", $zone)->format('T')
				]),
				static fn (string $abbreviation): bool => preg_match('/^[A-Z]/', $abbreviation) === 1
			);

			$options[] = [
				'value'  => $id,
				'label'  => $city,
				'hint'   => $id === 'UTC' ? null : implode(' · ', array_filter([$name, self::utc($offset)])),
				'group'  => $region,
				'search' => implode(' ', [$id, $name ?? '', ...$abbreviations, ...self::offsets($offset), ...($aliases[$id] ?? [])])
			];
		}

		usort($options, static fn (array $a, array $b): int => [$a['group'] !== null, $a['group']] <=> [$b['group'] !== null, $b['group']] ?: (int) $collator->compare($a['label'], $b['label']));

		return $options;
	}

	/**
	 * An offset as `UTC−5` or `UTC+5:30` (a minus sign, not a hyphen), or
	 * `UTC` for none.
	 */
	private static function utc(int $seconds): string
	{
		if ($seconds === 0) {
			return 'UTC';
		}

		$minutes = intdiv(abs($seconds), 60);

		return sprintf('UTC%s%d%s', $seconds < 0 ? '−' : '+', intdiv($minutes, 60), $minutes % 60 === 0 ? '' : sprintf(':%02d', $minutes % 60));
	}

	/**
	 * An offset as a search finds it: `-05:00` and `UTC-5`, `GMT-5`.
	 *
	 * @return list<string>
	 */
	private static function offsets(int $seconds): array
	{
		$minutes = intdiv(abs($seconds), 60);
		$sign    = $seconds < 0 ? '-' : '+';
		$short   = str_replace('−', '-', substr(self::utc($seconds), 3));

		return [sprintf('%s%02d:%02d', $sign, intdiv($minutes, 60), $minutes % 60), "UTC{$short}", "GMT{$short}"];
	}

	/**
	 * The old names that point to each zone (`US/Central` to
	 * `America/Chicago`), by zone.
	 *
	 * @return array<string, list<string>>
	 */
	private static function aliases(): array
	{
		// ICU's own canonical names aren't always IANA's (`Asia/Calcutta`
		// for `Asia/Kolkata`), so both sides go through ICU's.
		$canonical = static fn (string $id): string => IntlTimeZone::getCanonicalID($id) ?: $id;
		$current   = [];
		$aliases   = [];

		foreach (DateTimeZone::listIdentifiers() as $id) {
			$current[$canonical($id)] = $id;
		}

		foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC) as $id) {
			$zone = $current[$canonical($id)] ?? null;

			if ($zone !== null && $zone !== $id && ! in_array($id, $current, true)) {
				$aliases[$zone][] = $id;
			}
		}

		return $aliases;
	}
}
