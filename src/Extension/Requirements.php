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
 * `settle()` decides which enabled extensions run: those whose
 * requirements are met by the site and by the others that run.
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
	 * Checks an extension's requirements.
	 *
	 * @param  array<string, ExtensionManifest> $installed Every installed extension, of every kind, by name.
	 * @param  array<string, true>              $running   The extensions that run, by name.
	 * @param  array<string, true>              $blocked   The enabled extensions that can't run, by name.
	 * @return list<Requirement>
	 */
	public function check(ExtensionManifest $extension, array $installed, array $running, array $blocked = []): array
	{
		$requirements = [];

		foreach ($extension->require as $name => $constraint) {
			$requirements[] = $this->requirement($name, $constraint, $installed, $running, $blocked);
		}

		return $requirements;
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

		// Leave out the extensions whose requirements aren't met until
		// none is left out, since leaving one out can fail another.
		do {
			$changed = false;

			foreach ($enabled as $extension) {
				if (isset($running[$extension->name]) && ! self::met($this->check($extension, $installed, $running))) {
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
					$this->check($extension, $installed, $running, $blocked),
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
	 * 2.0.0), and Shop ^2.0 (is turned off).`
	 *
	 * @param list<Requirement> $requirements
	 */
	public static function reason(array $requirements): string
	{
		$unmet = array_filter($requirements, static fn (Requirement $requirement): bool => ! $requirement->met);

		return 'Needs ' . implode(', and ', array_map(static fn (Requirement $requirement): string => $requirement->describe(), $unmet)) . '.';
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
				$version = ($this->extension)(substr($name, 4));

				if ($version === false) {
					return new Requirement($name, $constraint, $kind, false, 'isn\'t loaded');
				}

				// As Composer reads an extension's version it can't
				// normalize: its leading numbers, or `0`.
				if (VersionConstraint::normalize($version) === null) {
					$version = preg_match('/^(\d+\.\d+\.\d+(?:\.\d+)?)/', $version, $match) === 1 ? $match[1] : '0';
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
