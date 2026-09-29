<?php

/**
 * Admin action controller.
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
use Blush\Admin\Action\AdminActions;
use Blush\Auth\Account;
use Blush\Auth\Permissions;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Runs an admin action: `POST {path}/api/actions/{action}`. An unknown
 * action is a 404 and one the account may not run a 403; otherwise the
 * answer is the action's result, whether it worked or not.
 */
final readonly class ActionController
{
	public function __construct(
		private AdminActions $actions,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request, string $action): ResponseInterface
	{
		$account  = $request->getAttribute(Account::class);
		$instance = $this->actions->get($action);

		if ($instance === null) {
			return self::json(['error' => sprintf('There\'s no "%s" action.', $action)], Status::NotFound);
		}

		if (! $account instanceof Account || ! $this->permissions->can($account, $instance->capability())) {
			return self::json(['error' => 'You aren\'t allowed to do that.'], Status::Forbidden);
		}

		return self::json($instance->run()->toArray());
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
