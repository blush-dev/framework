<?php

/**
 * Content storage tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Storage;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\FilesystemSource;
use Blush\Content\Storage\ContentStorage;
use Blush\Content\Storage\FilesystemStorage;
use Blush\Content\Storage\StorageDriver;
use Blush\Content\Storage\StorageDriverFactory;
use Blush\Content\Storage\StorageDriverRegistrar;
use Blush\Content\Storage\StorageDriverRegistry;
use Blush\Content\Storage\StorageException;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Storage\StorageConfig;
use Blush\Tests\BootsScratchSite;

#[CoversClass(FilesystemStorage::class)]
#[CoversClass(StorageDriver::class)]
#[CoversClass(StorageDriverFactory::class)]
#[CoversClass(StorageDriverRegistrar::class)]
#[CoversClass(StorageDriverRegistry::class)]
final class ContentStorageTest extends TestCase
{
	use BootsScratchSite;

	public function testFilesystemIsTheDefault(): void
	{
		$container = $this->scratchApplication()->container();

		$this->assertSame('filesystem', $container->make(StorageConfig::class)->driver);
		$this->assertInstanceOf(FilesystemStorage::class, $container->make(ContentStorage::class));
		$this->assertInstanceOf(FilesystemSource::class, $container->make(ContentSource::class));
		$this->assertInstanceOf(FilesystemWriter::class, $container->make(ContentWriter::class));
	}

	public function testAConfigFileWinsOverTheEnvironment(): void
	{
		$this->writeTemporaryFile('config/storage.php', "<?php\nreturn new Blush\\Storage\\StorageConfig(areas: ['content' => 'filesystem']);\n");

		$container = $this->scratchApplication(['STORAGE_DRIVER' => 'nowhere'])->container();

		$this->assertInstanceOf(FilesystemStorage::class, $container->make(ContentStorage::class));
	}

	public function testAnExtensionsDriverSuppliesTheSourceAndWriter(): void
	{
		$container = $this->scratchApplication(['STORAGE_DRIVER' => 'test'])->container();
		$container->make(StorageDriverRegistry::class)->register('test', TestStorage::class);

		$this->assertInstanceOf(TestStorage::class, $container->make(ContentStorage::class));
		$this->assertInstanceOf(FilesystemSource::class, $container->make(ContentSource::class));
	}

	public function testAnUnknownDriverFails(): void
	{
		$container = $this->scratchApplication(['STORAGE_DRIVER' => 'nowhere'])->container();

		$this->expectException(StorageException::class);
		$this->expectExceptionMessage('Unknown content storage driver "nowhere"; registered drivers: filesystem.');

		$container->make(ContentSource::class);
	}

	public function testTheRegistrarKeepsAnExtensionsName(): void
	{
		$registry = new StorageDriverRegistry(['filesystem' => TestStorage::class]);
		new StorageDriverRegistrar($registry)->register();

		$this->assertSame(TestStorage::class, $registry->get('filesystem'));
	}
}

/**
 * A storage an extension might register.
 */
final readonly class TestStorage implements ContentStorage
{
	#[Override]
	public function source(): string
	{
		return FilesystemSource::class;
	}

	#[Override]
	public function writer(): string
	{
		return FilesystemWriter::class;
	}
}
