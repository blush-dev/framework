<?php

/**
 * Composer plugin finder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Override;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Support\ComposerPackages;
use Blush\Support\FilesystemException;

/**
 * Finds plugins installed with Composer: packages of type `blush-plugin`
 * listed in `vendor/composer/installed.json`. The package's name is the
 * plugin's, and its `extra.blush` object supplies the rest of the
 * manifest (D-378):
 *
 *     "name": "acme/gallery",
 *     "type": "blush-plugin",
 *     "extra": {
 *         "blush": {
 *             "label": "Gallery",
 *             "namespace": "gallery",
 *             "provider": "Acme\\Gallery\\GalleryServiceProvider",
 *             "requires": { "blush": "^2.0" }
 *         }
 *     }
 *
 * Composer autoloads these packages itself.
 */
final readonly class ComposerPluginFinder implements PluginFinder
{
	public function __construct(private string $vendorPath)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(): array
	{
		try {
			$packages = new ComposerPackages($this->vendorPath)->ofType(ExtensionKind::Plugin->packageType());
		} catch (FilesystemException $e) {
			throw new ExtensionException($e->getMessage(), previous: $e);
		}

		return array_map($this->manifest(...), $packages);
	}

	/**
	 * Builds a manifest from an installed package entry.
	 *
	 * @param  array<array-key, mixed> $package
	 * @throws ExtensionException
	 */
	private function manifest(array $package): PluginManifest
	{
		$name  = is_string($package['name'] ?? null) ? $package['name'] : '';
		$extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
		$blush = is_array($extra['blush'] ?? null) ? $extra['blush'] : [];

		foreach (['label', 'namespace', 'provider'] as $key) {
			if (! isset($blush[$key])) {
				throw new ExtensionException(sprintf(
					'Composer package "%s" is a %s but has no "extra.blush.%s".',
					$name,
					ExtensionKind::Plugin->packageType(),
					$key
				));
			}
		}

		return PluginManifest::fromArray([
			'name'        => $name,
			'label'       => $blush['label'],
			'namespace'   => $blush['namespace'],
			'provider'    => $blush['provider'],
			'source'      => PluginSource::Composer,
			'path'        => is_string($package['path'] ?? null) ? $package['path'] : '',
			'version'     => is_string($package['version'] ?? null) ? $package['version'] : '0.0.0',
			'description' => is_string($package['description'] ?? null) ? $package['description'] : '',
			'requires'    => $blush['requires'] ?? []
		]);
	}
}
