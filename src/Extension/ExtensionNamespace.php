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

use Blush\Directive\DirectiveName;

/**
 * The namespace an extension declares in its manifest (D-378): what its
 * directives and components (D-171, D-532), icons (D-187), and
 * translation domain go by (`jtcom`, for `jtcom/entry-terms` and
 * `jtcom/github`). No two installed extensions may claim one, and core's
 * (`blush`), the site's (`app`), the theme chain's translation domain
 * (`theme`), and the framework default theme's (`default`) are reserved.
 * A manifest without one goes by its name, hyphenated (D-424).
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
	public const array RESERVED = [DirectiveName::CORE, DirectiveName::SITE, 'theme', 'default'];

	/**
	 * Returns whether a string is a valid namespace.
	 */
	public static function isValid(string $namespace): bool
	{
		return preg_match(self::PATTERN, $namespace) === 1;
	}

	/**
	 * Returns the namespace a manifest without one goes by: its name with
	 * the `/` and any `.` as hyphens (`acme/photo.gallery` is
	 * `acme-photo-gallery`, D-424). A valid name gives a valid namespace,
	 * never a reserved one, since it always has a hyphen.
	 */
	public static function fromName(string $name): string
	{
		return str_replace(['/', '.'], '-', $name);
	}

	/**
	 * Returns whether a namespace is reserved.
	 */
	public static function isReserved(string $namespace): bool
	{
		return in_array($namespace, self::RESERVED, true);
	}
}
