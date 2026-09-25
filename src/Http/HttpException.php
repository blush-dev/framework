<?php

/**
 * HTTP exception interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Blush\Core\BlushException;

/**
 * Marks every exception thrown by the HTTP layer.
 */
interface HttpException extends BlushException
{
}
