<?php

/**
 * Admin site health controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Site Health's report (`SiteHealth`, D-543, D-545), with `site.health`:
 *
 * - `GET {path}/api/health/site`: the last report, checking first only
 *   when there's none.
 * - `POST {path}/api/health/site`: checks again, and answers the new
 *   report.
 */
final readonly class SiteHealthController
{
	public function __construct(
		private SiteHealth $health,
		private Permissions $permissions
	) {}

	/**
	 * Answers the last report, or a new one when there's none.
	 */
	public function show(ServerRequestInterface $request): ResponseInterface
	{
		return $this->refusal($request) ?? self::json($this->health->saved() ?? $this->health->run($request->getServerParams()));
	}

	/**
	 * Checks again.
	 */
	public function run(ServerRequestInterface $request): ResponseInterface
	{
		return $this->refusal($request) ?? self::json($this->health->run($request->getServerParams()));
	}

	/**
	 * Returns a refusal when the account may not see site health, or
	 * `null`.
	 */
	private function refusal(ServerRequestInterface $request): ?ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteHealth)
			? null
			: self::json(['error' => 'You aren\'t allowed to see site health.'], Status::Forbidden);
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
