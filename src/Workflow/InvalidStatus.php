<?php

/**
 * Invalid status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use LogicException;

/**
 * Thrown for a status that can't be defined as given.
 */
final class InvalidStatus extends LogicException
{
}
