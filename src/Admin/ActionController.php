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
use Blush\Job\JobException;
use Blush\Job\JobQueue;

/**
 * The Tools screen's actions (D-222, D-540). `GET {path}/api/actions`
 * lists the ones the account may run, grouped by where they come from
 * (`Provenance::ofClass()`): Blush's first, then the site's, then each
 * plugin's. `POST {path}/api/actions/{action}` runs one: an unknown
 * action is a 404 and one the account may not run a 403; otherwise the
 * answer is the action's result, whether it worked or not. An action that
 * names a job (D-621) is queued instead, once however many ask, and the
 * answer is its `job`'s id, which the admin runs and follows
 * (`JobController`) to its result.
 */
final readonly class ActionController
{
	public function __construct(
		private AdminActions $actions,
		private Permissions $permissions,
		private Provenance $provenance,
		private JobQueue $queue
	) {}

	/**
	 * Lists the actions the account may run, in groups by source.
	 */
	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		$groups = [];

		foreach ($this->actions->allowed($account) as $name => $action) {
			$source = $this->provenance->ofClass($this->actions->classOf($name) ?? $action::class);
			$key    = "{$source['kind']}:{$source['label']}";

			$groups[$key] ??= ['source' => $source, 'actions' => []];
			$groups[$key]['actions'][] = [
				'name'        => $name,
				'label'       => $action->label(),
				'description' => $action->description(),
				'confirm'     => $action->confirm()
			];
		}

		$order = ['core' => 0, 'site' => 1, 'plugin' => 2, 'other' => 3];

		uasort($groups, static fn (array $a, array $b): int => [$order[$a['source']['kind']] ?? 3, $a['source']['label']] <=> [$order[$b['source']['kind']] ?? 3, $b['source']['label']]);

		return self::json(['groups' => array_values($groups)]);
	}

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

		$job = $instance->job();

		if ($job === null) {
			return self::json($instance->run()->toArray());
		}

		try {
			return self::json(['job' => $this->queue->push($job, account: $account->id, unique: "action:{$job}")->id]);
		} catch (JobException $e) {
			return self::json(['successful' => false, 'message' => $e->getMessage(), 'details' => []]);
		}
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
