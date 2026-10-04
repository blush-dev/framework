<?php

/**
 * Extension suggest.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Reads an extension's `suggest` (D-434), as Composer has it
 * (https://getcomposer.org/doc/04-schema.md#suggest): an object mapping
 * the packages that would work well with it to why, the same for every
 * kind. Any name will do, as in `require`: another extension's
 * `vendor/name`, a library's, or `ext-{name}`.
 *
 * It's never enforced, only shown. A manifest's is checked strictly;
 * `composer.json`'s and Composer's `installed.json`'s are read leniently,
 * keeping only the entries that map a name to a string.
 */
final readonly class ExtensionSuggest
{
	/**
	 * Reads a manifest's `suggest`, which may be left out.
	 *
	 * @return array<string, string>
	 * @throws ExtensionException When it isn't an object of names to reasons.
	 */
	public static function fromManifest(mixed $value): array
	{
		$value ??= [];

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new ExtensionException('"suggest" must be an object.');
		}

		$suggest = [];

		foreach ($value as $name => $reason) {
			if (! is_string($name) || trim($name) === '' || ! is_string($reason)) {
				throw new ExtensionException('"suggest" must map names to why each is suggested.');
			}

			$suggest[trim($name)] = trim($reason);
		}

		return $suggest;
	}

	/**
	 * Reads `suggest` leniently, as a package's `composer.json` or
	 * Composer's `installed.json` has it.
	 *
	 * @return array<string, string>
	 */
	public static function lenient(mixed $value): array
	{
		if (! is_array($value) || array_is_list($value)) {
			return [];
		}

		$suggest = [];

		foreach ($value as $name => $reason) {
			if (is_string($name) && trim($name) !== '' && is_string($reason)) {
				$suggest[trim($name)] = trim($reason);
			}
		}

		return $suggest;
	}
}
