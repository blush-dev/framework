<?php

/**
 * Extension keywords.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Reads an extension's `keywords` (D-565), as Composer has it
 * (https://getcomposer.org/doc/04-schema.md#keywords): a list of words
 * it's about, the same for every kind. They aren't shown; the admin's
 * extension lists search them.
 *
 * A manifest's are checked strictly; `composer.json`'s and Composer's
 * `installed.json`'s are read leniently, keeping only the strings. Each
 * is trimmed, empty ones dropped, and repeats kept once.
 */
final readonly class ExtensionKeywords
{
	/**
	 * Reads a manifest's `keywords`, which may be left out.
	 *
	 * @return list<string>
	 * @throws ExtensionException When it isn't a list of strings.
	 */
	public static function fromManifest(mixed $value): array
	{
		$value ??= [];

		if (! is_array($value) || ! array_is_list($value)) {
			throw new ExtensionException('"keywords" must be a list of strings.');
		}

		foreach ($value as $keyword) {
			if (! is_string($keyword)) {
				throw new ExtensionException('"keywords" must be a list of strings.');
			}
		}

		return self::clean($value);
	}

	/**
	 * Reads `keywords` leniently, as a package's `composer.json` or
	 * Composer's `installed.json` has it.
	 *
	 * @return list<string>
	 */
	public static function lenient(mixed $value): array
	{
		if (! is_array($value) || ! array_is_list($value)) {
			return [];
		}

		return self::clean(array_filter($value, is_string(...)));
	}

	/**
	 * Trims each, dropping empty ones and repeats.
	 *
	 * @param  array<array-key, string> $keywords
	 * @return list<string>
	 */
	private static function clean(array $keywords): array
	{
		$trimmed = array_map(trim(...), $keywords);

		return array_values(array_unique(array_filter($trimmed, static fn (string $keyword): bool => $keyword !== '')));
	}
}
