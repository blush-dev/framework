<?php

/**
 * Media index tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\IndexMedia;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Job\JobRunner;
use Blush\Job\Jobs\MediaIndexJob;
use Blush\Job\JobStatus;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Media\Index\MediaIndexReport;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\Index\MediaSnapshot;
use Blush\Media\MediaKind;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaIndex::class)]
#[CoversClass(MediaIndexer::class)]
#[CoversClass(MediaIndexReport::class)]
#[CoversClass(MediaLibrary::class)]
#[CoversClass(MediaQuery::class)]
#[CoversClass(MediaRecord::class)]
#[CoversClass(MediaSnapshot::class)]
#[CoversClass(IndexMedia::class)]
#[CoversClass(MediaIndexJob::class)]
final class MediaIndexTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * A 2×1 PNG.
	 */
	private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEUlEQVR42mP8z8Dwn4GBgQEAFQYCAR4v9JgAAAAASUVORK5CYII=';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function site(): Application
	{
		$png = (string) base64_decode(self::PNG, true);

		touch($this->writeTemporaryFile('user/media/2020/old.png', $png), 1_600_000_000);
		touch($this->writeTemporaryFile('user/media/2026/lake.png', $png), 1_700_000_000);
		$this->writeTemporaryFile('user/media/2026/.hidden.png', $png);
		$this->writeTemporaryFile('user/media/notes.txt', 'not media');
		$this->writeTemporaryFile('user/media/fake.png', 'not a PNG');
		touch($this->writeTemporaryFile('user/content/trip/beach.png', $png), 1_650_000_000);
		$this->writeTemporaryFile('user/content/trip/index.md', "---\ntitle: Trip\n---\n");
		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"alt": "A lake at dawn", "caption": "Mist on the water"}');
		$this->writeTemporaryFile('user/data/media/2019/gone.png.json', '{"alt": "Deleted"}');

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testIndexesTheLibrary(): void
	{
		$app    = $this->site();
		$report = $app->container()->make(MediaIndexer::class)->index();

		$this->assertTrue($report->written);
		$this->assertSame(['2020/old.png', '2026/lake.png'], $report->added, 'Allowed files in user/media only: not hidden, other types, a file that isn\'t what its name says, or one beside an entry (D-294).');
		$this->assertSame(['2019/gone.png'], $report->orphans, 'A metadata file with no media file.');

		$lake = $app->container()->make(MediaIndex::class)->snapshot()->records['2026/lake.png'] ?? null;

		$this->assertInstanceOf(MediaRecord::class, $lake);
		$this->assertSame(['/media/2026/lake.png', 'image/png', 2, 1, 'A lake at dawn'], [$lake->url, $lake->mime, $lake->width, $lake->height, $lake->metadata()->alt]);
	}

	public function testRefreshesOnlyWhatChanged(): void
	{
		$app     = $this->site();
		$indexer = $app->container()->make(MediaIndexer::class);
		$indexer->index();

		$this->assertFalse($indexer->index()->written, 'Nothing changed.');

		$metadata = $this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"alt": "A lake at noon"}');
		touch($metadata, time() + 10);
		unlink($this->temporaryDirectory() . '/user/media/2020/old.png');

		$report = $indexer->index();

		$this->assertSame(['2026/lake.png'], $report->changed, 'Its metadata file changed.');
		$this->assertSame(['2020/old.png'], $report->removed);
		$lake = $app->container()->make(MediaIndex::class)->snapshot()->records['2026/lake.png'] ?? null;

		$this->assertSame('A lake at noon', $lake?->metadata()->alt);
	}

	public function testARebuildCanTakeSeveralRuns(): void
	{
		$app     = $this->site();
		$indexer = $app->container()->make(MediaIndexer::class);
		$index   = $app->container()->make(MediaIndex::class);

		$first = $indexer->index(limit: 1);

		$this->assertSame(2, $first->pending);
		$this->assertSame(['2020/old.png'], array_keys($index->snapshot()->records), 'A file not read yet isn\'t listed.');
		$this->assertSame(['2026/lake.png', 'fake.png'], $index->snapshot()->pending);

		$this->assertSame(0, $indexer->index(limit: 2)->pending, 'fake.png is read, and isn\'t one.');
		$this->assertSame(['2020/old.png', '2026/lake.png'], array_keys($index->snapshot()->records));
		$this->assertSame([], $index->snapshot()->pending);
		$this->assertFalse($indexer->index(limit: 2)->written, 'Nothing is left to read: a file that isn\'t what its name says isn\'t read again until it changes.');

		$again = $indexer->index(full: true, limit: 2);

		$this->assertSame(1, $again->pending, 'A full rebuild reads every file again.');
		$this->assertCount(2, $index->snapshot()->records, 'Files not read again keep their records meanwhile.');
	}

	public function testTheLibraryReadsABatchAndQueuesTheRest(): void
	{
		$png = (string) base64_decode(self::PNG, true);

		for ($n = 1; $n <= MediaIndexJob::BATCH + 2; $n++) {
			$this->writeTemporaryFile(sprintf('user/media/many/%03d.png', $n), $png);
		}

		$app     = $this->scratchApplication();
		$app->boot();
		$library = $app->container()->make(MediaLibrary::class);

		$this->assertSame(MediaIndexJob::BATCH, $library->query(new MediaQuery())->total, 'The first batch is read at once.');

		$job = $library->indexing();

		$this->assertNotNull($job, 'The rest is a job.');
		$this->assertSame(JobStatus::Done, $app->container()->make(JobRunner::class)->finish($job)->status);
		$this->assertSame([], $app->container()->make(MediaIndex::class)->snapshot()->pending);
		$this->assertCount(MediaIndexJob::BATCH + 2, $app->container()->make(MediaIndex::class)->snapshot()->records);
		$this->assertNull($library->indexing());
	}

	public function testQueriesTheLibrary(): void
	{
		$library = $this->site()->container()->make(MediaLibrary::class);
		$keys    = static fn (MediaQuery $query): array => array_map(static fn (MediaRecord $record): string => $record->key, $library->query($query)->records);

		$this->assertSame(['2026/lake.png', '2020/old.png'], $keys(new MediaQuery()), 'The library, newest first, built on first use.');
		$this->assertSame(['2026/lake.png'], $keys(new MediaQuery(search: 'MIST')), 'Metadata is searched.');
		$this->assertSame(['2020/old.png'], $keys(new MediaQuery(missingAlt: true)));
		$this->assertSame([], $keys(new MediaQuery(kind: MediaKind::Audio)));
		$this->assertSame(['2020/old.png'], $keys(new MediaQuery(page: 2, per: 1)));
		$this->assertSame(2, $library->query(new MediaQuery(per: 1))->total);
	}

	public function testRebuildsForOtherTypes(): void
	{
		$app = $this->site();
		$app->container()->make(MediaIndexer::class)->index();

		$this->writeTemporaryFile('config/media.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Media\\MediaConfig(types: ['image/jpeg']);\n");

		$other = $this->scratchApplication();
		$other->boot();

		$this->assertSame([], $other->container()->make(MediaLibrary::class)->query(new MediaQuery())->records, 'Built with other types, so rebuilt: no PNGs allowed.');
	}

	public function testTheCommandIndexesAndWarnsOfOrphans(): void
	{
		$result = new CommandTester($this->site()->container()->make(Console::class))->run('media:index');

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString('Indexed 2 media files (2 added, 0 changed, 0 removed)', $result->output);
		$this->assertStringContainsString('user/data/media/2019/gone.png describes a media file that isn\'t there', $result->output . $result->errors);
	}
}
