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

use Throwable;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\LocalExtension;
use Blush\Extension\LocalExtensions;
use Blush\Extension\ManifestFile;
use Blush\Support\ComposerPackages;

/**
 * Finds every installed theme (D-034): the framework default theme,
 * Composer packages of type `blush-theme`, and folders in
 * `extensions/{vendor}/{name}` (D-418). Each is known by its manifest's
 * `name` (D-378), which for a folder theme must be its folder's.
 * It runs before the container exists (theme providers register at boot),
 * so it reads manifests itself: `theme.json`, else `theme.yaml` or
 * `theme.yml` (D-032).
 *
 * A manifest file is optional when the `composer.json` in the folder has
 * the type `blush-theme` (D-432). What a manifest leaves out it takes
 * from that `composer.json`: Blush's keys from its `extra.blush`, and the
 * keys it shares with Composer (its `authors`, D-384, and the rest,
 * D-418), so a package says them once.
 *
 * A Composer theme's manifest is the `theme.json` in its package, if it
 * has one, and its name is the package's: a manifest without a `name`
 * takes it, and one with another name is broken. With the same name, a folder theme
 * replaces a Composer theme; nothing replaces `blush/default`. A theme
 * whose manifest is broken is recorded as invalid, by where it was found
 * (`extensions/{vendor}/{name}`, or its package name), instead of
 * failing discovery, so one bad folder can't take the site (or the CLI
 * that would fix it) down.
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

		foreach (LocalExtensions::forPaths($this->paths)->of(ExtensionKind::Theme) as $extension) {
			$found[] = [$extension->where, $extension, ThemeSource::Local];
		}

		$found[] = [Themes::DEFAULT, Framework::path('resources/themes/default'), ThemeSource::Framework];

		$themes = [];

		foreach ($found as [$where, $at, $source]) {
			try {
				if ($at instanceof LocalExtension) {
					$path = $at->path;
					$data = $at->read();
				} else {
					$path = $at;
					$data = ManifestFile::load($path, ExtensionKind::Theme);
				}

				if ($source === ThemeSource::Composer) {
					$data['name'] ??= $where;

					if ($data['name'] !== $where) {
						throw new ThemeException(sprintf('The theme in Composer package "%s" is named "%s"; a Composer theme\'s name is its package\'s.', $where, is_string($data['name']) ? $data['name'] : ''));
					}
				}

				$theme = ThemeManifest::fromArray($path, $data, $source);
			} catch (ThemeException | ExtensionException $error) {
				$invalid[$where] = $error->getMessage();

				continue;
			}

			if ($theme->name === Themes::DEFAULT && $source !== ThemeSource::Framework) {
				$invalid[$where] = sprintf('"%s" is the framework default theme\'s name.', Themes::DEFAULT);

				continue;
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
}
