<?php

/**
 * Asset exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * Thrown for an asset that can't be registered, such as one whose
 * handle isn't a `vendor/name`.
 */
final class AssetException extends InvalidArgumentException implements BlushException
{
}
