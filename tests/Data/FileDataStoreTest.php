<?php

/**
 * File data store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Data;

use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Paths;
use Blush\Data\DataKeys;
use Blush\Data\DataLoader;
use Blush\Data\FileDataStore;
use Blush\Data\InvalidData;
use Blush\Storage\File\FileTransactions;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(FileDataStore::class)]
#[CoversClass(DataKeys::class)]
final class FileDataStoreTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function store(): FileDataStore
	{
		$paths = Paths::fromRoot($this->temporaryDirectory());

		return new FileDataStore($paths, new DataLoader(), new Filesystem(), new FileTransactions($paths, new Filesystem()));
	}

	private function file(string $name): string
	{
		return $this->temporaryDirectory() . "/user/data/{$name}.json";
	}

	public function testReadsAndWritesRecordsAsJsonFiles(): void
	{
		$store = $this->store();

		$this->assertFalse($store->has('types/post'));
		$this->assertNull($store->load('types/post'), 'A missing record is null, not empty.');

		$store->save('types/post', ['labels' => ['singular' => 'Café']]);

		$this->assertTrue($store->has('types/post'));
		$this->assertSame(['labels' => ['singular' => 'Café']], $store->load('types/post'));
		$this->assertStringContainsString('"Café"', (string) file_get_contents($this->file('types/post')));
		$this->assertSame('user/data/types/post.json', $store->location('types/post'));

		$store->delete('types/post');
		$store->delete('types/post');

		$this->assertFileDoesNotExist($this->file('types/post'), 'Deleting a missing record is nothing to do.');
	}

	public function testKeepsAnEditorsSchemaKeyFirst(): void
	{
		$this->writeTemporaryFile('user/data/menus/main.json', '{"$schema": "menu.schema.json", "label": "Main"}');
		$store = $this->store();

		$this->assertSame(['label' => 'Main'], $store->load('menus/main'), 'It isn\'t data (D-491).');

		$store->save('menus/main', ['label' => 'Primary']);

		$this->assertSame(['$schema' => 'menu.schema.json', 'label' => 'Primary'], json_decode((string) file_get_contents($this->file('menus/main')), true));
	}

	public function testListsAFoldersRecords(): void
	{
		$this->writeTemporaryFile('user/data/media/2026/b.png.json', '{"alt": "B"}');
		$this->writeTemporaryFile('user/data/media/a.png.json', '{"alt": "A"}');
		$this->writeTemporaryFile('user/data/media/notes.txt', 'Not data.');
		$store = $this->store();

		$this->assertSame(['a.png' => ['alt' => 'A']], $store->loadAll('media'), 'Only the records directly in it.');
		$this->assertSame(['2026/b.png', 'a.png'], array_keys($store->records('media')), 'Every record under it.');
		$this->assertSame([], $store->loadAll('missing'));
	}

	public function testNamesAnUnreadableRecordByItsLocation(): void
	{
		$this->writeTemporaryFile('user/data/theme.json', '{"settings": ');

		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage('user/data/theme.json: Invalid JSON');

		$this->store()->load('theme');
	}

	public function testRefusesUnsafeNames(): void
	{
		$this->expectException(InvalidData::class);

		$this->store()->load('../config');
	}

	public function testAFailedTransactionPutsEveryRecordBack(): void
	{
		$this->writeTemporaryFile('user/data/types/post.json', "{\n  \"icon\": \"pen\"\n}\n");
		$store = $this->store();

		try {
			$store->transaction(static function () use ($store): void {
				$store->save('types/post', ['icon' => 'book']);
				$store->save('relations/tags', ['kind' => 'classify']);

				throw new RuntimeException('Doesn\'t fit.');
			});
		} catch (RuntimeException $error) {
			$this->assertSame('Doesn\'t fit.', $error->getMessage(), 'The error goes on.');
		}

		$this->assertSame("{\n  \"icon\": \"pen\"\n}\n", file_get_contents($this->file('types/post')), 'Its text is put back as it was.');
		$this->assertFileDoesNotExist($this->file('relations/tags'), 'A new record is removed.');
	}

	public function testAFailedInnerTransactionPutsBackOnlyItsOwnWrites(): void
	{
		$store = $this->store();

		$kept = $store->transaction(static function () use ($store): string {
			$store->save('types/post', ['icon' => 'pen']);

			try {
				$store->transaction(static function () use ($store): void {
					$store->save('types/note', ['icon' => 'note']);

					throw new RuntimeException('Not this one.');
				});
			} catch (RuntimeException) {
			}

			return 'kept';
		});

		$this->assertSame('kept', $kept, 'A transaction returns what its write returns.');
		$this->assertSame(['icon' => 'pen'], $store->load('types/post'));
		$this->assertFalse($store->has('types/note'));
	}

	public function testAFailedOuterTransactionPutsBackItsInnerOnesWrites(): void
	{
		$store = $this->store();

		try {
			$store->transaction(static function () use ($store): void {
				$store->transaction(static fn () => $store->save('types/note', ['icon' => 'note']));

				throw new RuntimeException('Neither.');
			});
		} catch (RuntimeException) {
		}

		$this->assertFalse($store->has('types/note'));
	}

	public function testDataKeysSetsAndRemovesKeysInPlace(): void
	{
		$this->assertSame(
			['labels' => ['plural' => 'Notes'], 'icon' => 'pen', 'feed' => true],
			DataKeys::apply(['labels' => [], 'routing' => ['prefix' => 'n'], 'icon' => 'pen', 'public' => true], ['labels' => ['plural' => 'Notes'], 'public' => null, 'urls' => null, 'feed' => true], ['urls' => ['routing']])
		);

		$this->expectException(InvalidData::class);

		DataKeys::apply(['a', 'b'], ['icon' => 'pen']);
	}
}
