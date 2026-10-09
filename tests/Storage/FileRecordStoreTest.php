<?php

/**
 * File record store test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\AuthException;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Clock\FrozenClock;
use Blush\Content\Index\IndexStore;
use Blush\Core\Paths;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\ArrayRecordStore;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;
use Blush\Storage\StorageArea;
use Blush\Support\Filesystem;
use Blush\Support\Uuid;
use Blush\Tests\BootsScratchSite;

#[CoversClass(FileRecordStore::class)]
#[CoversClass(FileLayout::class)]
#[CoversClass(FileLayouts::class)]
#[CoversClass(KeyedTable::class)]
#[CoversClass(Roles::class)]
final class FileRecordStoreTest extends TestCase
{
	use BootsScratchSite;

	private const string ID = '01900000-0000-7000-8000-000000000001';

	private function store(?FileLayouts $layouts = null): FileRecordStore
	{
		$paths = Paths::fromRoot($this->temporaryDirectory());

		return new FileRecordStore($paths, $layouts ?? new FileLayouts($paths), new FileTransactions($paths, new Filesystem()), new Filesystem(), static fn (): IndexStore => throw new LogicException('These tests keep no content.'));
	}

	private function json(string $relative): mixed
	{
		return json_decode((string) file_get_contents($this->temporaryDirectory() . "/{$relative}"), true);
	}

	/**
	 * The records in `storage/roles.json`.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function roles(): array
	{
		$file  = $this->json('storage/roles.json');
		$roles = is_array($file) ? ($file['roles'] ?? null) : null;

		$this->assertIsList($roles);

		/** @var list<array<string, mixed>> $roles */
		return $roles;
	}

	public function testAFolderTableIsAFileARecord(): void
	{
		$store  = $this->store();
		$keyed  = new Table('albums', StorageArea::Data, key: 'slug');
		$byId   = new Table('notes', StorageArea::Accounts);

		$store->save($keyed, new Record(self::ID, ['slug' => 'summer', 'title' => 'Summer'], 'About summer.'));
		$store->save($byId, new Record(self::ID, ['text' => 'One']));

		$this->assertSame(['slug' => 'summer', 'title' => 'Summer', 'content' => 'About summer.', 'id' => self::ID], $this->json('user/data/albums/summer.json'), 'Fields, the content, then the id, last.');
		$this->assertSame(['text' => 'One', 'id' => self::ID], $this->json('storage/notes/' . self::ID . '.json'), 'Named by id without a key; accounts under storage/.');

		$store->save($keyed, new Record(self::ID, ['slug' => 'autumn', 'title' => 'Autumn']));

		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/albums/summer.json', 'A new key moves the file.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/data/albums/autumn.json');
	}

	public function testAOneFileTableKeepsItsOtherKeys(): void
	{
		$this->writeTemporaryFile('user/data/albums.json', '{"$schema": "albums.schema.json", "albums": [{"slug": "summer", "title": "Summer"}]}');
		$paths   = Paths::fromRoot($this->temporaryDirectory());
		$layouts = new FileLayouts($paths);
		$layouts->register('albums', FileLayout::oneFile("{$paths->data}/albums.json", 'albums'));
		$store   = $this->store($layouts);
		$table   = new Table('albums', StorageArea::Data, key: 'slug');
		$summer  = $store->findByKey($table, 'summer')?->id;

		$this->assertSame(Uuid::fromName('albums/summer'), $summer, 'A record without an id has a steady one from its key.');
		$this->assertSame($summer, $store->findByKey($table, 'summer')->id, 'The same every read.');

		$store->save($table, new Record(self::ID, ['slug' => 'autumn']));

		$this->assertSame([
			'$schema' => 'albums.schema.json',
			'albums'  => [
				['slug' => 'summer', 'title' => 'Summer', 'id' => Uuid::fromName('albums/summer')],
				['slug' => 'autumn', 'id' => self::ID]
			]
		], $this->json('user/data/albums.json'), 'Other keys stay; the file written, every record has its id; new ones go last.');
	}

	public function testAPathKeyIsKeptInOneFile(): void
	{
		$this->writeTemporaryFile('user/data/links.json', '[{"from": "/old", "to": "/new"}]');
		$paths   = Paths::fromRoot($this->temporaryDirectory());
		$layouts = new FileLayouts($paths);
		$layouts->register('links', FileLayout::oneFile("{$paths->data}/links.json"));
		$table   = new Table('links', StorageArea::Data, key: 'from', pathKey: true);
		$store   = $this->store($layouts);

		$this->assertSame(Uuid::fromName('links//old'), $store->findByKey($table, '/old')?->id, 'A path is a key (D-679), with a steady id from it.');
		$this->assertTrue($table->isKey('/news/{name}'));
		$this->assertFalse($table->isKey('old'), 'A path starts with "/".');
		$this->assertFalse($table->isKey('/a b'), 'And has no spaces.');
		$this->assertFalse(new Table('names', StorageArea::Data, key: 'from')->isKey('/old'), 'Other keys hold no paths.');

		$this->expectException(InvalidRecord::class);
		$this->expectExceptionMessage('"folders" is keyed by paths, so it\'s kept as one file');

		$this->store()->findByKey(new Table('folders', StorageArea::Data, key: 'from', pathKey: true), '/old');
	}

	public function testAFileWhoseNameIsItsKeyNeverSaysSo(): void
	{
		$this->writeTemporaryFile('user/data/types/movie.json', "{\n\t\"\$schema\": \"../../../vendor/blush/framework/resources/schemas/type.json\",\n\t\"folder\": \"movies\"\n}\n");
		$paths   = Paths::fromRoot($this->temporaryDirectory());
		$layouts = new FileLayouts($paths);
		$layouts->register('types', FileLayout::folder("{$paths->data}/types", keyInName: true));
		$store   = $this->store($layouts);
		$table   = new Table('types', StorageArea::Data, key: 'name');
		$movie   = $store->findByKey($table, 'movie');

		$this->assertNotNull($movie);
		$this->assertSame(['folder' => 'movies', 'name' => 'movie'], $movie->fields, 'Its key from its name; its $schema isn\'t a field (D-491).');

		$saved = $store->save($table, $movie->with('icon', 'film'));

		$this->assertSame(['$schema' => '../../../vendor/blush/framework/resources/schemas/type.json', 'folder' => 'movies', 'icon' => 'film', 'id' => Uuid::fromName('types/movie')], $this->json('user/data/types/movie.json'), 'Its $schema first, no name, the id last (D-672).');
		$this->assertSame($saved->version, $store->findByKey($table, 'movie')?->version, 'The version is the file\'s.');
		$this->assertSame('user/data/types/movie.json', $store->location($table, 'movie'));

		$this->writeTemporaryFile('user/data/types/show.json', '{"name": "series"}');

		$this->expectException(InvalidRecord::class);
		$this->expectExceptionMessage('user/data/types/show.json names the record "series"; a record here is named after its file.');

		$store->select($table, new RecordQuery());
	}

	public function testAKeyedTableIsDataByKey(): void
	{
		$tables = [new ArrayRecordStore(), $this->store()];

		foreach ($tables as $store) {
			$table = new KeyedTable($store, new Table('albums', StorageArea::Data, key: 'slug'), new FrozenClock(new DateTimeImmutable('2026-10-09')));

			$table->save('summer', ['title' => 'Summer', 'slug' => 'ignored']);
			$table->save('autumn', ['title' => 'Autumn']);
			$id = $store->findByKey($table->table, 'summer')?->id;

			$this->assertSame(['autumn' => ['title' => 'Autumn'], 'summer' => ['title' => 'Summer']], $table->all(), 'By key, sorted, without the key.');
			$this->assertTrue($table->has('summer'));
			$this->assertFalse($table->has('../summer'));

			$table->save('summer', ['title' => 'High Summer']);

			$this->assertSame($id, $store->findByKey($table->table, 'summer')?->id, 'Saving keeps the id.');
			$this->assertSame(['title' => 'High Summer'], $table->find('summer'));

			$table->delete('summer');
			$table->delete('winter');

			$this->assertNull($table->find('summer'));

			try {
				$table->save('winter', ['content' => 'Cold']);
				$this->fail('Saved a record\'s own key as a field.');
			} catch (InvalidRecord $error) {
				$this->assertStringContainsString('"content" is a record\'s own', $error->getMessage());
			}
		}

		$this->assertSame('albums/summer', new KeyedTable($tables[0], new Table('albums', StorageArea::Data, key: 'slug'), new FrozenClock(new DateTimeImmutable('2026-10-09')))->location('summer'), 'A store that can\'t say is named by table and key.');
	}

	public function testAFileNamedByItsKeyFillsTheKeyIn(): void
	{
		$this->writeTemporaryFile('user/data/albums/summer.json', '{"title": "Summer"}');

		$record = $this->store()->findByKey(new Table('albums', StorageArea::Data, key: 'slug'), 'summer');

		$this->assertSame(['title' => 'Summer', 'slug' => 'summer'], $record?->fields);
	}

	public function testNamesAFileItCantRead(): void
	{
		$this->writeTemporaryFile('user/data/albums/summer.json', '{"title": ');

		$this->expectException(InvalidRecord::class);
		$this->expectExceptionMessage('user/data/albums/summer.json isn\'t valid JSON');

		$this->store()->select(new Table('albums', StorageArea::Data, key: 'slug'), new RecordQuery());
	}

	public function testRefusesARecordWithoutAnId(): void
	{
		$this->writeTemporaryFile('user/data/notes/one.json', '{"text": "One"}');

		$this->expectException(InvalidRecord::class);
		$this->expectExceptionMessage('user/data/notes/one.json has a record with no id.');

		$this->store()->select(new Table('notes', StorageArea::Data), new RecordQuery());
	}

	public function testRolesAreARecordTableKeptAsBefore(): void
	{
		$this->writeTemporaryFile('storage/roles.json', (string) json_encode(['roles' => [
			['name' => 'editor', 'label' => 'Editor', 'capabilities' => ['content.*.edit', 'media.upload']],
			['name' => 'reviewer', 'label' => 'Reviewer', 'capabilities' => []]
		]]));

		$container = $this->scratchApplication()->container();
		$roles     = $container->make(Roles::class);

		$this->assertSame(['editor', 'reviewer'], array_map(static fn (Role $role): string => $role->name, $roles->stored()), 'In the file\'s order.');
		$this->assertSame(['content.*.edit'], $roles->stored()[0]->capabilities, 'Retired capabilities are dropped as they\'re read.');
		$this->assertArrayHasKey('roles', $container->make(TableRegistry::class)->all());

		$roles->save([...$roles->stored(), new Role('critic', 'Critic', ['content.*.view'])]);

		$this->assertSame(['name' => 'editor', 'label' => 'Editor', 'capabilities' => ['content.*.edit'], 'id' => Uuid::fromName('roles/editor')], $this->roles()[0], 'Each role gains its id, last.');
		$this->assertSame(['editor', 'reviewer', 'critic'], array_column($this->roles(), 'name'));
		$this->assertSame('0660', substr(sprintf('%o', fileperms($this->temporaryDirectory() . '/storage/roles.json')), -4), 'Kept from other users, as before.');

		$roles->save([$roles->stored()[2]]);

		$this->assertSame(['critic'], array_map(static fn (Role $role): string => $role->name, $roles->stored()), 'Roles left out are removed.');
		$id = $this->roles()[0]['id'] ?? null;

		$this->assertTrue(is_string($id) && Uuid::isValid($id));
		$this->assertSame('7', $id[14], 'A new role\'s id is version 7.');
	}

	public function testABrokenRolesFileSaysSo(): void
	{
		$this->writeTemporaryFile('storage/roles.json', '{"roles": {"editor": true}}');

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('storage/roles.json needs a list of records under "roles".');

		$this->scratchApplication()->container()->make(Roles::class)->stored();
	}

	public function testANewRecordGetsAVersionSevenId(): void
	{
		$record = Record::create(new DateTimeImmutable(), ['slug' => 'x']);

		$this->assertSame('7', $record->id[14]);
	}
}
