<?php

/**
 * Extension namespace.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Component\ComponentName;

/**
 * The namespace an extension declares in its manifest (D-378): what its
 * components (D-171), icons (D-187), and translation domain go by
 * (`jtcom`, for `::jtcom/post-archives` and `jtcom/github`). No two
 * installed extensions may claim one, and core's (`blush`), the site's
 * (`app`), the theme chain's translation domain (`theme`), and the
 * framework default theme's (`default`) are reserved.
 */
final readonly class ExtensionNamespace
{
	/**
	 * Matches a namespace: lowercase letters and digits, with hyphens or
	 * underscores between them.
	 */
	public const string PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

	/**
	 * Namespaces no extension may claim.
	 *
	 * @var list<string>
	 */
	public const array RESERVED = [ComponentName::CORE, ComponentName::SITE, 'theme', 'default'];

	/**
	 * Returns whether a string is a valid namespace.
	 */
	public static function isValid(string $namespace): bool
	{
		return preg_match(self::PATTERN, $namespace) === 1;
	}

	/**
	 * Returns whether a namespace is reserved.
	 */
	public static function isReserved(string $namespace): bool
	{
		return in_array($namespace, self::RESERVED, true);
	}
}
