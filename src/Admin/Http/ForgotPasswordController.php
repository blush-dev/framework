<?php

/**
 * Admin forgot password controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AuthConfig;
use Blush\Auth\AuthException;
use Blush\Auth\PasswordResets;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Notification\NotificationType;
use Blush\Notification\Notifier;
use Blush\Notification\Recipient;
use Blush\Translation\Messages;

/**
 * Answers `POST {path}/api/forgot-password` (D-846), which anyone may
 * send: `{"login"}`, a username or an email address. When it names an
 * account that can sign in (not suspended or token-only, with an email
 * address), the account is emailed a link for choosing a new password
 * (`core/password-link`, `reason` `forgot`), which lasts an hour.
 *
 * It answers `202` the same way whether or not there's such an account,
 * so it never tells anyone which usernames or addresses exist. An
 * address that asks too often is a `429` (`PasswordResets`), and an
 * account is sent one link every five minutes at most, whoever asks. A
 * site that can't send mail is a `404`: the sign-in screen offers no
 * link there.
 */
final readonly class ForgotPasswordController
{
	public function __construct(
		private Accounts $accounts,
		private PasswordResets $resets,
		private Notifier $notifier,
		private AuthConfig $config,
		private Messages $messages
	) {}

	/**
	 * @throws AuthException  When an account's record is damaged.
	 * @throws CacheException
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->notifier->isOn()) {
			return self::json(['error' => $this->messages->t('admin.forgot_password_controller.no_mail', 'This site can\'t send email, so ask an administrator for a password link.')], Status::NotFound);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$login = is_array($input) && is_string($input['login'] ?? null) ? trim($input['login']) : '';

		if ($login === '') {
			return self::json(['error' => $this->messages->t('admin.forgot_password_controller.no_login', 'Enter your username or email address.'), 'field' => 'login'], Status::UnprocessableContent);
		}

		if ($this->resets->isLockedOut(ClientIp::of($request))) {
			return self::json(['error' => $this->messages->t('admin.forgot_password_controller.too_many', 'Too many tries. Try again later.')], Status::TooManyRequests, ['Retry-After' => (string) $this->config->lockout]);
		}

		$account = str_contains($login, '@') ? $this->accounts->findByEmail($login) : $this->accounts->find(strtolower($login));

		if ($account !== null && self::canReset($account) && $this->resets->claim($account)) {
			$this->notifier->send(NotificationType::PasswordLink->value, Recipient::account($account), ['reason' => 'forgot']);
		}

		return self::json(['message' => $this->messages->t('admin.forgot_password_controller.sent', 'If that\'s an account here, a link to choose a new password is on its way to its email address. It works for an hour.')], Status::Accepted);
	}

	/**
	 * Whether an account is one a reset link can go to.
	 */
	private static function canReset(Account $account): bool
	{
		return ! $account->suspended && ! $account->tokenOnly && $account->email !== null && $account->email !== '';
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed>  $data
	 * @param array<string, string> $headers
	 */
	private static function json(array $data, Status $status = Status::Ok, array $headers = []): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store', ...$headers]);
	}
}
