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
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionAuthor;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionLinks;
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
 *             "require": { "blush-dev/framework": "^2.0" }
 *         }
 *     }
 *
 * Every key is optional: without a `label`, it's shown by its name
 * (D-423), without a `namespace`, it goes by its name, hyphenated
 * (D-424), and without a `provider`, it registers nothing (D-425), so a
 * package needs no `extra.blush` at all. Composer autoloads
 * these packages itself. A package whose manifest
 * doesn't hold is broken, known by its name (D-394).
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
	public function find(): DiscoveredPlugins
	{
		try {
			$packages = new ComposerPackages($this->vendorPath)->ofType(ExtensionKind::Plugin->packageType());
		} catch (FilesystemException $e) {
			throw new ExtensionException($e->getMessage(), previous: $e);
		}

		$manifests = [];
		$broken    = [];

		foreach ($packages as $package) {
			try {
				$manifests[] = $this->manifest($package);
			} catch (ExtensionException $e) {
				$name     = is_string($package['name'] ?? null) ? $package['name'] : '';
				$broken[] = new BrokenPlugin($name, $e->getMessage(), $name, PluginSource::Composer);
			}
		}

		return new DiscoveredPlugins($manifests, $broken);
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

		return PluginManifest::fromArray([
			'name'        => $name,
			'label'       => $blush['label'] ?? '',
			'namespace'   => $blush['namespace'] ?? null,
			'provider'    => $blush['provider'] ?? null,
			'source'      => PluginSource::Composer,
			'path'        => is_string($package['path'] ?? null) ? $package['path'] : '',
			'version'     => is_string($package['version'] ?? null) ? $package['version'] : '0.0.0',
			'description' => is_string($package['description'] ?? null) ? $package['description'] : '',
			'require'     => $blush['require'] ?? [],
			'authors'     => array_map(static fn (ExtensionAuthor $author): array => $author->toArray(), ExtensionAuthor::lenient($package['authors'] ?? [])),
			'license'     => ComposerJson::license($package['license'] ?? null),
			'abandoned'   => ExtensionAbandoned::lenient($package['abandoned'] ?? false),
			...ExtensionLinks::lenient($package)->toArray()
		]);
	}
}
