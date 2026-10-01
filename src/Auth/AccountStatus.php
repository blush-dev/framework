<?php

/**
 * Account status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * Where an account stands (D-312), as the admin shows it.
 */
enum AccountStatus: string
{
	// It can sign in.
	case Active = 'active';

	// It has never signed in, and has a link for choosing a password.
	case Invited = 'invited';

	// It can't sign in until it's reinstated.
	case Suspended = 'suspended';
}
