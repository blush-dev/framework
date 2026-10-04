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
 * `require`, which are checked the same way for every kind, and whether
 * it's abandoned (D-433), which warns the same way.
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
	 * Whether it's abandoned (`true`), or the name of the package to use
	 * instead, as Composer's `abandoned` is (D-433); `false` when it isn't.
	 */
	public bool|string $abandoned { get; }
	// phpcs:enable

	/**
	 * The extension's kind.
	 */
	public function kind(): ExtensionKind;
}
