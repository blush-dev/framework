<?php

/**
 * Authentication middleware.
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
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\Authenticator;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Session\Session;

/**
 * Lets only signed-in requests through, with their account as the
 * `Account::class` attribute; anything else gets a 401 with a JSON error.
 * Runs after `StartSession`.
 */
final readonly class Authenticate implements MiddlewareInterface
{
	public function __construct(private Authenticator $authenticator)
	{}

	/**
	 * @inheritDoc
	 * @throws AuthException When the account's record is damaged.
	 */
	#[Override]
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$session = $request->getAttribute(Session::class);
		$account = $session instanceof Session ? $this->authenticator->account($session) : null;

		return $account === null
			? Response::json(['error' => 'Sign in first.'], Status::Unauthorized, ['Cache-Control' => 'no-store'])
			: $handler->handle($request->withAttribute(Account::class, $account));
	}
}
