<?php

/**
 * Requirement kind enum.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use Blush\Core\Framework;

/**
 * What a `require` key names (D-385, D-418, D-431): Blush itself (its
 * package, `blush-dev/framework`), PHP (`php`), a PHP extension
 * (`ext-{name}`), an installed plugin, theme, or icon pack (by its
 * `vendor/name`), a library Composer installed (D-438), a `vendor/name`
 * that isn't installed, something only Composer checks (`lib-*`,
 * `composer-plugin-api`, `composer-runtime-api`, and PHP's build:
 * `php-64bit`, `php-ipv6`, `php-zts`, `php-debug`; D-438), or something
 * Blush can't check.
 *
 * `of()` can't tell a library from a missing package; `Requirements`
 * looks it up in Composer's installed packages.
 */
enum RequirementKind: string
{
	case Blush     = 'blush';
	case Php       = 'php';
	case Extension = 'extension';
	case Plugin    = 'plugin';
	case Theme     = 'theme';
	case IconPack  = 'icon-pack';
	case Library   = 'library';
	case Composer  = 'composer';
	case Missing   = 'missing';
	case Unknown   = 'unknown';

	/**
	 * The kind a `require` key names, given the extension installed at
	 * that name, if any.
	 */
	/**
	 * The platform packages only Composer checks (D-438).
	 */
	private const string COMPOSER = '/^(lib-.+|composer-plugin-api|composer-runtime-api|php-(?:64bit|ipv6|zts|debug))$/';

	public static function of(string $name, ?ExtensionManifest $installed = null): self
	{
		return match (true) {
			$name === Framework::PACKAGE                    => self::Blush,
			$name === 'php'                                 => self::Php,
			preg_match('/^ext-[A-Za-z0-9_]+$/', $name) === 1 => self::Extension,
			preg_match(self::COMPOSER, $name) === 1          => self::Composer,
			$installed !== null                             => self::for($installed->kind()),
			ExtensionName::isValid($name)                   => self::Missing,
			default                                         => self::Unknown
		};
	}

	/**
	 * The kind for an extension kind.
	 */
	public static function for(ExtensionKind $kind): self
	{
		return match ($kind) {
			ExtensionKind::Plugin   => self::Plugin,
			ExtensionKind::Theme    => self::Theme,
			ExtensionKind::IconPack => self::IconPack
		};
	}
}
