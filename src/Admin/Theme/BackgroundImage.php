<?php

/**
 * Background image.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

/**
 * An admin theme's ground texture (D-878): `none`, or gradients written
 * with colors, numbers, and the gradients' own keywords, read by an
 * allowlist rather than a grammar. Nothing else gets through, so a value
 * can't make a request (`url()`), reach another token (`var()`), or
 * break out of its declaration; whether it's a gradient CSS will draw is
 * the browser's to say. Each scheme's colors are written in `light-dark()`
 * inside it, as anywhere a color goes.
 *
 *     radial-gradient(circle, light-dark(rgb(20 24 29 / 16%), rgb(255 255 255 / 7%)) 1px, transparent 1.4px)
 */
final readonly class BackgroundImage
{
	/**
	 * The longest value read.
	 */
	public const int MAX_LENGTH = 2000;

	/**
	 * The functions a value may be made of.
	 */
	private const array GRADIENTS = [
		'linear-gradient',
		'radial-gradient',
		'conic-gradient',
		'repeating-linear-gradient',
		'repeating-radial-gradient',
		'repeating-conic-gradient'
	];

	/**
	 * The functions a gradient may hold: colors.
	 */
	private const array COLORS = ['rgb', 'rgba', 'hsl', 'hsla', 'oklch', 'light-dark'];

	/**
	 * The words a gradient may hold: its shapes, sizes, directions, and
	 * color spaces, and `transparent`.
	 */
	private const array KEYWORDS = [
		'at', 'to', 'from', 'in',
		'circle', 'ellipse', 'closest-side', 'closest-corner', 'farthest-side', 'farthest-corner',
		'left', 'right', 'top', 'bottom', 'center',
		'srgb', 'srgb-linear', 'oklab', 'oklch', 'hsl', 'longer', 'shorter', 'increasing', 'decreasing', 'hue',
		'transparent', 'none'
	];

	/**
	 * One piece of a value: space, punctuation, a hex color, a number
	 * with its unit, or a word with the parenthesis that makes it a
	 * function.
	 */
	private const string PIECE = '/\G(?:(\s+)|([(),\/])|(#[0-9a-f]{3,8})(?![0-9a-z])|([+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:px|%|deg|turn|rad|grad)?)(?![0-9a-z.%])|([a-z][a-z-]*)(\()?)/i';

	/**
	 * Returns a value as CSS writes it (its spaces collapsed), or `null`
	 * when it isn't a string or holds anything the allowlist doesn't.
	 */
	public static function parse(mixed $value): ?string
	{
		if (! is_string($value)) {
			return null;
		}

		$value = trim((string) preg_replace('/\s+/', ' ', $value));

		if ($value === 'none') {
			return $value;
		}

		if ($value === '' || strlen($value) > self::MAX_LENGTH) {
			return null;
		}

		$offset    = 0;
		$depth     = 0;
		$gradients = 0;

		while ($offset < strlen($value)) {
			if (preg_match(self::PIECE, $value, $match, 0, $offset) !== 1) {
				return null;
			}

			$offset += strlen($match[0]);
			$word    = strtolower($match[5] ?? '');
			$opens   = ($match[6] ?? '') === '(';

			if (($match[2] ?? '') === '(') {
				return null;
			}

			if (($match[2] ?? '') === ')') {
				if (--$depth < 0) {
					return null;
				}

				continue;
			}

			// Outside a gradient, only gradients and the commas between them.
			if ($depth === 0) {
				if ($word !== '' && $opens && in_array($word, self::GRADIENTS, true)) {
					$depth++;
					$gradients++;
				} elseif (($match[1] ?? '') === '' && ($match[2] ?? '') !== ',') {
					return null;
				}

				continue;
			}

			if ($word === '') {
				continue;
			}

			if ($opens ? ! in_array($word, self::COLORS, true) : ! in_array($word, self::KEYWORDS, true)) {
				return null;
			}

			if ($opens) {
				$depth++;
			}
		}

		return $depth === 0 && $gradients > 0 ? $value : null;
	}

	/**
	 * Returns a background size (`auto`, or one or two of pixels or a
	 * percent, as `"20px 20px"`) as CSS writes it, or `null`.
	 */
	public static function size(mixed $value): ?string
	{
		if (! is_string($value)) {
			return null;
		}

		$value = trim((string) preg_replace('/\s+/', ' ', $value));
		$one   = '(?:auto|0|(?:\d+(?:\.\d+)?|\.\d+)(?:px|%))';

		return preg_match("/^{$one}(?: {$one})?$/", $value) === 1 ? $value : null;
	}
}
