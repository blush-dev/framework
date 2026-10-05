<?php

/**
 * Filesystem source tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Source\SourceFile;
use Blush\Content\Source\UnreadableSource;
use Blush\Tests\BootsScratchSite;

#[CoversClass(FilesystemSource::class)]
#[CoversClass(SourceFile::class)]
#[CoversClass(UnreadableSource::class)]
final class FilesystemSourceTest extends TestCase
{
	use BootsScratchSite;

	private function source(): ContentSource
	{
		$app = $this->scratchApplication();
		$app->boot();

		return $app->container()->make(ContentSource::class);
	}

	public function testListsContentDocumentsInPathOrder(): void
	{
		$files = [
			'user/content/index.md'               => 'home',
			'user/content/b.MD'                   => 'b',
			'user/content/c.html'                 => 'c',
			'user/content/a/deep/entry.md'        => 'title: Deep',
			'user/content/a/deep/data.yaml'       => 'title: Data',
			'user/content/a/deep/photo.jpg'       => 'jpg',
			'user/content/.hidden.md'             => 'hidden',
			'user/content/.git/config.md'         => 'git',
			'user/content/writing/Archive.php'    => '<?php',
			'user/content/writing/notes.markdown' => 'notes'
		];

		foreach ($files as $path => $contents) {
			$this->writeTemporaryFile($path, $contents);
		}

		$source = $this->source();

		$this->assertSame(
			['a/deep/entry.md', 'b.MD', 'index.md'],
			array_map(static fn (SourceFile $file): string => $file->path, $source->files())
		);

		$stat = $source->stat('index.md');
		$this->assertNotNull($stat);

		$this->assertSame('index.md', $stat->path);
		$this->assertSame(4, $stat->size);
		$this->assertSame(filemtime($this->temporaryDirectory() . '/user/content/index.md'), $stat->modified);
		$this->assertNull($source->stat('missing.md'));
		$this->assertSame('title: Deep', $source->read('a/deep/entry.md'));
	}

	public function testAMissingContentFolderIsEmpty(): void
	{
		$this->assertSame([], $this->source()->files());
	}

	public function testReadingAMissingFileFails(): void
	{
		$this->expectException(UnreadableSource::class);

		$this->source()->read('missing.md');
	}

	public function testPathsAreConfinedToTheContentFolder(): void
	{
		$this->writeTemporaryFile('.env', 'SECRET=1');

		$this->expectException(UnreadableSource::class);

		$this->source()->read('../../.env');
	}
}
