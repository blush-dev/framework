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
use Blush\Extension\LocalExtensions;
use Blush\Extension\ManifestFile;

/**
 * Finds local plugins: folders in `extensions/{vendor}/{name}` holding a
 * `plugin.json` manifest (D-378, D-418), or a
 * `composer.json` of type `blush-plugin`, with Blush's keys under
 * `extra.blush` (D-432):
 *
 *     {
 *         "name": "acme/gallery",
 *         "label": "Gallery",
 *         "namespace": "gallery",
 *         "version": "1.0.0",
 *         "description": "Photo galleries.",
 *         "provider": "Acme\\Gallery\\GalleryServiceProvider",
 *         "autoload": { "psr-4": { "Acme\\Gallery\\": "src/" } },
 *         "require": { "blush-dev/framework": "^2.0" }
 *     }
 *
 * The folder is the plugin's name, and a manifest naming another is
 * broken. What the manifest leaves out, its `composer.json` may say
 * (Blush's keys under `extra.blush`, and the keys it shares with
 * Composer). Blush autoloads it itself (see
 * `LocalAutoloader`). A folder whose manifest doesn't hold is broken,
 * known by its path from the site's root (D-394).
 */
final readonly class LocalPluginFinder implements PluginFinder
{
	/**
	 * @param string $root The site's root, which a broken plugin's messages give paths from.
	 */
	public function __construct(
		private LocalExtensions $extensions,
		private string $root = ''
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(): DiscoveredPlugins
	{
		$manifests = [];
		$broken    = [];

		foreach ($this->extensions->of(ExtensionKind::Plugin) as $extension) {
			try {
				$manifests[] = self::build($extension->read(), $extension->path, $extension->source());
			} catch (ExtensionException $e) {
				$reason   = $this->root === '' ? $e->getMessage() : str_replace($this->root . '/', '', $e->getMessage());
				$broken[] = new BrokenPlugin($extension->where, $reason, self::name($extension->path), PluginSource::Local);
			}
		}

		return new DiscoveredPlugins($manifests, $broken);
	}

	/**
	 * Builds a plugin folder's manifest, filling in what it leaves to its
	 * `composer.json` (all of it, without one; D-432).
	 *
	 * @throws ExtensionException
	 */
	public static function manifest(string $folder): PluginManifest
	{
		$file = ManifestFile::find($folder, ExtensionKind::Plugin) ?? "{$folder}/composer.json";

		return self::build(ManifestFile::load($folder, ExtensionKind::Plugin), $folder, $file);
	}

	/**
	 * Builds a manifest from its read data.
	 *
	 * @param  array<string, mixed> $data
	 * @param  string               $file The file it was read from, for messages.
	 * @throws ExtensionException
	 */
	private static function build(array $data, string $folder, string $file): PluginManifest
	{
		try {
			return PluginManifest::fromArray([
				...$data,
				'source' => PluginSource::Local,
				'path'   => $folder
			]);
		} catch (ExtensionException $e) {
			throw new ExtensionException(sprintf('%s (%s)', $e->getMessage(), $file), previous: $e);
		}
	}

	/**
	 * The name a plugin that doesn't hold gives, if its manifest parses
	 * and gives one, or an empty string.
	 */
	private static function name(string $folder): string
	{
		try {
			$name = ManifestFile::load($folder, ExtensionKind::Plugin)['name'] ?? null;
		} catch (ExtensionException) {
			return '';
		}

		return is_string($name) ? $name : '';
	}
}
