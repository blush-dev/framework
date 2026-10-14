<?php

/**
 * Admin language middleware.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Admin\AdminLanguages;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Session\Session;
use Blush\Translation\CurrentLocale;

/**
 * Answers the admin API in the signed-in account's language (D-831), so
 * the text PHP sends the admin (labels, errors, toasts) arrives
 * translated: the request runs in that language (`CurrentLocale`), and
 * in the site's when no one is signed in. Runs after `StartSession`.
 */
final readonly class AdminLanguage implements MiddlewareInterface
{
	public function __construct(
		private Authenticator $authenticator,
		private AdminLanguages $languages,
		private CurrentLocale $locale
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$session = $request->getAttribute(Session::class);

		try {
			$account = $session instanceof Session ? $this->authenticator->account($session) : null;
		} catch (AuthException) {
			$account = null;
		}

		return $this->locale->in($this->languages->for($account?->preferences), static fn (): ResponseInterface => $handler->handle($request));
	}
}
