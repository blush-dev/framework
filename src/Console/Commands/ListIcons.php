<?php

/**
 * List icons command.
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
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Icon\IconName;
use Blush\Icon\Icons;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use Blush\View\ViewFactory;

/**
 * Lists the icons a theme can show (the active theme by default), with
 * each one's label and the file that draws it (D-187).
 */
#[Command('icon:list', 'List the icons a theme can show.')]
final readonly class ListIcons
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private ViewFactory $views,
		private Icons $icons,
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

		$rows = [];

		foreach ($this->icons->all($chain) as $key => $file) {
			$name   = IconName::parse($key) ?? new IconName('', $key);
			$rows[] = [
				$key,
				$views->iconText($name, 'label') ?? $name->label(),
				str_starts_with($file, Framework::path()) ? '(core) ' . basename($file) : $this->paths->relative($file)
			];
		}

		$output->table(['Name', 'Label', 'File'], $rows);

		return ExitCode::Success;
	}
}
