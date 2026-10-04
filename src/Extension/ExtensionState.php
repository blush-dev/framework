<?php

/**
 * Extension state.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Icon\IconConfig;
use Blush\Icon\IconPacks;
use Blush\Plugin\BrokenPlugin;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;

/**
 * Which extensions run, with every kind's `require` enforced the same
 * way (D-431): the plugins and icon packs that are on, and the active
 * theme's chain, are settled together (`Requirements::settle()`), since
 * any of them may require any other by its `vendor/name`.
 *
 * - A plugin whose requirements aren't met doesn't run (D-385).
 * - An icon pack whose requirements aren't met doesn't load.
 * - A chain runs whole or not at all: when a theme in the active chain
 *   has a requirement that isn't met, the default theme runs in its
 *   place (`Themes::running()`).
 *
 * Bound in the container at boot, so the admin and commands can check
 * any extension against what runs (`check()`), as if it were on, and
 * see what would change with other choices (`with()`).
 */
final readonly class ExtensionState
{
	/**
	 * Every installed extension, of every kind, by name.
	 *
	 * @var array<string, ExtensionManifest>
	 */
	private array $installed;

	/**
	 * The extensions that run, by name.
	 *
	 * @var array<string, true>
	 */
	private array $running;

	/**
	 * The extensions that are on (or the active chain) but can't run, by
	 * name.
	 *
	 * @var array<string, true>
	 */
	private array $blocked;

	/**
	 * @param string $theme The active theme, as config names it.
	 */
	public function __construct(
		public Plugins $plugins,
		public Themes $themes,
		public IconPacks $packs,
		public PluginConfig $config,
		public string $theme,
		private Requirements $requirements = new Requirements()
	) {
		$this->installed = self::index($plugins->installed(), $themes, $packs);

		$running = [];

		// The default theme runs even when the active chain can't be built.
		$chain = self::chain($themes, $themes->running($theme)) ?: self::chain($themes, Themes::DEFAULT);

		foreach ([...$plugins->all(), ...array_values($packs->enabled()), ...$chain] as $extension) {
			$running[$extension->name] = true;
		}

		$this->running = $running;
		$this->blocked = array_fill_keys(array_map(strval(...), array_keys([...$plugins->unmet(), ...$themes->unmet(), ...$packs->unmet()])), true);
	}

	/**
	 * Settles which extensions run: the plugins config turns on, the icon
	 * packs that are on, and the active theme's chain, each only when its
	 * requirements are met by the site and by the others that run.
	 *
	 * @param  list<PluginManifest> $plugins Every installed plugin.
	 * @param  list<BrokenPlugin>   $broken  The plugins whose manifests can't be read.
	 * @param  IconPacks            $packs   With config saying which are on.
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public static function settle(
		array $plugins,
		array $broken,
		PluginConfig $config,
		Themes $themes,
		string $theme,
		IconPacks $packs,
		Requirements $requirements = new Requirements()
	): self {
		$chain   = self::chain($themes, $theme);
		$group   = array_values(array_diff(array_map(static fn (ThemeManifest $member): string => $member->name, $chain), [Themes::DEFAULT]));
		$enabled = [...array_values(array_filter($plugins, $config->isEnabled(...))), ...array_values($packs->on()), ...$chain];

		$installed = self::index($plugins, $themes, $packs);
		$unmet     = [ExtensionKind::Plugin->value => [], ExtensionKind::Theme->value => [], ExtensionKind::IconPack->value => []];

		foreach ($requirements->settle($enabled, $installed, $group === [] ? [] : [$group]) as $name => $requirementsUnmet) {
			$kind = ($installed[$name] ?? null)?->kind();

			if ($kind !== null) {
				$unmet[$kind->value][$name] = $requirementsUnmet;
			}
		}

		return new self(
			Plugins::enabled($plugins, $config, $requirements, $broken, $unmet[ExtensionKind::Plugin->value]),
			$themes->withUnmet($unmet[ExtensionKind::Theme->value]),
			$packs->withUnmet($unmet[ExtensionKind::IconPack->value]),
			$config,
			$theme,
			$requirements
		);
	}

	/**
	 * Settles again with other choices: other plugins on, another active
	 * theme, other packs on, or freshly discovered plugins.
	 *
	 * @param  ?array{list<PluginManifest>, list<BrokenPlugin>} $discovered Every installed plugin, and the broken ones.
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public function with(?PluginConfig $plugins = null, ?string $theme = null, ?IconConfig $icons = null, ?array $discovered = null): self
	{
		[$manifests, $broken] = $discovered ?? [$this->plugins->installed(), $this->plugins->broken()];

		return self::settle(
			$manifests,
			$broken,
			$plugins ?? $this->config,
			$this->themes,
			$theme ?? $this->theme,
			$icons === null ? $this->packs : $this->packs->withConfig($icons),
			$this->requirements
		);
	}

	/**
	 * Every installed extension, of every kind, by name.
	 *
	 * @return array<string, ExtensionManifest>
	 */
	public function installed(): array
	{
		return $this->installed;
	}

	/**
	 * Whether an extension runs: a plugin or pack that's on with its
	 * requirements met, or a theme in the chain that runs.
	 */
	public function runs(string $name): bool
	{
		return isset($this->running[$name]);
	}

	/**
	 * The extensions that run, by name.
	 *
	 * @return array<string, true>
	 */
	public function running(): array
	{
		return $this->running;
	}

	/**
	 * Checks an extension's requirements against what runs, as if it
	 * were on.
	 *
	 * @return list<Requirement>
	 */
	public function check(ExtensionManifest $extension): array
	{
		return $this->requirements->check($extension, $this->installed, $this->running, $this->blocked);
	}

	/**
	 * An extension's requirements as the admin answers them, the same
	 * for every kind: `requirements` (each checked as if it were on),
	 * `blocked` (why it can't run, or `null`), and `requiredBy` (the
	 * extensions of every kind that require it).
	 *
	 * @return array{requirements: list<array{name: string, constraint: string, kind: string, met: bool, note: string, label: string}>, blocked: ?string, requiredBy: list<array{name: string, label: string, kind: string}>}
	 */
	public function report(ExtensionManifest $extension): array
	{
		$checked = $this->check($extension);

		return [
			'requirements' => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), $checked),
			'blocked'      => Requirements::met($checked) ? null : Requirements::reason($checked),
			'requiredBy'   => array_map(static fn (ExtensionManifest $other): array => ['name' => $other->name, 'label' => $other->label, 'kind' => $other->kind()->value], $this->requiredBy($extension->name))
		];
	}

	/**
	 * The installed extensions, of every kind, whose `require` names an
	 * extension.
	 *
	 * @return list<ExtensionManifest>
	 */
	public function requiredBy(string $name): array
	{
		return array_values(array_filter($this->installed, static fn (ExtensionManifest $other): bool => array_key_exists($name, $other->require)));
	}

	/**
	 * The extensions that run in this state but not another, leaving out
	 * the one named: what starts, from the state before a change to the
	 * one after it, or what stops, the other way.
	 *
	 * @return list<ExtensionManifest>
	 */
	public function runningNotIn(self $other, string $except = ''): array
	{
		return array_values(array_filter(
			$this->installed,
			fn (ExtensionManifest $extension): bool => $extension->name !== $except && $this->runs($extension->name) && ! $other->runs($extension->name)
		));
	}

	/**
	 * Every installed extension, of every kind, by name. Names are each
	 * one extension's (`Bootstrap` leaves out a theme or pack whose name
	 * another kind has); the first kind found keeps one that isn't.
	 *
	 * @param  list<PluginManifest>             $plugins
	 * @return array<string, ExtensionManifest>
	 */
	private static function index(array $plugins, Themes $themes, IconPacks $packs): array
	{
		$installed = [];

		foreach ([...$plugins, ...array_values($themes->all()), ...array_values($packs->all())] as $extension) {
			$installed[$extension->name] ??= $extension;
		}

		ksort($installed);

		return $installed;
	}

	/**
	 * A theme's chain's manifests, or none when it can't be built (a page
	 * reports why, as does `theme:check`).
	 *
	 * @return list<ThemeManifest>
	 */
	private static function chain(Themes $themes, string $theme): array
	{
		try {
			return $themes->chain($theme)->themes;
		} catch (ThemeException) {
			return [];
		}
	}
}
