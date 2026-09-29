<?php

/**
 * Query tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Order;
use Blush\Content\Query\Query;
use Blush\Content\Status;
use Blush\Content\Visibility;

#[CoversClass(Query::class)]
#[CoversClass(Order::class)]
#[CoversClass(InvalidQuery::class)]
final class QueryTest extends TestCase
{
	public function testDefaults(): void
	{
		$query = new Query();

		$this->assertSame([], $query->types);
		$this->assertNull($query->limit);
		$this->assertSame('filename', $query->orderBy);
		$this->assertSame(Order::Asc, $query->order);
		$this->assertSame([Status::Published], $query->statuses);
		$this->assertSame([Visibility::Public], $query->visibilities());
		$this->assertFalse($query->findsLanding());
	}

	public function testSearchAndAlternatives(): void
	{
		$query  = new Query();
		$either = $query->either(static fn (Query $condition): Query => $condition->type('post'));

		$this->assertSame('spring', $query->search('  spring ')->search);
		$this->assertNull($query->search('  ')->search);
		$this->assertSame([], $query->alternatives);
		$this->assertCount(1, $either->alternatives);
		$this->assertSame(['post'], $either->alternatives[0][0]->types);
		$this->assertSame(Status::cases(), $either->alternatives[0][0]->statuses, 'Alternatives start from a condition that matches everything.');
		$this->assertTrue(Query::condition()->findsLanding());
		$this->assertSame([[]], $query->either()->alternatives);
	}

	public function testBuildersReturnCopies(): void
	{
		$query  = new Query();
		$posts  = $query->type('post', 'post')->limit(-5)->offset(-1)->orderBy('title', Order::Desc);
		$names  = $query->names('index', 'about');

		$this->assertSame([], $query->types);
		$this->assertSame(['post'], $posts->types);
		$this->assertSame(0, $posts->limit);
		$this->assertSame(0, $posts->offset);
		$this->assertSame(['title', Order::Desc], [$posts->orderBy, $posts->order]);
		$this->assertSame(Visibility::cases(), $names->visibilities());
		$this->assertTrue($names->findsLanding());
		$this->assertSame('writing/forms', $query->in('/writing/forms/')->directory);
		$this->assertSame(['year' => 2008, 'month' => 4], $query->date(year: 2008, month: 4)->date);
		$this->assertSame([['author', ['a']], ['author', ['b']]], $query->whereAuthor('a', 'b')->terms);
		$this->assertSame(Status::cases(), $query->any()->statuses);
		$this->assertTrue($query->any()->findsLanding());
		$this->assertSame(['category', 'art'], [$query->where('category', 'art')->metaKey, $query->where('category', 'art')->metaValue]);
	}

	public function testReadsOneXArguments(): void
	{
		$query = Query::fromArray([
			'type'          => 'post',
			'path'          => 'writing',
			'slug'          => 'hello',
			'names_exclude' => ['a', 'b'],
			'number'        => '5',
			'offset'        => 2,
			'order'         => 'DESC',
			'orderby'       => 'date',
			'author'        => 'justin',
			'meta_key'      => 'date',
			'meta_value'    => 2008,
			'year'          => 2008,
			'second'        => '30',
			'noindex'       => false,
			'nocontent'     => true
		]);

		$this->assertSame(['post'], $query->types);
		$this->assertSame('writing', $query->directory);
		$this->assertSame(['hello'], $query->names);
		$this->assertSame(['a', 'b'], $query->excludedNames);
		$this->assertSame([5, 2], [$query->limit, $query->offset]);
		$this->assertSame(['published', Order::Desc], [$query->orderBy, $query->order]);
		$this->assertSame([['author', ['justin']]], $query->terms);
		$this->assertSame(['published', '2008'], [$query->metaKey, $query->metaValue]);
		$this->assertSame(['year' => 2008, 'second' => 30], $query->date);
		$this->assertTrue($query->landing);
	}

	public function testReadsTwoXArguments(): void
	{
		$query = Query::fromArray([
			'names'      => ['a'],
			'number'     => 0,
			'terms'      => ['category' => ['art', 'life'], 'era' => 'college'],
			'status'     => ['published', 'scheduled'],
			'visibility' => 'unlisted',
			'locale'     => 'fr_FR'
		]);

		$this->assertNull($query->limit);
		$this->assertSame([['category', ['art', 'life']], ['era', ['college']]], $query->terms);
		$this->assertSame([Status::Published, Status::Scheduled], $query->statuses);
		$this->assertSame([Visibility::Unlisted], $query->visibilities());
		$this->assertSame('fr_FR', $query->locale);
		$this->assertSame(Query::DEFAULT_NUMBER, Query::fromArray([])->limit);
	}

	public function testRejectsBadArguments(): void
	{
		$cases = [
			['unknown' => true],
			['type' => ['a' => 'post']],
			['type' => [[]]],
			['number' => 'ten'],
			['order' => 'sideways'],
			['noindex' => 'yes'],
			['terms' => ['art']],
			['status' => 'lost'],
			['visibility' => 'invisible']
		];

		foreach ($cases as $arguments) {
			try {
				Query::fromArray($arguments);
				$this->fail('Expected ' . json_encode($arguments) . ' to be rejected.');
			} catch (InvalidQuery) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testRunningNeedsARunner(): void
	{
		$this->expectException(InvalidQuery::class);

		new Query()->get();
	}
}
