<?php

/**
 * Admin set-password controller.
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
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Accounts;
use Blush\Auth\AuthConfig;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Auth\LoginThrottle;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Session\Session;

/**
 * Answers `POST {path}/api/set-password` (D-312), the one write anyone
 * may send: `{"account", "token", "password"}` from a password link an
 * administrator made. It sets the password, ends the link, signs out
 * the account's other sessions, and signs this browser in (`204`).
 *
 * A password that's too short is a `422` with `field` `password`. A
 * link that expired, was used or replaced, or belongs to a suspended
 * account is a `410`, and counts against the address and username like
 * a failed sign-in (`429` once locked out).
 */
final readonly class SetPasswordController
{
	public function __construct(
		private Accounts $accounts,
		private Authenticator $authenticator,
		private LoginThrottle $throttle,
		private AuthConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * @throws AuthException  When the account's record is damaged.
	 * @throws CacheException
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || ! is_string($input['account'] ?? null) || ! is_string($input['token'] ?? null) || ! is_string($input['password'] ?? null)) {
			return self::json(['error' => 'Send a JSON "account", "token", and "password".'], Status::BadRequest);
		}

		$ip       = ClientIp::of($request);
		$username = strtolower(trim($input['account']));

		if ($this->throttle->isLockedOut($ip, $username)) {
			return self::json(['error' => 'Too many tries. Try again later.'], Status::TooManyRequests, ['Retry-After' => (string) $this->config->lockout]);
		}

		$problem = $this->accounts->passwordProblem($input['password']);

		if ($problem !== null) {
			return self::json(['error' => $problem, 'field' => 'password'], Status::UnprocessableContent);
		}

		try {
			$account = $this->accounts->usePasswordLink($username, $input['token'], $input['password']);
		} catch (AuthException $e) {
			$this->throttle->fail($ip, $username);

			return self::json(['error' => $e->getMessage()], Status::Gone);
		}

		$this->throttle->clear($ip, $username);

		$account = $account->withLastLogin($this->clock->now()->getTimestamp());
		$this->accounts->save($account);
		$this->authenticator->login(self::session($request), $account);

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
