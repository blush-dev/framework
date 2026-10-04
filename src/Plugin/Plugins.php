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
use Blush\Extension\Requirement;
use Blush\Extension\Requirements;

/**
 * The site's plugins: the ones that run, in name order, every installed
 * one, and the broken ones. Bound in the container, so commands like
 * `plugin:list` and the admin can inspect them.
 *
 * An enabled plugin whose `require` aren't met doesn't run (D-385); it's
 * kept with the requirements it doesn't meet (`unmet()`). A broken one
 * never runs, turned on or not (D-394; `broken()`).
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
	 * @param list<BrokenPlugin>               $broken    The plugins whose manifests can't be read.
	 */
	public function __construct(
		array $manifests = [],
		?array $installed = null,
		array $unmet = [],
		private array $broken = []
	) {
		ksort($unmet);

		$this->manifests = self::keyed($manifests);
		$this->installed = $installed === null ? $this->manifests : self::keyed($installed);
		$this->unmet     = $unmet;
	}

	/**
	 * Filters discovered manifests down to the ones config enables and
	 * whose requirements are met. A broken plugin config names isn't
	 * missing: it's installed, and doesn't run.
	 *
	 * Requirements are settled among plugins alone unless they're given,
	 * settled across every kind (`ExtensionState::settle()`, D-431).
	 *
	 * @param  list<PluginManifest>               $discovered
	 * @param  list<BrokenPlugin>                 $broken
	 * @param  ?array<string, list<Requirement>> $unmet The enabled plugins that can't run, when already settled.
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public static function enabled(array $discovered, PluginConfig $config, Requirements $requirements = new Requirements(), array $broken = [], ?array $unmet = null): self
	{
		$names   = array_map(static fn (PluginManifest $manifest): string => $manifest->name, $discovered);
		$missing = array_diff($config->named(), $names, array_map(static fn (BrokenPlugin $plugin): string => $plugin->name, $broken));

		if ($missing !== []) {
			// A broken plugin with no name to give may be the one meant.
			$unnamed = array_filter($broken, static fn (BrokenPlugin $plugin): bool => $plugin->name === '');

			throw new ExtensionException(sprintf(
				'Config enables plugin(s) that are not installed: %s.%s',
				implode(', ', $missing),
				implode('', array_map(static fn (BrokenPlugin $plugin): string => " {$plugin->where} is broken: {$plugin->reason}", $unnamed))
			));
		}

		$enabled = array_values(array_filter(
			$discovered,
			$config->isEnabled(...)
		));
		$unmet ??= $requirements->settle($enabled, self::keyed($discovered));

		return new self(
			array_values(array_filter($enabled, static fn (PluginManifest $manifest): bool => ! isset($unmet[$manifest->name]))),
			$discovered,
			$unmet,
			$broken
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
	 * The plugins whose manifests can't be read, which never run.
	 *
	 * @return list<BrokenPlugin>
	 */
	public function broken(): array
	{
		return $this->broken;
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
	 * Returns the provider class of every plugin that runs and has one
	 * (D-425), the plugins it requires before it, otherwise in name order.
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

			foreach (array_keys($manifest->require) as $name) {
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
