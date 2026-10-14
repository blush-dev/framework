<?php

/**
 * Email changes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Psr\Clock\ClockInterface;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Cache\Caches;
use Blush\Cache\Store;
use Blush\Core\AppConfig;
use Blush\Notification\Notifier;
use Blush\Notification\Recipient;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Translation\Messages;

/**
 * Changes of email address (D-849), the site's (D-723) and accounts'
 * (D-370), so an address can't be moved somewhere its owner doesn't
 * know of:
 *
 * - **Asked for:** someone changing their own account's address, or the
 *   site's on Settings → General, is asked to confirm the new one: it's
 *   sent a link (`core/email-change`, stage `confirm`), and the old one
 *   is told a change was asked (`asked`). Nothing changes until the
 *   link is used (`confirm()`), within a day (`LIFETIME`); the old
 *   address is then told it changed (`changed`). One waits per address
 *   owner; asking again replaces it.
 * - **Made at once:** an administrator changing another account's
 *   address, or `account:email`, changes it now (`changeAccount()`),
 *   and tells the old one.
 * - **Without mail** (`Notifier::isOn()`), or with no address to change
 *   from, there's nothing to confirm with, so changes are made at once.
 *
 * What waits is kept in the persistent `logins` store, beside password
 * resets (D-846), and expires with its link.
 */
final readonly class EmailChanges
{
	/**
	 * What a change of the site's own address is for.
	 */
	public const string SITE = 'site';

	/**
	 * How long a confirmation link works, in seconds.
	 */
	public const int LIFETIME = 86400;

	/**
	 * The notification that sends the links and notices.
	 */
	public const string NOTIFICATION = 'core/email-change';

	public function __construct(
		private Caches $caches,
		private Accounts $accounts,
		private SettingsStore $settings,
		private AppConfig $app,
		private Notifier $notifier,
		private ClockInterface $clock,
		private Messages $messages
	) {}

	/**
	 * Returns the change waiting for the site (`SITE`) or an account (its
	 * id), or `null` for none, or one that expired.
	 *
	 * @throws CacheException
	 */
	public function pending(string $for): ?EmailChange
	{
		$change = EmailChange::fromArray($this->store()->get(self::key($for)));

		return $change !== null && $change->expires > $this->clock->now()->getTimestamp() ? $change : null;
	}

	/**
	 * Whether a change from one address to another needs confirming: the
	 * site can send mail, there's an address to change from, and the new
	 * one isn't the same (in any case).
	 */
	public function needsConfirming(?string $from, string $to): bool
	{
		return $this->notifier->isOn() && $from !== null && $from !== '' && mb_strtolower(trim($from)) !== mb_strtolower(trim($to));
	}

	/**
	 * Asks to change an account's address, as its own account does: once
	 * the address is checked, it's emailed a link to confirm, and the old
	 * one is told, returning what waits; or, where it needn't be
	 * confirmed, it's changed now, returning `null`.
	 *
	 * @throws AuthException When the address is missing, invalid, or
	 *                       another account's.
	 * @throws CacheException
	 */
	public function askForAccount(Account $account, string $email): ?EmailChange
	{
		$email = $account->tokenOnly ? $email : $this->accounts->checkEmail($email, $account->username);

		if ($account->tokenOnly || ! $this->needsConfirming($account->email, $email)) {
			$this->changeAccount($account, $email);

			return null;
		}

		return $this->ask($account->id, $email, $account->email ?? '', $account);
	}

	/**
	 * Asks to change the site's address, as Settings → General does: the
	 * new one is emailed a link to confirm, and the old one is told.
	 * Where it needn't be confirmed (`needsConfirming()`), save it as any
	 * other setting instead.
	 *
	 * @throws InvalidSetting When it isn't one email address.
	 * @throws CacheException
	 */
	public function askForSite(string $email, Account $by): EmailChange
	{
		$email = trim($email);

		if (! Account::isValidEmail($email)) {
			throw new InvalidSetting((string) $this->messages->t('auth.email_changes.site_invalid', '"{email}" isn\'t an email address.', email: $email));
		}

		return $this->ask(self::SITE, $email, $this->app->email, $by);
	}

	/**
	 * Changes an account's address now, tells the old one, and ends any
	 * change that waited. `$by` is who changed it, when it wasn't the
	 * account itself.
	 *
	 * @throws AuthException When the address is missing, invalid, or
	 *                       another account's.
	 * @throws CacheException
	 */
	public function changeAccount(Account $account, string $email, ?Account $by = null): Account
	{
		$old     = $account->email;
		$changed = $this->accounts->setEmail($account, $email);

		$this->store()->delete(self::key($account->id));

		if ($old !== null && $old !== '' && $changed->email !== null && mb_strtolower($old) !== mb_strtolower($changed->email)) {
			$this->notify('changed', $account->id, $changed->email, $old, $by === null || $by->id === $account->id ? null : $by->id);
		}

		return $changed;
	}

	/**
	 * Makes a new token for the link to a change waiting on an address,
	 * replacing the one before, for its email as it's sent; or `null`
	 * when nothing waits on that address now (it was confirmed,
	 * cancelled, replaced, or expired).
	 *
	 * @throws CacheException
	 */
	public function issue(string $for, string $email): ?string
	{
		$change = $this->pending($for);

		if ($change === null || mb_strtolower($change->email) !== mb_strtolower($email)) {
			return null;
		}

		[$change, $token] = $change->withNewToken();
		$this->put($change);

		return $token;
	}

	/**
	 * Whether a token is the link last emailed for a change that waits.
	 *
	 * @throws CacheException
	 */
	public function accepts(string $for, string $token): bool
	{
		return $this->pending($for)?->accepts($token, $this->clock->now()->getTimestamp()) === true;
	}

	/**
	 * Confirms a change with its link's token: the address changes, the
	 * old one is told, and the change ends.
	 *
	 * @throws AuthException When the link expired, was used or replaced,
	 *                       or its account is gone or suspended, or the
	 *                       address became another account's.
	 * @throws CacheException
	 */
	public function confirm(string $for, string $token): EmailChange
	{
		$change = $this->pending($for);

		if ($change === null || ! $this->accepts($for, $token)) {
			throw new AuthException((string) $this->messages->t('auth.email_changes.dead_link', 'This link expired, was used, or was replaced by a newer one.'));
		}

		if ($change->isSite()) {
			$old = $this->app->email;

			try {
				$this->settings->update(fn (Settings $settings): Settings => $settings->with([Setting::Email->value => $change->email], $this->messages));
			} catch (InvalidSetting $e) {
				throw new AuthException($e->getMessage(), previous: $e);
			}

			$this->store()->delete(self::key($for));

			if ($old !== '') {
				$this->notify('changed', self::SITE, $change->email, $old, $change->by);
			}

			return $change;
		}

		$account = $this->accounts->findById($for);

		if ($account === null || $account->suspended) {
			$this->store()->delete(self::key($for));

			throw new AuthException((string) $this->messages->t('auth.email_changes.no_account', 'The account this link is for is gone or suspended.'));
		}

		$this->changeAccount($account, $change->email);

		return $change;
	}

	/**
	 * Ends a change that waits, so its link stops working.
	 *
	 * @throws CacheException
	 */
	public function cancel(string $for): void
	{
		$this->store()->delete(self::key($for));
	}

	/**
	 * Keeps a change waiting, and sends the link and the notice.
	 *
	 * @throws CacheException
	 */
	private function ask(string $for, string $email, string $old, Account $by): EmailChange
	{
		$change = new EmailChange($for, $email, $this->clock->now()->getTimestamp() + self::LIFETIME, $by->id);

		$this->put($change);
		$this->notify('confirm', $for, $email, $old, $by->id);

		if ($old !== '') {
			$this->notify('asked', $for, $email, $old, $by->id);
		}

		return $change;
	}

	/**
	 * Sends one stage's email: `confirm` to the new address, `asked` and
	 * `changed` to the old.
	 */
	private function notify(string $stage, string $for, string $email, string $old, ?string $by): void
	{
		$this->notifier->send(
			self::NOTIFICATION,
			Recipient::address($stage === 'confirm' ? $email : $old),
			['stage' => $stage, 'for' => $for, 'email' => $email, 'old' => $old, 'by' => $by],
			$by
		);
	}

	/**
	 * Saves a change until its link expires.
	 *
	 * @throws CacheException
	 */
	private function put(EmailChange $change): void
	{
		$this->store()->set(self::key($change->for), $change->toArray(), max(1, $change->expires - $this->clock->now()->getTimestamp()));
	}

	/**
	 * The store changes wait in.
	 *
	 * @throws CacheException
	 */
	private function store(): Store
	{
		return $this->caches->persistent(CacheNamespace::Logins);
	}

	/**
	 * Returns the key a change is kept under.
	 */
	private static function key(string $for): string
	{
		return hash('sha256', 'email-change|' . $for);
	}
}
