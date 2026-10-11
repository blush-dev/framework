<?php

/**
 * API values.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use UnitEnum;
use Blush\Core\AppConfig;

/**
 * Turns values into what the API sends (D-737): dates in ISO 8601, in
 * UTC (`2026-10-01T14:00:00Z`), enums as their values, maps and lists as
 * they are, and anything else JSON can't hold as `null`.
 */
final class ApiValues
{
	/**
	 * Returns a date as the API sends it, or `null`.
	 */
	public static function date(?DateTimeInterface $date): ?string
	{
		return $date === null ? null : DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
	}

	/**
	 * Returns a URL as a full one on the site's origin, unless it's one
	 * already (a media URL on another host).
	 */
	public static function url(AppConfig $app, string $url): string
	{
		return preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $url) === 1 ? $url : $app->absoluteUrl($url);
	}

	/**
	 * Returns any value as the API sends it.
	 */
	public static function value(mixed $value): mixed
	{
		return match (true) {
			$value === null, is_bool($value), is_int($value), is_string($value) => $value,
			is_float($value)                       => is_finite($value) ? $value : null,
			$value instanceof DateTimeInterface    => self::date($value),
			$value instanceof BackedEnum           => $value->value,
			$value instanceof UnitEnum             => $value->name,
			$value instanceof Stringable           => (string) $value,
			is_array($value)                       => self::map($value),
			default                                => null
		};
	}

	/**
	 * Returns a list as a list and a map as an object (an empty array is
	 * an empty list; `object()` makes a map that may be empty).
	 *
	 * @param  array<array-key, mixed> $values
	 * @return list<mixed>|object
	 */
	public static function map(array $values): array|object
	{
		$mapped = array_map(self::value(...), $values);

		return array_is_list($mapped) ? $mapped : (object) $mapped;
	}

	/**
	 * Returns a map as an object, so an empty one is `{}`, not `[]`.
	 *
	 * @param array<string, mixed> $values
	 */
	public static function object(array $values): object
	{
		return (object) $values;
	}
}
