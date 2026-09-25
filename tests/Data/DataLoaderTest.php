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
use Blush\Container\ServiceContainer;
use Blush\Data\DataFormat;
use Blush\Data\DataLoader;
use Blush\Data\DataParserRegistrar;
use Blush\Data\DataParserRegistry;
use Blush\Data\InvalidData;
use Blush\Data\JsonParser;
use Blush\Data\SymfonyYamlParser;
use Blush\Data\YamlDataParser;
use Blush\Data\YamlParser;
use Blush\Tests\Fixtures\Data\KeyValueParser;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(DataLoader::class)]
#[CoversClass(DataFormat::class)]
#[CoversClass(DataParserRegistry::class)]
#[CoversClass(DataParserRegistrar::class)]
#[CoversClass(JsonParser::class)]
#[CoversClass(YamlDataParser::class)]
final class DataLoaderTest extends TestCase
{
	use TemporaryDirectory;

	private DataParserRegistry $registry;

	protected function setUp(): void
	{
		$this->registry = new DataParserRegistry();
		new DataParserRegistrar($this->registry)->register();
	}

	private function loader(): DataLoader
	{
		$container = new ServiceContainer();
		$container->singleton(YamlParser::class, SymfonyYamlParser::class);

		return new DataLoader($this->registry, $container);
	}

	public function testLoadsJsonAndYamlByName(): void
	{
		$this->writeTemporaryFile('menus.yaml', "primary:\n  - Home\n");
		$this->writeTemporaryFile('authors.json', '{"justin": {"name": "Justin"}}');

		$loader = $this->loader();

		$this->assertSame(['primary' => ['Home']], $loader->load($this->temporaryDirectory(), 'menus'));
		$this->assertSame(['justin' => ['name' => 'Justin']], $loader->load($this->temporaryDirectory(), 'authors'));
		$this->assertNull($loader->load($this->temporaryDirectory(), 'missing'));
	}

	public function testJsonWinsAndShadowsYaml(): void
	{
		$json = $this->writeTemporaryFile('theme.json', '{"from": "json"}');
		$yaml = $this->writeTemporaryFile('theme.yaml', 'from: yaml');
		$yml  = $this->writeTemporaryFile('theme.yml', 'from: yml');

		$loader = $this->loader();

		$this->assertSame(['json', 'yaml', 'yml'], $loader->extensions());
		$this->assertSame($json, $loader->find($this->temporaryDirectory(), 'theme'));
		$this->assertSame([$yaml, $yml], $loader->shadowed($this->temporaryDirectory(), 'theme'));
		$this->assertSame(['from' => 'json'], $loader->load($this->temporaryDirectory(), 'theme'));
	}

	public function testReadsNamesInSubdirectories(): void
	{
		$this->writeTemporaryFile('types/post.yml', 'path: _posts');

		$this->assertSame(['path' => '_posts'], $this->loader()->load($this->temporaryDirectory(), 'types/post'));
	}

	public function testRejectsNamesThatEscapeTheDirectory(): void
	{
		$this->expectException(InvalidData::class);

		$this->loader()->find($this->temporaryDirectory(), '../secrets');
	}

	public function testLoadsEveryFileInADirectory(): void
	{
		$this->writeTemporaryFile('types/post.json', '{"path": "_posts"}');
		$this->writeTemporaryFile('types/post.yaml', 'path: ignored');
		$this->writeTemporaryFile('types/era.yaml', 'taxonomy: true');
		$this->writeTemporaryFile('types/notes.txt', 'not data');
		$this->writeTemporaryFile('types/.hidden.json', '{}');

		$data = $this->loader()->loadAll($this->temporaryDirectory() . '/types');

		$this->assertSame(['era' => ['taxonomy' => true], 'post' => ['path' => '_posts']], $data);
		$this->assertSame([], $this->loader()->loadAll($this->temporaryDirectory() . '/missing'));
	}

	public function testExtensionsCanAddFormatsAfterTheBuiltIns(): void
	{
		$this->registry->register('kv', KeyValueParser::class);
		$this->writeTemporaryFile('site.kv', 'name = Blush');
		$this->writeTemporaryFile('site.yaml', 'name: YAML wins');

		$loader = $this->loader();

		$this->assertSame(['json', 'yaml', 'yml', 'kv'], $loader->extensions());
		$this->assertSame(['name' => 'YAML wins'], $loader->load($this->temporaryDirectory(), 'site'));
		$this->assertSame(['name' => 'Blush'], $loader->loadFile($this->temporaryDirectory() . '/site.kv'));
	}

	public function testParseErrorsNameTheFile(): void
	{
		$path = $this->writeTemporaryFile('broken.json', '{"a": ');

		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage($path . ': Invalid JSON');

		$this->loader()->load($this->temporaryDirectory(), 'broken');
	}

	public function testDataFilesMustHoldMapsOrLists(): void
	{
		$this->writeTemporaryFile('scalar.json', '"text"');
		$this->writeTemporaryFile('scalar.yaml', 'just text');
		$this->writeTemporaryFile('empty.yaml', '');
		$this->writeTemporaryFile('empty.json', ' ');

		$loader = $this->loader();

		$this->assertSame([], $loader->loadFile($this->temporaryDirectory() . '/empty.yaml'));
		$this->assertSame([], $loader->loadFile($this->temporaryDirectory() . '/empty.json'));

		foreach (['scalar.json', 'scalar.yaml'] as $file) {
			try {
				$loader->loadFile($this->temporaryDirectory() . '/' . $file);
				$this->fail("{$file} should not parse.");
			} catch (InvalidData $e) {
				$this->assertStringContainsString('must hold', $e->getMessage());
			}
		}
	}

	public function testUnknownExtensionsHaveNoParser(): void
	{
		$this->expectException(InvalidData::class);
		$this->expectExceptionMessage('No data parser is registered for ".ini" files.');

		$this->loader()->parser('ini');
	}
}
