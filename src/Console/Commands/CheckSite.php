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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
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

	/**
	 * Each area's heading.
	 */
	private const array AREAS = [
		'system'     => 'System',
		'extensions' => 'Extensions',
		'accounts'   => 'Accounts'
	];

	public function __invoke(Output $output): ExitCode
	{
		$areas = array_map(
			static fn (array $results): array => array_map(
				static fn (CheckResult $result): StatusLine => new StatusLine(self::outcome($result->status), $result->label, $result->message, $result->hint),
				array_values($results)
			),
			$this->checks->byArea()
		);
		$lines = array_merge(...array_values($areas));
		$align = max(0, ...array_map(static fn (StatusLine $line): int => mb_strwidth($line->label), $lines));

		foreach (array_filter($areas) as $area => $results) {
			$output->section(self::AREAS[$area]);
			$output->status($results, $align);
		}

		$outcomes = array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines);
		$failures = count(array_filter($outcomes, static fn (Outcome $outcome): bool => $outcome === Outcome::Failure));
		$warnings = count(array_filter($outcomes, static fn (Outcome $outcome): bool => $outcome === Outcome::Warning));

		$output->summary(Outcome::worst(...$outcomes), Output::count(count($outcomes), 'check', 'checks'), ...Output::problems($failures, $warnings));

		return $failures > 0 ? ExitCode::Failure : ExitCode::Success;
	}

	/**
	 * Returns the outcome of a check's status.
	 */
	private static function outcome(CheckStatus $status): Outcome
	{
		return match ($status) {
			CheckStatus::Pass    => Outcome::Pass,
			CheckStatus::Warning => Outcome::Warning,
			CheckStatus::Failure => Outcome::Failure
		};
	}
}
