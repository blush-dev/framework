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
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SetupChecks;
use Blush\Setup\SiteChecks;

/**
 * Runs every setup check (D-218) and says what to fix: the same checks
 * Site Health shows in the admin (`SiteChecks`, D-543). Fails when any
 * check fails, so deploy scripts can run it too. It also warns of
 * extensions that are on but can't run, since their requirements aren't
 * met (D-431): an active theme whose chain falls back to the default
 * theme, and plugins and icon packs that are on but don't run. And it
 * warns of a site with accounts but no owner (D-500).
 *
 * It runs under the command line's PHP, which may not be the web
 * server's: a different version, extensions, `php.ini`, or user. So it
 * leaves out opcache, which only the web server's settings decide.
 */
#[Command('doctor', 'Check that the site is set up to run.')]
final readonly class CheckSite
{
	public function __construct(
		private SiteChecks $checks
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$results = $this->checks->all();

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
