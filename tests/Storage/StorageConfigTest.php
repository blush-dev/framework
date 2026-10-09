<?php

/**
 * Storage config tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Env\Env;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageConfig;

#[CoversClass(StorageConfig::class)]
#[CoversClass(StorageArea::class)]
final class StorageConfigTest extends TestCase
{
	public function testEveryAreaUsesTheFilesystemByDefault(): void
	{
		$config = new StorageConfig();

		foreach (StorageArea::cases() as $area) {
			$this->assertSame('filesystem', $config->driverFor($area));
		}
	}

	public function testAnAreaCanUseAnotherDriver(): void
	{
		$config = new StorageConfig(driver: 'mysql', areas: ['sessions' => 'filesystem']);

		$this->assertSame('mysql', $config->driverFor(StorageArea::Content));
		$this->assertSame('filesystem', $config->driverFor(StorageArea::Sessions));
	}

	public function testTheEnvironmentPicksTheDriver(): void
	{
		$this->assertSame('sqlite', StorageConfig::fromEnv(new Env(['STORAGE_DRIVER' => 'sqlite']))->driver);
		$this->assertSame('filesystem', StorageConfig::fromEnv(new Env(['STORAGE_DRIVER' => '']))->driver);
		$this->assertSame('filesystem', StorageConfig::fromEnv(new Env())->driver);
	}

	public function testTheConfigRoundTrips(): void
	{
		$data = ['driver' => 'sqlite', 'areas' => ['accounts' => 'filesystem'], 'sqlite' => '/var/data/site.sqlite'];

		$this->assertSame($data, StorageConfig::fromArray($data)->toArray());
		$this->assertSame(['driver' => 'filesystem', 'areas' => [], 'sqlite' => 'user/site.sqlite'], StorageConfig::fromArray([])->toArray());
	}

	public function testSaysWhetherAnyAreaUsesADriver(): void
	{
		$this->assertFalse(new StorageConfig()->uses('sqlite'));
		$this->assertTrue(new StorageConfig(areas: ['content' => 'sqlite'])->uses('sqlite'));
		$this->assertTrue(new StorageConfig('sqlite')->uses('sqlite'));
		$this->assertFalse(new StorageConfig('sqlite', array_fill_keys(['content', 'data', 'accounts', 'sessions', 'jobs'], 'filesystem'))->uses('sqlite'));
	}

	public function testRejectsAnEmptyDatabaseFile(): void
	{
		$this->expectException(InvalidConfig::class);
		new StorageConfig(sqlite: ' ');
	}

	public function testRejectsABadDriverName(): void
	{
		$this->expectException(InvalidConfig::class);
		new StorageConfig('Not A Name');
	}

	public function testRejectsAnUnknownArea(): void
	{
		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('StorageConfig "areas" keys must be content, data, accounts, sessions, jobs; "media" given.');
		new StorageConfig(areas: ['media' => 'filesystem']);
	}
}
