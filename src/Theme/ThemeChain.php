<?php

/**
 * Theme chain.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;

/**
 * The active theme, its ancestors, and the framework default theme, in
 * lookup order (D-024). Views, message catalogs, and assets resolve
 * through it: the first theme that has a file wins, so a child theme
 * overrides only what it provides.
 *
 * @implements IteratorAggregate<int, ThemeManifest>
 */
final readonly class ThemeChain implements IteratorAggregate, Countable
{
	/**
	 * The URL path theme assets are served under.
	 */
	public const string ASSET_URL = '/themes';

	/**
	 * Extensions of files a theme may serve, with their MIME types.
	 * Everything else in a theme folder (views, PHP, manifests, catalogs)
	 * stays private.
	 *
	 * @var array<string, string>
	 */
	public const array ASSET_TYPES = [
		'css'         => 'text/css',
		'js'          => 'text/javascript',
		'mjs'         => 'text/javascript',
		'map'         => 'application/json',
		'webmanifest' => 'application/manifest+json',
		'woff2'       => 'font/woff2',
		'woff'        => 'font/woff',
		'ttf'         => 'font/ttf',
		'otf'         => 'font/otf',
		'png'         => 'image/png',
		'jpg'         => 'image/jpeg',
		'jpeg'        => 'image/jpeg',
		'gif'         => 'image/gif',
		'webp'        => 'image/webp',
		'avif'        => 'image/avif',
		'svg'         => 'image/svg+xml',
		'ico'         => 'image/x-icon'
	];

	/**
	 * Top-level theme folders that are never served: views, catalogs, PHP,
	 * and build sources (`resources/`, D-155; `src/`).
	 *
	 * @var list<string>
	 */
	private const array PRIVATE_FOLDERS = ['views', 'lang', 'src', 'resources', 'vendor', 'node_modules'];

	/**
	 * Build tool config files (`vite.config.js`, `postcss.config.mjs`),
	 * which a theme keeps in its own folder (D-168), are never served.
	 */
	private const string PRIVATE_FILES = '#(^|/)[^/]+\.config\.[cm]?js$#i';

	/**
	 * @param non-empty-list<ThemeManifest> $themes The active theme first, the framework default theme last.
	 */
	public function __construct(public array $themes)
	{}

	/**
	 * Returns the active theme.
	 */
	public function active(): ThemeManifest
	{
		return $this->themes[0];
	}

	/**
	 * Returns the classes that make an element wider than the text column
	 * under this chain (D-313): `wide` (into the margin) and `full` (edge to
	 * edge), each from the first theme naming it, else `bleed-wide` and
	 * `bleed-full`. The text column's own width is no class at all.
	 *
	 * @return array{wide: string, full: string}
	 */
	public function bleedClasses(): array
	{
		$classes = ['wide' => 'bleed-wide', 'full' => 'bleed-full'];

		foreach (array_reverse($this->themes) as $theme) {
			$classes = [...$classes, ...$theme->bleed()];
		}

		return $classes;
	}

	/**
	 * Returns the theme slugs, in lookup order.
	 *
	 * @return list<string>
	 */
	public function slugs(): array
	{
		return array_map(static fn (ThemeManifest $theme): string => $theme->slug, $this->themes);
	}

	/**
	 * Returns every theme's views folder, in lookup order.
	 *
	 * @return list<string>
	 */
	public function viewDirectories(): array
	{
		return array_map(static fn (ThemeManifest $theme): string => $theme->viewsPath(), $this->themes);
	}

	/**
	 * Returns every theme's message catalog folder, in lookup order.
	 *
	 * @return list<string>
	 */
	public function langDirectories(): array
	{
		return array_map(static fn (ThemeManifest $theme): string => $theme->langPath(), $this->themes);
	}

	/**
	 * Returns the theme that provides an asset and the file's path, or
	 * `null` when no theme does or the path isn't a servable asset.
	 *
	 * @return ?array{ThemeManifest, string}
	 */
	public function asset(string $path): ?array
	{
		if (! self::isServable($path)) {
			return null;
		}

		foreach ($this->themes as $theme) {
			if (is_file("{$theme->path}/{$path}")) {
				return [$theme, "{$theme->path}/{$path}"];
			}
		}

		return null;
	}

	/**
	 * Returns the service providers of the chain's themes, ancestors
	 * first, so a child theme's bindings win.
	 *
	 * @return list<string>
	 */
	public function providers(): array
	{
		$providers = [];

		foreach (array_reverse($this->themes) as $theme) {
			if ($theme->provider !== null) {
				$providers[] = $theme->provider;
			}
		}

		return $providers;
	}

	/**
	 * Returns whether a path is a safe relative path inside a theme:
	 * segments of letters, digits, `.`, `_`, and `-` that don't start
	 * with a dot.
	 */
	public static function isValidAssetPath(string $path): bool
	{
		return preg_match('#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#', $path) === 1;
	}

	/**
	 * Returns whether a theme may serve a path: a valid asset path, with
	 * an allowed extension, outside the private folders, and not a build
	 * tool config file.
	 */
	public static function isServable(string $path): bool
	{
		return self::isValidAssetPath($path)
			&& isset(self::ASSET_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))])
			&& ! in_array(explode('/', $path)[0], self::PRIVATE_FOLDERS, true)
			&& preg_match(self::PRIVATE_FILES, $path) !== 1;
	}

	/**
	 * @inheritDoc
	 * @return ArrayIterator<int, ThemeManifest>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->themes);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->themes);
	}
}
