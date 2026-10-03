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

namespace Blush\Admin;

use Blush\Core\Paths;
use Blush\Extension\ExtensionKind;
use Blush\Icon\IconPacks;
use Blush\Icon\IconPackSource;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSource;

/**
 * What installing and rolling back ask of the extensions this request
 * booted with (D-392, D-393), for every kind: where a folder extension
 * is, and whether one runs.
 */
final readonly class InstalledExtensions
{
	public function __construct(
		private Paths $paths,
		private Plugins $plugins,
		private Themes $themes,
		private ThemeConfig $theme,
		private IconPacks $packs
	) {}

	/**
	 * The folder of an extension in its kind's folder in `user/`, or
	 * `null` when there's none (it isn't installed, or Composer or the
	 * framework has it).
	 */
	public function folder(ExtensionKind $kind, string $name): ?string
	{
		$path = match ($kind) {
			ExtensionKind::Plugin   => array_find($this->plugins->installed(), static fn (PluginManifest $plugin): bool => $plugin->name === $name && $plugin->source === PluginSource::Local)?->path,
			ExtensionKind::Theme    => ($theme = $this->themes->find($name)) !== null && $theme->source === ThemeSource::Local ? $theme->path : null,
			ExtensionKind::IconPack => ($pack = $this->packs->find($name)) !== null && $pack->source === IconPackSource::Local ? $pack->path : null
		};

		return $path !== null && dirname($path) === $kind->folder($this->paths) ? $path : null;
	}

	/**
	 * Whether an extension runs on this site: a running plugin, a theme in
	 * the active chain, or a pack that's on.
	 */
	public function live(ExtensionKind $kind, string $name): bool
	{
		try {
			return match ($kind) {
				ExtensionKind::Plugin   => $this->plugins->has($name),
				ExtensionKind::Theme    => in_array($name, $this->themes->chain($this->theme->active)->names(), true),
				ExtensionKind::IconPack => $this->packs->isEnabled($name)
			};
		} catch (ThemeException) {
			return false;
		}
	}
}
