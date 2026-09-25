<?php

/**
 * Installed themes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use DirectoryIterator;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * The installed themes: each folder in `user/themes` with a `theme.json`
 * (or `theme.yaml`/`theme.yml`; JSON wins, D-032), plus the framework
 * default theme, `default`, which is always there and always last in a
 * chain. The framework's `default` can't be replaced by a site folder of
 * the same name.
 *
 * Manifests are read on first use and kept for the request.
 */
final class Themes
{
	/**
	 * The framework default theme's slug.
	 */
	public const string DEFAULT = 'default';

	/**
	 * Manifests read so far, keyed by slug (`false` when missing).
	 *
	 * @var array<string, ThemeManifest|false>
	 */
	private array $manifests = [];

	public function __construct(
		private readonly Paths $paths,
		private readonly DataLoader $loader
	) {}

	/**
	 * Returns whether a string is a valid theme slug.
	 */
	public static function isValidSlug(string $slug): bool
	{
		return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1;
	}

	/**
	 * Returns a theme's manifest, or `null` when it isn't installed.
	 *
	 * @throws ThemeException When its manifest is invalid.
	 */
	public function find(string $slug): ?ThemeManifest
	{
		if (! self::isValidSlug($slug)) {
			return null;
		}

		if (! array_key_exists($slug, $this->manifests)) {
			$this->manifests[$slug] = $this->read($slug) ?? false;
		}

		return $this->manifests[$slug] === false ? null : $this->manifests[$slug];
	}

	/**
	 * Returns whether a theme is installed.
	 *
	 * @throws ThemeException
	 */
	public function has(string $slug): bool
	{
		return $this->find($slug) !== null;
	}

	/**
	 * Returns every installed theme, by slug, the default theme first.
	 *
	 * @return array<string, ThemeManifest>
	 * @throws ThemeException
	 */
	public function all(): array
	{
		$slugs = [];

		if (is_dir($this->paths->themes)) {
			foreach (new DirectoryIterator($this->paths->themes) as $folder) {
				if ($folder->isDir() && ! $folder->isDot() && self::isValidSlug($folder->getFilename())) {
					$slugs[] = $folder->getFilename();
				}
			}
		}

		sort($slugs);

		$themes = [];

		foreach ([self::DEFAULT, ...$slugs] as $slug) {
			$theme = $this->find($slug);

			if ($theme !== null) {
				$themes[$slug] = $theme;
			}
		}

		return $themes;
	}

	/**
	 * Returns a theme's chain: the theme, its ancestors, then the default
	 * theme.
	 *
	 * @throws ThemeException When a theme in the chain is missing or the
	 *         chain loops.
	 */
	public function chain(string $slug): ThemeChain
	{
		$themes = [];
		$next   = $slug;

		while ($next !== null && $next !== self::DEFAULT) {
			if (isset($themes[$next])) {
				throw new ThemeException(sprintf('The "%s" theme\'s parents loop back to "%s".', $slug, $next));
			}

			$theme = $this->find($next) ?? throw new ThemeException(
				$next === $slug
					? sprintf('The "%s" theme is not installed.', $slug)
					: sprintf('The "%s" theme\'s ancestor "%s" is not installed.', $slug, $next)
			);

			$themes[$next] = $theme;
			$next          = $theme->parent;
		}

		$default = $this->find(self::DEFAULT) ?? throw new ThemeException('The framework default theme is missing.');

		return new ThemeChain([...array_values($themes), $default]);
	}

	/**
	 * Returns the folder a theme lives in.
	 */
	public function path(string $slug): string
	{
		return $slug === self::DEFAULT
			? Framework::path('resources/themes/' . self::DEFAULT)
			: "{$this->paths->themes}/{$slug}";
	}

	/**
	 * Reads a theme's manifest.
	 *
	 * @throws ThemeException
	 */
	private function read(string $slug): ?ThemeManifest
	{
		$path = $this->path($slug);

		try {
			$data = is_dir($path) ? $this->loader->load($path, 'theme') : null;
		} catch (InvalidData $error) {
			throw new ThemeException(sprintf('The "%s" theme\'s manifest is invalid: %s', $slug, $error->getMessage()), 0, $error);
		}

		return $data === null ? null : ThemeManifest::fromArray($slug, $path, $data);
	}
}
