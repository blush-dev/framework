<?php

/**
 * Data loader tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(DataLoader::class)]
final class DataLoaderTest extends TestCase
{
	use TemporaryDirectory;

	public function testLoadsJsonByName(): void
	{
		$this->writeTemporaryFile('authors.json', '{"justin": {"name": "Justin"}}');

		$loader = new DataLoader();

		$this->assertSame(['justin' => ['name' => 'Justin']], $loader->load($this->temporaryDirectory(), 'authors'));
		$this->assertNull($loader->load($this->temporaryDirectory(), 'missing'));
	}

	public function testReadsOnlyJson(): void
	{
		$this->writeTemporaryFile('menus.yaml', "primary:\n  - Home\n");
		$this->writeTemporaryFile('theme.yml', 'from: yml');

		$loader = new DataLoader();

		$this->assertNull($loader->find($this->temporaryDirectory(), 'menus'), 'Data is JSON only (D-631).');
		$this->assertNull($loader->load($this->temporaryDirectory(), 'theme'));
		$this->assertSame($this->temporaryDirectory() . '/menus.json', $loader->path($this->temporaryDirectory(), 'menus'));
		$this->assertTrue(DataLoader::isDataFile('a/b.JSON'));
		$this->assertFalse(DataLoader::isDataFile('a/b.yaml'));
	}

	public function testLeavesOutAnEditorsSchemaKey(): void
	{
		$this->writeTemporaryFile('types/post.json', '{"$schema": "../schemas/type.json", "folder": "_posts"}');
		$this->writeTemporaryFile('types/era.json', '{"$schema": "../schemas/type.json", "kind": "taxonomy"}');
		$this->writeTemporaryFile('list.json', '["$schema"]');

		$loader = new DataLoader();

		$this->assertSame(['folder' => '_posts'], $loader->load($this->temporaryDirectory(), 'types/post'), 'Not part of the data (D-491).');
		$this->assertSame(['era' => ['kind' => 'taxonomy'], 'post' => ['folder' => '_posts']], $loader->loadAll($this->temporaryDirectory() . '/types'));
		$this->assertSame(['$schema'], $loader->load($this->temporaryDirectory(), 'list'), 'Only a key at the top.');
	}

	public function testReadsNamesInSubdirectories(): void
	{
		$this->writeTemporaryFile('types/post.json', '{"path": "_posts"}');

		$this->assertSame(['path' => '_posts'], new DataLoader()->load($this->temporaryDirectory(), 'types/post'));
	}

	public function testRejectsNamesThatEscapeTheDirectory(): void
	{
		$this->expectException(InvalidData::class);

		new DataLoader()->find($this->temporaryDirectory(), '../secrets');
	}

	public function testLoadsEveryFileInADirectory(): void
	{
		$this->writeTemporaryFile('types/post.json', '{"path": "_posts"}');
		$this->writeTemporaryFile('types/era.json', '{"taxonomy": true}');
		$this->writeTemporaryFile('types/old.yaml', 'path: ignored');
		$this->writeTemporaryFile('types/notes.txt', 'not data');
		$this->writeTemporaryFile('types/.hidden.json', '{}');

		$loader = new DataLoader();

		$this->assertSame(['era' => ['taxonomy' => true], 'post' => ['path' => '_posts']], $loader->loadAll($this->temporaryDirectory() . '/types'));
		$this->assertSame([], $loader->loadAll($this->temporaryDirectory() . '/missing'));
	}

	public function testParseErrorsNameTheFile(): void
	{
		$path = $this->writeTemporaryFile('broken.json', '{"a": ');

		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage($path . ': Invalid JSON');

		new DataLoader()->load($this->temporaryDirectory(), 'broken');
	}

	public function testDataFilesMustHoldMapsOrLists(): void
	{
		$this->writeTemporaryFile('scalar.json', '"text"');
		$this->writeTemporaryFile('empty.json', ' ');

		$loader = new DataLoader();

		$this->assertSame([], $loader->loadFile($this->temporaryDirectory() . '/empty.json'));

		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage('must hold');

		$loader->loadFile($this->temporaryDirectory() . '/scalar.json');
	}
}
