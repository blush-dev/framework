<?php

/**
 * Media files tests.
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
use Blush\Core\Paths;
use Blush\Media\MediaFiles;
use Blush\Media\MediaMetadataStore;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(MediaFiles::class)]
final class MediaFilesTest extends TestCase
{
	use TemporaryDirectory;

	private const string LAKE = '0199b6e2-0000-7000-8000-000000000001';

	private const string SHARED = '0199b6e2-0000-7000-8000-000000000002';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function files(): MediaFiles
	{
		$paths = Paths::fromRoot($this->temporaryDirectory());

		return new MediaFiles($paths, new FileTransactions($paths, new Filesystem()));
	}

	/**
	 * @return array<array-key, mixed>
	 */
	private function json(string $relative): array
	{
		$data = json_decode((string) file_get_contents($this->temporaryDirectory() . "/user/data/media/{$relative}"), true);

		return is_array($data) ? $data : [];
	}

	public function testTheMetadataFilesAreTheTable(): void
	{
		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"$schema": "../../../media.schema.json", "alt": "A lake", "content": "Long.", "id": "' . self::LAKE . '"}');
		$this->writeTemporaryFile('user/data/media/2026/no-id.png.json', '{"alt": "Missing"}');
		$this->writeTemporaryFile('user/data/media/a.png.json', '{"id": "' . self::SHARED . '"}');
		$this->writeTemporaryFile('user/data/media/b.png.json', '{"id": "' . self::SHARED . '"}');
		$this->writeTemporaryFile('user/data/media/broken.png.json', '{"alt": [');

		$files = $this->files();
		$table = MediaMetadataStore::table();
		$all   = $files->select($table, new RecordQuery())->records;

		$this->assertSame([self::LAKE], array_map(static fn (Record $record): string => $record->id, $all), 'Without an id, sharing one, or broken isn\'t a record (D-674).');
		$this->assertSame(['alt' => 'A lake', 'path' => '2026/lake.png'], $all[0]->fields, 'Its path from where its file is; no $schema.');
		$this->assertSame('Long.', $all[0]->content, 'Its description is its content.');
		$this->assertSame('2026/lake.png', $files->select($table, new RecordQuery()->where('path', Operator::Equal, '2026/lake.png'))->records[0]->fields['path'] ?? null, 'Found by path.');
		$this->assertSame(['2026/lake.png', '2026/no-id.png', 'a.png', 'b.png', 'broken.png'], array_keys($files->stamps()), 'Every file, for the index.');
		$this->assertSame(['alt' => 'Missing'], $files->raw('2026/no-id.png'), 'As it is, for checking.');
		$this->assertSame('user/data/media/2026/lake.png.json', $files->location($table, self::LAKE));
	}

	public function testWritesAndMovesARecordsFile(): void
	{
		$this->writeTemporaryFile('user/data/media/2026/lake.png.json', '{"$schema": "x.json", "alt": "A lake", "id": "' . self::LAKE . '"}');

		$files = $this->files();
		$table = MediaMetadataStore::table();
		$lake  = $files->find($table, self::LAKE);

		$this->assertNotNull($lake);

		$files->save($table, $lake->with('caption', 'Calm')->withContent('Long.'));

		$this->assertSame(['$schema' => 'x.json', 'alt' => 'A lake', 'caption' => 'Calm', 'content' => 'Long.', 'id' => self::LAKE], $this->json('2026/lake.png.json'), 'Its $schema first, no path, the id last.');

		$files->save($table, $lake->with('path', '2027/lake.png'));

		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/media/2026/lake.png.json', 'A moved file\'s metadata moves with it (D-674).');
		$this->assertSame(self::LAKE, $this->json('2027/lake.png.json')['id'] ?? null);

		$files->delete($table, self::LAKE);

		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/media/2027/lake.png.json');
	}
}
