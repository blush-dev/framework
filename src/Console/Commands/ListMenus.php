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
use Blush\Field\Severity;
use Blush\Menu\MenuException;
use Blush\Menu\Menus;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Lists a theme's menu locations (the active theme's by default) and the
 * site menus (D-199): each location's label, the site menu assigned to
 * it (D-676) or the theme's default, how many top-level items resolve,
 * and where the menu is kept. Site menus no location shows are listed
 * after, and problems (items that don't resolve, keys that don't fit,
 * assignments to menus the site doesn't have) at the end.
 */
#[Command('menu:list', 'List the menu locations and site menus.')]
final readonly class ListMenus
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private Menus $menus
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('The theme to list for; defaults to the active theme.')] ?string $theme = null
	): ExitCode {
		try {
			$chain       = $this->themes->chain($theme ?? $this->config->active);
			$locations   = $this->menus->locations($chain);
			$menus       = $this->menus->menus();
			$assignments = $this->menus->assignments($chain);
			$rows        = [];
			$shown       = [];

			foreach ($locations as $name => $location) {
				$menuName = $assignments[$name] ?? null;
				$record   = $menuName === null ? null : $menus[$menuName] ?? null;
				$default  = $menuName === null && $location->items !== [];
				$menu     = $record !== null || $default ? $this->menus->forLocation($chain, $name) : null;
				$rows[]   = [
					$name,
					$location->label,
					match (true) {
						$menuName !== null => $record === null ? "{$menuName} (missing)" : $menuName,
						$default           => '(theme default)',
						default            => '(none)'
					},
					$menu === null && $record === null && ! $default ? '' : (string) count($menu->items ?? []),
					$record->location ?? ($default ? 'theme.json' : '')
				];

				if ($menuName !== null) {
					$shown[$menuName] = true;
				}
			}

			foreach ($menus as $name => $menu) {
				if (! isset($shown[$name])) {
					$rows[] = ['(none)', '', $name, (string) count($menu->items), $menu->location];
				}
			}
		} catch (ThemeException | MenuException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		if ($rows === []) {
			$output->line(sprintf('The "%s" theme declares no menu locations, and the site has no menus.', $chain->active()->name));

			return ExitCode::Success;
		}

		$output->table(['Location', 'Label', 'Menu', 'Items', 'Kept in'], $rows);

		foreach ($this->menus->check($chain) as $problem) {
			match ($problem->severity) {
				Severity::Notice => $output->comment((string) $problem),
				default          => $output->warning((string) $problem)
			};
		}

		return ExitCode::Success;
	}
}
