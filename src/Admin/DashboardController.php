<?php

/**
 * Admin dashboard controller.
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
use Blush\Cache\ContentVersion;
use Blush\Content\ContentRepository;
use Blush\Content\Status;
use Blush\Core\AppConfig;
use Blush\Http\Response;

/**
 * Answers `GET {path}/api/dashboard`: the site, counts of its entries by
 * status, and the actions the account may run, each described so the
 * admin can draw its button (D-222).
 */
final readonly class DashboardController
{
	public function __construct(
		private AppConfig $site,
		private ContentRepository $content,
		private ContentVersion $version,
		private AdminActions $actions,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);
		$actions = [];

		foreach ($this->actions->all() as $name => $action) {
			if ($account instanceof Account && $this->permissions->can($account, $action->capability())) {
				$actions[] = [
					'name'        => $name,
					'label'       => $action->label(),
					'description' => $action->description(),
					'confirm'     => $action->confirm()
				];
			}
		}

		$counts = [];

		// The trash isn't content anyone reads or writes (D-484).
		foreach (Status::active() as $status) {
			$counts[$status->value] = $this->content->query()->any()->status($status)->count();
		}

		return Response::json([
			'site'    => [
				'name'        => $this->site->name,
				'url'         => $this->site->url,
				'environment' => $this->site->environment->value,
				'version'     => $this->version->current()
			],
			'content' => ['total' => array_sum($counts), ...$counts],
			'actions' => $actions
		], headers: ['Cache-Control' => 'no-store']);
	}
}
