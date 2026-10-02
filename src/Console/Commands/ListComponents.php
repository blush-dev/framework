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
use Blush\Component\Variant;
use Blush\View\ViewFactory;

/**
 * Lists the components a theme can render (the active theme by default):
 * each full name, its label, whether it's registered (and so offered in
 * the admin's inserter), its class, its variants besides Default (D-266),
 * and the file that renders it.
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
				$component->displayLabel(),
				$component->isRegistered() ? 'yes' : '',
				$component->className() ?? '',
				implode(', ', array_map(static fn (Variant $variant): string => $variant->name, $component->variants)),
				match (true) {
					$file !== null              => $this->paths->relative($file),
					$component->rendersItself() => '(its own)',
					default                     => '(none)'
				}
			];
		}

		$output->table(['Name', 'Label', 'Registered', 'Class', 'Variants', 'Template'], $rows);

		foreach ($components as $component) {
			if ($component->isMissingTemplate()) {
				$output->warning(sprintf('"%s" has no %s.php template, so it can\'t render.', $component->name, implode('.php or ', $component->name->views())));
			}
		}

		foreach ($views->strayComponentFiles() as $file) {
			$output->warning(sprintf('%s isn\'t named for a component, so nothing renders it. Name it {namespace}-%s.php.', $this->paths->relative($file), basename($file, '.php')));
		}

		return ExitCode::Success;
	}
}
