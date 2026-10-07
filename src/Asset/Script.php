<?php

/**
 * Script.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

/**
 * One of an asset's scripts (D-569): a file, where it's from (as for a
 * `Style`), its attributes, and whether it prints in the footer.
 *
 * Its attributes control how it loads, as `Head::script()` takes them:
 * scripts are deferred unless they say otherwise (`['defer' => false]`,
 * `['async' => true]`, or a `type`, such as `module`). In the footer,
 * it's added to the page's `Foot` instead of its head, and prints where
 * the layout prints `$template->foot()` (D-577, D-578).
 */
final readonly class Script
{
	/**
	 * @param array<string, string|bool> $attributes
	 */
	public function __construct(
		public string $path,
		public string $from = '',
		public array $attributes = [],
		public bool $footer = false
	) {}
}
