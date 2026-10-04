<?php

/**
 * Welcome page notes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

/**
 * What the welcome page tells a site with no homepage yet: where the
 * homepage goes, how to reach the admin, and, outside production, any
 * setup problems `doctor` would report.
 */
final readonly class Welcome
{
	/**
	 * @param string            $homepage The homepage's file, relative to the root.
	 * @param string            $binary   The CLI's path, relative to the root.
	 * @param ?string           $admin    The admin's URL path, or `null` while it's off.
	 * @param bool              $accounts Whether any accounts exist.
	 * @param list<CheckResult> $problems Setup warnings and failures.
	 */
	public function __construct(
		public string $homepage,
		public string $binary,
		public ?string $admin = null,
		public bool $accounts = false,
		public array $problems = []
	) {}
}
