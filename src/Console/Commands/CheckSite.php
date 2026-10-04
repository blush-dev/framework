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
use Blush\Core\Framework;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Setup\CheckResult;
use Blush\Setup\CheckStatus;
use Blush\Setup\SetupChecks;

/**
 * Runs every setup check (D-218) and says what to fix. Fails when any
 * check fails, so deploy scripts can run it too. It also warns of
 * extensions that are on but can't run, since their requirements aren't
 * met (D-431): an active theme whose chain falls back to the default
 * theme, and plugins and icon packs that are on but don't run.
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
		private AppConfig $app,
		private ExtensionState $extensions
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$results = [...$this->checks->all($this->app), ...$this->extensions()];

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

	/**
	 * Warns of the extensions that are on but can't run (D-431), or
	 * passes when everything that's on runs.
	 *
	 * @return list<CheckResult>
	 */
	private function extensions(): array
	{
		$results = [];
		$themes  = $this->extensions->themes->unmet();

		if ($themes !== []) {
			$results[] = CheckResult::warning(
				'Theme',
				sprintf('The "%s" theme can\'t run, so the default theme runs in its place. %s', $this->extensions->theme, Requirements::reason(array_merge(...array_values($themes)))),
				sprintf('Run "%s theme:check" for more.', Framework::BINARY)
			);
		}

		$kinds = [
			['Plugins', 'plugin', array_keys($this->extensions->plugins->unmet()), 'plugin:check'],
			['Icon packs', 'icon pack', array_keys($this->extensions->packs->unmet()), 'icon-pack:check']
		];

		foreach ($kinds as [$label, $kind, $names, $command]) {
			if ($names !== []) {
				$results[] = CheckResult::warning(
					$label,
					sprintf('%s %s %s turned on but can\'t run: %s.', count($names), count($names) === 1 ? $kind : "{$kind}s", count($names) === 1 ? 'is' : 'are', implode(', ', $names)),
					sprintf('Run "%s %s" to see why.', Framework::BINARY, $command)
				);
			}
		}

		return $results === [] ? [CheckResult::pass('Extensions', 'Everything that\'s on runs.')] : $results;
	}
}
