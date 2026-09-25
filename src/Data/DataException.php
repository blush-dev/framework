<?php

/**
 * Data exception marker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Blush\Core\BlushException;

/**
 * Marks every exception thrown while finding or parsing data files.
 */
interface DataException extends BlushException
{
}
