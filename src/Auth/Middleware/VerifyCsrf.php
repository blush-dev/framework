<?php

/**
 * CSRF middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Auth\Authenticator;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Session\Session;

/**
 * Refuses cross-site requests that change things (anything but `GET`,
 * `HEAD`, and `OPTIONS`), in layers:
 *
 * 1. A browser's `Sec-Fetch-Site` must be `same-origin` (or `none`, typed
 *    by the user).
 * 2. A browser's `Origin` must be the site's (`AppConfig::$url`) or the
 *    request's own.
 * 3. Once signed in, the request must carry the session's token in the
 *    `X-CSRF-Token` header. Signing in has no token yet, so the first two
 *    layers, and the `SameSite=Strict` session cookie, guard it.
 *
 * Runs after `StartSession`. A refusal is a 403 with a JSON error.
 */
final readonly class VerifyCsrf implements MiddlewareInterface
{
	/**
	 * The header that carries the token.
	 */
	public const string HEADER = 'X-CSRF-Token';

	public function __construct(
		private Authenticator $authenticator,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
			return $handler->handle($request);
		}

		$site = $request->getHeaderLine('Sec-Fetch-Site');

		if ($site !== '' && ! in_array($site, ['same-origin', 'none'], true)) {
			return self::refuse('Cross-site requests aren\'t allowed.');
		}

		$origin = $request->getHeaderLine('Origin');
		$uri    = $request->getUri();

		if ($origin !== '' && ! in_array(rtrim($origin, '/'), [$this->app->origin(), $uri->getScheme() . '://' . $uri->getAuthority()], true)) {
			return self::refuse('Cross-site requests aren\'t allowed.');
		}

		$session = $request->getAttribute(Session::class);
		$token   = $session instanceof Session ? $this->authenticator->csrfToken($session) : null;

		if ($token !== null && ! hash_equals($token, $request->getHeaderLine(self::HEADER))) {
			return self::refuse('The request\'s CSRF token is missing or wrong.');
		}

		return $handler->handle($request);
	}

	/**
	 * Builds a refusal.
	 */
	private static function refuse(string $message): ResponseInterface
	{
		return Response::json(['error' => $message], Status::Forbidden, ['Cache-Control' => 'no-store']);
	}
}
