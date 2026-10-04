<?php

/**
 * Extension package.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

use Blush\Extension\ExtensionKind;

/**
 * What the installer needs to know about an extension, of any kind: one
 * being installed (its `path` the folder it was unpacked into) or one
 * already there (D-392).
 */
final readonly class ExtensionPackage
{
	/**
	 * @param bool        $local     Whether it's a folder in its kind's folder in `user/` (not Composer's, or the framework's).
	 * @param bool|string $abandoned Whether it's abandoned, or the package to use instead (D-433).
	 * @param array<string, string> $suggest What it suggests, each mapped to why (D-434).
	 */
	public function __construct(
		public ExtensionKind $kind,
		public string $name,
		public string $label,
		public string $namespace,
		public string $version,
		public string $path,
		public bool $local,
		public bool|string $abandoned = false,
		public array $suggest = []
	) {}
}
