<?php

/**
 * URL probe.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

/**
 * Asks the site's own address for a path (D-716), for the exposure
 * check. An interface, so tests (and sites behind something unusual)
 * can swap it.
 */
interface UrlProbe
{
	/**
	 * Returns the response's status and up to `$limit` bytes of its body,
	 * or `null` when the address can't be reached (a refused connection,
	 * a timeout, a certificate that won't verify). Redirects aren't
	 * followed.
	 */
	public function get(string $url, int $limit): ?ProbeResponse;
}
