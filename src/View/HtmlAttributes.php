<?php

/**
 * HTML attributes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

/**
 * Prints attributes passed by name to a method that prints a tag, such
 * as `link()` and `img()` (D-803, D-814): a camelCase name is dashed
 * (`dataId:` is `data-id`), `true` prints a bare attribute, and `false`
 * or `null` leaves it out. Every value is escaped.
 */
final class HtmlAttributes
{
	/**
	 * Returns the attributes, each after a space.
	 *
	 * @param  array<int|string, string|int|bool|null> $attributes
	 * @param  string                                   $method For the error, such as `link()`.
	 * @throws InvalidHtmlAttribute When an attribute isn't named.
	 */
	public static function render(array $attributes, string $method): string
	{
		$html = '';

		foreach ($attributes as $name => $value) {
			if (! is_string($name)) {
				throw new InvalidHtmlAttribute("Pass {$method} attributes by name, such as {$method}(class: 'name') with its parentheses.");
			}

			if ($value === false || $value === null) {
				continue;
			}

			$name  = self::name($name);
			$html .= $value === true ? " {$name}" : " {$name}=\"" . Escaper::attr($value) . '"';
		}

		return $html;
	}

	/**
	 * Returns an attribute's name as HTML writes it: `ariaCurrent` is
	 * `aria-current`.
	 */
	public static function name(string $name): string
	{
		return strtolower((string) preg_replace('/[A-Z]/', '-$0', $name));
	}
}
