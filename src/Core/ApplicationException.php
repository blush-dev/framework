<?php

/**
 * Application exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use LogicException;

/**
 * Base exception for application-layer misconfiguration, such as registering an
 * invalid service provider or listing a non-bootable service. Extends
 * `LogicException` because these represent programming errors in how the
 * application is wired rather than recoverable runtime failures. Catch this to
 * handle any application bootstrap error.
 */
class ApplicationException extends LogicException implements BlushException
{
}
