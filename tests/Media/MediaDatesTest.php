<?php

/**
 * Media upload date tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Media;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\FixMediaDates;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Media\FilledMediaDates;
use Blush\Media\Embedded\EmbeddedMetadata;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaDates;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataCheck;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaDates::class)]
#[CoversClass(FilledMediaDates::class)]
#[CoversClass(FixMediaDates::class)]
final class MediaDatesTest extends TestCase
{
	use BootsScratchSite;

	private const string COPIED = '2022-10-30 12:00:00';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function png(string $path, string $modified = self::COPIED): void
	{
		$image = imagecreatetruecolor(4, 4);
		$this->assertNotFalse($image);
		$this->writeTemporaryFile($path, '');
		imagepng($image, $this->temporaryDirectory() . '/' . $path);
		touch($this->temporaryDirectory() . '/' . $path, new DateTimeImmutable($modified, new DateTimeZone('UTC'))->getTimestamp());
	}

	/**
	 * A library copied from another system on one day, its files in dated
	 * folders, and one uploaded since.
	 */
	private function site(): Application
	{
		$this->png('user/media/2006/04/old.png');
		$this->png('user/media/2011/04/brad.png');
		$this->png('user/media/2011/04/kept.png', '2011-04-20 08:30:00');
		$this->png('user/media/2021/07/trees.png');
		$this->png('user/media/2026/10/new.png', '2026-10-10 09:00:00');
		$this->png('user/media/2026/10/new-4x4.png', '2026-10-10 09:00:00');
		$this->writeTemporaryFile('user/data/media/2026/10/new.png.json', '{"uploaded": "2026-10-10 09:00:00 +00:00", "id": "0199b6e2-7f3a-7c41-9d2e-000000000001"}');
		$this->writeTemporaryFile('user/data/media/2006/04/old.png.json', '{"alt": "Old", "id": "0199b6e2-7f3a-7c41-9d2e-000000000002"}');

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	/**
	 * @param array<string, mixed> $metadata
	 */
	private static function record(string $key, string $modified, ?string $created = null, array $metadata = []): MediaRecord
	{
		return new MediaRecord(
			$key,
			'/media/' . $key,
			'image/png',
			1,
			new DateTimeImmutable($modified, new DateTimeZone('UTC'))->getTimestamp(),
			metadata: $metadata,
			embedded: $created === null ? null : new EmbeddedMetadata(['created' => $created])
		);
	}

	private static function guess(MediaRecord $record): string
	{
		return MediaDates::guess($record, new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}

	public function testGuessesFromDatedFolders(): void
	{
		$this->assertSame('2006-04-01 00:00:00', self::guess(self::record('2006/04/a.png', self::COPIED)), 'A copied file: the start of its month.');
		$this->assertSame('2011-04-20 08:30:00', self::guess(self::record('2011/04/a.png', '2011-04-20 08:30:00')), 'Its modified time, in its month.');
		$this->assertSame('2011-04-03 10:00:00', self::guess(self::record('2011/04/a.png', self::COPIED, '2011-04-03 10:00:00')), 'The date it was made, in its month.');
		$this->assertSame('2011-04-01 00:00:00', self::guess(self::record('2011/04/a.png', self::COPIED, '2009-01-01 10:00:00')), 'Made before its month.');
		$this->assertSame('2011-04-15 00:00:00', self::guess(self::record('uploads/2011/04/15/a.png', self::COPIED)), 'A day, under another folder.');
		$this->assertSame('2011-01-01 00:00:00', self::guess(self::record('2011/a.png', self::COPIED)), 'A year.');
		$this->assertSame('2011-04-01 00:00:00', self::guess(self::record('2011/04/12-photos/a.png', self::COPIED)), 'A folder that isn\'t a day.');
		$this->assertSame(self::COPIED, self::guess(self::record('photos/2011.png', self::COPIED)), 'No dated folder: its modified time.');
	}

	public function testAStoredDateWins(): void
	{
		$record = self::record('2006/04/a.png', self::COPIED, metadata: ['uploaded' => '2020-01-02 03:04:05 -05:00']);

		$this->assertSame('2020-01-02T03:04:05-05:00', MediaDates::of($record, new DateTimeZone('UTC'))->format(DATE_ATOM));
		$this->assertNull(new MediaMetadata(['uploaded' => '2020-01-02'])->uploaded, 'Only a date and time with its offset.');
		$this->assertArrayNotHasKey('uploaded', new MediaMetadata(['uploaded' => '2020-01-02 03:04:05 +00:00', 'alt' => 'A'])->fields(), 'Not a field.');
	}

	public function testTheLibraryIsNewestUploadFirst(): void
	{
		$records = $this->site()->container()->make(MediaLibrary::class)->query(new MediaQuery())->records;

		$this->assertSame(
			['2026/10/new.png', '2021/07/trees.png', '2011/04/kept.png', '2011/04/brad.png', '2006/04/old.png'],
			array_map(static fn (MediaRecord $record): string => $record->key, $records)
		);
	}

	public function testFillsMissingDates(): void
	{
		$app    = $this->site();
		$dates  = $app->container()->make(MediaDates::class);
		$filled = $dates->fill(static fn (string $key): bool => $key !== '2021/07/trees.png');

		$this->assertSame([
			'2006/04/old.png'  => '2006-04-01 00:00:00 +00:00',
			'2011/04/brad.png' => '2011-04-01 00:00:00 +00:00',
			'2011/04/kept.png' => '2011-04-20 08:30:00 +00:00'
		], $filled->dates);
		$this->assertSame([], $filled->failed);
		$this->assertSame(['2021/07/trees.png'], array_keys($dates->missing()), 'Only the files allowed; never a size.');

		$written = (string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2006/04/old.png.json');

		$this->assertMatchesRegularExpression('/"alt": "Old",\s+"uploaded": "2006-04-01 00:00:00 \+00:00",\s+"id"/', $written, 'Before the id, which stays last.');
	}

	public function testLintReportsAMalformedDate(): void
	{
		$this->writeTemporaryFile('user/data/media/2021/07/trees.png.json', '{"uploaded": "last week", "id": "0199b6e2-7f3a-7c41-9d2e-000000000003"}');

		[, $violations] = $this->site()->container()->make(MediaMetadataCheck::class)->check();

		$this->assertSame(
			['uploaded: isn\'t a date and time with its offset, such as 2026-10-10 19:03:00 +00:00; the library orders the file by a likely one until it\'s fixed.'],
			array_map(static fn (Violation $violation): string => "{$violation->field}: {$violation->message}", $violations['user/data/media/2021/07/trees.png.json'] ?? [])
		);
	}

	public function testTheCommandChecksAndFixes(): void
	{
		$console = $this->site()->container()->make(Console::class);

		$checked = new CommandTester($console)->run('media:dates');

		$this->assertFalse($checked->isSuccessful());
		$this->assertStringContainsString("4 files have no upload date, or one not in UTC.\n  Write them with blush media:dates --write.", $checked->output . $checked->errors);
		$this->assertStringNotContainsString('2006/04/old.png', $checked->output, 'Each file only with -v.');

		$fixed = new CommandTester($console)->run('media:dates --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->output . $fixed->errors);
		$this->assertStringContainsString('Gave 4 media files an upload date.', $fixed->output);
		$this->assertStringContainsString('Every media file has an upload date in UTC.', $fixed->output);
	}

	public function testWritesDatesInUtc(): void
	{
		$this->writeTemporaryFile('user/data/media/2021/07/trees.png.json', '{"uploaded": "2021-07-04 20:00:00 -05:00", "id": "0199b6e2-7f3a-7c41-9d2e-000000000003"}');

		$dates = $this->site()->container()->make(MediaDates::class);

		$this->assertSame('2021-07-05 01:00:00 +00:00', MediaMetadata::utc($dates->missing()['2021/07/trees.png'] ?? ''), 'Another offset is written again.');
		$this->assertArrayNotHasKey('2026/10/new.png', $dates->missing(), 'Already in UTC.');
		$this->assertSame('2021-07-05 01:00:00 +00:00', $dates->fill()->dates['2021/07/trees.png'] ?? null);
		$this->assertSame('last week', MediaMetadata::utc('last week'), 'Text that isn\'t a date is left as it is.');
	}
}
