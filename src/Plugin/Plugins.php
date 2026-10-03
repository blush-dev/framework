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
 * The site's plugins: the ones that run, in name order, and every
 * installed one. Bound in the container, so commands like `doctor` and
 * the admin can inspect them.
 *
 * An enabled plugin whose `requires` aren't met doesn't run (D-385); it's
 * kept with the requirements it doesn't meet (`unmet()`).
 */
final readonly class Plugins
{
	/**
	 * The manifests that run, keyed by name.
	 *
	 * @var array<string, PluginManifest>
	 */
	private array $manifests;

	/**
	 * Every installed manifest, keyed by name.
	 *
	 * @var array<string, PluginManifest>
	 */
	private array $installed;

	/**
	 * The enabled plugins that can't run, by name, in name order.
	 *
	 * @var array<string, list<Requirement>>
	 */
	private array $unmet;

	/**
	 * @param list<PluginManifest>             $manifests The plugins that run.
	 * @param ?list<PluginManifest>            $installed Every installed plugin, or `null` for the ones that run.
	 * @param array<string, list<Requirement>> $unmet     The enabled plugins that can't run, by name.
	 */
	public function __construct(
		array $manifests = [],
		?array $installed = null,
		array $unmet = []
	) {
		ksort($unmet);

		$this->manifests = self::keyed($manifests);
		$this->installed = $installed === null ? $this->manifests : self::keyed($installed);
		$this->unmet     = $unmet;
	}

	/**
	 * Filters discovered manifests down to the ones config enables and
	 * whose requirements are met.
	 *
	 * @param  list<PluginManifest> $discovered
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public static function enabled(array $discovered, PluginConfig $config, PluginRequirements $requirements = new PluginRequirements()): self
	{
		$names   = array_map(static fn (PluginManifest $manifest): string => $manifest->name, $discovered);
		$missing = array_diff($config->named(), $names);

		if ($missing !== []) {
			throw new ExtensionException(sprintf(
				'Config enables plugin(s) that are not installed: %s.',
				implode(', ', $missing)
			));
		}

		$enabled = array_values(array_filter(
			$discovered,
			$config->isEnabled(...)
		));
		$unmet = $requirements->settle($enabled, self::keyed($discovered));

		return new self(
			array_values(array_filter($enabled, static fn (PluginManifest $manifest): bool => ! isset($unmet[$manifest->name]))),
			$discovered,
			$unmet
		);
	}

	/**
	 * The plugins that run.
	 *
	 * @return list<PluginManifest>
	 */
	public function all(): array
	{
		return array_values($this->manifests);
	}

	/**
	 * Every installed plugin, on or off, in name order.
	 *
	 * @return list<PluginManifest>
	 */
	public function installed(): array
	{
		return array_values($this->installed);
	}

	/**
	 * The enabled plugins that can't run, by name, each with the
	 * requirements it doesn't meet.
	 *
	 * @return array<string, list<Requirement>>
	 */
	public function unmet(): array
	{
		return $this->unmet;
	}

	/**
	 * Whether the named plugin runs.
	 */
	public function has(string $name): bool
	{
		return isset($this->manifests[$name]);
	}

	/**
	 * Returns the named plugin's manifest, if it runs, or `null`.
	 */
	public function get(string $name): ?PluginManifest
	{
		return $this->manifests[$name] ?? null;
	}

	/**
	 * Returns the provider class of every plugin that runs, the plugins it
	 * requires before it, otherwise in name order.
	 *
	 * @return list<class-string>
	 */
	public function providers(): array
	{
		$ordered = [];
		$visit   = function (PluginManifest $manifest) use (&$visit, &$ordered): void {
			if (array_key_exists($manifest->name, $ordered)) {
				return;
			}

			// Marked first, so a requirement that loops back ends here.
			$ordered[$manifest->name] = null;

			foreach (array_keys($manifest->requires) as $name) {
				if (isset($this->manifests[$name])) {
					$visit($this->manifests[$name]);
				}
			}

			unset($ordered[$manifest->name]);
			$ordered[$manifest->name] = $manifest->providerClass();
		};

		foreach ($this->manifests as $manifest) {
			$visit($manifest);
		}

		return array_values(array_filter($ordered, is_string(...)));
	}

	/**
	 * Keys manifests by name, in name order.
	 *
	 * @param  list<PluginManifest> $manifests
	 * @return array<string, PluginManifest>
	 */
	private static function keyed(array $manifests): array
	{
		$keyed = [];

		foreach ($manifests as $manifest) {
			$keyed[$manifest->name] = $manifest;
		}

		ksort($keyed);

		return $keyed;
	}
}
