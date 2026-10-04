<?php

/**
 * Extension manifest interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * What every kind's manifest shares for its requirements (D-431): a
 * plugin's, a theme's, or an icon pack's name, label, version, and
 * `require`, `conflict` (D-435), `replace` (D-436), and `provide`
 * (D-439), which are checked the same way for every kind, and whether
 * it's abandoned (D-433), which warns the same way, and what it
 * suggests (D-434), which is shown the same way.
 */
interface ExtensionManifest
{
	// phpcs:disable PSR2.Classes.PropertyDeclaration, PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess -- PHPCS can't parse property hooks yet.
	/**
	 * The extension's `vendor/name`.
	 */
	public string $name { get; }

	/**
	 * The extension's title.
	 */
	public string $label { get; }

	/**
	 * The extension's version, as its manifest, `composer.json`, or
	 * Composer gives it (`''` when none does).
	 */
	public string $version { get; }

	/**
	 * What it needs, each mapped to a version constraint.
	 *
	 * @var array<string, string>
	 */
	public array $require { get; }

	/**
	 * What it can't run with, each mapped to the versions it can't, as
	 * Composer's `conflict` is (D-435).
	 *
	 * @var array<string, string>
	 */
	public array $conflict { get; }

	/**
	 * The packages it replaces, each mapped to the versions it stands in
	 * for (`self.version` for its own), as Composer's `replace` is
	 * (D-436).
	 *
	 * @var array<string, string>
	 */
	public array $replace { get; }

	/**
	 * The packages it provides an implementation of, each mapped to the
	 * versions it provides (`self.version` for its own), as Composer's
	 * `provide` is (D-439).
	 *
	 * @var array<string, string>
	 */
	public array $provide { get; }

	/**
	 * Whether it's abandoned (`true`), or the name of the package to use
	 * instead, as Composer's `abandoned` is (D-433); `false` when it isn't.
	 */
	public bool|string $abandoned { get; }

	/**
	 * The packages that would work well with it, each mapped to why, as
	 * Composer's `suggest` is (D-434). Only shown, never enforced.
	 *
	 * @var array<string, string>
	 */
	public array $suggest { get; }
	// phpcs:enable

	/**
	 * The extension's kind.
	 */
	public function kind(): ExtensionKind;
}
