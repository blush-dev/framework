<?php

/**
 * Install result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension\Install;

/**
 * An installed extension (D-392): where it is now, and, when it replaced
 * one, that one's version and where its folder was kept.
 */
final readonly class InstallResult
{
	public function __construct(
		public ExtensionPackage $package,
		public ?ExtensionPackage $replaced = null,
		public ?string $backup = null
	) {}
}
