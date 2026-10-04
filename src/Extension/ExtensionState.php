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
	 * `conflicts` (D-435, each met when it doesn't conflict with what's
	 * on), `replaces` (D-436, each met when what it replaces isn't on),
	 * `provides` (D-439, each `{"name", "constraint"}`, `self.version`
	 * resolved),
	 * `blocked` (why it can't run, or `null`), and `requiredBy` (the
	 * extensions of every kind that require it); and `abandoned` (`false`,
	 * `true`, or the package to use instead, D-433), with the
	 * `replacement` when it's an installed extension, or `null`; and what
	 * it `suggests` (D-434), each with the `extension` when it's an
	 * installed one, and whether a PHP extension is `loaded` (`null` for
	 * anything else).
	 *
	 * @return array{requirements: list<array{name: string, constraint: string, kind: string, met: bool, note: string, label: string, metBy: string}>, conflicts: list<array{name: string, constraint: string, kind: string, met: bool, note: string, label: string, metBy: string}>, replaces: list<array{name: string, constraint: string, kind: string, met: bool, note: string, label: string, metBy: string}>, provides: list<array{name: string, constraint: string}>, blocked: ?string, requiredBy: list<array{name: string, label: string, kind: string}>, abandoned: bool|string, replacement: ?array{name: string, label: string, kind: string}, suggests: list<array{name: string, reason: string, extension: ?array{name: string, label: string, kind: string}, loaded: ?bool}>}
	 */
	public function report(ExtensionManifest $extension): array
	{
		$checked = $this->check($extension);

		return [
			'requirements' => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), array_values(array_filter($checked, static fn (Requirement $requirement): bool => ! $requirement->conflict))),
			'conflicts'    => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), array_values(array_filter($checked, Requirements::isConflict(...)))),
			'replaces'     => array_map(static fn (Requirement $requirement): array => $requirement->toArray(), array_values(array_filter($checked, Requirements::isReplace(...)))),
			'provides'     => array_map(static fn (string $name): array => ['name' => $name, 'constraint' => Requirements::provided($extension, $name, 'provide') ?? ''], array_map(strval(...), array_keys($extension->provide))),
			'blocked'      => Requirements::met($checked) ? null : Requirements::reason($checked),
			'requiredBy'   => array_map(self::describe(...), $this->requiredBy($extension->name)),
			'abandoned'    => $extension->abandoned,
			'replacement'  => is_string($extension->abandoned) && isset($this->installed[$extension->abandoned]) ? self::describe($this->installed[$extension->abandoned]) : null,
			'suggests'     => $this->suggests($extension)
		];
	}

	/**
	 * What an extension suggests (D-434), each with the installed
	 * extension it names, if any, and whether a PHP extension it names
	 * is loaded. Nothing else is looked up: a `vendor/name` that isn't an
	 * extension may be a library Composer has installed.
	 *
	 * @return list<array{name: string, reason: string, extension: ?array{name: string, label: string, kind: string}, loaded: ?bool}>
	 */
	private function suggests(ExtensionManifest $extension): array
	{
		$suggests = [];

		foreach ($extension->suggest as $name => $reason) {
			$installed = $this->installed[$name] ?? null;

			$suggests[] = [
				'name'      => $name,
				'reason'    => $reason,
				'extension' => $installed === null ? null : self::describe($installed),
				'loaded'    => str_starts_with($name, 'ext-') ? extension_loaded(substr($name, 4)) : null
			];
		}

		return $suggests;
	}

	/**
	 * An extension as the admin names and links to it.
	 *
	 * @return array{name: string, label: string, kind: string}
	 */
	private static function describe(ExtensionManifest $extension): array
	{
		return ['name' => $extension->name, 'label' => $extension->label, 'kind' => $extension->kind()->value];
	}

	/**
	 * The installed extensions, of every kind, whose `require` names an
	 * extension, or a package it replaces (D-436) or provides (D-439).
	 *
	 * @return list<ExtensionManifest>
	 */
	public function requiredBy(string $name): array
	{
		$extension = $this->installed[$name] ?? null;
		$names     = [$name, ...array_map(strval(...), array_keys([...$extension->replace ?? [], ...$extension->provide ?? []]))];

		return array_values(array_filter($this->installed, static fn (ExtensionManifest $other): bool => $other->name !== $name && array_intersect($names, array_keys($other->require)) !== []));
	}

	/**
	 * The other side of package links (D-440): the installed extensions
	 * whose `conflict` hits an extension at its version (naming it, or a
	 * package it replaces or provides, as `Requirements` judges them), the
	 * ones that `replace` it, and the ones that `provide` it, each as the
	 * admin names and links to it.
	 *
	 * @return array{conflictedBy: list<array{name: string, label: string, kind: string}>, replacedBy: list<array{name: string, label: string, kind: string}>, providedBy: list<array{name: string, label: string, kind: string}>}
	 */
	public function opposite(ExtensionManifest $extension): array
	{
		$conflicted = [];
		$replaced   = [];
		$provided   = [];
		$version    = Requirements::version($extension);

		foreach ($this->installed as $other) {
			if ($other->name === $extension->name) {
				continue;
			}

			foreach ($other->conflict as $name => $constraint) {
				$stands = $name === $extension->name
					? VersionConstraint::satisfies($version, $constraint)
					: array_any(['replace', 'provide'], static fn (string $link): bool => ($in = Requirements::provided($extension, (string) $name, $link)) !== null && VersionConstraint::matches($constraint, $in));

				if ($stands) {
					$conflicted[] = self::describe($other);

					break;
				}
			}

			if (array_key_exists($extension->name, $other->replace)) {
				$replaced[] = self::describe($other);
			}

			if (array_key_exists($extension->name, $other->provide)) {
				$provided[] = self::describe($other);
			}
		}

		return ['conflictedBy' => $conflicted, 'replacedBy' => $replaced, 'providedBy' => $provided];
	}

	/**
	 * What turning on an extension that doesn't run would stop (D-440):
	 * a plugin or pack turned on as well, or a theme activated, settled as
	 * saving it would be, so it takes in what stops because of what stops.
	 * The themes an activated theme takes the place of aren't listed.
	 * Nothing for one that runs.
	 *
	 * @return list<array{name: string, label: string, kind: string}>
	 * @throws ExtensionException When config enables a plugin that isn't installed.
	 */
	public function stops(ExtensionManifest $extension): array
	{
		if ($this->runs($extension->name)) {
			return [];
		}

		$after = match ($extension->kind()) {
			ExtensionKind::Plugin   => $this->with($this->config->with($extension->name)),
			ExtensionKind::IconPack => $this->with(icons: $this->packs->configWith($extension->name)),
			ExtensionKind::Theme    => $this->with(theme: $extension->name)
		};

		$stopped = array_filter(
			$this->runningNotIn($after, $extension->name),
			static fn (ExtensionManifest $other): bool => $extension->kind() !== ExtensionKind::Theme || $other->kind() !== ExtensionKind::Theme
		);

		return array_values(array_map(self::describe(...), $stopped));
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
