<?php

/**
 * Plugins.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Blush\Extension\ExtensionException;

/**
 * The site's enabled plugins, in name order. Bound in the container, so
 * commands like `plugin:list` and `doctor` can inspect them.
 */
final readonly class Plugins
{
	/**
	 * Enabled manifests keyed by name.
	 *
	 * @var array<string, PluginManifest>
	 */
	private array $manifests;

	/**
	 * @param list<PluginManifest> $manifests
	 */
	public function __construct(array $manifests = [])
	{
		$keyed = [];

		foreach ($manifests as $manifest) {
			$keyed[$manifest->name] = $manifest;
		}

		ksort($keyed);

		$this->manifests = $keyed;
	}

	/**
	 * Filters discovered manifests down to the ones config enables.
	 *
	 * @param  list<PluginManifest> $discovered
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public static function enabled(array $discovered, PluginConfig $config): self
	{
		$names   = array_map(static fn (PluginManifest $manifest): string => $manifest->name, $discovered);
		$missing = array_diff($config->enabled ?? [], $names);

		if ($missing !== []) {
			throw new ExtensionException(sprintf(
				'Config enables plugin(s) that are not installed: %s.',
				implode(', ', $missing)
			));
		}

		return new self(array_values(array_filter(
			$discovered,
			static fn (PluginManifest $manifest): bool => $config->isEnabled($manifest->name)
		)));
	}

	/**
	 * @return list<PluginManifest>
	 */
	public function all(): array
	{
		return array_values($this->manifests);
	}

	/**
	 * Whether the named plugin is enabled.
	 */
	public function has(string $name): bool
	{
		return isset($this->manifests[$name]);
	}

	/**
	 * Returns the named plugin's manifest, or `null`.
	 */
	public function get(string $name): ?PluginManifest
	{
		return $this->manifests[$name] ?? null;
	}

	/**
	 * Returns every enabled plugin's provider class, in name order.
	 *
	 * @return list<class-string>
	 */
	public function providers(): array
	{
		return array_map(
			static fn (PluginManifest $manifest): string => $manifest->providerClass(),
			$this->all()
		);
	}
}
