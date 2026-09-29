<?php

/**
 * Setup check status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

/**
 * How a setup check came out. A failure stops the site from working; a
 * warning is worth fixing but doesn't.
 */
enum CheckStatus: string
{
	case Pass    = 'ok';
	case Warning = 'warning';
	case Failure = 'failure';
}
