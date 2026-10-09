<?php

/**
 * Entry records test.
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
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\SnapshotRecords;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\EntryValues;
use Blush\Content\Record\QueryCompiler;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\Ref;
use Blush\Support\Uuid;

#[CoversClass(SnapshotRecords::class)]
#[CoversClass(EntryTable::class)]
#[CoversClass(EntryValues::class)]
#[CoversClass(QueryCompiler::class)]
#[CoversClass(IndexSnapshot::class)]
final class EntryRecordsTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
	}

	/**
	 * The records the index stores, by slug, for one type.
	 *
	 * @return array<string, Record>
	 */
	private function records(ContentIndex $index, string $type): array
	{
		$records = SnapshotRecords::fromSnapshot($index->snapshot());

		$this->assertNotNull($records, 'The index stores its records.');

		$found = [];

		foreach ($records->entries as $row) {
			$slug = $row['fields']['slug'] ?? null;

			if (($row['fields']['type'] ?? null) === $type && is_string($slug)) {
				$found[$slug] = ArrayEvaluator::record($row);
			}
		}

		return $found;
	}

	public function testTheIndexKeepsEntriesAsRecords(): void
	{
		$app = $this->site();
		$app->container()->make(ContentRepository::class)->query()->count();

		$index  = $app->container()->make(ContentIndex::class);
		$posts  = $this->records($index, 'post');
		$spring = $posts['spring'] ?? null;

		$this->assertNotNull($spring);
		$this->assertSame('2008-04-05T14:00:00Z', $spring->fields['published'], 'Times in UTC, as text (the site is in Chicago).');
		$this->assertSame('published', $spring->fields['status']);
		$this->assertSame('public', $spring->fields['visibility']);
		$this->assertNull($spring->fields['parent_id']);
		$this->assertSame(['art', 'book-reviews'], $spring->value('fields.category'), 'Front matter, a relation\'s values as the index reads them.');
		$this->assertSame(['art', 'book-reviews'], $spring->value('slugs.category'), 'And as slugs.');
		$this->assertArrayHasKey('', $posts, 'A landing page has no slug.');

		$pages = $this->records($index, 'page');

		$this->assertSame($pages['about']->id ?? null, $pages['biography']->fields['parent_id'] ?? null, 'A tree page\'s parent is its folder\'s page.');
	}

	public function testRefsJoinEntriesToTheirTerms(): void
	{
		$app     = $this->site();
		$content = $app->container()->make(ContentRepository::class);
		$content->query()->count();

		$records = SnapshotRecords::fromSnapshot($app->container()->make(ContentIndex::class)->snapshot());
		$spring  = $content->named('post', 'spring');
		$art     = $content->named('category', 'art');

		$this->assertNotNull($records);
		$this->assertNotNull($spring);
		$this->assertNotNull($art);
		$this->assertContains(
			new Ref((string) $spring->id, 'category', (string) $art->id, 0)->record()->id,
			array_column($records->refs(), 'id')
		);
	}

	public function testFilesWithoutIdsReferToTheTermsTheyWrite(): void
	{
		$this->writeTemporaryFile('user/content/_posts/2009-01-01.no-id.md', "---\ntitle: No id\npublished: 2009-01-01\ncategory: art\n---\n");

		$content = $this->repository();

		$this->assertContains('no-id', array_map(static fn (mixed $entry): string => $entry->slug, $content->query()->type('post')->whereTerm('category', 'art')->get()->all()));
		$this->assertSame(Uuid::fromName('content/_posts/2009-01-01.no-id.md'), $this->records($this->site()->container()->make(ContentIndex::class), 'post')['no-id']->id ?? null, 'A steady id from its path.');
	}

	public function testDatesMatchFromTheYearDown(): void
	{
		$content = $this->repository();

		$this->assertSame(['rainy', 'spring'], array_map(static fn (mixed $entry): string => $entry->slug, $content->query()->type('post')->any()->date(year: 2008, month: 4)->get()->all()));
		$this->assertSame(['spring'], array_map(static fn (mixed $entry): string => $entry->slug, $content->query()->type('post')->any()->date(year: 2008, month: 4, day: 5)->get()->all()));

		$this->expectException(InvalidQuery::class);
		$this->expectExceptionMessage('Dates match from the year down');

		$content->query()->type('post')->date(month: 4)->get();
	}
}
