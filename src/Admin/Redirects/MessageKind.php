<?php

/**
 * Redirect message kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Redirects;

/**
 * How much a Redirects screen message matters (D-686).
 */
enum MessageKind: string
{
	// Stops a save: a short red line under its field.
	case Bad = 'bad';

	// Saves, but likely not as meant: in the form's notice.
	case Warn = 'warn';

	// What will happen: in the form's notice.
	case Say = 'say';
}
