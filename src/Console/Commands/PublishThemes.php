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

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Core\Paths;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;

/**
 * Copies theme assets to `public/themes/{slug}` (D-034) so the web server
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
		private Paths $paths
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

			$output->line(sprintf('%s: copied %d, %d already current, removed %d.', $theme->slug, $copied, $current, $removed));
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
		$target  = $this->paths->public . ThemeChain::ASSET_URL . "/{$theme->slug}";
		$copied  = 0;
		$current = 0;
		$kept    = [];

		foreach (self::files($theme->path) as $relative => $file) {
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

			$output->line("Copied {$theme->slug}/{$relative}", Verbosity::Verbose);
			$copied++;
		}

		$removed = 0;

		foreach (is_dir($target) ? self::files($target) : [] as $relative => $file) {
			if (! isset($kept[$relative])) {
				unlink($file->getPathname());
				$removed++;
			}
		}

		return [$copied, $current, $removed];
	}

	/**
	 * Returns the files under a folder by relative path, skipping dotfiles
	 * and dot-folders.
	 *
	 * @return iterable<string, SplFileInfo>
	 */
	private static function files(string $root): iterable
	{
		$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			static fn (SplFileInfo $file): bool => ! str_starts_with($file->getFilename(), '.')
		));

		foreach ($files as $file) {
			if ($file instanceof SplFileInfo && $file->isFile()) {
				yield substr($file->getPathname(), strlen($root) + 1) => $file;
			}
		}
	}
}
