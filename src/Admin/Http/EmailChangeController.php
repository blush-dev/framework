<?php

/**
 * Admin email change controller.
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
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\EmailChanges;
use Blush\Auth\Permissions;
use Blush\Cache\CacheException;
use Blush\Cache\ContentVersion;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Translation\Messages;

/**
 * Confirms and cancels changes of email address waiting on the new
 * address (D-849):
 *
 * - `POST confirm-email` (no account needed, as `set-password` isn't):
 *   `{"for", "token"}` from a confirmation link, `for` being `site` or
 *   an account's id. Changes the address and answers `{"for", "email"}`,
 *   `for` being `site` or `account`. A link that expired, was used or
 *   replaced, or whose account is gone, is a `410`; an address another
 *   account took in the meantime, a `422`.
 * - `DELETE email-change/{for}`: ends a change that waits, so its link
 *   stops working (`204`): your own account's (`account`), or the
 *   site's (`site`) with `site.settings`.
 */
final readonly class EmailChangeController
{
	public function __construct(
		private EmailChanges $changes,
		private Permissions $permissions,
		private ContentVersion $version,
		private Messages $messages
	) {}

	/**
	 * @throws CacheException
	 */
	public function confirm(ServerRequestInterface $request): ResponseInterface
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || ! is_string($input['for'] ?? null) || ! is_string($input['token'] ?? null)) {
			return self::json(['error' => 'Send a JSON "for" and "token".'], Status::BadRequest);
		}

		if (! $this->changes->accepts($input['for'], $input['token'])) {
			return self::json(['error' => $this->messages->t('auth.email_changes.dead_link', 'This link expired, was used, or was replaced by a newer one.')], Status::Gone);
		}

		try {
			$change = $this->changes->confirm($input['for'], $input['token']);
		} catch (AuthException $e) {
			return self::json(['error' => $e->getMessage()], $this->changes->pending($input['for']) === null ? Status::Gone : Status::UnprocessableContent);
		}

		if ($change->isSite()) {
			$this->version->bump();
		}

		return self::json(['for' => $change->isSite() ? 'site' : 'account', 'email' => $change->email]);
	}

	/**
	 * @throws CacheException
	 */
	public function cancel(ServerRequestInterface $request, string $for): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => $this->messages->t('admin.common.sign_in_first', 'Sign in first.')], Status::Unauthorized);
		}

		if ($for === EmailChanges::SITE && ! $this->permissions->can($account, Capability::SiteSettings)) {
			return self::json(['error' => $this->messages->t('admin.email_change_controller.not_allowed', 'You aren\'t allowed to cancel that change.')], Status::Forbidden);
		}

		if ($for !== EmailChanges::SITE && $for !== 'account') {
			return self::json(['error' => 'Cancel "site" or "account" (your own).'], Status::NotFound);
		}

		$this->changes->cancel($for === 'account' ? $account->id : $for);

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
