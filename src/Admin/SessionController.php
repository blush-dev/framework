<?php

/**
 * Admin session controller.
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
use Blush\Auth\AccountSuspended;
use Blush\Auth\AuthConfig;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Auth\LockedOut;
use Blush\Auth\Permissions;
use Blush\Cache\CacheException;
use Blush\Http\ClientIp;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Session\Session;

/**
 * Signs accounts in and out of the admin, and tells the admin who's
 * signed in. A signed-in answer carries the account (never its password
 * hash), the capabilities it has, and the CSRF token later requests send.
 */
final readonly class SessionController
{
	public function __construct(
		private Authenticator $authenticator,
		private Permissions $permissions,
		private AuthConfig $config
	) {}

	/**
	 * Answers with the signed-in account, or `null`.
	 *
	 * @throws AuthException When the account's record is damaged.
	 */
	public function show(ServerRequestInterface $request): ResponseInterface
	{
		$session = self::session($request);

		return self::json($this->state($session, $this->authenticator->account($session)));
	}

	/**
	 * Signs in with a JSON `username` and `password`. A suspended
	 * account is refused with a `403` (D-312).
	 *
	 * @throws AuthException  When the account's record is damaged.
	 * @throws CacheException
	 */
	public function login(ServerRequestInterface $request): ResponseInterface
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || ! is_string($input['username'] ?? null) || ! is_string($input['password'] ?? null)) {
			return self::json(['error' => 'Send a JSON "username" and "password".'], Status::BadRequest);
		}

		try {
			$account = $this->authenticator->attempt($input['username'], $input['password'], ClientIp::of($request));
		} catch (LockedOut $e) {
			return self::json(['error' => $e->getMessage()], Status::TooManyRequests, ['Retry-After' => (string) $this->config->lockout]);
		} catch (AccountSuspended $e) {
			return self::json(['error' => $e->getMessage()], Status::Forbidden);
		}

		if ($account === null) {
			return self::json(['error' => 'The username or password is wrong.'], Status::Unauthorized);
		}

		$session = self::session($request);
		$this->authenticator->login($session, $account);

		return self::json($this->state($session, $account));
	}

	/**
	 * Signs out.
	 */
	public function logout(ServerRequestInterface $request): ResponseInterface
	{
		$this->authenticator->logout(self::session($request));

		return new Response(Status::NoContent, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns what the admin needs to know about a session.
	 *
	 * @return array<string, mixed>
	 */
	private function state(Session $session, ?Account $account): array
	{
		if ($account === null) {
			return ['account' => null];
		}

		return [
			'account'   => [
				'username'     => $account->username,
				'author'       => $account->author,
				'roles'        => $account->roles,
				'capabilities' => $this->permissions->capabilities($account),
				'lastLogin'    => $account->lastLogin,
				'preferences'  => $account->preferences->toArray()
			],
			'csrfToken' => $this->authenticator->csrfToken($session)
		];
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
