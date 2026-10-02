<?php

/**
 * Plugin requirements.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Closure;
use Blush\Core\Framework;
use Blush\Extension\VersionConstraint;

/**
 * Checks plugins' `requires` against the site (D-385), with
 * Composer-style constraints (`VersionConstraint`):
 *
 * - `blush` and `php`: the versions the site runs.
 * - `ext-{name}`: a PHP extension that's loaded, at a version that fits.
 * - `vendor/name`: another plugin, installed at a version that fits, and
 *   running. One that's off, or can't run itself, isn't met, so turning
 *   a plugin off stops the ones that need it.
 * - Anything else isn't met: Blush can't check it.
 *
 * `settle()` decides which enabled plugins run: those whose requirements
 * are met by the site and by the others that run.
 */
final readonly class PluginRequirements
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
	 * Checks a plugin's requirements.
	 *
	 * @param  array<string, PluginManifest> $installed Every installed plugin, by name.
	 * @param  array<string, true>           $running   The plugins that run, by name.
	 * @param  array<string, true>           $blocked   The enabled plugins that can't run, by name.
	 * @return list<Requirement>
	 */
	public function check(PluginManifest $plugin, array $installed, array $running, array $blocked = []): array
	{
		$requirements = [];

		foreach ($plugin->requires as $name => $constraint) {
			$requirements[] = $this->requirement($name, $constraint, $installed, $running, $blocked);
		}

		return $requirements;
	}

	/**
	 * The enabled plugins that can't run, by name, each with the
	 * requirements it doesn't meet. The rest run.
	 *
	 * @param  list<PluginManifest>          $enabled
	 * @param  array<string, PluginManifest> $installed Every installed plugin, by name.
	 * @return array<string, list<Requirement>>
	 */
	public function settle(array $enabled, array $installed): array
	{
		$running = [];

		foreach ($enabled as $plugin) {
			$running[$plugin->name] = true;
		}

		// Leave out the plugins whose requirements aren't met until none
		// is left out, since leaving one out can fail another.
		do {
			$changed = false;

			foreach ($enabled as $plugin) {
				if (isset($running[$plugin->name]) && ! self::met($this->check($plugin, $installed, $running))) {
					unset($running[$plugin->name]);
					$changed = true;
				}
			}
		} while ($changed);

		$blocked = [];

		foreach ($enabled as $plugin) {
			if (! isset($running[$plugin->name])) {
				$blocked[$plugin->name] = true;
			}
		}

		$unmet = [];

		foreach ($enabled as $plugin) {
			if (isset($blocked[$plugin->name])) {
				$unmet[$plugin->name] = array_values(array_filter(
					$this->check($plugin, $installed, $running, $blocked),
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
	 * Says why a plugin can't run: `Needs Blush ^3.0 (this site runs
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
	 * Checks one requirement.
	 *
	 * @param array<string, PluginManifest> $installed
	 * @param array<string, true>           $running
	 * @param array<string, true>           $blocked
	 */
	private function requirement(string $name, string $constraint, array $installed, array $running, array $blocked): Requirement
	{
		$kind = RequirementKind::of($name);

		if (! VersionConstraint::isValid($constraint)) {
			return new Requirement($name, $constraint, $kind, false, 'isn\'t a version constraint Blush understands', $installed[$name]->label ?? '');
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

				$met = VersionConstraint::satisfies($version, $constraint);

				return new Requirement($name, $constraint, $kind, $met, $met ? '' : "version {$version} is loaded");

			case RequirementKind::Plugin:
				$plugin = $installed[$name] ?? null;

				if ($plugin === null) {
					return new Requirement($name, $constraint, $kind, false, 'isn\'t installed');
				}

				if (! VersionConstraint::satisfies($plugin->version, $constraint)) {
					return new Requirement($name, $constraint, $kind, false, "version {$plugin->version} is installed", $plugin->label);
				}

				if (isset($running[$name])) {
					return new Requirement($name, $constraint, $kind, true, '', $plugin->label);
				}

				return new Requirement($name, $constraint, $kind, false, isset($blocked[$name]) ? 'can\'t run' : 'is turned off', $plugin->label);

			default:
				return new Requirement($name, $constraint, $kind, false, 'isn\'t something Blush can check');
		}
	}
}
