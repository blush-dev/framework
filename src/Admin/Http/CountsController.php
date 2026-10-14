<?php

/**
 * Admin counts controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Health\SiteHealth;
use Blush\Admin\ToolButtons;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/counts` (D-371): what the section panel needs
 * counted. Its lists aren't counted since D-800.
 *
 * - `tools`: how many of the Tools screen's buttons the account may use,
 *   so the screen is listed only with something on it (D-540, D-761).
 * - `health`: how many checks in Site Health's last report need a look,
 *   with `site.health`, when there's a report (D-545).
 */
final readonly class CountsController
{
	public function __construct(
		private Permissions $permissions,
		private ToolButtons $tools,
		private SiteHealth $health
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		$counts = ['tools' => count($this->tools->allowed($account))];

		// From Site Health's last report, never a new check (D-545).
		if ($this->permissions->can($account, Capability::SiteHealth) && ($issues = $this->health->issues()) !== null) {
			$counts['health'] = $issues;
		}

		return self::json($counts);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
