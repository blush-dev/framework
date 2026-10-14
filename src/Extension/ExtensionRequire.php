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

use Blush\Translation\Messages;

/**
 * Reads a manifest's `require` (D-385, D-431), the same for every kind:
 * an object mapping what's needed to a version constraint. `conflict`
 * (D-435), `replace` (D-436), and `provide` (D-439) have the same
 * shape, so they're read here too.
 */
final readonly class ExtensionRequire
{
	/**
	 * Reads `require`, or another key of its shape (`conflict`,
	 * `replace`, `provide`), which may be left out.
	 *
	 * @return array<string, string>
	 * @throws ExtensionException
	 */
	public static function fromArray(mixed $value, string $key = 'require', Messages $messages = new Messages()): array
	{
		$value ??= [];

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new ExtensionException((string) $messages->t('extension.extension_require.not_object', '"{field}" must be an object.', field: $key));
		}

		$require = [];

		foreach ($value as $name => $constraint) {
			if (! is_string($name) || ! is_string($constraint)) {
				throw new ExtensionException((string) $messages->t('extension.extension_require.not_map', '"{field}" must map names to version constraints.', field: $key));
			}

			$require[$name] = $constraint;
		}

		return $require;
	}
}
