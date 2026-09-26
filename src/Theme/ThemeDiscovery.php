<?php

/**
 * Theme discovery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use DirectoryIterator;
use Throwable;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Data\SymfonyYamlParser;
use Blush\Support\ComposerPackages;

/**
 * Finds every installed theme (D-034): the framework `default` theme,
 * Composer packages of type `blush-theme`, folders in the site's
 * `resources/themes` (D-144), and folders in `user/themes`.
 * It runs before the container exists (theme providers register at boot),
 * so it reads manifests itself: `theme.json`, else `theme.yaml` or
 * `theme.yml` (D-032).
 *
 * With the same slug, a site theme replaces a Composer theme, and a
 * `user/themes` theme replaces both; nothing replaces `default`. A theme whose manifest is broken is recorded as
 * invalid instead of failing discovery, so one bad folder can't take the
 * site (or the CLI that would fix it) down.
 *
 * A Composer theme's slug is `extra.blush.slug`, or the package name after
 * the `/`.
 */
final readonly class ThemeDiscovery
{
	/**
	 * The Composer package type for Blush themes.
	 */
	public const string PACKAGE_TYPE = 'blush-theme';

	/**
	 * Manifest file names, in precedence order.
	 *
	 * @var list<string>
	 */
	public const array MANIFESTS = ['theme.json', 'theme.yaml', 'theme.yml'];

	public function __construct(private Paths $paths)
	{}

	/**
	 * Finds every theme.
	 */
	public function discover(): Themes
	{
		$themes  = [];
		$invalid = [];
		$found   = [];

		try {
			foreach (new ComposerPackages($this->paths->vendor)->ofType(self::PACKAGE_TYPE) as $package) {
				$name  = is_string($package['name'] ?? null) ? $package['name'] : '';
				$extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
				$blush = is_array($extra['blush'] ?? null) ? $extra['blush'] : [];
				$slug  = is_string($blush['slug'] ?? null) ? $blush['slug'] : substr((string) strrchr('/' . $name, '/'), 1);

				if (is_string($package['path'] ?? null)) {
					$found[$slug] = [$package['path'], ThemeSource::Composer];
				}
			}
		} catch (Throwable $error) {
			$invalid['composer'] = $error->getMessage();
		}

		foreach ([$this->paths->siteThemes => ThemeSource::Site, $this->paths->themes => ThemeSource::Local] as $directory => $source) {
			if (! is_dir($directory)) {
				continue;
			}

			foreach (new DirectoryIterator($directory) as $folder) {
				if ($folder->isDir() && ! $folder->isDot()) {
					$found[$folder->getFilename()] = [$folder->getPathname(), $source];
				}
			}
		}

		$found[Themes::DEFAULT] = [Framework::path('resources/themes/' . Themes::DEFAULT), ThemeSource::Framework];

		ksort($found);

		foreach ($found as $slug => [$path, $source]) {
			$slug = (string) $slug;

			if (! Themes::isValidSlug($slug)) {
				continue;
			}

			try {
				$data = self::read($path);

				if ($data !== null) {
					$themes[$slug] = ThemeManifest::fromArray($slug, $path, $data, $source);
				}
			} catch (ThemeException $error) {
				$invalid[$slug] = $error->getMessage();
			}
		}

		return new Themes($themes, $invalid);
	}

	/**
	 * Returns the winning manifest file in a folder, and any it shadows.
	 *
	 * @return list<string>
	 */
	public static function manifestFiles(string $path): array
	{
		return array_values(array_filter(
			array_map(static fn (string $name): string => "{$path}/{$name}", self::MANIFESTS),
			is_file(...)
		));
	}

	/**
	 * Reads a theme folder's manifest, or returns `null` when it has none.
	 *
	 * @return ?array<array-key, mixed>
	 * @throws ThemeException When the manifest can't be parsed.
	 */
	private static function read(string $path): ?array
	{
		$file = self::manifestFiles($path)[0] ?? null;

		if ($file === null) {
			return null;
		}

		$contents = (string) file_get_contents($file);

		try {
			$data = str_ends_with($file, '.json')
				? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
				: new SymfonyYamlParser()->parse($contents);
		} catch (Throwable $error) {
			throw new ThemeException(sprintf('The theme manifest %s is invalid: %s', $file, $error->getMessage()), 0, $error);
		}

		if (! is_array($data)) {
			throw new ThemeException(sprintf('The theme manifest %s must hold an object.', $file));
		}

		return $data;
	}
}
