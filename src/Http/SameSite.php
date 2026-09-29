<?php

/**
 * Cookie SameSite values.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

/**
 * When a browser sends a cookie with requests from other sites.
 */
enum SameSite: string
{
	case Strict = 'Strict';
	case Lax    = 'Lax';
	case None   = 'None';
}
