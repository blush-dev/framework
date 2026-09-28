<?php

/**
 * Entry region item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region\Item;

use Override;
use Blush\Content\ContentRepository;
use Blush\Markdown\MarkdownException;
use Blush\Region\RegionException;
use Blush\Region\RegionRender;

/**
 * An entry's rendered body, by type and key, such as a page kept out of
 * the site's URLs in a `_regions/` folder:
 *
 * ```yaml
 * - entry: page/_regions/about
 * ```
 *
 * The entry in the page's locale is used when there is one, else the
 * site's. It must be published.
 */
final class EntryItem extends RegionItem
{
	public function __construct(private readonly ContentRepository $content)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && preg_match('#^[a-z][a-z0-9_]*/[^/].*$#', trim($value)) === 1
			? null
			: 'must be an entry as {type}/{key}, such as "page/_regions/about".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(mixed $value, array $item, RegionRender $render): string
	{
		$value        = self::text($value);
		[$type, $key] = [...explode('/', $value, 2), ''];

		$entry = $this->content->named($type, $key, $render->locale) ?? $this->content->named($type, $key);

		if ($entry === null || ! $entry->isPublished()) {
			throw new RegionException(sprintf('No published entry "%s".', $value));
		}

		try {
			return $entry->body();
		} catch (MarkdownException $error) {
			throw new RegionException($error->getMessage(), 0, $error);
		}
	}
}
