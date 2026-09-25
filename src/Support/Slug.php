<?php

/**
 * Slugs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

/**
 * Turns strings into URL slugs the way 1.x did (D-078), so term slugs in
 * existing front matter match: underscores and runs of punctuation or
 * whitespace become one separator, letters and digits in any script are
 * kept and lowercased, and separators are trimmed from both ends.
 * `"Book Reviews"` and `"book_reviews"` both become `book-reviews`.
 */
final class Slug
{
	/**
	 * Returns the slug for a string.
	 */
	public static function from(string $value, string $separator = '-'): string
	{
		$quoted  = preg_quote($separator, '/');
		$divider = preg_quote($separator === '-' ? '_' : '-', '/');

		$slug = (string) preg_replace("/[{$divider}]+/u", $separator, $value);
		$slug = (string) preg_replace("/[^{$quoted}\\pL\\pN\\s]+/u", $separator, $slug);
		$slug = (string) preg_replace("/[{$quoted}\\s]+/u", $separator, $slug);

		return trim(mb_strtolower($slug), $separator);
	}

	/**
	 * Returns whether a string is already a slug.
	 */
	public static function isSlug(string $value): bool
	{
		return $value !== '' && self::from($value) === $value;
	}
}
