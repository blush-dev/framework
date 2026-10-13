<?php

/**
 * Fixture: a block that queries content.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Content;

use Override;
use Blush\Block\Block;
use Blush\Block\BlockKind;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;

final class PostTitles extends Block
{
	public const ?BlockKind KIND = BlockKind::Leaf;

	public function __construct(private readonly Entries $content)
	{}

	/**
	 * Lists the posts' titles, then the `art` term's.
	 */
	#[Override]
	public function render(): string
	{
		$titles = array_map(static fn (Entry $entry): string => $entry->title, $this->content->query()->type('post')->limit(null)->get()->all());

		return implode(', ', $titles) . ' | ' . ($this->content->term('category', 'art')->title ?? '');
	}
}
