<?php

/**
 * Media id tests.
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
use Blush\Console\Commands\FixMediaIds;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Media\AssignedMediaIds;
use Blush\Media\Index\MediaIndex;
use Blush\Media\Index\MediaIndexer;
use Blush\Media\Index\MediaVariants;
use Blush\Media\MediaException;
use Blush\Media\MediaIdReport;
use Blush\Media\MediaIds;
use Blush\Media\MediaMetadata;
use Blush\Media\MediaMetadataCheck;
use Blush\Support\Uuid;
use Blush\Tests\BootsScratchSite;

#[CoversClass(MediaIds::class)]
#[CoversClass(MediaIdReport::class)]
#[CoversClass(AssignedMediaIds::class)]
#[CoversClass(MediaVariants::class)]
#[CoversClass(FixMediaIds::class)]
final class MediaIdsTest extends TestCase
{
	use BootsScratchSite;

	private const string SHARED = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74';

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
	 * A library brought from another system, with its sizes, beside files of
	 * Blush's own.
	 */
	private function site(): Application
	{
		$this->png('user/media/2019/photo.png', 60, 40);
		$this->png('user/media/2019/photo-30x20.png', 30, 20);
		$this->png('user/media/2019/photo-15x10.png', 15, 10);
		$this->png('user/media/2019/photo-90x60.png', 90, 60);
		$this->png('user/media/2019/photo-24x24.png', 30, 20);
		$this->png('user/media/2019/kept-30x20.png', 30, 20);
		$this->png('user/media/2019/kept.png', 60, 40);
		$this->png('user/media/2019/daisy-3x4.png', 30, 40);
		$this->png('user/media/2026/lake.png', 20, 10);
		$this->png('user/media/2026/copy.png', 20, 10);

		$this->writeTemporaryFile('user/data/media/2019/kept-30x20.png.yml', "alt: Cropped on purpose\nid: 0199b6e2-7f3a-7c41-9d2e-000000000001\n");
		$this->writeTemporaryFile('user/data/media/2019/photo-15x10.png.yml', "alt: A small one\n");
		$this->writeTemporaryFile('user/data/media/2026/lake.png.yml', "alt: A lake\nid: " . self::SHARED . "\n");
		$this->writeTemporaryFile('user/data/media/2026/copy.png.json', '{"id": "' . self::SHARED . '", "alt": "A copy"}');

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testFindsSizesOfAnotherImageByRule(): void
	{
		$app = $this->site();
		$app->container()->make(MediaIndexer::class)->index();

		$records  = $app->container()->make(MediaIndex::class)->snapshot()->records;
		$variants = array_map(static fn ($record): ?string => $record->original, $records);

		$this->assertSame('2019/photo.png', $variants['2019/photo-30x20.png'], 'Sizes belong to the original (D-239).');
		$this->assertSame('2019/photo.png', $variants['2019/photo-15x10.png'], 'Even with a metadata file, without an id.');
		$this->assertNull($variants['2019/photo-90x60.png'], 'Not when larger than the original.');
		$this->assertNull($variants['2019/photo-24x24.png'], 'Not when it isn\'t the size its name says.');
		$this->assertNull($variants['2019/kept-30x20.png'], 'Not when it has an id of its own.');
		$this->assertNull($variants['2019/daisy-3x4.png'], 'Not an aspect ratio in a name, with no original beside it.');
		$this->assertNull($variants['2019/photo.png']);
	}

	public function testReportsMissingAndSharedIds(): void
	{
		$report = $this->site()->container()->make(MediaIds::class)->report();

		$this->assertSame(['2019/daisy-3x4.png', '2019/kept.png', '2019/photo-24x24.png', '2019/photo-90x60.png', '2019/photo.png'], $report->missing, 'Originals only, never sizes of another image.');
		$this->assertSame([self::SHARED => ['2026/copy.png', '2026/lake.png']], $report->duplicates);
		$this->assertFalse($report->isClean());
	}

	public function testGivesMissingIdsAndKeepsSharedOnes(): void
	{
		$app = $this->site();
		$ids = $app->container()->make(MediaIds::class);

		$assigned = $ids->assignMissing(static fn (string $key): bool => $key !== '2019/kept.png');

		$this->assertSame(['2019/daisy-3x4.png', '2019/photo-24x24.png', '2019/photo-90x60.png', '2019/photo.png'], array_keys($assigned->ids), 'Only the files allowed.');
		$this->assertTrue(array_all($assigned->ids, Uuid::isValid(...)));
		$this->assertSame(['id' => $assigned->ids['2019/photo.png']], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2019/photo.png.json'), true), 'A metadata file is written for a file with none, as JSON (D-490).');

		try {
			$ids->keep('2019/photo.png');
			$this->fail('Only a file that shares its id.');
		} catch (MediaException) {
		}

		$kept = $ids->keep('2026/lake.png');

		$this->assertSame(['2026/copy.png'], array_keys($kept->ids));
		$this->assertSame(['2019/kept.png'], $ids->report()->missing);
		$this->assertSame([], $ids->report()->duplicates);

		$copy = json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/media/2026/copy.png.json'), true);

		$this->assertSame(['alt' => 'A copy', 'id' => $kept->ids['2026/copy.png']], $copy, 'The id is written last.');
	}

	public function testTheIdStaysLastAndIsNoField(): void
	{
		$app = $this->site();
		$this->writeTemporaryFile('user/data/media/2019/photo.png.yml', "alt: A photo\nid: 42\ncredit: Me\n");

		$assigned = $app->container()->make(MediaIds::class)->assignMissing();
		$id       = $assigned->ids['2019/photo.png'] ?? '';
		$path     = $this->temporaryDirectory() . '/user/data/media/2019/photo.png.yml';

		$this->assertSame("alt: A photo\ncredit: Me\nid: {$id}\n", file_get_contents($path), 'One that isn\'t a UUID is replaced, and moved last.');

		$metadata = MediaMetadata::fromArray(['alt' => 'A photo', 'id' => strtoupper($id), 'owner' => 'jane']);

		$this->assertSame($id, $metadata->id, 'Read lowercase.');
		$this->assertSame(['alt' => 'A photo'], $metadata->fields(), 'Neither the id nor the owner is a field.');
		$this->assertSame('', new MediaMetadata(['id' => '42'])->id);
	}

	public function testLeavesUnreadableMetadataAlone(): void
	{
		$app = $this->site();
		$this->writeTemporaryFile('user/data/media/2019/kept.png.yml', "alt: [unclosed\n");

		$assigned = $app->container()->make(MediaIds::class)->assignMissing();

		$this->assertArrayHasKey('2019/kept.png', $assigned->failed);
		$this->assertSame("alt: [unclosed\n", file_get_contents($this->temporaryDirectory() . '/user/data/media/2019/kept.png.yml'));
	}

	public function testLintReportsIds(): void
	{
		$app = $this->site();
		$this->writeTemporaryFile('user/data/media/2019/photo.png.yml', "id: not-a-uuid\n");

		[, $violations] = $app->container()->make(MediaMetadataCheck::class)->check();
		$messages       = array_map(static fn (array $list): array => array_map(static fn (Violation $violation): string => "{$violation->severity->value} {$violation}", $list), array_filter($violations));

		$this->assertSame(['error id: has no id; add one with media:ids --write, or on Content health in the admin.'], $messages['user/media/2019/kept.png'] ?? null, 'By the media file, which has no metadata file.');
		$this->assertSame(['error id: isn\'t a UUID; give the file a new one with media:ids --write, or on Content health in the admin.'], $messages['user/data/media/2019/photo.png.yml'] ?? null);
		$this->assertSame(['error id: is also the id of user/data/media/2026/lake.png.yml; keep it on one file and give the others new ones with media:ids --keep, or on Content health in the admin.'], $messages['user/data/media/2026/copy.png.json'] ?? null);
		$this->assertSame(['warning file: describes user/media/2019/photo-15x10.png, a size of user/media/2019/photo.png, whose details are read instead; move these there, or give this file an id of its own to keep it apart.'], $messages['user/data/media/2019/photo-15x10.png.yml'] ?? null);
		$this->assertArrayNotHasKey('user/media/2019/photo-30x20.png', $messages, 'Sizes need no id.');
	}

	public function testTheCommandChecksAndFixes(): void
	{
		$console = $this->site()->container()->make(Console::class);

		$checked = new CommandTester($console)->run('media:ids');

		$this->assertFalse($checked->isSuccessful());
		$this->assertStringContainsString('5 files are missing a valid id; add them with --write. 1 id is shared; keep each on one file with --keep={path}.', $checked->output . $checked->errors);
		$this->assertStringNotContainsString('2019/kept.png', $checked->output, 'Each file only with -v.');

		$fixed = new CommandTester($console)->run('media:ids --write --keep=2026/lake.png');

		$this->assertTrue($fixed->isSuccessful(), $fixed->output . $fixed->errors);
		$this->assertStringContainsString('Gave 5 media files a new id.', $fixed->output);
		$this->assertStringContainsString('Every media file has an id of its own.', $fixed->output);
	}
}
