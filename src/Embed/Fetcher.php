<?php

/**
 * Fetcher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

/**
 * Fetches an oEmbed response. An interface, so tests (and sites with
 * other needs, such as a proxy) can swap the HTTP layer.
 */
interface Fetcher
{
	/**
	 * Returns the body of a successful (200) response to a GET request
	 * for an HTTPS URL, or `null` for any failure.
	 */
	public function get(string $url, int $timeout): ?string;
}
