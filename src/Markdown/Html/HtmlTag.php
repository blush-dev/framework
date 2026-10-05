<?php

/**
 * HTML tag.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Html;

/**
 * An opening tag found in raw HTML: its name and its attributes, both
 * lowercased names, with values decoded. A declaration (`<!doctype>`) or
 * processing instruction (`<?php`) is one too, named `!` or `?`.
 */
final readonly class HtmlTag
{
	/**
	 * @param array<string, string> $attributes
	 */
	public function __construct(
		public string $name,
		public array $attributes = []
	) {}

	/**
	 * Finds the opening tags in a piece of HTML, leaving out comments.
	 *
	 * @return list<self>
	 */
	public static function all(string $html): array
	{
		$html = preg_replace('/<!--.*?(?:-->|$)/s', '', $html) ?? $html;
		$tags = [];

		preg_match_all('/<(?:([A-Za-z][A-Za-z0-9:-]*)((?:\s+[^\s"\'>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*)\s*\/?>|(!)[A-Za-z]|(\?))/', $html, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			if (($match[3] ?? '') !== '' || ($match[4] ?? '') !== '') {
				$tags[] = new self(($match[3] ?? '') !== '' ? '!' : '?');

				continue;
			}

			$attributes = [];

			preg_match_all('/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', $match[2] ?? '', $parts, PREG_SET_ORDER);

			foreach ($parts as $part) {
				$attributes[strtolower($part[1])] ??= html_entity_decode(($part[2] ?? '') . ($part[3] ?? '') . ($part[4] ?? ''), ENT_QUOTES | ENT_HTML5);
			}

			$tags[] = new self(strtolower($match[1] ?? ''), $attributes);
		}

		return $tags;
	}
}
