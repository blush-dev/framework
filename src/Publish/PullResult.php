<?php

/**
 * Pull result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

/**
 * Whether a pull worked, and what the tool printed.
 */
final readonly class PullResult
{
	public function __construct(
		public bool $successful,
		public string $output = ''
	) {}
}
