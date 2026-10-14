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

use Blush\Admin\Theme\AdminThemeDiscovery;
use Blush\Core\Paths;
use Blush\Icon\IconPackDiscovery;
use Blush\Plugin\PluginDiscovery;
use Blush\Theme\ThemeDiscovery;
use Blush\Translation\Messages;

/**
 * Every installed extension's name and namespace, of every kind, whether
 * it's on or off, read fresh from disk. `plugin:new` and `theme:new` ask
 * it whether a new extension's name and namespace are free (D-417), as
 * installing from a zip checks them (D-392).
 */
final readonly class InstalledExtensions
{
	public function __construct(
		private Paths $paths,
		private Messages $messages
	) {}

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
				return (string) $this->messages->t('extension.installed_extensions.name_taken', '{kind, select, Plugin {A plugin} Theme {A theme} IconPack {An icon pack} other {An admin theme}} named "{name}" is already installed.', kind: $installedKind->name, name: $name);
			}

			if ($installedNamespace !== '' && $installedNamespace === $namespace) {
				return (string) $this->messages->t('extension.installed_extensions.namespace_taken', 'The "{name}" {kind, select, Plugin {plugin} Theme {theme} IconPack {icon pack} other {admin theme}} already has the namespace "{namespace}"; pass another with --namespace.', name: $installedName, kind: $installedKind->name, namespace: $namespace);
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

		foreach (PluginDiscovery::forPaths($this->paths, $this->messages)->discover()->manifests as $plugin) {
			$installed[] = [ExtensionKind::Plugin, $plugin->name, $plugin->namespace];
		}

		foreach (new ThemeDiscovery($this->paths, $this->messages)->discover()->all() as $theme) {
			$installed[] = [ExtensionKind::Theme, $theme->name, $theme->namespace];
		}

		foreach (new IconPackDiscovery($this->paths, $this->messages)->discover()->all() as $pack) {
			$installed[] = [ExtensionKind::IconPack, $pack->name, $pack->namespace];
		}

		// An admin theme has a name but no namespace (D-827).
		foreach (new AdminThemeDiscovery($this->paths)->discover()->all() as $adminTheme) {
			$installed[] = [ExtensionKind::AdminTheme, $adminTheme->name, ''];
		}

		return $installed;
	}
}
