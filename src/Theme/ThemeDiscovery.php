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
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ManifestFile;
use Blush\Support\ComposerPackages;

/**
 * Finds every installed theme (D-034): the framework default theme,
 * Composer packages of type `blush-theme`, and folders in `user/themes`
 * (D-166). Each is known by its manifest's `name` (D-378), not its
 * folder.
 * It runs before the container exists (theme providers register at boot),
 * so it reads manifests itself: `theme.json`, else `theme.yaml` or
 * `theme.yml` (D-032).
 *
 * A manifest without `authors` takes them from the `composer.json` in
 * its folder (D-384), so a package lists them once.
 *
 * A Composer theme's manifest is the `theme.json` in its package, and
 * its name is the package's: a manifest without a `name` takes it, and
 * one with another name is broken. With the same name, a `user/themes`
 * theme replaces a Composer theme; nothing replaces `blush/default`, and
 * two `user/themes` folders with one name are both broken. A theme whose
 * manifest is broken is recorded as invalid, by where it was found
 * (`user/themes/{folder}`, or its package name), instead of failing
 * discovery, so one bad folder can't take the site (or the CLI that would
 * fix it) down.
 */
final readonly class ThemeDiscovery
{
	public function __construct(private Paths $paths)
	{}

	/**
	 * Finds every theme.
	 */
	public function discover(): Themes
	{
		$invalid = [];
		$found   = [];

		try {
			foreach (new ComposerPackages($this->paths->vendor)->ofType(ExtensionKind::Theme->packageType()) as $package) {
				$name = is_string($package['name'] ?? null) ? $package['name'] : '';

				if (is_string($package['path'] ?? null)) {
					$found[] = [$name, $package['path'], ThemeSource::Composer];
				}
			}
		} catch (Throwable $error) {
			$invalid['composer'] = $error->getMessage();
		}

		if (is_dir($this->paths->themes)) {
			$folders = [];

			foreach (new DirectoryIterator($this->paths->themes) as $folder) {
				if ($folder->isDir() && ! $folder->isDot()) {
					$folders[] = $folder->getPathname();
				}
			}

			sort($folders);

			foreach ($folders as $folder) {
				$found[] = [$this->paths->relative($folder), $folder, ThemeSource::Local];
			}
		}

		$found[] = [Themes::DEFAULT, Framework::path('resources/themes/default'), ThemeSource::Framework];

		$themes = [];
		$local  = [];

		foreach ($found as [$where, $path, $source]) {
			try {
				$data = self::read($path);

				if ($data === null) {
					continue;
				}

				if ($source === ThemeSource::Composer) {
					$data['name'] ??= $where;

					if ($data['name'] !== $where) {
						throw new ThemeException(sprintf('The theme in Composer package "%s" is named "%s"; a Composer theme\'s name is its package\'s.', $where, is_string($data['name']) ? $data['name'] : ''));
					}
				}

				// A manifest without authors takes its composer.json's (D-384).
				if (! array_key_exists('authors', $data)) {
					$authors = ExtensionAuthor::fromComposer($path);

					if ($authors !== []) {
						$data['authors'] = array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), $authors);
					}
				}

				$theme = ThemeManifest::fromArray($path, $data, $source);
			} catch (ThemeException $error) {
				$invalid[$where] = $error->getMessage();

				continue;
			}

			if ($theme->name === Themes::DEFAULT && $source !== ThemeSource::Framework) {
				$invalid[$where] = sprintf('"%s" is the framework default theme\'s name.', Themes::DEFAULT);

				continue;
			}

			if ($source === ThemeSource::Local && isset($local[$theme->name])) {
				$invalid[$where]                = sprintf('%s is also named "%s".', $local[$theme->name], $theme->name);
				$invalid[$local[$theme->name]] = sprintf('%s is also named "%s".', $where, $theme->name);
				unset($themes[$theme->name]);

				continue;
			}

			if ($source === ThemeSource::Local) {
				$local[$theme->name] = $where;
			}

			$themes[$theme->name] = $theme;
		}

		ksort($themes);

		// Two themes claiming one namespace are both broken (D-378), but
		// the default theme keeps its own.
		$claims = [];

		foreach ($themes as $theme) {
			$claims[$theme->namespace][] = $theme;
		}

		foreach (array_filter($claims, static fn (array $claimants): bool => count($claimants) > 1) as $namespace => $claimants) {
			$names = array_map(static fn (ThemeManifest $theme): string => $theme->name, $claimants);

			foreach ($claimants as $theme) {
				if ($theme->source !== ThemeSource::Framework) {
					$invalid[self::where($theme, $this->paths)] = sprintf('The themes %s all have the namespace "%s".', implode(', ', $names), $namespace);
					unset($themes[$theme->name]);
				}
			}
		}

		return new Themes($themes, $invalid);
	}

	/**
	 * Returns where a theme was found, as `Themes::invalid()` keys it.
	 */
	public static function where(ThemeManifest $theme, Paths $paths): string
	{
		return match ($theme->source) {
			ThemeSource::Local     => $paths->relative($theme->path),
			ThemeSource::Composer,
			ThemeSource::Framework => $theme->name
		};
	}

	/**
	 * Returns the winning manifest file in a folder, and any it shadows.
	 *
	 * @return list<string>
	 */
	public static function manifestFiles(string $path): array
	{
		return ManifestFile::find($path, ExtensionKind::Theme);
	}

	/**
	 * Reads a theme folder's manifest, or returns `null` when it has none.
	 *
	 * @return ?array<string, mixed>
	 * @throws ThemeException When the manifest can't be parsed.
	 */
	private static function read(string $path): ?array
	{
		$file = self::manifestFiles($path)[0] ?? null;

		if ($file === null) {
			return null;
		}

		try {
			return ManifestFile::read($file);
		} catch (Throwable $error) {
			throw new ThemeException($error->getMessage(), 0, $error);
		}
	}
}
