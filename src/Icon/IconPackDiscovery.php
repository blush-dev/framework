<?php

/**
 * Icon pack discovery.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Throwable;
use Blush\Core\Paths;
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\LocalExtension;
use Blush\Extension\LocalExtensions;
use Blush\Extension\ManifestFile;
use Blush\Support\ComposerPackages;

/**
 * Finds every installed icon pack (D-378): Composer packages of type
 * `blush-icons` (the manifest is the `icons.json` in the package, and its
 * name is the package's) and folders in `extensions/{vendor}/{name}`
 * holding an `icons.json` (or `.yaml`), whose name must be the folder's
 * (D-418). What a manifest leaves out of the keys it shares with
 * Composer it takes from its `composer.json`. With the same name, a
 * folder pack replaces a Composer one. A broken pack is recorded, by
 * where it was found, instead of failing discovery, since icons are never
 * worth taking the site down for.
 */
final readonly class IconPackDiscovery
{
	public function __construct(private Paths $paths)
	{}

	/**
	 * Finds every pack.
	 */
	public function discover(): IconPacks
	{
		$invalid = [];
		$found   = [];

		try {
			foreach (new ComposerPackages($this->paths->vendor)->ofType(ExtensionKind::IconPack->packageType()) as $package) {
				if (is_string($package['name'] ?? null) && is_string($package['path'] ?? null)) {
					$found[] = [$package['name'], $package['path'], IconPackSource::Composer];
				}
			}
		} catch (Throwable $error) {
			$invalid['composer'] = $error->getMessage();
		}

		foreach (LocalExtensions::forPaths($this->paths)->of(ExtensionKind::IconPack) as $extension) {
			$found[] = [$extension->where, $extension, IconPackSource::Local];
		}

		$packs = [];

		foreach ($found as [$where, $at, $source]) {
			try {
				if ($at instanceof LocalExtension) {
					$path = $at->path;
					$data = $at->read();
				} else {
					$path = $at;
					$file = ManifestFile::find($path, ExtensionKind::IconPack)[0] ?? null;

					if ($file === null) {
						continue;
					}

					$data            = ComposerJson::fill(ManifestFile::read($file), $path, ['require']);
					$data['name'] ??= $where;

					if ($data['name'] !== $where) {
						throw new ExtensionException(sprintf('The icon pack in Composer package "%s" is named "%s"; a Composer icon pack\'s name is its package\'s.', $where, is_string($data['name']) ? $data['name'] : ''));
					}
				}

				$pack = IconPack::fromArray($path, $data, $source);
			} catch (ExtensionException $error) {
				$invalid[$where] = $error->getMessage();

				continue;
			}

			$packs[$pack->name] = $pack;
		}

		ksort($packs);

		// Two packs claiming one namespace are both broken (D-378).
		$claims = [];

		foreach ($packs as $pack) {
			$claims[$pack->namespace][] = $pack;
		}

		$reasons = [];

		foreach (array_filter($claims, static fn (array $claimants): bool => count($claimants) > 1) as $namespace => $claimants) {
			$names = array_map(static fn (IconPack $pack): string => $pack->name, $claimants);

			foreach ($claimants as $pack) {
				$reasons[$pack->name] = [self::where($pack, $this->paths), sprintf('The icon packs %s all have the namespace "%s".', implode(', ', $names), $namespace)];
			}
		}

		return new IconPacks($packs, $invalid)->without($reasons);
	}

	/**
	 * Returns where a pack was found, as `IconPacks::invalid()` keys it.
	 */
	public static function where(IconPack $pack, Paths $paths): string
	{
		return $pack->source === IconPackSource::Composer ? $pack->name : $paths->relative($pack->path);
	}
}
