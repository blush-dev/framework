<?php

/**
 * Entry position tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\Position;
use Blush\Content\Index\ArraySelector;
use Blush\Content\Query\Order;
use Blush\Content\Type\ContentTypes;

#[CoversClass(Position::class)]
#[CoversClass(ArraySelector::class)]
final class PositionTest extends TestCase
{
	use BuildsContentSite;

	private function content(): ContentRepository
	{
		$this->contentConfig(['types' => ['topic' => ['folder' => 'topics', 'order' => 'position'], 'note' => ['kind' => 'collection', 'folder' => '_notes']], 'relations' => ['topic' => ['kind' => 'classify', 'to' => ['topic']]]]);
		$this->entry('guide/index.md', 'title: Guide');
		$this->entry('guide/install.md', "title: Install\nposition: 1");
		$this->entry('guide/upgrade.md', "title: Upgrade\nposition: 2");
		$this->entry('guide/faq.md', 'title: FAQ');
		$this->entry('guide/about.md', 'title: About');
		$this->entry('topics/later.md', "title: Later\nposition: 2");
		$this->entry('topics/sooner.md', "title: Sooner\nposition: 1");
		$this->entry('topics/whenever.md', 'title: Whenever');

		return $this->site()->container()->make(ContentRepository::class);
	}

	/**
	 * @param  iterable<Entry> $entries
	 * @return list<string>
	 */
	private static function titles(iterable $entries): array
	{
		$titles = [];

		foreach ($entries as $entry) {
			$titles[] = $entry->title;
		}

		return $titles;
	}

	public function testSiblingsGoByPositionThenTitle(): void
	{
		$content = $this->content();
		$guide   = $content->named('page', 'guide');

		$this->assertNotNull($guide);
		$this->assertSame(['Install', 'Upgrade', 'About', 'FAQ'], self::titles($content->children($guide)), 'Those without one follow, by title (D-412).');
	}

	public function testListingsSortByPositionWithTheRestLast(): void
	{
		$content = $this->content();

		$this->assertSame(['Sooner', 'Later', 'Whenever'], self::titles($content->query()->type('topic')->withLanding(false)->orderBy('position')->get()));
		$this->assertSame(['Later', 'Sooner', 'Whenever'], self::titles($content->query()->type('topic')->withLanding(false)->orderBy('position', Order::Desc)->get()), 'Last either way.');
	}

	public function testTreesAndTaxonomiesHaveTheField(): void
	{
		$this->content();
		$types = $this->site()->container()->make(ContentTypes::class);

		$this->assertNotNull($types->schema('page')->field('position'));
		$this->assertNotNull($types->schema('topic')->field('position'));
		$this->assertNull($types->schema('note')->field('position'), 'A collection orders by date or file name.');
	}
}
