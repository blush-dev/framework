<?php

/**
 * Markdown region item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Override;
use Blush\Content\Entry\BodyCache;
use Blush\Markdown\MarkdownException;
use Blush\Markdown\MarkdownParser;
use Blush\Region\RegionException;
use Blush\Region\RegionRender;
use Blush\Translation\LocaleMap;

/**
 * Markdown text, written in the item (or a locale map of it, D-202), and
 * rendered as an entry's body is, directives included. The HTML is kept
 * in the body cache, per content version and theme.
 *
 * ```yaml
 * - markdown: "Powered by **Blush**."
 * ```
 */
final class MarkdownItem extends RegionItem
{
	public function __construct(
		private readonly MarkdownParser $parser,
		private readonly BodyCache $bodies
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function itemSchema(string $key, array $text): array
	{
		return [$key => [...$text, 'description' => 'Shows Markdown text, with components, or a map of locales to it.']];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) || LocaleMap::isMap($value) ? null : 'must be Markdown text, or a map of locales to it.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(mixed $value, array $item, RegionRender $render): string
	{
		$markdown = LocaleMap::text($value, $render->locale, $render->defaultLocale) ?? '';

		try {
			return $this->bodies->remember('region ' . hash('xxh128', $markdown), fn (): string => $this->parser->toHtml($markdown));
		} catch (MarkdownException $error) {
			throw new RegionException($error->getMessage(), 0, $error);
		}
	}
}
