<?php

/**
 * Region exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A region problem: a theme's location declaration with the wrong shape,
 * or an item that can't render.
 */
final class RegionException extends RuntimeException implements BlushException
{
}
