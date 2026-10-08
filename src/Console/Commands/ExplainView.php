<?php

/**
 * View explanation command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Core\Paths;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use Blush\View\ViewException;
use Blush\View\ViewFactory;

/**
 * Shows which file wins for a view name (`single-post`, `layouts/base`,
 * `directives/callout`) and which it shadows, down the theme chain, so
 * layering is easy to debug.
 */
#[Command('theme:why', 'Show which file a view name resolves to.')]
final readonly class ExplainView
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
		#[Argument('The view name, such as single-post or layouts/base.')] string $view,
		#[Option('The theme to look through; defaults to the active theme.')] ?string $theme = null
	): ExitCode {
		try {
			$chain = $this->themes->chain($theme ?? $this->config->active);
			$files = $this->views->forChain($chain)->finder->all($view);
		} catch (ThemeException | ViewException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		if ($files === []) {
			$output->warning(sprintf('No file provides "%s". Searched:', $view));

			foreach ($this->views->directories($chain) as $directory) {
				$output->line('  ' . $this->paths->relative("{$directory}/{$view}.php"));
			}

			return ExitCode::Failure;
		}

		foreach ($files as $index => $file) {
			$output->line(sprintf('%s %s', $index === 0 ? 'uses   ' : 'shadows', $this->paths->relative($file)));
		}

		return ExitCode::Success;
	}
}
