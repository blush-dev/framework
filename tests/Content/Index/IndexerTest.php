<?php

/**
 * Indexer tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Events\ContentIndexed;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\IndexReport;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\ParsedEntry;
use Blush\Content\Index\PhpIndex;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Visibility;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(Indexer::class)]
#[CoversClass(IndexReport::class)]
#[CoversClass(IndexRecord::class)]
#[CoversClass(IndexSnapshot::class)]
#[CoversClass(RecordBuilder::class)]
#[CoversClass(ParsedEntry::class)]
#[CoversClass(PhpIndex::class)]
#[CoversClass(FilesystemSource::class)]
#[CoversClass(SourceFile::class)]
#[CoversClass(ContentIndexed::class)]
final class IndexerTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	/**
	 * @var list<ContentIndexed>
	 */
	private array $events = [];

	protected function setUp(): void
	{
		$this->standardContent();

		$this->app = $this->site();
		$this->app->container()->make(ListenerRegistry::class)->listen(ContentIndexed::class, function (ContentIndexed $event): void {
			$this->events[] = $event;
		});
	}

	private function indexer(): Indexer
	{
		return $this->app->container()->make(Indexer::class);
	}

	private function snapshot(): IndexSnapshot
	{
		return $this->app->container()->make(ContentIndex::class)->snapshot();
	}

	private function record(string $id): IndexRecord
	{
		$record = $this->snapshot()->record($id);
		$this->assertNotNull($record, "No record for {$id}.");

		return $record;
	}

	public function testIndexesEveryContentFile(): void
	{
		$report = $this->indexer()->index();

		$this->assertTrue($report->written);
		$this->assertTrue($report->full);
		$this->assertSame(19, $report->total);
		$this->assertCount(19, $report->added);
		$this->assertSame([], $report->failures);
		$this->assertFileExists($this->temporaryDirectory() . '/storage/index/content.php');
		$this->assertNotContains('_posts/hello/photo.jpg', array_keys($this->snapshot()->records));
		$this->assertCount(1, $this->events);
		$this->assertSame($report, $this->events[0]->report);
	}

	public function testAppliesTheFileNameConventions(): void
	{
		$this->indexer()->index();

		$cases = [
			// id => [type, slug, key, directory, landing]
			'index.md'                     => ['page', 'index', '', '', true],
			'about/index.md'               => ['page', 'about', 'about', '', false],
			'about/biography.md'           => ['page', 'biography', 'about/biography', 'about', false],
			'_posts/index.md'              => ['post', 'index', '', '_posts', true],
			'_posts/2003-04-15.welcome.md' => ['post', 'welcome', 'welcome', '_posts', false],
			'_posts/hello/index.md'        => ['post', 'hello', 'hello', '_posts', false],
			'topics/art.md'                => ['category', 'art', 'art', 'topics', false],
			'notes.md'                     => ['page', 'notes', 'notes', '', false]
		];

		foreach ($cases as $id => [$type, $slug, $key, $directory, $landing]) {
			$record = $this->record($id);

			$this->assertSame([$type, $slug, $key, $directory, $landing], [$record->type, $record->slug, $record->key, $record->directory, $record->landing], $id);
		}
	}

	public function testPrivateNamesAreHiddenAndDraftFoldersAreDrafts(): void
	{
		$this->entry('_posts/_drafts/soon.md', 'title: Soon');
		$this->entry('_posts/visible.md', "title: Visible\nvisibility: unlisted");
		$this->indexer()->index();

		$this->assertSame(Visibility::Hidden, $this->record('_private.md')->visibility);
		$this->assertSame(Visibility::Hidden, $this->record('__drafts/idea.md')->visibility);
		$this->assertSame(Visibility::Hidden, $this->record('_posts/_drafts/soon.md')->visibility);
		$this->assertSame(Status::Draft, $this->record('_posts/_drafts/soon.md')->status);
		$this->assertSame(Visibility::Unlisted, $this->record('_posts/visible.md')->visibility);
		$this->assertSame(Visibility::Public, $this->record('_posts/2003-04-15.welcome.md')->visibility);
	}

	public function testRecordsDatesTermsAndFrontMatter(): void
	{
		$this->indexer()->index();

		$welcome = $this->record('_posts/2003-04-15.welcome.md');

		$this->assertSame(strtotime('2003-04-15 17:39:00 -05:00'), $welcome->published);
		$this->assertSame($welcome->published, $welcome->updated);
		$this->assertSame('20030415173900', $welcome->date);
		$this->assertSame('Welcome', $welcome->title);
		$this->assertSame('en_US', $welcome->locale);
		$this->assertSame(['old-posts'], $welcome->terms['category']);
		$this->assertSame(['justintadlock'], $welcome->terms['profile']);

		$spring = $this->record('_posts/2008-04-05.spring.md');

		$this->assertSame(['art', 'book-reviews'], $spring->terms['category']);
		$this->assertSame(['book-reviews' => 'Book Reviews'], $spring->labels['category']);
		$this->assertSame(['tag' => 'flowers'], $spring->extra);
		$this->assertSame('20080405090000', $spring->date);

		$home = $this->record('index.md');

		$this->assertNull($home->published);
		$this->assertSame($home->modified, $home->updated);
	}

	public function testDerivesLookupsAndConflicts(): void
	{
		$this->entry('about.md', 'title: Old About');
		$this->indexer()->index();

		$snapshot = $this->snapshot();

		$this->assertSame('about/index.md', $snapshot->find('en', 'page', 'about'));
		$this->assertSame(['en/page/about' => ['about.md', 'about/index.md']], $snapshot->conflicts);
		$this->assertSame('_posts/index.md', $snapshot->find('en', 'post', ''));
		$this->assertSame(['_posts/2003-04-15.welcome.md', '_posts/2008-04-05.spring.md'], $snapshot->referencing('profile', 'justintadlock'));
		$this->assertSame(['old-posts' => 'old-posts', 'art' => 'art', 'book-reviews' => 'Book Reviews'], $snapshot->termLabels('category'));
		$this->assertSame(strtotime('2026-12-25 08:00:00 America/Chicago'), $snapshot->scheduled);
	}

	public function testIncrementalRunsReadOnlyChangedFiles(): void
	{
		$this->indexer()->index();

		$unchanged = $this->indexer()->index();

		$this->assertFalse($unchanged->written);
		$this->assertFalse($unchanged->full);
		$this->assertFalse($unchanged->hasChanges());
		$this->assertCount(1, $this->events);

		$path = $this->temporaryDirectory() . '/user/content/_posts/2003-04-15.welcome.md';
		touch($path, time() + 10);
		$this->entry('_posts/2008-04-05.spring.md', "title: Spring Again\npublished: 2008-04-05 09:00:00");
		touch($this->temporaryDirectory() . '/user/content/_posts/2008-04-05.spring.md', time() + 20);
		$this->entry('_posts/new.md', 'title: New');
		unlink($this->temporaryDirectory() . '/user/content/topics/art.md');

		$report = $this->indexer()->index();

		$this->assertTrue($report->written);
		$this->assertSame(['_posts/new.md'], $report->added);
		$this->assertSame(['_posts/2008-04-05.spring.md'], $report->changed);
		$this->assertSame(['topics/art.md'], $report->removed);
		$this->assertSame(['_posts/new.md', '_posts/2008-04-05.spring.md', 'topics/art.md'], $report->changedIds());
		$this->assertSame('Spring Again', $this->record('_posts/2008-04-05.spring.md')->title);
		$this->assertSame(time() + 10, $this->record('_posts/2003-04-15.welcome.md')->modified);
		$this->assertCount(2, $this->events);
	}

	public function testFullRunsWhenAskedOrWhenTheTypesChange(): void
	{
		$this->indexer()->index();

		$this->assertTrue($this->indexer()->index(full: true)->full);

		unlink($this->temporaryDirectory() . '/user/data/types/category.json');
		unlink($this->temporaryDirectory() . '/user/data/relations/category.json');
		$this->app = $this->site();

		$report = $this->indexer()->index();

		$this->assertTrue($report->full);
		$this->assertSame('page', $this->record('topics/art.md')->type);
	}

	public function testReportsFilesThatCannotBeParsed(): void
	{
		$this->indexer()->index();
		$this->entry('_posts/2003-04-15.welcome.md', "title: [unclosed\n");

		$progress = [];
		$report   = $this->indexer()->index(progress: static function (int $done, int $total) use (&$progress): void {
			$progress[] = "{$done}/{$total}";
		});

		$this->assertArrayHasKey('_posts/2003-04-15.welcome.md', $report->failures);
		$this->assertSame(['_posts/2003-04-15.welcome.md'], $report->removed);
		$this->assertNull($this->snapshot()->record('_posts/2003-04-15.welcome.md'));
		$this->assertSame('19/19', array_last($progress));
		$this->assertCount(19, $progress);
	}

	public function testClearingTheIndexDeletesIt(): void
	{
		$index = $this->app->container()->make(ContentIndex::class);
		$this->indexer()->index();

		$this->assertTrue($index->exists());

		$index->clear();

		$this->assertFalse($index->exists());
		$this->assertTrue($index->snapshot()->isEmpty());
	}

	public function testAnIndexFromAnotherVersionIsRebuilt(): void
	{
		$this->writeTemporaryFile('storage/index/content.php', "<?php return ['version' => 0, 'records' => []];");

		$this->assertTrue(IndexSnapshot::fromArray(['version' => 0])->isEmpty());
		$this->assertTrue($this->indexer()->index()->full);
	}
}
