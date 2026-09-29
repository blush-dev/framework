<?php

/**
 * Session middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use Override;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Cookie;

/**
 * Loads the request's session from its cookie, or starts one, and saves
 * it after the response. Only routes that need a session (the admin's)
 * run it, so public pages never set a cookie and stay cacheable.
 *
 * - A session past its idle time or lifetime is dropped, as is a cookie
 *   that doesn't name one; a fresh session takes its place.
 * - A new session is saved only once something is stored in it, so a
 *   visitor who never signs in leaves no file behind.
 * - The cookie is a browser-session cookie (`HttpOnly`, `SameSite=Strict`,
 *   and `Secure` with the `__Host-` prefix over HTTPS). It's set when the
 *   session starts or its id changes, and expired when it ends.
 * - Every response gets `Cache-Control: no-store`.
 * - About one request in a hundred prunes idle sessions; `schedule:run`
 *   also does.
 */
final readonly class StartSession implements MiddlewareInterface
{
	public function __construct(
		private SessionStore $store,
		private SessionConfig $config,
		private ClockInterface $clock
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$now    = $this->clock->now()->getTimestamp();
		$cookie = new Cookie($this->config->cookieName($this->isSecure($request)), secure: $this->isSecure($request));
		$sent   = $request->getCookieParams()[$cookie->name] ?? null;

		$session = (is_string($sent) ? $this->load($sent, $now) : null) ?? Session::start($now);

		$response = $handler->handle($request->withAttribute(Session::class, $session))
			->withHeader('Cache-Control', 'no-store');

		if (random_int(1, 100) === 1) {
			$this->store->prune($now - $this->config->idle);
		}

		if ($session->isInvalidated()) {
			if (! $session->isNew()) {
				$this->store->delete($session->id());
			}

			if ($session->previousId() !== null) {
				$this->store->delete($session->previousId());
			}

			return is_string($sent) ? $response->withAddedHeader('Set-Cookie', $cookie->expired()->header()) : $response;
		}

		if ($session->isNew() && ! $session->isDirty()) {
			// Nothing was stored, so there's nothing to keep. A cookie that
			// named no live session is cleared.
			return is_string($sent) ? $response->withAddedHeader('Set-Cookie', $cookie->expired()->header()) : $response;
		}

		if ($session->previousId() !== null) {
			$this->store->delete($session->previousId());
		}

		$this->store->write($session->id(), ['created' => $session->created, 'lastSeen' => $now, 'data' => $session->all()]);

		return $session->id() === $sent
			? $response
			: $response->withAddedHeader('Set-Cookie', $cookie->withValue($session->id())->header());
	}

	/**
	 * Loads a live session, or returns `null`.
	 */
	private function load(string $id, int $now): ?Session
	{
		if (! Session::isValidId($id)) {
			return null;
		}

		$record = $this->store->read($id);

		if ($record === null) {
			return null;
		}

		if ($record['lastSeen'] + $this->config->idle < $now || $record['created'] + $this->config->lifetime < $now) {
			$this->store->delete($id);

			return null;
		}

		return new Session($id, $record['data'], $record['created']);
	}

	/**
	 * Whether the cookie should be `Secure`.
	 */
	private function isSecure(ServerRequestInterface $request): bool
	{
		return $this->config->secure ?? $request->getUri()->getScheme() === 'https';
	}
}
