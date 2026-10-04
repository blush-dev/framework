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

namespace Blush\Plugin;

use Blush\Core\Framework;
use Blush\Extension\ExtensionName;

/**
 * What a plugin's `require` key names (D-385, D-418): Blush itself (its
 * package, `blush-dev/framework`), PHP (`php`), a PHP extension
 * (`ext-{name}`), another plugin (its `vendor/name`), or something Blush
 * can't check.
 */
enum RequirementKind: string
{
	case Blush     = 'blush';
	case Php       = 'php';
	case Extension = 'extension';
	case Plugin    = 'plugin';
	case Unknown   = 'unknown';

	/**
	 * The kind a `require` key names.
	 */
	public static function of(string $name): self
	{
		return match (true) {
			$name === Framework::PACKAGE                    => self::Blush,
			$name === 'php'                                 => self::Php,
			preg_match('/^ext-[A-Za-z0-9_]+$/', $name) === 1 => self::Extension,
			ExtensionName::isValid($name)                   => self::Plugin,
			default                                         => self::Unknown
		};
	}
}
