<?php

/**
 * Directive variant.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use InvalidArgumentException;

/**
 * A named look (or behavior) of a directive (D-266): `variant=bordered`
 * in Markdown. Its registrant is the namespace whose catalog has its text
 * (`directives.{name}.variants.{variant}.label` and `.description`):
 * core (`blush`), a theme's or plugin's namespace (D-378), or the site
 * (`app`); a theme's variants apply only while it's in the chain.
 *
 * It adds the root element's modifier class, `directive-{name}--{name}`,
 * unless it names another `modifier`. Every directive also has Default,
 * which isn't a `Variant`: it adds nothing and can't be registered.
 */
final readonly class Variant
{
	/**
	 * The name that means no variant.
	 */
	public const string DEFAULT = 'default';

	/**
	 * What a variant's name (and modifier) may be.
	 */
	public const string SYNTAX = '[a-z][a-z0-9-]*';

	/**
	 * @throws InvalidArgumentException When a name or modifier isn't valid, or the name is `default`.
	 */
	public function __construct(
		public string $name,
		public string $registrant,
		public ?string $modifier = null
	) {
		if (! self::isValidName($name)) {
			throw new InvalidArgumentException(sprintf('"%s" isn\'t a valid variant name: lowercase letters, digits, and hyphens, starting with a letter, and not "%s".', $name, self::DEFAULT));
		}

		if ($modifier !== null && preg_match('/^' . self::SYNTAX . '$/', $modifier) !== 1) {
			throw new InvalidArgumentException(sprintf('"%s" isn\'t a valid modifier for the "%s" variant.', $modifier, $name));
		}
	}

	/**
	 * Returns whether a name may be a variant's.
	 */
	public static function isValidName(string $name): bool
	{
		return $name !== self::DEFAULT && preg_match('/^' . self::SYNTAX . '$/', $name) === 1;
	}

	/**
	 * Returns the BEM modifier it adds: its `modifier`, else its name.
	 */
	public function modifier(): string
	{
		return $this->modifier ?? $this->name;
	}
}
