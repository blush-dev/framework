<?php

/**
 * Theme list command.
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
use Blush\Theme\ThemeConfig;
use Blush\Theme\Themes;

/**
 * Lists the installed themes: the framework default, Composer themes,
 * and `user/themes`, marking the active one and any broken manifests.
 */
#[Command('theme:list', 'List the installed themes.')]
final readonly class ListThemes
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$rows = [];

		foreach ($this->themes->all() as $name => $theme) {
			$rows[] = [
				$name === $this->config->active ? "* {$name}" : "  {$name}",
				$theme->label,
				$theme->namespace,
				$theme->version,
				$theme->parent ?? '',
				$theme->source->value
			];
		}

		$output->table(['Name', 'Label', 'Namespace', 'Version', 'Parent', 'Source'], $rows);

		foreach ($this->themes->invalid() as $where => $message) {
			$output->warning("{$where}: {$message}");
		}

		if (! $this->themes->has($this->config->active)) {
			$output->error(sprintf('The active theme "%s" isn\'t installed.', $this->config->active));

			return ExitCode::Failure;
		}

		return ExitCode::Success;
	}
}
