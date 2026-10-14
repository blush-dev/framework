<?php

/**
 * Translation catalog.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

/**
 * What a catalog says about itself (D-452): keys starting with `@@` are
 * metadata, not messages, and `@@locale` and `@@domain` name what it
 * translates, in ARB's style. A named catalog (D-831), the text a
 * domain's scripts read in the browser, says which with `@@catalog`.
 */
final class Catalog
{
	/**
	 * The prefix of metadata keys.
	 */
	public const string META = '@@';

	/**
	 * The key naming the catalog's locale.
	 */
	public const string LOCALE = '@@locale';

	/**
	 * The key naming the catalog's domain.
	 */
	public const string DOMAIN = '@@domain';

	/**
	 * The key naming a named catalog (D-831), such as `admin` or `site`.
	 */
	public const string CATALOG = '@@catalog';

	/**
	 * What a named catalog's name looks like: a folder in `lang/`.
	 */
	public const string NAME_PATTERN = '^[a-z][a-z0-9_-]*$';

	/**
	 * The same, as a PHP pattern.
	 */
	public const string NAME = '/' . self::NAME_PATTERN . '/';

	/**
	 * Returns whether a key is metadata rather than a message.
	 */
	public static function isMeta(int|string $key): bool
	{
		return str_starts_with((string) $key, self::META);
	}

	/**
	 * Returns whether a key is metadata or a message's note, never a
	 * message: `@@locale` and `@@domain`, and `@{key}` beside a message,
	 * with its `context` for translators (D-784), as ARB writes them.
	 */
	public static function isAnnotation(int|string $key): bool
	{
		return str_starts_with((string) $key, '@');
	}

	/**
	 * Returns whether a string is a named catalog's name.
	 */
	public static function isName(string $name): bool
	{
		return preg_match(self::NAME, $name) === 1;
	}

	/**
	 * Returns an empty catalog's JSON, with its metadata, for a new
	 * extension's `lang/en.json`.
	 */
	public static function starter(string $domain, string $locale = 'en'): string
	{
		return json_encode([self::LOCALE => $locale, self::DOMAIN => $domain], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
	}
}
