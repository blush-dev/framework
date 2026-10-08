<?php

/**
 * Record exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Blush\Core\BlushException;

/**
 * Marks every exception the record layer throws (D-643).
 */
interface RecordException extends BlushException
{
}
