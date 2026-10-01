<?php

/**
 * URL paths.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

/**
 * Percent-encodes decoded paths for URLs. Each segment is encoded on its
 * own, so the slashes between segments stay separators: `photos/my
 * cat.jpg` becomes `photos/my%20cat.jpg`.
 */
final class UrlPath
{
	/**
	 * Returns a decoded path with each segment percent-encoded.
	 */
	public static function encode(string $path): string
	{
		return implode('/', array_map(rawurlencode(...), explode('/', $path)));
	}
}
