<?php

/**
 * Escaper.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use JsonException;
use Stringable;

/**
 * Escapes values for each output context of a template. These are pure
 * functions with no state; templates call them through the global
 * helpers in `functions.php` (`e()`, `attr()`, `url()`, `js()`, `css()`,
 * and `raw()`), the only global functions Blush defines.
 *
 * `null` and `false` escape to `''`, so an optional value can be printed
 * without a check.
 */
final class Escaper
{
	/**
	 * URL schemes `url()` lets through. Anything else (`javascript:`,
	 * `data:`, `vbscript:`) becomes `''`.
	 *
	 * @var list<string>
	 */
	public const array SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel', 'sms', 'ftp', 'ftps', 'irc', 'ircs', 'xmpp', 'webcal', 'feed'];

	/**
	 * Escapes text for HTML content.
	 */
	public static function html(Stringable|string|int|float|bool|null $value): string
	{
		return $value instanceof SafeHtml ? (string) $value : self::text($value);
	}

	/**
	 * Escapes a value for a quoted HTML attribute. Always quote attribute
	 * values: `class="<?= attr($class) ?>"`.
	 */
	public static function attr(Stringable|string|int|float|bool|null $value): string
	{
		return self::text($value);
	}

	/**
	 * Escapes a value as text, whatever it is, trusted HTML included: an
	 * attribute never holds markup.
	 */
	private static function text(Stringable|string|int|float|bool|null $value): string
	{
		return htmlspecialchars(self::string($value), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}

	/**
	 * Escapes a URL for an `href` or `src` attribute. Relative URLs and
	 * the schemes in `SAFE_SCHEMES` pass; any other scheme returns `''`.
	 */
	public static function url(Stringable|string|null $value): string
	{
		$url = trim(self::string($value));

		// Browsers ignore control characters and whitespace inside a
		// scheme, so compare without them.
		$scheme = preg_replace('/[\x00-\x20\x7F]+/', '', $url) ?? '';

		if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $scheme, $matches) === 1 && ! in_array(strtolower($matches[1]), self::SAFE_SCHEMES, true)) {
			return '';
		}

		return self::html($url);
	}

	/**
	 * Encodes a value as a JavaScript literal (a quoted string for a
	 * string), safe inside `<script>` and in quoted attributes:
	 * `const data = <?= js($data) ?>;`.
	 *
	 * @throws JsonException When the value can't be encoded.
	 */
	public static function js(mixed $value): string
	{
		if ($value instanceof Stringable) {
			$value = (string) $value;
		}

		return json_encode(
			$value,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);
	}

	/**
	 * Escapes a value for use inside a CSS string or identifier: every
	 * character but ASCII letters and digits becomes a hex escape.
	 */
	public static function css(Stringable|string|int|float|null $value): string
	{
		return preg_replace_callback(
			'/[^a-zA-Z0-9]/u',
			static fn (array $match): string => sprintf('\\%X ', mb_ord($match[0], 'UTF-8')),
			self::string($value)
		) ?? '';
	}

	/**
	 * Converts an escapable value to a string.
	 */
	private static function string(Stringable|string|int|float|bool|null $value): string
	{
		return match (true) {
			$value === null, $value === false => '',
			$value === true                   => '1',
			default                           => (string) $value
		};
	}
}
