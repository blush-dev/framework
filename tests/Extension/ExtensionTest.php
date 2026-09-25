<?php

/**
 * Extension discovery tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Paths;
use Blush\Extension\ComposerExtensionFinder;
use Blush\Extension\ExtensionCache;
use Blush\Extension\ExtensionConfig;
use Blush\Extension\ExtensionDiscovery;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\Extensions;
use Blush\Extension\ExtensionSource;
use Blush\Extension\LocalAutoloader;
use Blush\Extension\LocalExtensionFinder;
use Blush\Support\PhpArrayFile;
use Blush\Tests\Fixtures\Extension\ComposerExtensionProvider;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExtensionManifest::class)]
#[CoversClass(LocalExtensionFinder::class)]
#[CoversClass(ComposerExtensionFinder::class)]
#[CoversClass(ExtensionDiscovery::class)]
#[CoversClass(Extensions::class)]
#[CoversClass(ExtensionCache::class)]
#[CoversClass(ExtensionConfig::class)]
#[CoversClass(LocalAutoloader::class)]
final class ExtensionTest extends TestCase
{
	use TemporaryDirectory;

	private const string SITE = __DIR__ . '/../Fixtures/site';

	/**
	 * @return list<ExtensionManifest>
	 */
	private function discover(): array
	{
		return ExtensionDiscovery::forPaths(Paths::fromRoot(self::SITE))->discover();
	}

	public function testFindsLocalExtensions(): void
	{
		$manifests = new LocalExtensionFinder(self::SITE . '/user/extensions')->find();

		$this->assertSame(['acme/disabled', 'fixture/hello'], array_map(
			static fn (ExtensionManifest $manifest): string => $manifest->name,
			$manifests
		));

		$hello = $manifests[1];

		$this->assertSame(ExtensionSource::Local, $hello->source);
		$this->assertSame('1.2.0', $hello->version);
		$this->assertSame('Fixture\Hello\HelloServiceProvider', $hello->providerClass());
		$this->assertSame(['Fixture\Hello\\' => 'src/'], $hello->autoload);
		$this->assertSame(['blush' => '^2.0'], $hello->requires);
		$this->assertStringEndsWith('user/extensions/hello', $hello->path);
	}

	public function testFindsComposerExtensions(): void
	{
		$manifests = new ComposerExtensionFinder(self::SITE . '/vendor')->find();

		$this->assertCount(1, $manifests);
		$this->assertSame('acme/composer-ext', $manifests[0]->name);
		$this->assertSame('2.1.0', $manifests[0]->version);
		$this->assertSame(ExtensionSource::Composer, $manifests[0]->source);
		$this->assertSame(ComposerExtensionProvider::class, $manifests[0]->providerClass());
		$this->assertSame(realpath(self::SITE . '/vendor/acme/composer-ext'), $manifests[0]->path);
	}

	public function testReadsComposerOneInstalledFiles(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			['name' => 'acme/one', 'type' => 'blush-extension', 'extra' => ['blush' => ['provider' => 'Acme\One\Provider']]]
		]));

		$manifests = new ComposerExtensionFinder($this->temporaryDirectory() . '/vendor')->find();

		$this->assertSame('acme/one', $manifests[0]->name);
	}

	public function testComposerExtensionsNeedAProvider(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			'packages' => [['name' => 'acme/none', 'type' => 'blush-extension']]
		]));

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('extra.blush.provider');

		new ComposerExtensionFinder($this->temporaryDirectory() . '/vendor')->find();
	}

	public function testDiscoveryCombinesSourcesInNameOrder(): void
	{
		$this->assertSame(['acme/composer-ext', 'acme/disabled', 'fixture/hello'], array_map(
			static fn (ExtensionManifest $manifest): string => $manifest->name,
			$this->discover()
		));
	}

	public function testDiscoveryRejectsDuplicateNames(): void
	{
		$this->writeTemporaryFile('one/a/extension.json', '{"name": "same/name", "provider": "A\\\\Provider"}');
		$this->writeTemporaryFile('two/b/extension.json', '{"name": "same/name", "provider": "B\\\\Provider"}');

		$this->expectException(ExtensionException::class);

		new ExtensionDiscovery([
			new LocalExtensionFinder($this->temporaryDirectory() . '/one'),
			new LocalExtensionFinder($this->temporaryDirectory() . '/two')
		])->discover();
	}

	public function testInvalidManifestsAreRejected(): void
	{
		$cases = [
			'{"name": "Bad Name", "provider": "A\\\\B"}',
			'{"name": "ok/name", "provider": "not a class"}',
			'{"name": "ok/name", "provider": "A\\\\B", "autoload": {"psr-4": {"A\\\\": "../escape"}}}',
			'{"name": "ok/name", "provider": "A\\\\B", "autoload": {"psr-4": {"NoSlash": "src"}}}',
			'{"name": "ok/name"}',
			'not json'
		];

		foreach ($cases as $index => $json) {
			$this->writeTemporaryFile("bad{$index}/ext/extension.json", $json);

			try {
				new LocalExtensionFinder($this->temporaryDirectory() . "/bad{$index}")->find();
				$this->fail("Expected manifest {$index} to be rejected.");
			} catch (ExtensionException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testReadsYamlManifestsAndJsonWins(): void
	{
		$this->writeTemporaryFile('extensions/gallery/extension.yaml', <<<'YAML'
			name: acme/gallery
			version: 1.0.0
			provider: Acme\Gallery\GalleryServiceProvider
			autoload:
			  psr-4:
			    Acme\Gallery\: src/
			YAML);
		$this->writeTemporaryFile('extensions/both/extension.json', '{"name": "acme/json", "provider": "A\\\\B"}');
		$this->writeTemporaryFile('extensions/both/extension.yml', "name: acme/yaml\nprovider: A\\B\n");
		$this->writeTemporaryFile('extensions/none/readme.md', 'No manifest.');

		$manifests = new LocalExtensionFinder($this->temporaryDirectory() . '/extensions')->find();

		$this->assertSame(['acme/json', 'acme/gallery'], array_map(
			static fn (ExtensionManifest $manifest): string => $manifest->name,
			$manifests
		));
		$this->assertSame(['Acme\Gallery\\' => 'src/'], $manifests[1]->autoload);
	}

	public function testInvalidYamlManifestsAreRejected(): void
	{
		$this->writeTemporaryFile('bad/ext/extension.yaml', "- a list\n- not a map\n");

		$this->expectException(ExtensionException::class);

		new LocalExtensionFinder($this->temporaryDirectory() . '/bad')->find();
	}

	public function testEnabledFiltersByConfig(): void
	{
		$discovered = $this->discover();

		$all      = Extensions::enabled($discovered, new ExtensionConfig());
		$disabled = Extensions::enabled($discovered, new ExtensionConfig(disabled: ['acme/disabled']));
		$only     = Extensions::enabled($discovered, new ExtensionConfig(enabled: ['fixture/hello', 'acme/disabled'], disabled: ['acme/disabled']));

		$this->assertCount(3, $all->all());
		$this->assertFalse($disabled->has('acme/disabled'));
		$this->assertSame(['fixture/hello'], array_map(static fn (ExtensionManifest $m): string => $m->name, $only->all()));
		$this->assertSame(['Fixture\Hello\HelloServiceProvider'], $only->providers());
		$this->assertNull($only->get('acme/composer-ext'));
	}

	public function testEnablingAMissingExtensionThrows(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('acme/missing');

		Extensions::enabled($this->discover(), new ExtensionConfig(enabled: ['acme/missing']));
	}

	public function testCacheRoundTrips(): void
	{
		$cache     = new ExtensionCache(new PhpArrayFile($this->temporaryDirectory() . '/extensions.php'));
		$manifests = $this->discover();

		$this->assertNull($cache->read());

		$cache->write($manifests);

		$this->assertEquals($manifests, $cache->read());
	}

	public function testLocalAutoloaderLoadsExtensionClasses(): void
	{
		$autoloader = new LocalAutoloader();
		$autoloader->addExtensions(Extensions::enabled($this->discover(), new ExtensionConfig()));
		$autoloader->register();

		try {
			$this->assertTrue(class_exists('Fixture\Hello\HelloServiceProvider'));
			$this->assertFalse(class_exists('Fixture\Hello\Missing'));
		} finally {
			$autoloader->unregister();
		}
	}
}
