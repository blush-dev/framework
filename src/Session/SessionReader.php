<?php

/**
 * Session reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Finds a request's session from its cookie. `read()` has no side
 * effects: it neither starts a session nor keeps one alive, so a page
 * that only wants to know who's signed in (the admin's shell, D-235)
 * can ask without setting cookies. `StartSession` does the rest.
 */
final readonly class SessionReader
{
	public function __construct(
		private SessionStore $store,
		private SessionConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * Returns the request's live session, or `null`.
	 */
	public function read(ServerRequestInterface $request): ?Session
	{
		$id     = $this->sentId($request);
		$record = $id === null ? null : $this->store->read($id);

		return $id === null || $record === null || $this->isExpired($record, $this->clock->now()->getTimestamp())
			? null
			: new Session($id, $record['data'], $record['created']);
	}

	/**
	 * Returns the session id the request's cookie sends, when it's one.
	 */
	public function sentId(ServerRequestInterface $request): ?string
	{
		$sent = $request->getCookieParams()[$this->cookieName($request)] ?? null;

		return is_string($sent) && Session::isValidId($sent) ? $sent : null;
	}

	/**
	 * Returns the name of the session cookie for a request.
	 */
	public function cookieName(ServerRequestInterface $request): string
	{
		return $this->config->cookieName($this->isSecure($request));
	}

	/**
	 * Whether the cookie should be `Secure`.
	 */
	public function isSecure(ServerRequestInterface $request): bool
	{
		return $this->config->secure ?? $request->getUri()->getScheme() === 'https';
	}

	/**
	 * Whether a stored session has been idle too long or lived too long.
	 *
	 * @param array{created: int, lastSeen: int, data: array<string, mixed>} $record
	 */
	public function isExpired(array $record, int $now): bool
	{
		return $record['lastSeen'] + $this->config->idle < $now || $record['created'] + $this->config->lifetime < $now;
	}
}
