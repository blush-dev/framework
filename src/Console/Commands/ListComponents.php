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
use Blush\Component\ComponentListing;
use Blush\View\ViewFactory;

/**
 * Lists the components a theme can render (D-532; the active theme by
 * default): each full name, its class, and the file that renders it.
 * `theme:why components/{file}` shows what that file shadows. Templates
 * in `components/` that aren't named for a component are reported.
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
			$chain = $this->themes->chain($theme ?? $this->config->active);
			$views = $this->views->forChain($chain);
		} catch (ThemeException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		// Another theme's components (its provider registers them when
		// it's active) can't render in this chain.
		$components = array_values(array_filter(
			$views->components(),
			fn (ComponentListing $component): bool => ! $this->themes->isOutside($component->name->namespace, $chain)
		));

		$rows = [];

		foreach ($components as $component) {
			$file   = $component->file();
			$rows[] = [
				(string) $component->name,
				$component->class ?? '',
				match (true) {
					$file !== null              => $this->paths->relative($file),
					$component->rendersItself() => '(its own)',
					default                     => '(none)'
				}
			];
		}

		$output->table(['Name', 'Class', 'Template'], $rows);

		foreach ($components as $component) {
			if ($component->isMissingTemplate()) {
				$output->warning(sprintf('"%s" has no %s template, so it can\'t render.', $component->name, $component->name->view()));
			}
		}

		foreach ($views->strayComponentFiles() as $file) {
			$output->warning(sprintf('%s isn\'t named for a component, so nothing renders it. Name it {namespace}-%s.', $this->paths->relative($file), basename($file)));
		}

		return ExitCode::Success;
	}
}
