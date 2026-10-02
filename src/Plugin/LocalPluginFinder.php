<?php

/**
 * Local plugin finder.
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
use Blush\Extension\ManifestFile;

/**
 * Finds local plugins: folders in `user/plugins/` holding a
 * `plugin.json` (or `plugin.yaml`/`.yml`) manifest (D-378):
 *
 *     {
 *         "name": "acme/gallery",
 *         "label": "Gallery",
 *         "namespace": "gallery",
 *         "version": "1.0.0",
 *         "description": "Photo galleries.",
 *         "provider": "Acme\\Gallery\\GalleryServiceProvider",
 *         "autoload": { "psr-4": { "Acme\\Gallery\\": "src/" } },
 *         "requires": { "blush": "^2.0" }
 *     }
 *
 * The folder's name is only where the plugin lives; its `name` is what
 * it's known by. Blush autoloads the `psr-4` map itself (see
 * `LocalAutoloader`). When a folder has manifests in several formats,
 * JSON wins (D-032).
 */
final readonly class LocalPluginFinder implements PluginFinder
{
	public function __construct(private string $pluginsPath)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(): array
	{
		$files = [];

		foreach (glob($this->pluginsPath . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
			$file = ManifestFile::find($directory, ExtensionKind::Plugin)[0] ?? null;

			if ($file !== null) {
				$files[] = $file;
			}
		}

		sort($files);

		return array_map($this->manifest(...), $files);
	}

	/**
	 * Builds a manifest from a manifest file.
	 *
	 * @throws ExtensionException
	 */
	private function manifest(string $file): PluginManifest
	{
		$data     = ManifestFile::read($file);
		$autoload = is_array($data['autoload'] ?? null) ? $data['autoload'] : [];

		try {
			return PluginManifest::fromArray([
				...$data,
				'source'   => PluginSource::Local,
				'path'     => dirname($file),
				'autoload' => $autoload['psr-4'] ?? []
			]);
		} catch (ExtensionException $e) {
			throw new ExtensionException(sprintf('%s (%s)', $e->getMessage(), $file), previous: $e);
		}
	}
}
