<?php

/**
 * Notifiable.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Blush\Auth\Account;
use Blush\Mail\Address;

/**
 * One person a notification is being sent to (D-846): their address,
 * and their account when they have one (an address on its own has
 * none), as `Notification::mail()` gets them.
 */
final readonly class Notifiable
{
	public function __construct(
		public Address $address,
		public ?Account $account = null
	) {}

	/**
	 * Returns what to call them: the account's name, else its username,
	 * else the name beside the address, else the address.
	 */
	public function name(): string
	{
		return $this->account === null
			? ($this->address->name !== '' ? $this->address->name : $this->address->email)
			: ($this->account->name ?? $this->account->username);
	}

	/**
	 * Returns the language their mail is written in: the account's
	 * (D-831), or `null` for the site's.
	 */
	public function language(): ?string
	{
		return $this->account?->preferences->language;
	}
}
