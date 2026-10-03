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

use DirectoryIterator;
use Throwable;
use Blush\Core\Paths;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ManifestFile;
use Blush\Support\ComposerPackages;

/**
 * Finds every installed icon pack (D-378): Composer packages of type
 * `blush-icons` (the manifest is the `icons.json` in the package, and its
 * name is the package's) and folders in `user/icons` holding an
 * `icons.json` (or `.yaml`). With the same name, a `user/icons` pack
 * replaces a Composer one. A broken pack is recorded, by where it was
 * found, instead of failing discovery, since icons are never worth taking
 * the site down for.
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

		if (is_dir($this->paths->icons)) {
			$folders = [];

			foreach (new DirectoryIterator($this->paths->icons) as $folder) {
				if ($folder->isDir() && ! str_starts_with($folder->getFilename(), '.')) {
					$folders[] = $folder->getPathname();
				}
			}

			sort($folders);

			foreach ($folders as $folder) {
				$found[] = [$this->paths->relative($folder), $folder, IconPackSource::Local];
			}
		}

		$packs = [];
		$local = [];

		foreach ($found as [$where, $path, $source]) {
			$file = ManifestFile::find($path, ExtensionKind::IconPack)[0] ?? null;

			if ($file === null) {
				continue;
			}

			try {
				$data = ManifestFile::read($file);

				if ($source === IconPackSource::Composer) {
					$data['name'] ??= $where;

					if ($data['name'] !== $where) {
						throw new ExtensionException(sprintf('The icon pack in Composer package "%s" is named "%s"; a Composer icon pack\'s name is its package\'s.', $where, is_string($data['name']) ? $data['name'] : ''));
					}
				}

				// A manifest without authors takes its composer.json's (D-384).
				if (! array_key_exists('authors', $data)) {
					$data['authors'] = array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), ExtensionAuthor::fromComposer($path));
				}

				$pack = IconPack::fromArray($path, $data, $source);
			} catch (ExtensionException $error) {
				$invalid[$where] = $error->getMessage();

				continue;
			}

			if ($source === IconPackSource::Local && isset($local[$pack->name])) {
				$invalid[$where] = sprintf('%s is also named "%s".', $local[$pack->name], $pack->name);

				continue;
			}

			if ($source === IconPackSource::Local) {
				$local[$pack->name] = $where;
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
