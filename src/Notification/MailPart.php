<?php

/**
 * Mail part.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

/**
 * One part of a notification's email (`MailContent`): its kind, its
 * text, and an action's link.
 */
final readonly class MailPart
{
	public function __construct(
		public MailPartKind $kind,
		public string $text,
		public string $url = ''
	) {}
}
