<?php

/**
 * Extension abandoned.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Reads an extension's `abandoned` (D-433), as Composer has it
 * (https://getcomposer.org/doc/04-schema.md#abandoned): `true` when it's
 * no longer maintained, or the name of the package to use instead. Any
 * package name will do, not only an extension's. `false` (or an empty
 * string, as Composer reads one) is not abandoned.
 *
 * It only warns, as in Composer: an abandoned extension still runs. A
 * manifest's is checked strictly; `composer.json`'s and Composer's
 * `installed.json`'s are read leniently, a value that doesn't fit being
 * read as Composer would read it (abandoned, with no replacement, when it
 * isn't empty).
 */
final readonly class ExtensionAbandoned
{
	/**
	 * Reads a manifest's `abandoned`.
	 *
	 * @throws ExtensionException When it isn't a boolean or a package name.
	 */
	public static function fromManifest(mixed $abandoned): bool|string
	{
		if (is_bool($abandoned)) {
			return $abandoned;
		}

		$abandoned = is_string($abandoned) ? trim($abandoned) : null;

		if ($abandoned === null || ($abandoned !== '' && ! ExtensionName::isValid($abandoned))) {
			throw new ExtensionException('"abandoned" must be true, false, or the name of the package to use instead (vendor/name).');
		}

		return $abandoned === '' ? false : $abandoned;
	}

	/**
	 * Reads `abandoned` leniently, as a package's `composer.json` or
	 * Composer's `installed.json` has it.
	 */
	public static function lenient(mixed $abandoned): bool|string
	{
		try {
			return self::fromManifest($abandoned);
		} catch (ExtensionException) {
			return (bool) $abandoned;
		}
	}

	/**
	 * Returns the warning an abandoned extension gets, or `null` for one
	 * that isn't.
	 */
	public static function warning(bool|string $abandoned): ?string
	{
		return match (true) {
			$abandoned === false => null,
			$abandoned === true  => 'It\'s abandoned, and no longer maintained.',
			default              => sprintf('It\'s abandoned; use "%s" instead.', $abandoned)
		};
	}
}
