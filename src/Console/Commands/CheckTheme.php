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
use Blush\Console\Output;
use Blush\Console\Style;
use Blush\Content\Schema\Severity;
use Blush\Theme\ThemeChecker;
use Blush\Theme\ThemeConfig;

/**
 * Checks a theme (the active one by default) with `ThemeChecker`: its
 * chain and manifests, settings, tokens and palette contrast, and the
 * base layout's accessibility landmarks (D-030). Errors fail the command.
 */
#[Command('theme:check', 'Check a theme\'s manifest, tokens, contrast, and layout.')]
final readonly class CheckTheme
{
	public function __construct(
		private ThemeChecker $checker,
		private ThemeConfig $config
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The theme\'s slug; defaults to the active theme.')] ?string $slug = null,
		#[Option('Also show notices.')] bool $strict = false
	): ExitCode {
		$report = $this->checker->check($slug ?? $this->config->active);

		foreach ($report->violations as $violation) {
			if ($violation->severity === Severity::Notice && ! $strict) {
				continue;
			}

			$output->line(sprintf(
				'%s %s: %s',
				$output->style(str_pad($violation->severity->value, 7), match ($violation->severity) {
					Severity::Error   => Style::Red,
					Severity::Warning => Style::Yellow,
					Severity::Notice  => Style::Dim
				}),
				$violation->field,
				$violation->message
			));
		}

		$summary = sprintf(
			'Checked the "%s" theme: %d error(s), %d warning(s)%s.',
			$report->theme,
			count($report->with(Severity::Error)),
			count($report->with(Severity::Warning)),
			$strict ? sprintf(', %d notice(s)', count($report->with(Severity::Notice))) : ''
		);

		if ($report->hasErrors()) {
			$output->error($summary);

			return ExitCode::Failure;
		}

		$output->success($summary);

		return ExitCode::Success;
	}
}
