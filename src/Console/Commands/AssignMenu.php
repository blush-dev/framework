<?php

/**
 * Assign menu command.
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
use Blush\Menu\MenuException;
use Blush\Menu\Menus;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Assigns a site menu to one of a theme's menu locations (the active
 * theme's by default), or with `--clear` takes it away, so the location
 * shows the theme's default (D-676). The assignment is kept in the
 * theme's own group of settings, so each theme keeps its own.
 */
#[Command('menu:assign', 'Assign a menu to a theme location, or clear it.')]
final readonly class AssignMenu
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
		#[Argument('The menu to show there, by name.')] string $menu = '',
		#[Option('Take the location\'s menu away, so it shows the theme\'s default.')] bool $clear = false,
		#[Option('The theme to assign for; defaults to the active theme.')] ?string $theme = null
	): ExitCode {
		if (($menu === '') === ! $clear) {
			throw new InvalidInput('Name a menu to assign, or use --clear to take the location\'s away.');
		}

		try {
			$chain = $this->themes->chain($theme ?? $this->config->active);
			$name  = $chain->active()->name;

			if (! isset($this->menus->locations($chain)[$location])) {
				throw new InvalidInput(sprintf('The "%s" theme has no menu location "%s".', $name, $location));
			}

			if ($menu !== '' && ! isset($this->menus->menus()[$menu])) {
				throw new InvalidInput(sprintf('The site has no menu "%s".', $menu));
			}

			$this->menus->assign($chain, $location, $clear ? null : $menu);
		} catch (ThemeException | MenuException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		$output->success($clear
			? sprintf('The "%s" location of the "%s" theme shows its default.', $location, $name)
			: sprintf('The "%s" location of the "%s" theme shows the "%s" menu.', $location, $name, $menu));

		return ExitCode::Success;
	}
}
