<?php

/**
 * Plugin cache.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Extension\ExtensionException;
use Blush\Support\PhpArrayFile;

/**
 * Compiles discovered plugins, broken ones included (D-394), to a PHP
 * file, so production requests don't scan `installed.json` or
 * `user/plugins` (D-041, D-044).
 */
final readonly class PluginCache
{
	public function __construct(private PhpArrayFile $file)
	{
	}

	/**
	 * Returns the cached plugins, or `null` when nothing is cached (or
	 * the cache is in an older shape, so they're discovered again).
	 *
	 * @throws ExtensionException When the cache is invalid.
	 */
	public function read(): ?DiscoveredPlugins
	{
		$data = $this->file->read();

		if ($data === null || ! is_array($data['manifests'] ?? null) || ! is_array($data['broken'] ?? null)) {
			return null;
		}

		$manifests = [];
		$broken    = [];

		foreach ($data['manifests'] as $manifest) {
			if (! is_array($manifest)) {
				throw new ExtensionException(sprintf('The plugin cache "%s" is invalid.', $this->file->path));
			}

			$manifests[] = PluginManifest::fromArray($manifest);
		}

		foreach ($data['broken'] as $plugin) {
			if (! is_array($plugin)) {
				throw new ExtensionException(sprintf('The plugin cache "%s" is invalid.', $this->file->path));
			}

			$broken[] = BrokenPlugin::fromArray($plugin);
		}

		return new DiscoveredPlugins($manifests, $broken);
	}

	/**
	 * Writes the plugins to the cache.
	 */
	public function write(DiscoveredPlugins $plugins): void
	{
		$this->file->write([
			'manifests' => array_map(static fn (PluginManifest $manifest): array => $manifest->toArray(), $plugins->manifests),
			'broken'    => array_map(static fn (BrokenPlugin $plugin): array => $plugin->toArray(), $plugins->broken)
		]);
	}

	/**
	 * Deletes the cache.
	 */
	public function clear(): void
	{
		$this->file->delete();
	}
}
