<?php

/**
 * Extension name.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * An extension's name, the key it's known by (D-378): a Composer-style
 * `vendor/name` (`justintadlock/jtcom`, `acme/tabs`), the same as its
 * Composer package's name. Its readable title is its manifest's `label`.
 */
final readonly class ExtensionName
{
	/**
	 * Matches a name: Composer's own package name rule.
	 */
	public const string PATTERN = '#^[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*$#';

	/**
	 * Returns whether a string is a valid name.
	 */
	public static function isValid(string $name): bool
	{
		return preg_match(self::PATTERN, $name) === 1;
	}
}
