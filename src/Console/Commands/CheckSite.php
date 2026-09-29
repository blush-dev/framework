<?php

/**
 * Site check command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Core\AppConfig;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SetupChecks;

/**
 * Runs every setup check (D-218) and says what to fix. Fails when any
 * check fails, so deploy scripts can run it too.
 *
 * It runs under the command line's PHP, which may not be the web
 * server's: a different version, extensions, `php.ini`, or user. So it
 * leaves out opcache, which only the web server's settings decide.
 */
#[Command('doctor', 'Check that the site is set up to run.')]
final readonly class CheckSite
{
	public function __construct(
		private SetupChecks $checks,
		private AppConfig $app
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$results = $this->checks->all($this->app);

		foreach ($results as $result) {
			$output->line(sprintf(
				'%s %s: %s',
				$output->style(str_pad($result->status->value, 7), match ($result->status) {
					CheckStatus::Pass    => Style::Green,
					CheckStatus::Warning => Style::Yellow,
					CheckStatus::Failure => Style::Red
				}),
				$result->label,
				$result->message
			));

			if ($result->hint !== '') {
				$output->line('        ' . $output->style($result->hint, Style::Dim));
			}
		}

		$failures = count(SetupChecks::failures($results));
		$warnings = count(array_filter($results, static fn (CheckResult $result): bool => $result->status === CheckStatus::Warning));
		$summary  = sprintf('%d check(s): %d failure(s), %d warning(s).', count($results), $failures, $warnings);

		if ($failures > 0) {
			$output->error($summary);

			return ExitCode::Failure;
		}

		$output->success($summary);

		return ExitCode::Success;
	}
}
