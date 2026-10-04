<?php

/**
 * Extension requirements.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Closure;
use Blush\Core\Framework;

/**
 * Checks extensions' `require` against the site, the same for every
 * kind (D-385, D-431), with Composer's constraints and rules
 * (`VersionConstraint`, D-429):
 *
 * - `blush-dev/framework` and `php`: the versions the site runs.
 * - `ext-{name}`: a PHP extension that's loaded, at a version that fits.
 * - `vendor/name`: another plugin, theme, or icon pack, installed at a
 *   version that fits, and running: a plugin or pack that's on and can
 *   run, or a theme in the active chain. One that's off, or can't run
 *   itself, isn't met, so turning one off stops the ones that need it.
 * - Anything else isn't met: Blush can't check it.
 *
 * Its `conflict` (D-435) is checked with them, each met unless the site
 * has what it names on at a version it names: `blush-dev/framework`,
 * `php`, or a loaded `ext-{name}`, or another extension that's turned on
 * (a plugin or pack that's on, or a theme in the active chain), whether
 * or not that one can run. So the one declaring a conflict is the one
 * that stops. An invalid constraint is a conflict, as it's an unmet
 * requirement; a name that isn't installed, or that Blush can't check,
 * conflicts with nothing.
 *
 * `settle()` decides which enabled extensions run: those whose
 * requirements are met by the site and by the others that run, and that
 * conflict with nothing that's on.
 */
final readonly class Requirements
{
	/**
	 * The version of a loaded PHP extension, or `false` when it isn't.
	 *
	 * @var Closure(string): (string|false)
	 */
	private Closure $extension;

	/**
	 * @param ?Closure(string): (string|false) $extension
	 */
	public function __construct(
		public string $blush = Framework::VERSION,
		public string $php = PHP_VERSION,
		?Closure $extension = null
	) {
		$this->extension = $extension ?? static fn (string $name): string|false => extension_loaded($name) ? (phpversion($name) ?: '0.0.0') : false;
	}

	/**
	 * Checks an extension's requirements, then its conflicts.
	 *
	 * @param  array<string, ExtensionManifest> $installed Every installed extension, of every kind, by name.
	 * @param  array<string, true>              $running   The extensions that run, by name.
	 * @param  array<string, true>              $blocked   The enabled extensions that can't run, by name.
	 * @param  ?array<string, true>             $on        The extensions that are turned on, by name, for conflicts (`$running` and `$blocked` without it).
	 * @return list<Requirement>
	 */
	public function check(ExtensionManifest $extension, array $installed, array $running, array $blocked = [], ?array $on = null): array
	{
		$requirements = [];
		$on         ??= $running + $blocked;

		foreach ($extension->require as $name => $constraint) {
			$requirements[] = $this->requirement($name, $constraint, $installed, $running, $blocked);
		}

		foreach ($extension->conflict as $name => $constraint) {
			if ($name !== $extension->name) {
				$requirements[] = $this->conflict($name, $constraint, $installed, $on);
			}
		}

		return $requirements;
	}

	/**
	 * Whether a requirement is a conflict (D-435).
	 */
	public static function isConflict(Requirement $requirement): bool
	{
		return $requirement->conflict;
	}

	/**
	 * The enabled extensions that can't run, by name, each with the
	 * requirements it doesn't meet. The rest run. The extensions in a
	 * group run together or not at all (a theme's chain): one in a group
	 * that's left out leaves out the rest, which are listed with their
	 * own requirements that aren't met, if any.
	 *
	 * @param  list<ExtensionManifest>          $enabled
	 * @param  array<string, ExtensionManifest> $installed Every installed extension, of every kind, by name.
	 * @param  list<list<string>>               $groups    Names that run together.
	 * @return array<string, list<Requirement>>
	 */
	public function settle(array $enabled, array $installed, array $groups = []): array
	{
		$running = [];

		foreach ($enabled as $extension) {
			$running[$extension->name] = true;
		}

		// Conflicts are judged against what's on, which doesn't change
		// here, so leaving one out never lets another back in (D-435).
		$on = $running;

		// Leave out the extensions whose requirements aren't met until
		// none is left out, since leaving one out can fail another.
		do {
			$changed = false;

			foreach ($enabled as $extension) {
				if (isset($running[$extension->name]) && ! self::met($this->check($extension, $installed, $running, [], $on))) {
					unset($running[$extension->name]);
					$changed = true;

					foreach ($groups as $group) {
						if (in_array($extension->name, $group, true)) {
							$running = array_diff_key($running, array_flip($group));
						}
					}
				}
			}
		} while ($changed);

		$blocked = [];

		foreach ($enabled as $extension) {
			if (! isset($running[$extension->name])) {
				$blocked[$extension->name] = true;
			}
		}

		$unmet = [];

		foreach ($enabled as $extension) {
			if (isset($blocked[$extension->name])) {
				$unmet[$extension->name] = array_values(array_filter(
					$this->check($extension, $installed, $running, $blocked, $on),
					static fn (Requirement $requirement): bool => ! $requirement->met
				));
			}
		}

		return $unmet;
	}

	/**
	 * Whether every requirement is met.
	 *
	 * @param list<Requirement> $requirements
	 */
	public static function met(array $requirements): bool
	{
		return array_all($requirements, static fn (Requirement $requirement): bool => $requirement->met);
	}

	/**
	 * Says why an extension can't run: `Needs Blush ^3.0 (this site runs
	 * 2.0.0), and Shop ^2.0 (is turned off).`, then any conflicts:
	 * `Conflicts with Shop <2.0 (version 1.5.0 is on).`
	 *
	 * @param list<Requirement> $requirements
	 */
	public static function reason(array $requirements): string
	{
		$unmet     = array_filter($requirements, static fn (Requirement $requirement): bool => ! $requirement->met);
		$describe  = static fn (Requirement $requirement): string => $requirement->describe();
		$needs     = array_filter($unmet, static fn (Requirement $requirement): bool => ! $requirement->conflict);
		$conflicts = array_filter($unmet, self::isConflict(...));
		$sentences = [];

		if ($needs !== []) {
			$sentences[] = 'Needs ' . implode(', and ', array_map($describe, $needs)) . '.';
		}

		if ($conflicts !== []) {
			$sentences[] = 'Conflicts with ' . implode(', and ', array_map($describe, $conflicts)) . '.';
		}

		return implode(' ', $sentences);
	}

	/**
	 * The version an extension is compared at: its own, or `0.0.0` when
	 * it gives none.
	 */
	public static function version(ExtensionManifest $extension): string
	{
		return $extension->version !== '' ? $extension->version : '0.0.0';
	}

	/**
	 * Checks one requirement.
	 *
	 * @param array<string, ExtensionManifest> $installed
	 * @param array<string, true>              $running
	 * @param array<string, true>              $blocked
	 */
	private function requirement(string $name, string $constraint, array $installed, array $running, array $blocked): Requirement
	{
		$other = $installed[$name] ?? null;
		$kind  = RequirementKind::of($name, $other);
		$label = $other->label ?? '';

		if (! VersionConstraint::isValid($constraint)) {
			return new Requirement($name, $constraint, $kind, false, 'isn\'t a version constraint Blush understands', $label);
		}

		if ($other !== null && $kind !== RequirementKind::Blush && $kind !== RequirementKind::Php && $kind !== RequirementKind::Extension) {
			return $this->extension($other, $constraint, $kind, $running, $blocked);
		}

		switch ($kind) {
			case RequirementKind::Blush:
			case RequirementKind::Php:
				$version = $kind === RequirementKind::Blush ? $this->blush : $this->php;

				return new Requirement($name, $constraint, $kind, VersionConstraint::satisfies($version, $constraint), "this site runs {$version}");

			case RequirementKind::Extension:
				$version = $this->loaded($name);

				if ($version === false) {
					return new Requirement($name, $constraint, $kind, false, 'isn\'t loaded');
				}

				$met = VersionConstraint::satisfies($version, $constraint);

				return new Requirement($name, $constraint, $kind, $met, $met ? '' : "version {$version} is loaded");

			case RequirementKind::Missing:
				return new Requirement($name, $constraint, $kind, false, 'isn\'t installed');

			default:
				return new Requirement($name, $constraint, $kind, false, 'isn\'t something Blush can check');
		}
	}

	/**
	 * Checks one conflict (D-435): met unless what it names is on at a
	 * version it names.
	 *
	 * @param array<string, ExtensionManifest> $installed
	 * @param array<string, true>              $on
	 */
	private function conflict(string $name, string $constraint, array $installed, array $on): Requirement
	{
		$other = $installed[$name] ?? null;
		$kind  = RequirementKind::of($name, $other);
		$label = $other->label ?? '';

		if (! VersionConstraint::isValid($constraint)) {
			return new Requirement($name, $constraint, $kind, false, 'isn\'t a version constraint Blush understands', $label, true);
		}

		if ($other !== null && $kind !== RequirementKind::Blush && $kind !== RequirementKind::Php && $kind !== RequirementKind::Extension) {
			$version = self::version($other);

			if (! isset($on[$name])) {
				return new Requirement($name, $constraint, $kind, true, $kind === RequirementKind::Theme ? 'isn\'t active' : 'is turned off', $label, true);
			}

			$hit = VersionConstraint::satisfies($version, $constraint);

			return new Requirement($name, $constraint, $kind, ! $hit, $hit ? sprintf('version %s is %s', $version, $kind === RequirementKind::Theme ? 'active' : 'on') : "version {$version} is installed", $label, true);
		}

		switch ($kind) {
			case RequirementKind::Blush:
			case RequirementKind::Php:
				$version = $kind === RequirementKind::Blush ? $this->blush : $this->php;

				return new Requirement($name, $constraint, $kind, ! VersionConstraint::satisfies($version, $constraint), "this site runs {$version}", conflict: true);

			case RequirementKind::Extension:
				$version = $this->loaded($name);

				if ($version === false) {
					return new Requirement($name, $constraint, $kind, true, 'isn\'t loaded', conflict: true);
				}

				return new Requirement($name, $constraint, $kind, ! VersionConstraint::satisfies($version, $constraint), "version {$version} is loaded", conflict: true);

			case RequirementKind::Missing:
				return new Requirement($name, $constraint, $kind, true, 'isn\'t installed', conflict: true);

			default:
				return new Requirement($name, $constraint, $kind, true, 'isn\'t something Blush can check', conflict: true);
		}
	}

	/**
	 * The version of a loaded PHP extension (`ext-{name}`), or `false`
	 * when it isn't. One Composer can't normalize is read as Composer
	 * reads it: its leading numbers, or `0`.
	 */
	private function loaded(string $name): string|false
	{
		$version = ($this->extension)(substr($name, 4));

		if ($version !== false && VersionConstraint::normalize($version) === null) {
			$version = preg_match('/^(\d+\.\d+\.\d+(?:\.\d+)?)/', $version, $match) === 1 ? $match[1] : '0';
		}

		return $version;
	}

	/**
	 * Checks a requirement of another installed extension.
	 *
	 * @param array<string, true> $running
	 * @param array<string, true> $blocked
	 */
	private function extension(ExtensionManifest $other, string $constraint, RequirementKind $kind, array $running, array $blocked): Requirement
	{
		$name    = $other->name;
		$version = self::version($other);

		if (! VersionConstraint::satisfies($version, $constraint)) {
			return new Requirement($name, $constraint, $kind, false, "version {$version} is installed", $other->label);
		}

		if (isset($running[$name])) {
			return new Requirement($name, $constraint, $kind, true, '', $other->label);
		}

		return new Requirement($name, $constraint, $kind, false, match (true) {
			isset($blocked[$name])            => 'can\'t run',
			$kind === RequirementKind::Theme => 'isn\'t active',
			default                          => 'is turned off'
		}, $other->label);
	}
}
