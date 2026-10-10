<?php

/**
 * Theme check command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Field\Severity;
use Blush\Theme\ThemeChecker;
use Blush\Theme\ThemeConfig;

/**
 * Checks a theme (the active one by default) with `ThemeChecker`: its
 * chain and manifests, settings, and the base layout's accessibility landmarks (D-030). Errors fail the command.
 */
#[Command('theme:check', 'Check a theme\'s manifest, settings, and layout.')]
final readonly class CheckTheme
{
	public function __construct(
		private ThemeChecker $checker,
		private ThemeConfig $config
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The theme\'s name (vendor/name); defaults to the active theme.')] ?string $name = null,
		#[Option('Also show notices.')] bool $strict = false
	): ExitCode {
		$report = $this->checker->check($name ?? $this->config->active);

		$lines = [];

		foreach ($report->violations as $violation) {
			if ($violation->severity !== Severity::Notice || $strict) {
				$lines[] = new StatusLine(Outcome::of($violation->severity), $violation->field, $violation->message);
			}
		}

		$output->status($lines);
		$output->summary(
			$report->hasErrors() ? Outcome::Failure : Outcome::worst(...array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines)),
			sprintf('Checked the "%s" theme', $report->theme),
			...Output::problems(count($report->with(Severity::Error)), count($report->with(Severity::Warning)), $strict ? count($report->with(Severity::Notice)) : null)
		);

		return $report->hasErrors() ? ExitCode::Failure : ExitCode::Success;
	}
}
