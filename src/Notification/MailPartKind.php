<?php

/**
 * Mail part kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

/**
 * What a part of a notification's email is (D-846): a paragraph, a
 * button with its link, or small print under a rule.
 */
enum MailPartKind: string
{
	case Line   = 'line';
	case Action = 'action';
	case Note   = 'note';
}
