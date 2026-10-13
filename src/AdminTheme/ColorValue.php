<?php

/**
 * Color value.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

use Blush\Auth\ColorScheme;

/**
 * A color token's value (D-827): `light-dark(light, dark)`, or one color
 * for both color schemes. Never separate light and dark keys.
 */
final readonly class ColorValue
{
	public function __construct(
		public CssColor $light,
		public CssColor $dark
	) {}

	/**
	 * Reads a value, or returns `null` when it isn't a color or a
	 * `light-dark()` of two.
	 */
	public static function parse(string $value): ?self
	{
		$value = trim($value);

		if (preg_match('/^light-dark\s*\((.*)\)$/is', $value, $match) !== 1) {
			$color = CssColor::parse($value);

			return $color === null ? null : new self($color, $color);
		}

		$halves = self::split($match[1]);

		if ($halves === null) {
			return null;
		}

		$light = CssColor::parse($halves[0]);
		$dark  = CssColor::parse($halves[1]);

		return $light === null || $dark === null ? null : new self($light, $dark);
	}

	/**
	 * Returns the value as Blush writes it.
	 */
	public function css(): string
	{
		return $this->light->css === $this->dark->css
			? $this->light->css
			: "light-dark({$this->light->css}, {$this->dark->css})";
	}

	/**
	 * Returns one color scheme's color (the light one for the system's).
	 */
	public function in(ColorScheme $scheme): CssColor
	{
		return $scheme === ColorScheme::Dark ? $this->dark : $this->light;
	}

	/**
	 * Splits `light-dark()`'s arguments at the comma between them, outside
	 * any parentheses (a legacy `rgb(0, 0, 0)` has its own).
	 *
	 * @return ?array{string, string}
	 */
	private static function split(string $arguments): ?array
	{
		$depth = 0;

		foreach (str_split($arguments) as $at => $character) {
			$depth += match ($character) {
				'('     => 1,
				')'     => -1,
				default => 0
			};

			if ($character === ',' && $depth === 0) {
				return [substr($arguments, 0, $at), substr($arguments, $at + 1)];
			}
		}

		return null;
	}
}
