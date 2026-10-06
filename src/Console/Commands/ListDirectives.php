<?php

/**
 * List directives command.
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
use Blush\Directive\Variant;
use Blush\View\ViewFactory;

/**
 * Lists the directives (D-532), with what a theme gives them (the active
 * theme by default): each full name, its label, its class, its variants
 * besides Default (D-266), and the file that renders it.
 * `theme:why directives/{file}` shows what that file shadows. Templates
 * in `directives/` that aren't for a directive are reported.
 */
#[Command('directive:list', 'List the directives, and the templates a theme gives them.')]
final readonly class ListDirectives
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

		$directives = $views->directives();
		$rows       = [];

		foreach ($directives as $directive) {
			$file   = $directive->file();
			$rows[] = [
				(string) $directive->name,
				$directive->displayLabel(),
				$directive->className(),
				implode(', ', array_map(static fn (Variant $variant): string => $variant->name, $directive->variants)),
				match (true) {
					$file !== null              => $this->paths->relative($file),
					$directive->rendersItself() => '(its own)',
					default                     => '(none)'
				}
			];
		}

		$output->table(['Name', 'Label', 'Class', 'Variants', 'Template'], $rows);

		foreach ($directives as $directive) {
			if ($directive->isMissingTemplate()) {
				$output->warning(sprintf('"%s" has no %s template, so it can\'t render.', $directive->name, implode(' or ', $directive->name->views())));
			}
		}

		foreach ($views->strayDirectiveFiles() as $file) {
			$output->warning(sprintf('%s isn\'t for a registered directive, so nothing renders it.', $this->paths->relative($file)));
		}

		return ExitCode::Success;
	}
}
