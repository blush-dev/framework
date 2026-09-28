<?php

/**
 * CSS length.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component\Layout;

/**
 * Checks CSS lengths given as component props (D-177), since they go into
 * a `style` attribute: `0`, or a number with a length unit or `%`.
 * Anything else (keywords, functions, several values) is refused, so a
 * prop can't carry other CSS.
 */
final class CssLength
{
	/**
	 * The accepted form.
	 */
	private const string PATTERN = '/^(?:0|\d*\.?\d+(?:px|r?em|ch|ex|r?lh|%|vw|vh|vi|vb|vmin|vmax|[sld]v[whib]|cq[whib]|cqmin|cqmax))$/';

	/**
	 * Returns the length, trimmed, or `null` when it isn't one.
	 */
	public static function sanitize(string $value): ?string
	{
		$value = trim($value);

		return preg_match(self::PATTERN, $value) === 1 ? $value : null;
	}
}
