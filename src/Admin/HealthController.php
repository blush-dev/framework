<?php

/**
 * Admin content health controller.
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
use Blush\Content\Lint\Linter;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `GET {path}/api/health`: the content's problems, as
 * `content:lint` finds them (D-225). Errors and warnings by default;
 * `?strict=1` adds notices (undeclared keys, 1.x names, virtual terms).
 * It reads every file, so it runs when asked, not on the dashboard.
 *
 * It lists every file's problems, so it needs `content.edit.others`.
 */
final readonly class HealthController
{
	public function __construct(
		private Linter $linter,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::ContentEditOthers)) {
			return Response::json(['error' => 'You aren\'t allowed to see content health.'], Status::Forbidden, ['Cache-Control' => 'no-store']);
		}

		$strict = in_array($request->getQueryParams()['strict'] ?? '', ['1', 'true'], true);
		$report = $this->linter->lint();
		$files  = [];

		foreach ($report->violations($strict ? Severity::Notice : Severity::Warning) as $path => $violations) {
			$files[] = [
				'path'       => (string) $path,
				'violations' => array_map(static fn (Violation $violation): array => [
					'field'    => $violation->field,
					'message'  => $violation->message,
					'severity' => $violation->severity->value
				], $violations)
			];
		}

		return Response::json([
			'checked' => $report->checked,
			'strict'  => $strict,
			'counts'  => [
				'error'   => $report->count(Severity::Error),
				'warning' => $report->count(Severity::Warning),
				'notice'  => $strict ? $report->count(Severity::Notice) : null
			],
			'files'   => $files
		], headers: ['Cache-Control' => 'no-store']);
	}
}
