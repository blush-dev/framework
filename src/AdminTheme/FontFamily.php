<?php

/**
 * Font family.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

/**
 * A family token's list of names (D-827), written as CSS writes a stack:
 * each name quoted, the generic families and the platforms' system font
 * names bare. A stack that ends without a generic family falls back to
 * the platform's own UI font (`--font-system`).
 */
final readonly class FontFamily
{
	/**
	 * The names written bare.
	 */
	public const array KEYWORDS = [
		'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'math', 'system-ui',
		'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded', '-apple-system', 'BlinkMacSystemFont'
	];

	/**
	 * The generic families, one of which ends a full stack.
	 */
	private const array GENERIC = ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'math', 'system-ui', 'ui-serif', 'ui-sans-serif', 'ui-monospace', 'ui-rounded'];

	/**
	 * @param list<string> $names
	 */
	public function __construct(public array $names)
	{}

	/**
	 * Reads a stack, or returns `null` when it isn't a list of names.
	 */
	public static function parse(mixed $value): ?self
	{
		if (! is_array($value) || ! array_is_list($value) || $value === []) {
			return null;
		}

		$names = [];

		foreach ($value as $name) {
			$name = is_string($name) ? self::name($name) : null;

			if ($name === null) {
				return null;
			}

			$names[] = $name;
		}

		return new self($names);
	}

	/**
	 * Returns a family name trimmed, or `null` when it isn't one:
	 * letters, digits, spaces, hyphens, and underscores.
	 */
	public static function name(string $name): ?string
	{
		$name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

		return preg_match('/^-?[A-Za-z0-9][A-Za-z0-9 _-]*$/', $name) === 1 ? $name : null;
	}

	/**
	 * Returns the stack as CSS writes it.
	 */
	public function css(): string
	{
		$stack = array_map(static fn (string $name): string => in_array($name, self::KEYWORDS, true) ? $name : "\"{$name}\"", $this->names);

		if (array_intersect($this->names, self::GENERIC) === []) {
			$stack[] = 'var(--font-system)';
		}

		return implode(', ', $stack);
	}
}
