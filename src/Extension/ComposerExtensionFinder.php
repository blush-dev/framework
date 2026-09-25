<?php

/**
 * Composer extension finder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Override;
use Blush\Support\ComposerPackages;
use Blush\Support\FilesystemException;

/**
 * Finds extensions installed with Composer: packages of type `blush-extension`
 * listed in `vendor/composer/installed.json`. The package's `extra.blush`
 * object supplies the provider and any requirements:
 *
 *     "type": "blush-extension",
 *     "extra": {
 *         "blush": {
 *             "provider": "Acme\\Gallery\\GalleryServiceProvider",
 *             "requires": { "blush": "^2.0" }
 *         }
 *     }
 *
 * Composer autoloads these packages itself.
 */
final readonly class ComposerExtensionFinder implements ExtensionFinder
{
	/**
	 * The Composer package type for Blush extensions.
	 */
	public const string PACKAGE_TYPE = 'blush-extension';

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
			$packages = new ComposerPackages($this->vendorPath)->ofType(self::PACKAGE_TYPE);
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
	private function manifest(array $package): ExtensionManifest
	{
		$name  = is_string($package['name'] ?? null) ? $package['name'] : '';
		$extra = is_array($package['extra'] ?? null) ? $package['extra'] : [];
		$blush = is_array($extra['blush'] ?? null) ? $extra['blush'] : [];

		if (! isset($blush['provider'])) {
			throw new ExtensionException(sprintf(
				'Composer package "%s" is a %s but has no "extra.blush.provider".',
				$name,
				self::PACKAGE_TYPE
			));
		}

		return ExtensionManifest::fromArray([
			'name'        => $name,
			'provider'    => $blush['provider'],
			'source'      => ExtensionSource::Composer,
			'path'        => is_string($package['path'] ?? null) ? $package['path'] : '',
			'version'     => is_string($package['version'] ?? null) ? $package['version'] : '0.0.0',
			'description' => is_string($package['description'] ?? null) ? $package['description'] : '',
			'requires'    => $blush['requires'] ?? []
		]);
	}
}
