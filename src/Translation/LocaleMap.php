<?php

/**
 * Locale map.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Locale;

/**
 * Text in user data that may be written once per locale (D-202): a text
 * value is a string, or a map of locales to strings.
 *
 * ```yaml
 * label:
 *     en: About
 *     fr_CA: À propos
 * ```
 *
 * A map is picked by locale with the message catalogs' fallback (the
 * locale, its language, the default locale and its language), then its
 * first value. Locale keys may use `-` or `_` (`fr-CA`).
 *
 * A map counts as a locale map when every key looks like a locale and
 * every value is a string, so other maps (a view's data) pass through.
 */
final class LocaleMap
{
	/**
	 * What a locale key looks like: a language, then optional script,
	 * region, or variant parts.
	 */
	public const string LOCALE = '/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})*$/';

	/**
	 * Returns whether a value is a locale map.
	 */
	public static function isMap(mixed $value): bool
	{
		return is_array($value)
			&& $value !== []
			&& ! array_is_list($value)
			&& array_all($value, static fn (mixed $text, int|string $key): bool => is_string($text) && preg_match(self::LOCALE, (string) $key) === 1);
	}

	/**
	 * Returns a text value in a locale: a string as it is, a locale map's
	 * best match, or `null` for anything else.
	 */
	public static function text(mixed $value, string $locale, string $default): ?string
	{
		if (is_string($value)) {
			return $value;
		}

		if (! is_array($value) || ! self::isMap($value)) {
			return null;
		}

		$texts = [];

		foreach ($value as $key => $text) {
			if (is_string($text)) {
				$texts[self::normalize((string) $key)] ??= $text;
			}
		}

		foreach (Translator::fallbacks($locale, $default) as $candidate) {
			if (isset($texts[$candidate])) {
				return $texts[$candidate];
			}
		}

		return array_first($texts);
	}

	/**
	 * Returns data with every locale map in it (at any depth) replaced by
	 * its text in a locale.
	 *
	 * @template K of array-key
	 * @param    array<K, mixed> $data
	 * @return   array<K, mixed>
	 */
	public static function resolve(array $data, string $locale, string $default): array
	{
		foreach ($data as $key => $value) {
			if (self::isMap($value)) {
				$data[$key] = self::text($value, $locale, $default);
			} elseif (is_array($value)) {
				$data[$key] = self::resolve($value, $locale, $default);
			}
		}

		return $data;
	}

	/**
	 * Normalizes a locale name to `ll_RR` form, as the translator does.
	 */
	private static function normalize(string $locale): string
	{
		return Locale::canonicalize(str_replace('-', '_', $locale)) ?? $locale;
	}
}
