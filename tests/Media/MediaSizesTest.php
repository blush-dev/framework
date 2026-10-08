<?php

/**
 * Media size tests.
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
use Blush\Console\Commands\RecordMediaSizes;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\Index\MediaVariants;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataCheck;
use Blush\Media\MediaSizeReport;
use Blush\Media\MediaSizes;
use Blush\Media\RecordedMediaSizes;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaSizes::class)]
#[CoversClass(MediaSizeReport::class)]
#[CoversClass(RecordedMediaSizes::class)]
#[CoversClass(MediaVariants::class)]
#[CoversClass(MediaLibrary::class)]
#[CoversClass(RecordMediaSizes::class)]
final class MediaSizesTest extends TestCase
{
	use BootsScratchSite;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * @param positive-int $width
	 * @param positive-int $height
	 */
	private function png(string $path, int $width, int $height): void
	{
		$image = imagecreatetruecolor($width, $height);
		$this->assertNotFalse($image);
		$this->writeTemporaryFile($path, '');
		imagepng($image, $this->temporaryDirectory() . '/' . $path);
	}

	/**
	 * An image with sizes found by name, one listing a size by another name,
	 * and one listing a file that's gone.
	 */
	private function site(): Application
	{
		$this->png('user/media/2019/photo.png', 60, 40);
		$this->png('user/media/2019/photo-30x20.png', 30, 20);
		$this->png('user/media/2019/photo-15x10.png', 15, 10);
		$this->png('user/media/2026/lake.png', 40, 20);
		$this->png('user/media/2026/lake-small.png', 20, 10);
		$this->png('user/media/2026/kite.png', 20, 20);

		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"alt": "A photo", "id": "0199b6e2-7f3a-7c41-9d2e-000000000001"}');
		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"alt": "A lake", "sizes": {"2026/lake-small.png": {"width": 20, "height": 10}}, "id": "0199b6e2-7f3a-7c41-9d2e-000000000002"}');
		$this->writeTemporaryFile('user/data/media/2026/lake-small.png.json', '{"id": "0199b6e2-7f3a-7c41-9d2e-000000000003"}');
		$this->writeTemporaryFile('user/data/media/2026/kite.png.json', '{"sizes": {"2026/kite-10x10.png": {"width": 10, "height": 10}}, "id": "0199b6e2-7f3a-7c41-9d2e-000000000004"}');

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testRecordedSizesComeFirst(): void
	{
		$library = $this->site()->container()->make(MediaLibrary::class);

		$this->assertSame('2026/lake.png', $library->find('2026/lake-small.png')?->original, 'Listed by name, even with an id of its own (D-488).');
		$this->assertSame('2019/photo.png', $library->find('2019/photo-30x20.png')?->original, 'Found by rule until it\'s recorded.');
		$this->assertSame(['2019/photo-15x10.png', '2019/photo-30x20.png'], array_map(static fn (MediaRecord $record): string => $record->key, $library->sizes('2019/photo.png')), 'Smallest first.');
	}

	public function testTheLibraryListsOneItemPerImage(): void
	{
		$library = $this->site()->container()->make(MediaLibrary::class);
		$keys    = array_map(static fn (MediaRecord $record): string => $record->key, $library->query(new MediaQuery())->records);

		sort($keys);

		$this->assertSame(['2019/photo.png', '2026/kite.png', '2026/lake.png'], $keys);
		$this->assertSame(3, $library->query(new MediaQuery(per: 1))->total);
	}

	public function testReportsAndRecordsSizes(): void
	{
		$app   = $this->site();
		$sizes = $app->container()->make(MediaSizes::class);

		$report = $sizes->report();

		$this->assertSame(['2019/photo.png' => ['2019/photo-15x10.png', '2019/photo-30x20.png']], $report->unrecorded);
		$this->assertSame(['2026/kite.png' => ['2026/kite-10x10.png']], $report->stale, 'A listed file that\'s gone.');
		$this->assertSame(['2019/photo.png', '2026/kite.png'], $report->images());
		$this->assertSame(2, $report->count());

		$recorded = $sizes->record(static fn (string $key): bool => $key !== '2026/kite.png');

		$this->assertSame(['2019/photo.png' => ['2019/photo-15x10.png', '2019/photo-30x20.png']], $recorded->images, 'Only the images allowed.');
		$this->assertSame(
			['alt' => 'A photo', 'sizes' => ['2019/photo-15x10.png' => ['width' => 15, 'height' => 10], '2019/photo-30x20.png' => ['width' => 30, 'height' => 20]], 'id' => '0199b6e2-7f3a-7c41-9d2e-000000000001'],
			json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2019/photo.png.json'), true),
			'Before the id.'
		);

		$sizes->record();

		$this->assertSame(['id' => '0199b6e2-7f3a-7c41-9d2e-000000000004'], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2026/kite.png.json'), true), 'An image with no sizes loses the key.');
		$this->assertTrue($sizes->report()->isClean());

		$metadata = new MediaMetadata(['alt' => 'A photo', 'sizes' => ['2019/photo-15x10.png' => ['width' => 15, 'height' => 10], 'bad' => 'value']]);

		$this->assertSame(['2019/photo-15x10.png' => ['width' => 15, 'height' => 10]], $metadata->sizes, 'Entries that aren\'t a map are left out.');
		$this->assertSame(['alt' => 'A photo'], $metadata->fields(), 'Sizes aren\'t a field.');
	}

	public function testRecordedSizesAreKnownByName(): void
	{
		$app = $this->site();
		$app->container()->make(MediaSizes::class)->record();

		// Renamed from the rule's shape, so only the list says what it is.
		rename($this->temporaryDirectory() . '/user/media/2019/photo-30x20.png', $this->temporaryDirectory() . '/user/media/2019/photo-medium.png');
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"sizes": {"2019/photo-medium.png": {"width": 30, "height": 20}, "2019/photo-15x10.png": {"width": 15, "height": 10}}, "id": "0199b6e2-7f3a-7c41-9d2e-000000000001"}');

		$library = $app->container()->make(MediaLibrary::class);
		$library->refresh(['2019/photo.png']);

		$this->assertSame('2019/photo.png', $library->find('2019/photo-medium.png')?->original);
	}

	public function testLintReportsSizes(): void
	{
		$app = $this->site();
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"sizes": ["one", "two"], "id": "0199b6e2-7f3a-7c41-9d2e-000000000001"}');

		[, $violations] = $app->container()->make(MediaMetadataCheck::class)->check();
		$messages       = array_map(static fn (array $list): array => array_map(static fn (Violation $violation): string => "{$violation->severity->value} {$violation}", $list), array_filter($violations));

		$this->assertSame(['error sizes: isn\'t a map of each size\'s file to its width and height; record them again with media:sizes --write.'], $messages['user/data/media/2019/photo.png.json'] ?? null);
		$this->assertSame(['warning sizes: lists user/media/2026/kite-10x10.png, which isn\'t one of its sizes (it\'s gone, or another image\'s); record them again with media:sizes --write, or on Site Health in the admin.'], $messages['user/data/media/2026/kite.png.json'] ?? null);
		$this->assertSame(['warning file: describes user/media/2026/lake-small.png, a size of user/media/2026/lake.png, whose details are read instead; move these there, or give this file an id of its own to keep it apart.'], $messages['user/data/media/2026/lake-small.png.json'] ?? null);
		$this->assertArrayNotHasKey('user/media/2026/lake-small.png', $messages, 'A recorded size needs no id.');
	}

	public function testTheCommandChecksAndRecords(): void
	{
		$console = $this->site()->container()->make(Console::class);

		$checked = new CommandTester($console)->run('media:sizes');

		$this->assertFalse($checked->isSuccessful());
		$this->assertStringContainsString('2 sizes of 1 image aren\'t recorded. 1 image lists files that aren\'t its sizes. Record them with --write.', $checked->output . $checked->errors);

		$recorded = new CommandTester($console)->run('media:sizes --write');

		$this->assertTrue($recorded->isSuccessful(), $recorded->output . $recorded->errors);
		$this->assertStringContainsString('Recorded the sizes of 2 images.', $recorded->output);
		$this->assertStringContainsString('Every image lists its sizes.', $recorded->output);
	}
}
