<?php

/**
 * Extension require.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Reads a manifest's `require` (D-385, D-431), the same for every kind:
 * an object mapping what's needed to a version constraint.
 */
final readonly class ExtensionRequire
{
	/**
	 * Reads `require`, which may be left out.
	 *
	 * @return array<string, string>
	 * @throws ExtensionException
	 */
	public static function fromArray(mixed $value): array
	{
		$value ??= [];

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new ExtensionException('"require" must be an object.');
		}

		$require = [];

		foreach ($value as $name => $constraint) {
			if (! is_string($name) || ! is_string($constraint)) {
				throw new ExtensionException('"require" must map names to version constraints.');
			}

			$require[$name] = $constraint;
		}

		return $require;
	}
}
