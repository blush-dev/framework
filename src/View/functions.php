<?php

/**
 * Template escaping helpers.
 *
 * The only global functions Blush defines. Each escapes a value for one
 * output context; use the most specific one at the point of output.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

use Blush\View\Escaper;

/**
 * Escapes text for HTML content.
 */
function e(Stringable|string|int|float|bool|null $value): string
{
	return Escaper::html($value);
}

/**
 * Escapes a value for a quoted HTML attribute.
 */
function attr(Stringable|string|int|float|bool|null $value): string
{
	return Escaper::attr($value);
}

/**
 * Escapes a URL for an `href` or `src` attribute, dropping unsafe
 * schemes such as `javascript:`.
 */
function url(Stringable|string|null $value): string
{
	return Escaper::url($value);
}

/**
 * Encodes a value as a JavaScript literal.
 *
 * @throws JsonException
 */
function js(mixed $value): string
{
	return Escaper::js($value);
}

/**
 * Escapes a value for a CSS string or identifier.
 */
function css(Stringable|string|int|float|null $value): string
{
	return Escaper::css($value);
}

/**
 * Marks trusted, already-rendered HTML (such as an entry body) for output
 * as is. It returns the value unchanged; it exists so templates say so.
 */
function raw(Stringable|string|null $html): string
{
	return (string) $html;
}
