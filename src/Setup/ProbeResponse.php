<?php

/**
 * Probe response.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

/**
 * What a `UrlProbe` got back: the status and the start of the body.
 */
final readonly class ProbeResponse
{
	public function __construct(
		public int $status,
		public string $body
	) {}
}
