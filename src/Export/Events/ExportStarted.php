<?php

/**
 * Export started event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Events;

/**
 * Dispatched when a static export starts rendering, with its output
 * folder and the origin it's exported for.
 */
final readonly class ExportStarted
{
	public function __construct(
		public string $path,
		public string $url
	) {}
}
