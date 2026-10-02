<?php

/**
 * Icon name.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Override;
use Stringable;

/**
 * An icon's name, `{namespace}/{name}`, named like components (D-171,
 * D-187): `blush` for the core icons, a theme's, icon pack's, or
 * plugin's namespace (D-378), or `app` for the site's own. A name without a namespace is a
 * core icon (`house` is `blush/house`). The name part is lowercase
 * letters, digits, and hyphens, as icon file names are.
 */
final readonly class IconName implements Stringable
{
	/**
	 * The core icons' namespace.
	 */
	public const string CORE = 'blush';

	/**
	 * The site's own icons' namespace.
	 */
	public const string SITE = 'app';

	public function __construct(
		public string $namespace,
		public string $name
	) {}

	/**
	 * Returns the name a string refers to, or `null` when it isn't one.
	 */
	public static function parse(string $value): ?self
	{
		if (preg_match('#^(?:([A-Za-z][A-Za-z0-9_-]*)/)?([a-z0-9][a-z0-9-]*)$#', trim($value), $match) !== 1) {
			return null;
		}

		return new self($match[1] === '' ? self::CORE : $match[1], $match[2]);
	}

	/**
	 * Returns whether it's a core icon.
	 */
	public function isCore(): bool
	{
		return $this->namespace === self::CORE;
	}

	/**
	 * Returns a label made from the name (`map-pin` → "Map pin"), for when
	 * no translation gives one.
	 */
	public function label(): string
	{
		return ucfirst(str_replace('-', ' ', $this->name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return "{$this->namespace}/{$this->name}";
	}
}
