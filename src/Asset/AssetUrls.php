<?php

/**
 * Asset URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Blush\Core\Framework;
use Blush\Plugin\Plugins;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Turns an asset file and where it's from into a URL (D-569), versioned
 * with `?v=`, a CRC32 of its contents (D-194), as theme assets are:
 *
 * - `blush`: core's own built files, in the framework's `public/site`,
 *   at `/blush/{path}` (`AssetRoutes::CORE_URL`).
 * - A plugin that runs: a file in its folder, at
 *   `/extensions/{vendor}/{name}/{path}` (`AssetRoutes::PLUGIN_URL`).
 * - An installed theme: a file in its folder, or its build manifest's
 *   (`ThemeAssets`), at `/themes/{vendor}/{name}/{path}`.
 * - `''`: the path is already a URL, and is used as given.
 *
 * Only files a theme could serve can be served this way
 * (`ThemeChain::isServable()`): stylesheets, scripts, fonts, and images,
 * never PHP, views, catalogs, or build sources.
 */
final class AssetUrls
{
	/**
	 * The `from` of core's own files.
	 */
	public const string CORE = 'blush';

	/**
	 * Core's built site files, relative to the framework.
	 */
	public const string CORE_DIRECTORY = 'public/site';

	/**
	 * Theme asset resolvers built so far, by theme name.
	 *
	 * @var array<string, ThemeAssets>
	 */
	private array $themeAssets = [];

	/**
	 * Versions computed so far, by absolute path.
	 *
	 * @var array<string, string>
	 */
	private array $versions = [];

	public function __construct(
		private readonly Themes $themes,
		private readonly Plugins $plugins
	) {}

	/**
	 * Returns a file's URL, or `null` when where it's from doesn't have it
	 * (or doesn't run).
	 *
	 * @throws ThemeException When a theme's build manifest is invalid.
	 */
	public function url(string $path, string $from = ''): ?string
	{
		if ($from === '') {
			return $path;
		}

		if ($from === self::CORE) {
			$file = $this->corePath($path);

			return $file === null ? null : $this->versioned(AssetRoutes::CORE_URL . "/{$path}", $file);
		}

		$file = $this->pluginPath($from, $path);

		if ($file !== null) {
			return $this->versioned(AssetRoutes::PLUGIN_URL . "/{$from}/{$path}", $file);
		}

		$theme = $this->themes->find($from);

		if ($theme === null) {
			return null;
		}

		return ($this->themeAssets[$from] ??= new ThemeAssets(new ThemeChain([$theme])))->url($path);
	}

	/**
	 * Returns the file for one of core's servable site files, or `null`.
	 */
	public function corePath(string $path): ?string
	{
		return self::servable(Framework::path(self::CORE_DIRECTORY), $path);
	}

	/**
	 * Returns the file for a servable file in a plugin that runs, or
	 * `null`.
	 */
	public function pluginPath(string $plugin, string $path): ?string
	{
		$manifest = $this->plugins->get($plugin);

		return $manifest === null ? null : self::servable($manifest->path, $path);
	}

	/**
	 * Returns a file under a folder when it exists and may be served.
	 */
	private static function servable(string $directory, string $path): ?string
	{
		$file = "{$directory}/{$path}";

		return ThemeChain::isServable($path) && is_file($file) ? $file : null;
	}

	/**
	 * Returns a URL with its file's version.
	 */
	private function versioned(string $url, string $file): string
	{
		$this->versions[$file] ??= (string) hash_file('crc32b', $file);

		return "{$url}?v={$this->versions[$file]}";
	}
}
