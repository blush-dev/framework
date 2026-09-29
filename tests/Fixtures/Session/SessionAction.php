<?php

/**
 * Session test handler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Session;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Blush\Http\Response;
use Blush\Session\Session;

/**
 * Does one thing to the request's session (`count` adds one to a counter;
 * `read`, `regenerate`, and `invalidate` do what they say) and answers
 * with the counter.
 */
final readonly class SessionAction implements RequestHandlerInterface
{
	public function __construct(private string $action = 'read')
	{}

	#[Override]
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$session = $request->getAttribute(Session::class);
		assert($session instanceof Session);

		$count = $session->get('count', 0);
		$count = is_int($count) ? $count : 0;

		match ($this->action) {
			'count'      => $session->set('count', ++$count),
			'regenerate' => $session->regenerate(),
			'invalidate' => $session->invalidate(),
			default      => null
		};

		return Response::text((string) $count);
	}
}
