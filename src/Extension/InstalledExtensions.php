<?php

/**
 * Installed extensions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Core\Paths;
use Blush\Icon\IconPackDiscovery;
use Blush\Plugin\PluginDiscovery;
use Blush\Theme\ThemeDiscovery;

/**
 * Every installed extension's name and namespace, of every kind, whether
 * it's on or off, read fresh from disk. `plugin:new` and `theme:new` ask
 * it whether a new extension's name and namespace are free (D-417), as
 * installing from a zip checks them (D-392).
 */
final readonly class InstalledExtensions
{
	public function __construct(private Paths $paths)
	{}

	/**
	 * Returns why a new extension can't take a name and a namespace,
	 * naming the installed extension of any kind that has one of them, or
	 * `null` when both are free.
	 *
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	public function clash(string $name, string $namespace): ?string
	{
		foreach ($this->all() as [$installedKind, $installedName, $installedNamespace]) {
			if ($installedName === $name) {
				return sprintf('A %s named "%s" is already installed.', $installedKind->label(), $name);
			}

			if ($installedNamespace === $namespace) {
				return sprintf('The "%s" %s already has the namespace "%s"; pass another with --namespace.', $installedName, $installedKind->label(), $namespace);
			}
		}

		return null;
	}

	/**
	 * Returns every installed extension's kind, name, and namespace.
	 *
	 * @return list<array{ExtensionKind, string, string}>
	 * @throws ExtensionException
	 */
	private function all(): array
	{
		$installed = [];

		foreach (PluginDiscovery::forPaths($this->paths)->discover()->manifests as $plugin) {
			$installed[] = [ExtensionKind::Plugin, $plugin->name, $plugin->namespace];
		}

		foreach (new ThemeDiscovery($this->paths)->discover()->all() as $theme) {
			$installed[] = [ExtensionKind::Theme, $theme->name, $theme->namespace];
		}

		foreach (new IconPackDiscovery($this->paths)->discover()->all() as $pack) {
			$installed[] = [ExtensionKind::IconPack, $pack->name, $pack->namespace];
		}

		return $installed;
	}
}
