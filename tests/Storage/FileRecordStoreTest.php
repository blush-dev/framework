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
use Blush\Auth\RecordRoleStore;
use Blush\Auth\Role;
use Blush\Auth\RoleStore;
use Blush\Content\Index\IndexStore;
use Blush\Core\Paths;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\File\FileRecordStore;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\InvalidRecord;
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
#[CoversClass(RecordRoleStore::class)]
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
		$roles     = $container->make(RoleStore::class);

		$this->assertInstanceOf(RecordRoleStore::class, $roles);
		$this->assertSame(['editor', 'reviewer'], array_map(static fn (Role $role): string => $role->name, $roles->all()), 'In the file\'s order.');
		$this->assertSame(['content.*.edit'], $roles->all()[0]->capabilities, 'Retired capabilities are dropped as they\'re read.');
		$this->assertArrayHasKey('roles', $container->make(TableRegistry::class)->all());

		$roles->save([...$roles->all(), new Role('critic', 'Critic', ['content.*.view'])]);

		$this->assertSame(['name' => 'editor', 'label' => 'Editor', 'capabilities' => ['content.*.edit'], 'id' => Uuid::fromName('roles/editor')], $this->roles()[0], 'Each role gains its id, last.');
		$this->assertSame(['editor', 'reviewer', 'critic'], array_column($this->roles(), 'name'));
		$this->assertSame('0660', substr(sprintf('%o', fileperms($this->temporaryDirectory() . '/storage/roles.json')), -4), 'Kept from other users, as before.');

		$roles->save([$roles->all()[2]]);

		$this->assertSame(['critic'], array_map(static fn (Role $role): string => $role->name, $roles->all()), 'Roles left out are removed.');
		$id = $this->roles()[0]['id'] ?? null;

		$this->assertTrue(is_string($id) && Uuid::isValid($id));
		$this->assertSame('7', $id[14], 'A new role\'s id is version 7.');
	}

	public function testABrokenRolesFileSaysSo(): void
	{
		$this->writeTemporaryFile('storage/roles.json', '{"roles": {"editor": true}}');

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('storage/roles.json needs a list of records under "roles".');

		$this->scratchApplication()->container()->make(RoleStore::class)->all();
	}

	public function testANewRecordGetsAVersionSevenId(): void
	{
		$record = Record::create(new DateTimeImmutable(), ['slug' => 'x']);

		$this->assertSame('7', $record->id[14]);
	}
}
