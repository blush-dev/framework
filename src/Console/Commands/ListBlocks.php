<?php

/**
 * List blocks command.
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
use Blush\Block\Variant;
use Blush\View\ViewFactory;

/**
 * Lists the blocks (D-532), with what a theme gives them (the active
 * theme by default): each full name, its label, its class, its variants
 * besides Default (D-266), and the file that renders it.
 * `theme:why blocks/{file}` shows what that file shadows. Templates
 * in `blocks/` that aren't for a block are reported.
 */
#[Command('block:list', 'List the blocks, and the templates a theme gives them.')]
final readonly class ListBlocks
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
			$views = $this->views->forChain($this->themes->chain($theme ?? $this->config->active));
		} catch (ThemeException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		$blocks = $views->blocks();
		$rows   = [];

		foreach ($blocks as $block) {
			$file   = $block->file();
			$rows[] = [
				(string) $block->name,
				$block->displayLabel(),
				$block->className(),
				implode(', ', array_map(static fn (Variant $variant): string => $variant->name, $block->variants)),
				match (true) {
					$file !== null          => $this->paths->relative($file),
					$block->rendersItself() => '(its own)',
					default                 => '(none)'
				}
			];
		}

		$output->table(['Name', 'Label', 'Class', 'Variants', 'Template'], $rows);

		foreach ($blocks as $block) {
			if ($block->isMissingTemplate()) {
				$output->warning(sprintf('"%s" has no %s template, so it can\'t render.', $block->name, implode(' or ', $block->name->views())));
			}
		}

		foreach ($views->strayBlockFiles() as $file) {
			$output->warning(sprintf('%s isn\'t for a registered block, so nothing renders it.', $this->paths->relative($file)));
		}

		return ExitCode::Success;
	}
}
