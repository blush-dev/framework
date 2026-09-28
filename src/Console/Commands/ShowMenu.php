<?php

/**
 * Show menu command.
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
use Blush\Content\Schema\Violation;
use Blush\Data\InvalidData;
use Blush\Menu\MenuException;
use Blush\Menu\MenuItem;
use Blush\Menu\Menus;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Shows the menu a theme location shows (D-199), resolved as a page
 * would see it: each item's label and URL, nested, with the items that
 * don't resolve reported. `--locale` resolves it for another locale
 * (D-202).
 */
#[Command('menu:show', 'Show the menu a location shows, resolved.')]
final readonly class ShowMenu
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
		#[Argument('The menu location, such as primary.')] string $location,
		#[Option('The theme to resolve for; defaults to the active theme.')] ?string $theme = null,
		#[Option('The locale to resolve text in; defaults to the site\'s.')] string $locale = ''
	): ExitCode {
		try {
			$chain    = $this->themes->chain($theme ?? $this->config->active);
			$menu     = $this->menus->forLocation($chain, $location, $locale);
			$menuName = $this->menus->menuName($location);
			$problems = array_filter(
				$this->menus->check($chain),
				static fn (Violation $problem): bool => $problem->field === "menu {$menuName}"
			);
		} catch (ThemeException | MenuException | InvalidData $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		if ($menu === null) {
			$output->warning(sprintf('The "%s" location shows no menu: the site has no user/data/menus/%s file, or none of its items resolve.', $location, $menuName));
		} else {
			$output->line(sprintf('%s (%s: user/data/menus/%s)', $menu->label !== '' ? $menu->label : $location, $location, $menu->name));
			$this->tree($output, $menu->items, 1);
		}

		foreach ($problems as $problem) {
			$output->warning($problem->message);
		}

		return $menu === null ? ExitCode::Failure : ExitCode::Success;
	}

	/**
	 * Prints items and their children, indented by level.
	 *
	 * @param list<MenuItem> $items
	 */
	private function tree(Output $output, array $items, int $level): void
	{
		foreach ($items as $item) {
			$output->line(rtrim(sprintf('%s%s  %s', str_repeat('  ', $level), $item->label, $item->url ?? '')));
			$this->tree($output, $item->children, $level + 1);
		}
	}
}
