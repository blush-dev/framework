<?php

/**
 * Mail address.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

use Override;
use Stringable;
use Blush\Auth\Account;

/**
 * An email address, with the name shown beside it.
 */
final readonly class Address implements Stringable
{
	/**
	 * @throws MailException When the address isn't one.
	 */
	public function __construct(
		public string $email,
		public string $name = ''
	) {
		if (! Account::isValidEmail($email)) {
			throw new MailException(sprintf('"%s" isn\'t an email address.', $email));
		}

		if (preg_match('/[\r\n]/', $name) === 1) {
			throw new MailException('A name beside an email address is one line.');
		}
	}

	/**
	 * Returns it as a header has it: `Name <email>`, or the address alone.
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->name === '' ? $this->email : sprintf('%s <%s>', $this->name, $this->email);
	}
}
