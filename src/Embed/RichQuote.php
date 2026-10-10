<?php

/**
 * Rich quote.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Dom\Element;
use Dom\HTMLDocument;
use Blush\Markdown\Html\HtmlRules;

/**
 * Cleans a rich embed's answer down to its quote (D-691): the first
 * `<blockquote>`, which reads as the post without the provider's script
 * and is what the script draws the post from. Only the tags in
 * `ALLOWED` are kept; other tags give way to their content, and those
 * nobody may add (`HtmlRules::REFUSED`: scripts, frames, styles, and
 * the like) go with theirs. Elements keep only `class`, `lang`, `dir`,
 * and `data-*` (what the script reads), with a link's `href` and a
 * quote's `cite` when they're HTTPS.
 */
final class RichQuote
{
	/**
	 * The tags a quote keeps.
	 *
	 * @var list<string>
	 */
	public const array ALLOWED = ['a', 'blockquote', 'br', 'p'];

	/**
	 * The attributes any kept tag may have, with `data-*`.
	 *
	 * @var list<string>
	 */
	private const array GLOBAL = ['class', 'dir', 'lang'];

	/**
	 * The URL attributes kept on a tag, when they're HTTPS.
	 *
	 * @var array<string, string>
	 */
	private const array URLS = ['a' => 'href', 'blockquote' => 'cite'];

	/**
	 * Returns the HTML's first blockquote, cleaned, or `null` when it has
	 * none or it has no text.
	 */
	public static function clean(string $html): ?string
	{
		if (! str_contains($html, '<blockquote')) {
			return null;
		}

		$document = HTMLDocument::createFromString('<!DOCTYPE html><meta charset="utf-8"><body>' . $html . '</body>', LIBXML_NOERROR);
		$quote    = $document->querySelector('blockquote');

		if ($quote === null) {
			return null;
		}

		self::attributes($quote);
		self::children($quote);

		return trim($quote->textContent ?? '') === '' ? null : $document->saveHtml($quote);
	}

	/**
	 * Cleans an element's children: comments and refused tags go, other
	 * tags not allowed give way to their content, and allowed ones are
	 * cleaned in turn.
	 */
	private static function children(Element $element): void
	{
		foreach (iterator_to_array($element->childNodes) as $node) {
			if ($node instanceof Element) {
				$tag = strtolower($node->localName);

				if (in_array($tag, HtmlRules::REFUSED, true)) {
					$node->remove();
					continue;
				}

				self::children($node);

				if (in_array($tag, self::ALLOWED, true)) {
					self::attributes($node);
				} else {
					$node->replaceWith(...iterator_to_array($node->childNodes));
				}
			} elseif ($node->nodeType !== XML_TEXT_NODE) {
				$node->parentNode?->removeChild($node);
			}
		}
	}

	/**
	 * Removes the attributes an element may not have.
	 */
	private static function attributes(Element $element): void
	{
		$url = self::URLS[strtolower($element->localName)] ?? null;

		foreach (iterator_to_array($element->attributes) as $attribute) {
			$name = $attribute->name;
			$keep = match (true) {
				in_array($name, self::GLOBAL, true)           => true,
				preg_match('/^data-[a-z0-9-]+$/', $name) === 1 => true,
				$name === $url                                => str_starts_with($attribute->value, 'https://'),
				default                                       => false
			};

			if (! $keep) {
				$element->removeAttribute($name);
			}
		}
	}
}
