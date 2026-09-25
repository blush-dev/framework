<?php

/**
 * Config tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\ConfigCache;
use Blush\Config\ConfigLoader;
use Blush\Config\ConfigRepository;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Core\Paths;
use Blush\Env\Env;
use Blush\Extension\ExtensionConfig;
use Blush\Log\Level;
use Blush\Log\LogConfig;
use Blush\Log\LogDriver;
use Blush\Support\PhpArrayFile;
use Blush\Tests\Fixtures\Container\BindingProvider;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ConfigRepository::class)]
#[CoversClass(ConfigLoader::class)]
#[CoversClass(ConfigCache::class)]
#[CoversClass(ConfigValues::class)]
#[CoversClass(AppConfig::class)]
#[CoversClass(LogConfig::class)]
#[CoversClass(ExtensionConfig::class)]
final class ConfigTest extends TestCase
{
	use TemporaryDirectory;

	private function loader(): ConfigLoader
	{
		return new ConfigLoader(
			new Env(['APP_URL' => 'https://example.com']),
			Paths::fromRoot($this->temporaryDirectory())
		);
	}

	public function testLoadsConfigFilesWithEnvAndPaths(): void
	{
		$this->writeTemporaryFile('config/app.php', <<<'PHP'
			<?php
			declare(strict_types=1);
			return new Blush\Core\AppConfig(
				name: 'Site at ' . basename($paths->root),
				url: $env->string('APP_URL'),
				timezone: 'America/Chicago'
			);
			PHP);
		$this->writeTemporaryFile('config/log.php', <<<'PHP'
			<?php
			declare(strict_types=1);
			return [new Blush\Log\LogConfig(level: Blush\Log\Level::Debug), new Blush\Extension\ExtensionConfig(disabled: ['x'])];
			PHP);

		$config = $this->loader()->load($this->temporaryDirectory() . '/config');

		$app = $config->get(AppConfig::class);

		$this->assertSame('https://example.com', $app->url);
		$this->assertSame('America/Chicago', $app->timezone);
		$this->assertStringStartsWith('Site at blush-tests-', $app->name);
		$this->assertSame(Level::Debug, $config->get(LogConfig::class)->level);
		$this->assertFalse($config->get(ExtensionConfig::class)->isEnabled('x'));
	}

	public function testMissingDirectoryLoadsNothing(): void
	{
		$this->assertSame([], $this->loader()->load($this->temporaryDirectory() . '/missing')->all());
	}

	public function testRejectsFilesThatDoNotReturnConfig(): void
	{
		$this->writeTemporaryFile('config/bad.php', "<?php\n\nreturn ['name' => 'nope'];\n");

		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('config/bad.php');

		$this->loader()->load($this->temporaryDirectory() . '/config');
	}

	public function testRejectsDuplicateConfigClasses(): void
	{
		$this->expectException(InvalidConfig::class);

		new ConfigRepository(new LogConfig(), new LogConfig());
	}

	public function testRepositoryDefaultsAndOverrides(): void
	{
		$config = new ConfigRepository(new LogConfig(level: Level::Error))
			->withDefaults(new LogConfig(), new AppConfig())
			->with(new ExtensionConfig(enabled: []));

		$this->assertSame(Level::Error, $config->get(LogConfig::class)->level);
		$this->assertTrue($config->has(AppConfig::class));
		$this->assertSame([], $config->get(ExtensionConfig::class)->enabled);
		$this->assertNull(new ConfigRepository()->find(AppConfig::class));

		$this->expectException(InvalidConfig::class);

		new ConfigRepository()->get(AppConfig::class);
	}

	public function testCacheRoundTrips(): void
	{
		$cache  = new ConfigCache(new PhpArrayFile($this->temporaryDirectory() . '/config.php'));
		$config = new ConfigRepository(
			new AppConfig(name: 'Cached', environment: Environment::Staging, providers: [BindingProvider::class]),
			new LogConfig(driver: LogDriver::Stderr),
			new ExtensionConfig(enabled: ['a'], disabled: ['b'])
		);

		$this->assertNull($cache->read());

		$cache->write($config);

		$this->assertEquals($config, $cache->read());

		$cache->clear();

		$this->assertNull($cache->read());
	}

	public function testAppConfigFromEnv(): void
	{
		$config = AppConfig::fromEnv(new Env([
			'APP_ENV'   => 'dev',
			'APP_DEBUG' => 'true',
			'APP_URL'   => 'https://example.test'
		]));

		$this->assertSame(Environment::Development, $config->environment);
		$this->assertTrue($config->debug);
		$this->assertSame('https://example.test', $config->url);
		$this->assertSame('UTC', $config->timezone()->getName());
	}

	public function testAppConfigFromArrayAcceptsShortEnvironmentNames(): void
	{
		$this->assertSame(Environment::Development, AppConfig::fromArray(['environment' => 'local'])->environment);
		$this->assertSame(Environment::Staging, AppConfig::fromArray(['environment' => 'staging'])->environment);
	}

	public function testAppConfigValidates(): void
	{
		foreach ([['url' => 'example.com'], ['timezone' => 'Mars/Olympus'], ['providers' => [self::class]], ['nme' => 'typo'], ['debug' => 'yes']] as $data) {
			try {
				AppConfig::fromArray($data);
				$this->fail('Expected invalid config: ' . json_encode($data));
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testConfigValuesReadsEnumsByValueOrCase(): void
	{
		$values = new ConfigValues(['a' => 'error', 'b' => Level::Info, 'c' => 'nope'], 'Test');

		$this->assertSame(Level::Error, $values->enum('a', Level::class, Level::Debug));
		$this->assertSame(Level::Info, $values->enum('b', Level::class, Level::Debug));
		$this->assertSame(Level::Debug, $values->enum('missing', Level::class, Level::Debug));

		$this->expectExceptionMessage('Test "c" must be one of');

		$values->enum('c', Level::class, Level::Debug);
	}
}
