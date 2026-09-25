<?php

/**
 * Application booted event.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core\Events;

use Blush\Core\Application;

/**
 * Dispatched once, after every provider registered before `boot()` has booted.
 */
final readonly class ApplicationBooted
{
	public function __construct(public Application $application)
	{
	}
}
