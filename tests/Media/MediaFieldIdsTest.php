<?php

/**
 * Media field ids tests.
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
use Blush\Console\Commands\RewriteMediaIds;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Lint\Linter;
use Blush\Content\MediaFieldIds;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Field\ViolationKind;
use Blush\Media\MediaValues;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesContentConfig;

#[CoversClass(MediaValues::class)]
#[CoversClass(MediaFieldIds::class)]
#[CoversClass(RewriteMediaIds::class)]
final class MediaFieldIdsTest extends TestCase
{
	use BootsScratchSite;
	use WritesContentConfig;

	private const string LAKE = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74';

	private const string PHOTO = '0199b6e2-7f3a-7c41-9d2e-000000000001';

	private const string RECIPE = '0199b6e2-7f3a-7c41-9d2e-0000000000a1';

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
	 * A recipe whose media fields were written before ids: its featured
	 * image names a size, its gallery a path and an id, and the photo in
	 * its group a file that isn't there.
	 */
	private function site(): Application
	{
		$this->png('user/media/2026/lake.png', 20, 10);
		$this->png('user/media/2019/photo.png', 60, 40);
		$this->png('user/media/2019/photo-30x20.png', 30, 20);
		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"alt": "A lake", "id": "' . self::LAKE . '"}');
		$this->writeTemporaryFile('user/data/media/2019/photo.png.json', '{"alt": "A photo", "id": "' . self::PHOTO . '"}');

		$this->contentConfig(['types' => ['recipe' => ['kind' => 'collection', 'fields' => [
			['name' => 'gallery', 'type' => 'list', 'item' => ['type' => 'media', 'kind' => 'image']],
			['name' => 'rights', 'control' => 'group', 'fields' => [['name' => 'holder'], ['name' => 'photo', 'control' => 'image']]]
		]]]]);

		$this->writeTemporaryFile('user/content/_recipe/chili.md', implode("\n", [
			'---',
			'id: ' . self::RECIPE,
			'title: Chili',
			'published: 2026-01-01',
			'image: /user/media/2019/photo-30x20.png',
			'gallery:',
			'  - /user/media/2026/lake.png',
			'  - ' . strtoupper(self::PHOTO),
			'rights:',
			'  holder: Jane',
			'  photo: /user/media/2026/gone.png',
			'---',
			'Body'
		]));

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testReadsAMediaValueByIdOrByAnOlderPath(): void
	{
		$media = $this->site()->container()->make(MediaValues::class);

		$this->assertSame('2026/lake.png', $media->record(self::LAKE)?->key);
		$this->assertSame('2026/lake.png', $media->record('/user/media/2026/lake.png')?->key);
		$this->assertSame('2019/photo-30x20.png', $media->record('/user/media/2019/photo-30x20.png')?->key, 'A path may name a size.');
		$this->assertSame(self::PHOTO, $media->id('/user/media/2019/photo-30x20.png'), 'Its id is its original\'s.');
		$this->assertNull($media->record('0199b6e2-7f3a-7c41-9d2e-00000000ffff'));
		$this->assertNull($media->address('0199b6e2-7f3a-7c41-9d2e-00000000ffff'), 'An id no file has shows nothing.');
		$this->assertSame('https://cdn.example.com/a.jpg', $media->address('https://cdn.example.com/a.jpg'), 'An address elsewhere is shown as written.');
		$this->assertStringEndsWith('2026/lake.png', (string) $media->address(self::LAKE));
	}

	public function testReportsPathsAndRewritesThoseThatNameAFile(): void
	{
		$app    = $this->site();
		$ids    = $app->container()->make(MediaFieldIds::class);
		$report = $ids->report();

		$this->assertSame([
			['field' => 'image', 'path' => '/user/media/2019/photo-30x20.png', 'id' => self::PHOTO],
			['field' => 'gallery.0', 'path' => '/user/media/2026/lake.png', 'id' => self::LAKE],
			['field' => 'rights.photo', 'path' => '/user/media/2026/gone.png', 'id' => null]
		], $report[self::RECIPE]['values'] ?? null);

		$done = $ids->write();

		$this->assertSame([self::RECIPE], $done['changed']);
		$this->assertSame([], $done['failed']);

		$file = (string) file_get_contents($this->temporaryDirectory() . '/user/content/_recipe/chili.md');

		$this->assertStringContainsString('image: ' . self::PHOTO, $file);
		$this->assertStringContainsString('gallery: [' . self::LAKE . ', ', $file);
		$this->assertStringContainsString('photo: /user/media/2026/gone.png', $file, 'A path naming no file is left.');
		$this->assertSame([['field' => 'rights.photo', 'path' => '/user/media/2026/gone.png', 'id' => null]], $ids->report()[self::RECIPE]['values'] ?? null);
	}

	public function testLintReportsMediaWrittenAsPaths(): void
	{
		$report = $this->site()->container()->make(Linter::class)->lint();
		$found  = array_values(array_filter($report->violations()['_recipe/chili.md'] ?? [], static fn (Violation $violation): bool => $violation->kind === ViolationKind::Media));

		$this->assertSame(['image', 'gallery.0', 'rights.photo'], array_map(static fn (Violation $violation): string => $violation->field, $found));
		$this->assertStringContainsString('content:media-ids', $found[0]->message ?? '');
		$this->assertStringContainsString('names no media file', $found[2]->message ?? '');
	}

	public function testTheCommandChecksAndRewrites(): void
	{
		$console = $this->site()->container()->make(Console::class);
		$checked = new CommandTester($console)->run('content:media-ids');

		$this->assertFalse($checked->isSuccessful());
		$this->assertStringContainsString('3 media values are written as a path.', $checked->output . $checked->errors);

		$fixed = new CommandTester($console)->run('content:media-ids --write');

		$this->assertFalse($fixed->isSuccessful(), 'The path naming no file is still listed.');
		$this->assertStringContainsString('1 media value is written as a path.', $fixed->output . $fixed->errors);
	}
}
