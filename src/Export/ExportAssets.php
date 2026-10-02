<?php

/**
 * Export assets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Blush\Core\Paths;
use Blush\Media\MediaConfig;
use Blush\Media\MediaResolver;
use Blush\Support\Filesystem;
use Blush\Support\UrlPath;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Copies the files a static site serves as they are (D-137), at the URLs
 * the live site serves them from:
 *
 * - `public()`: the files in `public/`, except PHP, dotfiles, symlinks,
 *   and the theme and media folders (published copies, which the next
 *   step copies from their source). They're copied first, so they win
 *   over rendered URLs, as a real file does on the live site.
 * - `themes()`: the active chain's servable theme files, as
 *   `theme:publish` would publish them, at `/themes/{vendor}/{name}/…`.
 * - `media()`: the allowed files in `user/media` at the media URL,
 *   resolved by `MediaResolver`, so exactly what the media route would
 *   serve.
 */
final readonly class ExportAssets
{
	public function __construct(
		private Paths $paths,
		private MediaConfig $media,
		private MediaResolver $resolver,
		private Themes $themes,
		private ThemeConfig $theme,
		private Filesystem $filesystem
	) {}

	/**
	 * Copies the public folder's files. Returns how many were copied.
	 *
	 * @throws ExportException
	 */
	public function public(ExportWriter $writer): int
	{
		$themes = ltrim(ThemeChain::ASSET_URL, '/') . '/';
		$media  = ltrim($this->media->url, '/') . '/';
		$count  = 0;

		foreach ($this->filesystem->files($this->paths->public, links: false) as $relative => $file) {
			if (str_starts_with($relative, $themes) || str_starts_with($relative, $media) || preg_match('/^(php\d*|phtml|phar|phps)$/i', $file->getExtension()) === 1) {
				continue;
			}

			$count += (int) $writer->copy($relative, $file->getPathname());
		}

		return $count;
	}

	/**
	 * Copies the active theme chain's servable files. Returns how many
	 * were copied.
	 *
	 * @throws ExportException
	 */
	public function themes(ExportWriter $writer): int
	{
		try {
			$chain = $this->themes->chain($this->theme->active);
		} catch (ThemeException $error) {
			throw new ExportException($error->getMessage(), 0, $error);
		}

		$count = 0;

		foreach ($chain->themes as $theme) {
			foreach ($this->filesystem->files($theme->path) as $relative => $file) {
				if (ThemeChain::isServable($relative)) {
					$count += (int) $writer->copy(ltrim(ThemeChain::ASSET_URL, '/') . "/{$theme->name}/{$relative}", $file->getPathname());
				}
			}
		}

		return $count;
	}

	/**
	 * Copies the media files. Returns how many were copied.
	 *
	 * @throws ExportException
	 */
	public function media(ExportWriter $writer): int
	{
		$count = 0;

		foreach ($this->filesystem->files($this->paths->media) as $relative => $file) {
			$count += (int) $this->copyMedia($writer, $this->media->url . '/' . UrlPath::encode($relative));
		}

		return $count;
	}

	/**
	 * Copies a media reference's file to its URL, if the media route
	 * would serve it.
	 *
	 * @throws ExportException
	 */
	private function copyMedia(ExportWriter $writer, string $reference): bool
	{
		$file = $this->resolver->resolve($reference);

		return $file !== null && $writer->copy(ltrim(rawurldecode($file->url), '/'), $file->path);
	}
}
