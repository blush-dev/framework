<?php

/**
 * Invalid setting exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * Thrown when a saved setting is unknown or has a value that doesn't fit,
 * or the saved settings (`user/data/settings/`) can't be read or written. The message is
 * written for the person who changed it.
 */
final class InvalidSetting extends InvalidArgumentException implements BlushException
{
}
