<?php

/**
 * Publish in progress exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * Thrown when a publish starts while another is still running.
 */
final class PublishInProgress extends RuntimeException implements BlushException
{
}
