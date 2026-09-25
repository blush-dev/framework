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

use JsonException;
use Override;
use Blush\Support\Filesystem;

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
		$file = "{$this->vendorPath}/composer/installed.json";

		if (! is_file($file)) {
			return [];
		}

		try {
			$installed = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException(sprintf('Unable to read "%s": %s', $file, $e->getMessage()), previous: $e);
		}

		// Composer 2 wraps the list in `packages`; Composer 1 did not.
		$packages = is_array($installed) && isset($installed['packages']) ? $installed['packages'] : $installed;

		if (! is_array($packages)) {
			return [];
		}

		$manifests = [];

		foreach ($packages as $package) {
			if (is_array($package) && ($package['type'] ?? null) === self::PACKAGE_TYPE) {
				$manifests[] = $this->manifest($package);
			}
		}

		return $manifests;
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

		$installPath = is_string($package['install-path'] ?? null)
			? $package['install-path']
			: '../' . $name;

		$path = realpath("{$this->vendorPath}/composer/{$installPath}")
			?: new Filesystem()->normalize("{$this->vendorPath}/composer/{$installPath}");

		return ExtensionManifest::fromArray([
			'name'        => $name,
			'provider'    => $blush['provider'],
			'source'      => ExtensionSource::Composer,
			'path'        => $path,
			'version'     => is_string($package['version'] ?? null) ? $package['version'] : '0.0.0',
			'description' => is_string($package['description'] ?? null) ? $package['description'] : '',
			'requires'    => $blush['requires'] ?? []
		]);
	}
}
