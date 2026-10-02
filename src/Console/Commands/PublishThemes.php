<?php

/**
 * Theme publishing command.
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
use Blush\Console\Verbosity;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;

/**
 * Copies theme assets to `public/themes/{vendor}/{name}` (D-034, D-378) so the web server
 * serves them without starting PHP. Only servable files are copied (the
 * extensions `ThemeChain::ASSET_TYPES` allows, outside `views/`, `src/`,
 * and the other private folders), never PHP or manifests, which is why
 * themes are copied rather than linked. Changed files are copied again,
 * and published files whose source is gone are removed.
 *
 * It publishes the active theme's chain, or every installed theme with
 * `--all`. Until a theme is published, the `theme.asset` route streams
 * its assets.
 */
#[Command('theme:publish', 'Copy theme assets into the public folder.')]
final readonly class PublishThemes
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('Publish every installed theme, not just the active chain.')] bool $all = false
	): ExitCode {
		try {
			$themes = $all ? array_values($this->themes->all()) : $this->themes->chain($this->config->active)->themes;
		} catch (ThemeException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		foreach ($themes as $theme) {
			[$copied, $current, $removed] = $this->publish($output, $theme);

			$output->line(sprintf('%s: copied %d, %d already current, removed %d.', $theme->name, $copied, $current, $removed));
		}

		$output->success(sprintf('Published %d theme(s) to %s.', count($themes), $this->paths->relative($this->paths->public . ThemeChain::ASSET_URL)));

		return ExitCode::Success;
	}

	/**
	 * Publishes one theme and returns how many files were copied, already
	 * current, and removed.
	 *
	 * @return array{int, int, int}
	 */
	private function publish(Output $output, ThemeManifest $theme): array
	{
		$target  = $this->paths->public . ThemeChain::ASSET_URL . "/{$theme->name}";
		$copied  = 0;
		$current = 0;
		$kept    = [];

		foreach ($this->filesystem->files($theme->path) as $relative => $file) {
			if (! ThemeChain::isServable($relative)) {
				continue;
			}

			$destination     = "{$target}/{$relative}";
			$kept[$relative] = true;

			if (is_file($destination) && filesize($destination) === $file->getSize() && filemtime($destination) === $file->getMTime()) {
				$current++;
				continue;
			}

			if (! is_dir(dirname($destination))) {
				mkdir(dirname($destination), 0775, true);
			}

			copy($file->getPathname(), $destination);
			touch($destination, (int) $file->getMTime());

			$output->line("Copied {$theme->name}/{$relative}", Verbosity::Verbose);
			$copied++;
		}

		$removed = 0;

		foreach ($this->filesystem->files($target) as $relative => $file) {
			if (! isset($kept[$relative])) {
				unlink($file->getPathname());
				$removed++;
			}
		}

		return [$copied, $current, $removed];
	}
}
