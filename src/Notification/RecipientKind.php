<?php

/**
 * Recipient kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

/**
 * Who a notification is addressed to (D-846): an account by id, an email
 * address, or every account with a capability, worked out when it's
 * sent.
 */
enum RecipientKind: string
{
	case Account    = 'account';
	case Address    = 'address';
	case Capability = 'capability';
}
