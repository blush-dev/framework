<?php

/**
 * Status tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Core\Application;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\Fixtures\Workflow\ReviewStatuses;
use Blush\Workflow\CoreStatuses;
use Blush\Workflow\InvalidStatus;
use Blush\Workflow\Status;
use Blush\Workflow\Statuses;
use Blush\Workflow\StatusSource;
use Blush\Workflow\Subject;
use Blush\Workflow\Tone;

#[CoversClass(Status::class)]
#[CoversClass(Statuses::class)]
#[CoversClass(CoreStatuses::class)]
final class StatusesTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => ['post' => ['kind' => 'collection']]]);
		$this->entry('_post/live.md', "title: Live\ndate: 2026-05-01 09:00:00");
		$this->entry('_post/rough.md', "title: Rough\nstatus: draft");
		$this->entry('_post/review.md', "title: Review\nstatus: in_review");
		$this->entry('_post/featured.md', "title: Featured\nstatus: featured\ndate: 2026-05-02 09:00:00");
		$this->entry('_post/later.md', "title: Later\nstatus: featured\ndate: 2026-07-01 09:00:00");
		$this->entry('_post/lost.md', "title: Lost\nstatus: Lost");
		$this->entry('_post/binned.md', "title: Binned\nstatus: trash");

		$this->app = $this->site();
		$this->app->container()->singleton(ReviewStatuses::class);
		$this->app->container()->tag(ReviewStatuses::class, StatusSource::TAG);
	}

	private function statuses(): Statuses
	{
		return $this->app->container()->make(Statuses::class);
	}

	/**
	 * Returns the titles of a query's entries, sorted.
	 *
	 * @return list<string>
	 */
	private function titles(Query $query): array
	{
		$titles = array_map(static fn (Entry $entry): string => $entry->title, $query->limit(null)->get()->all());
		sort($titles);

		return $titles;
	}

	private function posts(): Query
	{
		return $this->app->container()->make(Entries::class)->query()->type('post');
	}

	public function testListsCoresAndRegisteredStatusesBySubject(): void
	{
		$statuses = $this->statuses();

		$this->assertSame(['draft', 'published', 'in_review', 'featured', 'scheduled', 'expired', 'trash'], array_keys($statuses->all()));
		$this->assertSame(['draft', 'published', 'in_review', 'featured'], array_keys($statuses->registered()), 'Core\'s own names aren\'t a workflow\'s to use.');
		$this->assertSame(['spam'], array_keys($statuses->all(Subject::Responses)), 'Responses have statuses of their own.');
		$this->assertSame(['published', 'featured'], $statuses->live());
		$this->assertSame(['draft', 'in_review'], $statuses->offline());
		$this->assertSame(['trash'], $statuses->inactive());
		$this->assertSame('Draft', (string) $statuses->get('draft')->label, 'The first registered keeps the name.');
		$this->assertSame([Tone::Good, Tone::Danger, Tone::Accent], [$statuses->get('published')->tone, $statuses->get('trash')->tone, $statuses->get('scheduled')->tone]);
	}

	public function testReportsClashesAndKeepsTheFirst(): void
	{
		$clashes = $this->statuses()->clashes();

		$this->assertSame(['draft', 'trash'], array_map(static fn ($clash): string => $clash->name, $clashes));
		$this->assertSame([CoreStatuses::class, Statuses::class], array_map(static fn ($clash): string => $clash->kept, $clashes));
		$this->assertSame(ReviewStatuses::class, $clashes[0]->dropped);
	}

	public function testMakesAStatusForANameNothingRegisters(): void
	{
		$lost = $this->statuses()->get('Lost');

		$this->assertFalse($lost->known);
		$this->assertFalse($lost->live);
		$this->assertTrue($lost->active);
		$this->assertSame('Lost', (string) $lost->label);
		$this->assertNull($this->statuses()->find('lost'));
	}

	public function testWorksOutScheduledFromALiveStatusesDate(): void
	{
		$statuses = $this->statuses();

		$this->assertSame('scheduled', $statuses->at('featured', 200, 100)->name);
		$this->assertSame('featured', $statuses->at('featured', 100, 200)->name);
		$this->assertSame('in_review', $statuses->at('in_review', 200, 100)->name, 'Only a live status is scheduled.');
		$this->assertSame('published', $statuses->at('', null, 100)->name, 'No status is published.');
	}

	public function testQueriesFindStatusesByNameAndScope(): void
	{
		$this->assertSame(['Featured', 'Live'], $this->titles($this->posts()), 'Every live status whose time has come.');
		$this->assertSame(['Review'], $this->titles($this->posts()->status('in_review')));
		$this->assertSame(['Featured'], $this->titles($this->posts()->status('featured')));
		$this->assertSame(['Later'], $this->titles($this->posts()->status('scheduled')), 'Scheduled is any live status still to come.');
		$this->assertSame(['Featured', 'Later', 'Live', 'Lost', 'Review', 'Rough'], $this->titles($this->posts()->anyStatus()), 'Every active status, unknown ones included, so they can be fixed.');
		$this->assertSame(['Binned', 'Featured', 'Later', 'Live', 'Lost', 'Review', 'Rough'], $this->titles($this->posts()->allStatuses()));
	}

	public function testEntriesCarryTheirStatus(): void
	{
		$entries = array_column(array_map(static fn (Entry $entry): array => [$entry->title, $entry], $this->posts()->allStatuses()->limit(null)->get()->all()), 1, 0);

		$this->assertTrue($entries['Featured']->isPublished(), 'A live status is on the site.');
		$this->assertFalse($entries['Review']->isPublished());
		$this->assertFalse($entries['Lost']->isPublished(), 'A status nothing registers isn\'t live (D-735).');
		$this->assertSame('lost', $entries['Lost']->status->name, 'Kept in lowercase.');
		$this->assertSame('scheduled', $entries['Later']->status->name);
	}

	public function testStatusNamesAreChecked(): void
	{
		$this->expectException(InvalidStatus::class);

		new Status('In Review');
	}
}
