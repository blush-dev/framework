<?php

/**
 * List menus command.
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
use Blush\Content\Schema\Severity;
use Blush\Core\Paths;
use Blush\Data\InvalidData;
use Blush\Menu\MenuException;
use Blush\Menu\Menus;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Lists a theme's menu locations (the active theme's by default) and the
 * site menus (D-199): each location's label, the site menu that fills it,
 * how many top-level items resolve, and its file. Site menus no location
 * shows are listed after, and problems (items that don't resolve, keys
 * that don't fit) at the end.
 */
#[Command('menu:list', 'List the menu locations and site menus.')]
final readonly class ListMenus
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private Menus $menus,
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
			$chain     = $this->themes->chain($theme ?? $this->config->active);
			$locations = $this->menus->locations($chain);
			$files     = $this->menus->files();
			$rows      = [];
			$shown     = [];

			foreach ($locations as $name => $location) {
				$menuName = $this->menus->menuName($name);
				$file     = $files[$menuName] ?? null;
				$menu     = $file === null ? null : $this->menus->forLocation($chain, $name);
				$rows[]   = [
					$name,
					$location->label,
					$file === null ? '(none)' : $menuName,
					$file === null ? '' : (string) count($menu->items ?? []),
					$file === null ? '' : $this->paths->relative($file->path)
				];

				$shown[$menuName] = true;
			}

			foreach ($files as $name => $file) {
				if (! isset($shown[$name])) {
					$rows[] = ['(none)', '', $name, (string) count($file->items), $this->paths->relative($file->path)];
				}
			}
		} catch (ThemeException | MenuException | InvalidData $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		if ($rows === []) {
			$output->line(sprintf('The "%s" theme declares no menu locations, and the site has no menus.', $chain->active()->slug));

			return ExitCode::Success;
		}

		$output->table(['Location', 'Label', 'Menu', 'Items', 'File'], $rows);

		foreach ($this->menus->check($chain) as $problem) {
			match ($problem->severity) {
				Severity::Notice => $output->comment((string) $problem),
				default          => $output->warning((string) $problem)
			};
		}

		return ExitCode::Success;
	}
}
