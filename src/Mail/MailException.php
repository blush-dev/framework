<?php

/**
 * Mail exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A message couldn't be sent: the transport refused it, couldn't be
 * reached, or isn't set up.
 */
class MailException extends RuntimeException implements BlushException
{
}
