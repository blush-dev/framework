<?php

/**
 * List components command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Core\Paths;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use Blush\View\ViewFactory;

/**
 * Lists the components a theme can render (the active theme by default):
 * each key, whether it's a core content component, its class, and the
 * file that renders it. `theme:why components/{key}` shows what that
 * file shadows.
 */
#[Command('component:list', 'List the components a theme can render.')]
final readonly class ListComponents
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private ViewFactory $views,
		private Paths $paths
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('The theme to list for; defaults to the active theme.')] ?string $theme = null
	): ExitCode {
		try {
			$components = $this->views->forChain($this->themes->chain($theme ?? $this->config->active))->components();
		} catch (ThemeException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		$rows = [];

		foreach ($components as $component) {
			$file   = $component->file();
			$rows[] = [
				$component->key,
				$component->isCore ? 'yes' : '',
				$component->class ?? '',
				$file === null ? '(none)' : $this->paths->relative($file)
			];
		}

		$output->table(['Key', 'Core', 'Class', 'Template'], $rows);

		foreach ($components as $component) {
			if ($component->isMissingTemplate()) {
				$output->warning(sprintf('"%s" has no components/%s.php template, so it can\'t render.', $component->key, $component->key));
			}
		}

		return ExitCode::Success;
	}
}
