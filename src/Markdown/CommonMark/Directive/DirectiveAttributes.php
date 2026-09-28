<?php

/**
 * Directive attributes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

/**
 * Parses a directive's `{…}` attributes: `key=value`, `key="a value"`,
 * `key='a value'`, `.class` (added to `class`), `#id`, and a bare `key`
 * (read as `"true"`). Anything else is skipped.
 */
final class DirectiveAttributes
{
	/**
	 * A directive's name, captured: a word, optionally namespaced
	 * (`callout`, `acme/tabs`, D-171). The `/` is escaped for patterns
	 * delimited by `/`.
	 */
	public const string NAME = '([A-Za-z][A-Za-z0-9_-]*(?:\/[A-Za-z][A-Za-z0-9_-]*)?)';

	/**
	 * The shared syntax of the three directive forms: a name, an optional
	 * `[label]`, and optional `{attributes}`.
	 */
	public const string SYNTAX = self::NAME . '(?:\[([^\]\n]*)\])?(?:\{([^}\n]*)\})?';

	/**
	 * Returns the attributes in a `{…}` body (without the braces).
	 *
	 * @return array<string, string>
	 */
	public static function parse(string $source): array
	{
		preg_match_all(
			'/([.#])([A-Za-z0-9_:-]+)|([A-Za-z_:][A-Za-z0-9_:.-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=]+)))?/',
			$source,
			$matches,
			PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL
		);

		$attributes = [];

		foreach ($matches as $match) {
			if ($match[1] === '.') {
				$attributes['class'] = trim(($attributes['class'] ?? '') . ' ' . $match[2]);
			} elseif ($match[1] === '#') {
				$attributes['id'] = (string) $match[2];
			} elseif ($match[3] !== null) {
				$attributes[$match[3]] = $match[4] ?? $match[5] ?? $match[6] ?? 'true';
			}
		}

		return $attributes;
	}
}
