<?php

/**
 * Embed exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * A provider that can't be defined as given, such as one whose oEmbed
 * endpoint isn't HTTPS.
 */
final class EmbedException extends InvalidArgumentException implements BlushException
{
}
