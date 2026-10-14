<?php

/**
 * Recipient.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Notification;

use Blush\Auth\Account;
use Blush\Auth\Capability;

/**
 * Who a notification goes to (D-846), as data a job can keep:
 *
 *     Recipient::account($account->id);
 *     Recipient::address('orders@example.com');
 *     Recipient::capability('site.jobs');
 *
 * An account is read when its message is sent, so a changed address or
 * language is the new one. A capability is every account that has it
 * then, with an email address, that isn't suspended or token-only, so a
 * notification needn't list accounts that change.
 */
final readonly class Recipient
{
	/**
	 * @throws NotificationException When the value doesn't fit the kind.
	 */
	public function __construct(
		public RecipientKind $kind,
		public string $value
	) {
		$valid = match ($kind) {
			RecipientKind::Account    => $value !== '',
			RecipientKind::Address    => Account::isValidEmail($value),
			RecipientKind::Capability => preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z0-9_*-]+)+$/', $value) === 1
		};

		if (! $valid) {
			throw new NotificationException(sprintf('"%s" isn\'t a recipient of the kind "%s".', $value, $kind->value));
		}
	}

	/**
	 * An account, by its id.
	 */
	public static function account(string|Account $account): self
	{
		return new self(RecipientKind::Account, $account instanceof Account ? $account->id : $account);
	}

	/**
	 * An email address of no account.
	 */
	public static function address(string $email): self
	{
		return new self(RecipientKind::Address, trim($email));
	}

	/**
	 * Every account with a capability when it's sent.
	 */
	public static function capability(string|Capability $capability): self
	{
		return new self(RecipientKind::Capability, $capability instanceof Capability ? $capability->value : $capability);
	}

	/**
	 * Returns it as one string (`account:{id}`), for `unique` keys.
	 */
	public function key(): string
	{
		return $this->kind->value . ':' . ($this->kind === RecipientKind::Address ? mb_strtolower($this->value) : $this->value);
	}

	/**
	 * @return array{kind: string, value: string}
	 */
	public function toArray(): array
	{
		return ['kind' => $this->kind->value, 'value' => $this->value];
	}

	/**
	 * Reads one back from `toArray()`, or `null` when it isn't one.
	 */
	public static function fromArray(mixed $data): ?self
	{
		if (! is_array($data) || ! is_string($data['kind'] ?? null) || ! is_string($data['value'] ?? null)) {
			return null;
		}

		$kind = RecipientKind::tryFrom($data['kind']);

		try {
			return $kind === null ? null : new self($kind, $data['value']);
		} catch (NotificationException) {
			return null;
		}
	}
}
