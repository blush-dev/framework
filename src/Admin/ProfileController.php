<?php

/**
 * Admin profile controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `PATCH {path}/api/profile` (D-322, D-370): changes the
 * signed-in account's own `name` (`null` or empty takes it away) and
 * `email`, either or both, which any account may do, and answers with
 * its `name`, `email`, and `displayName`. A name that's too long, or an
 * email address that's missing, invalid, or another account's, is a
 * `422` naming the `field`.
 */
final readonly class ProfileController
{
	public function __construct(
		private Accounts $accounts,
		private AccountProfiles $profiles
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$hasName  = is_array($input) && array_key_exists('name', $input);
		$hasEmail = is_array($input) && array_key_exists('email', $input);

		if (! is_array($input) || (! $hasName && ! $hasEmail) || ($hasName && $input['name'] !== null && ! is_string($input['name'])) || ($hasEmail && ! is_string($input['email']))) {
			return self::json(['error' => 'Send a JSON "name" (or null), an "email", or both.'], Status::BadRequest);
		}

		try {
			$name    = $input['name'] ?? null;
			$account = $hasName && ($name === null || is_string($name)) ? $this->accounts->setName($account, $name) : $account;
		} catch (AuthException $e) {
			return self::json(['error' => $e->getMessage(), 'field' => 'name'], Status::UnprocessableContent);
		}

		try {
			$email   = $input['email'] ?? null;
			$account = is_string($email) && trim($email) !== $account->email ? $this->accounts->setEmail($account, $email) : $account;
		} catch (AuthException $e) {
			return self::json(['error' => $e->getMessage(), 'field' => 'email'], Status::UnprocessableContent);
		}

		return self::json(['name' => $account->name, 'email' => $account->email, 'displayName' => $this->profiles->displayName($account)]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
