<?php

/**
 * Cache store tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Cache;

use DateInterval;
use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Cache\ApcuStore;
use Blush\Cache\ArrayStore;
use Blush\Cache\CacheConfig;
use Blush\Cache\CacheDriver;
use Blush\Cache\CacheDriverFactory;
use Blush\Cache\CacheDriverRegistrar;
use Blush\Cache\CacheDriverRegistry;
use Blush\Cache\CacheException;
use Blush\Cache\Caches;
use Blush\Cache\FileStore;
use Blush\Cache\InvalidCacheKey;
use Blush\Cache\InvalidCacheValue;
use Blush\Cache\Item;
use Blush\Cache\NullStore;
use Blush\Cache\PhpFileStore;
use Blush\Cache\Store;
use Blush\Clock\FrozenClock;
use Blush\Config\InvalidConfig;
use Blush\Container\ServiceContainer;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Core\Paths;
use Blush\Tests\TemporaryDirectory;
use Psr\Clock\ClockInterface;

#[CoversClass(Store::class)]
#[CoversClass(Item::class)]
#[CoversClass(ArrayStore::class)]
#[CoversClass(NullStore::class)]
#[CoversClass(FileStore::class)]
#[CoversClass(PhpFileStore::class)]
#[CoversClass(ApcuStore::class)]
#[CoversClass(CacheDriver::class)]
#[CoversClass(CacheDriverRegistry::class)]
#[CoversClass(CacheDriverRegistrar::class)]
#[CoversClass(CacheDriverFactory::class)]
#[CoversClass(CacheConfig::class)]
#[CoversClass(Caches::class)]
final class CacheStoresTest extends TestCase
{
	use TemporaryDirectory;

	private FrozenClock $clock;

	protected function setUp(): void
	{
		$this->clock = new FrozenClock('2026-06-01 12:00:00');
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function persistentDrivers(): array
	{
		return ['array' => ['array'], 'file' => ['file'], 'php' => ['php']];
	}

	private function store(string $driver, string $namespace = 'test'): Store
	{
		return $this->factory()->make($driver, $namespace);
	}

	private function factory(): CacheDriverFactory
	{
		$container = new ServiceContainer();
		$container->instance(ClockInterface::class, $this->clock);
		$container->instance(Paths::class, Paths::fromRoot($this->temporaryDirectory()));

		$registry = new CacheDriverRegistry();
		new CacheDriverRegistrar($registry)->register();

		return new CacheDriverFactory($registry, $container);
	}

	#[DataProvider('persistentDrivers')]
	public function testStoresAndReadsPlainData(string $driver): void
	{
		$store = $this->store($driver);

		$this->assertTrue($store->set('page', ['status' => 200, 'body' => '<p>Hi</p>', 'nested' => [1.5, true, null]]));
		$this->assertSame(['status' => 200, 'body' => '<p>Hi</p>', 'nested' => [1.5, true, null]], $store->get('page'));
		$this->assertTrue($store->set('false', false));
		$this->assertFalse($store->get('false', 'default'));
		$this->assertTrue($store->has('page'));
		$this->assertSame('default', $store->get('missing', 'default'));
		$this->assertFalse($store->has('missing'));

		$this->assertTrue($store->delete('page'));
		$this->assertNull($store->get('page'));
	}

	#[DataProvider('persistentDrivers')]
	public function testEntriesExpire(string $driver): void
	{
		$store = $this->store($driver);

		$store->set('short', 'a', 60);
		$store->set('interval', 'b', new DateInterval('PT2M'));
		$store->set('forever', 'c');
		$store->set('gone', 'd', 0);

		$this->assertSame('a', $store->get('short'));
		$this->assertNull($store->get('gone'));

		$this->clock->advance('PT90S');

		$this->assertNull($store->get('short'));
		$this->assertSame('b', $store->get('interval'));

		$this->clock->advance('PT1H');

		$this->assertSame(['short' => null, 'interval' => null, 'forever' => 'c'], $store->getMultiple(['short', 'interval', 'forever']));
		$this->assertSame(2, $store->prune());
		$this->assertSame('c', $store->get('forever'));
	}

	#[DataProvider('persistentDrivers')]
	public function testMultipleKeysAndClearing(string $driver): void
	{
		$store = $this->store($driver);
		$other = $this->store($driver, 'other');

		$this->assertTrue($store->setMultiple(['a' => 1, 'b' => 2]));
		$other->set('a', 'kept');

		$this->assertSame(['a' => 1, 'b' => 2, 'c' => 0], $store->getMultiple(['a', 'b', 'c'], 0));
		$this->assertTrue($store->deleteMultiple(['a']));
		$this->assertNull($store->get('a'));

		$this->assertTrue($store->clear());
		$this->assertNull($store->get('b'));
		$this->assertSame('kept', $other->get('a'));
	}

	#[DataProvider('persistentDrivers')]
	public function testRememberComputesOnce(string $driver): void
	{
		$store = $this->store($driver);
		$calls = 0;
		$make  = static function () use (&$calls): string {
			$calls++;

			return 'value';
		};

		$store->remember('key', null, $make);
		$store->remember('key', null, $make);

		$this->assertSame(1, $calls);
		$this->assertSame('value', $store->get('key'));

		$store->remember('null', null, static fn (): null => null);
		$this->assertFalse($store->has('null'));
	}

	public function testTheNullStoreKeepsNothing(): void
	{
		$store = $this->store('null');

		$this->assertFalse($store->set('a', 1));
		$this->assertNull($store->get('a'));
		$this->assertTrue($store->delete('a'));
		$this->assertTrue($store->clear());
		$this->assertSame(0, $store->prune());
	}

	public function testFileStoresShardEntriesUnderTheirNamespace(): void
	{
		$store = $this->store('file', 'pages');
		$this->assertInstanceOf(FileStore::class, $store);

		$store->set('home', 'x', 60);

		$files = glob($this->temporaryDirectory() . '/storage/cache/store/pages/*/*.cache') ?: [];
		$this->assertCount(1, $files);
		$this->assertStringStartsWith((string) ($this->clock->now()->getTimestamp() + 60) . "\n", (string) file_get_contents($files[0]));
		$this->assertSame($this->temporaryDirectory() . '/storage/cache/store/pages', $store->directory());

		file_put_contents($files[0], 'garbage');
		$this->assertNull($store->get('home'));
		$this->assertSame(1, $store->prune());

		$php = $this->store('php', 'tokens');
		$this->assertInstanceOf(PhpFileStore::class, $php);
		$php->set('css', 'a{}');
		$this->assertCount(1, glob($this->temporaryDirectory() . '/storage/cache/store/tokens/*/*.php') ?: []);
		$this->assertSame($this->temporaryDirectory() . '/storage/cache/store/tokens', $php->directory());

		$php->clear();
		$this->assertSame([], glob($this->temporaryDirectory() . '/storage/cache/store/tokens/*') ?: []);
	}

	public function testKeysAndValuesFollowTheRules(): void
	{
		$store = $this->store('array');

		foreach (['', 'a/b', 'a:b', 'a{b}', 'a@b'] as $key) {
			try {
				$store->get($key);
				$this->fail("Key \"{$key}\" was accepted.");
			} catch (InvalidCacheKey) {
				$this->addToAssertionCount(1);
			}
		}

		$this->expectException(InvalidCacheValue::class);
		$store->set('object', ['nested' => new stdClass()]);
	}

	public function testNamespacesAreChecked(): void
	{
		$this->expectException(CacheException::class);
		$this->store('array', 'Bad Name');
	}

	public function testUnknownDriversAreReported(): void
	{
		$this->expectException(CacheException::class);
		$this->expectExceptionMessage('Unknown cache driver "redis"; registered drivers: file, php, apcu, array, null.');
		$this->store('redis');
	}

	public function testApcuNeedsItsExtension(): void
	{
		if (function_exists('apcu_enabled') && apcu_enabled()) {
			$store = $this->store('apcu');
			$store->set('a', [1]);
			$this->assertSame([1], $store->get('a'));
			$store->clear();
			$this->assertNull($store->get('a'));

			return;
		}

		$this->expectException(CacheException::class);
		$this->expectExceptionMessage('The "apcu" cache driver needs the APCu extension, enabled.');
		$this->store('apcu');
	}

	public function testConfigPicksDriversPerNamespace(): void
	{
		$config = CacheConfig::fromArray(['driver' => 'array', 'stores' => ['tokens' => 'php'], 'maxAge' => 60]);

		$this->assertSame('array', $config->driverFor('pages'));
		$this->assertSame('php', $config->driverFor('tokens'));
		$this->assertNull($config->enabled);
		$this->assertTrue($config->isEnabled(Environment::Production));
		$this->assertTrue($config->isEnabled(Environment::Staging));
		$this->assertFalse($config->isEnabled(Environment::Development));
		$this->assertTrue(new CacheConfig(enabled: true)->isEnabled(Environment::Development));
		$this->assertSame(['enabled' => null, 'driver' => 'array', 'stores' => ['tokens' => 'php'], 'pages' => true, 'maxAge' => 60], $config->toArray());
	}

	public function testConfigIsValidated(): void
	{
		$cases = [
			static fn (): CacheConfig => new CacheConfig(driver: 'Bad'),
			static fn (): CacheConfig => new CacheConfig(stores: ['pages' => 'no way']),
			static fn (): CacheConfig => new CacheConfig(maxAge: -1),
			static fn (): CacheConfig => CacheConfig::fromArray(['enabled' => 'yes']),
			static fn (): CacheConfig => CacheConfig::fromArray(['stores' => ['pages' => 1]]),
			static fn (): CacheConfig => CacheConfig::fromArray(['unknown' => 1])
		];

		foreach ($cases as $case) {
			try {
				$case();
				$this->fail('Invalid config was accepted.');
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testCachesHandOutNullStoresWhenOff(): void
	{
		$dev  = new Caches(new CacheConfig(driver: 'array'), new AppConfig(environment: Environment::Development), $this->factory(), $this->clock);
		$live = new Caches(new CacheConfig(driver: 'array', stores: ['custom' => 'array']), new AppConfig(), $this->factory(), $this->clock);

		$this->assertFalse($dev->enabled());
		$this->assertInstanceOf(NullStore::class, $dev->store('pages'));
		$this->assertInstanceOf(ArrayStore::class, $dev->persistent('pages'));

		$this->assertTrue($live->enabled());
		$this->assertSame($live->store('pages'), $live->persistent('pages'));

		$live->store('extension')->set('a', 1, 10);
		$live->store('custom')->set('b', 2);

		$this->clock->advance('PT1M');

		$this->assertSame(1, $live->prune());
		$this->assertSame(['pages', 'bodies', 'tokens', 'fragments', 'custom', 'extension'], $live->clear());
		$this->assertNull($live->store('custom')->get('b'));
	}
}
