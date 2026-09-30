<?php

/**
 * Admin password controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
use Blush\Auth\Accounts;
use Blush\Auth\Authenticator;
use Blush\Auth\LockedOut;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Session\Session;

/**
 * Answers `POST {path}/api/password` (D-273): changes the signed-in
 * account's own password, which any account may do. It takes the
 * `current` password (checked and throttled like a sign-in) and the new
 * `password`. The account's other sessions are signed out; this one
 * stays signed in with a new id, and the answer is `204 No Content`.
 */
final readonly class PasswordController
{
	public function __construct(
		private Accounts $accounts,
		private Authenticator $authenticator,
		private AuthConfig $config
	) {}

	/**
	 * @throws CacheException
	 */
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

		if (! is_array($input) || ! is_string($input['current'] ?? null) || ! is_string($input['password'] ?? null)) {
			return self::json(['error' => 'Send a JSON "current" and "password".'], Status::BadRequest);
		}

		try {
			$confirmed = $this->authenticator->confirm($account, $input['current'], ClientIp::of($request));
		} catch (LockedOut $e) {
			return self::json(['error' => $e->getMessage()], Status::TooManyRequests, ['Retry-After' => (string) $this->config->lockout]);
		}

		if (! $confirmed) {
			return self::json(['error' => 'Your current password is wrong.', 'field' => 'current'], Status::UnprocessableContent);
		}

		$problem = $this->accounts->passwordProblem($input['password']);

		if ($problem !== null) {
			return self::json(['error' => $problem, 'field' => 'password'], Status::UnprocessableContent);
		}

		$account = $this->accounts->setPassword($account, $input['password']);
		$this->authenticator->refresh(self::session($request), $account);

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns the request's session.
	 *
	 * @throws LogicException When `StartSession` didn't run.
	 */
	private static function session(ServerRequestInterface $request): Session
	{
		$session = $request->getAttribute(Session::class);

		return $session instanceof Session ? $session : throw new LogicException('The admin API needs the StartSession middleware.');
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
