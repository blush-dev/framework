<?php

/**
 * Plugin discovery tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\LocalAutoloader;
use Blush\Plugin\ComposerPluginFinder;
use Blush\Plugin\LocalPluginFinder;
use Blush\Plugin\PluginCache;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Plugin\PluginSource;
use Blush\Support\PhpArrayFile;
use Blush\Tests\Fixtures\Plugin\ComposerPluginProvider;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(PluginManifest::class)]
#[CoversClass(LocalPluginFinder::class)]
#[CoversClass(ComposerPluginFinder::class)]
#[CoversClass(PluginDiscovery::class)]
#[CoversClass(Plugins::class)]
#[CoversClass(PluginCache::class)]
#[CoversClass(PluginConfig::class)]
#[CoversClass(LocalAutoloader::class)]
final class PluginTest extends TestCase
{
	use TemporaryDirectory;

	private const string SITE = __DIR__ . '/../Fixtures/site';

	/**
	 * @return list<PluginManifest>
	 */
	private function discover(): array
	{
		return PluginDiscovery::forPaths(Paths::fromRoot(self::SITE))->discover();
	}

	public function testFindsLocalPlugins(): void
	{
		$manifests = new LocalPluginFinder(self::SITE . '/user/plugins')->find();

		$this->assertSame(['acme/disabled', 'fixture/hello'], array_map(
			static fn (PluginManifest $manifest): string => $manifest->name,
			$manifests
		));

		$hello = $manifests[1];

		$this->assertSame(PluginSource::Local, $hello->source);
		$this->assertSame('Hello', $hello->label);
		$this->assertSame('hello', $hello->namespace);
		$this->assertSame('1.2.0', $hello->version);
		$this->assertSame('Fixture\Hello\HelloServiceProvider', $hello->providerClass());
		$this->assertSame(['Fixture\Hello\\' => 'src/'], $hello->autoload);
		$this->assertSame(['blush' => '^2.0'], $hello->requires);
		$this->assertStringEndsWith('user/plugins/hello', $hello->path);
	}

	public function testFindsComposerPlugins(): void
	{
		$manifests = new ComposerPluginFinder(self::SITE . '/vendor')->find();

		$this->assertCount(1, $manifests);
		$this->assertSame('acme/composer-plugin', $manifests[0]->name);
		$this->assertSame('Composer Plugin', $manifests[0]->label);
		$this->assertSame('composer-plugin', $manifests[0]->namespace);
		$this->assertSame('2.1.0', $manifests[0]->version);
		$this->assertSame(PluginSource::Composer, $manifests[0]->source);
		$this->assertSame(ComposerPluginProvider::class, $manifests[0]->providerClass());
		$this->assertSame(realpath(self::SITE . '/vendor/acme/composer-plugin'), $manifests[0]->path);
	}

	public function testReadsComposerOneInstalledFiles(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			['name' => 'acme/one', 'type' => 'blush-plugin', 'extra' => ['blush' => ['label' => 'One', 'namespace' => 'one', 'provider' => 'Acme\One\Provider']]]
		]));

		$manifests = new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find();

		$this->assertSame('acme/one', $manifests[0]->name);
	}

	public function testComposerPluginsNeedTheirManifest(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			'packages' => [['name' => 'acme/none', 'type' => 'blush-plugin', 'extra' => ['blush' => ['label' => 'None', 'namespace' => 'none']]]]
		]));

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('extra.blush.provider');

		new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find();
	}

	public function testDiscoveryCombinesSourcesInNameOrder(): void
	{
		$this->assertSame(['acme/composer-plugin', 'acme/disabled', 'fixture/hello'], array_map(
			static fn (PluginManifest $manifest): string => $manifest->name,
			$this->discover()
		));
	}

	public function testDiscoveryRejectsDuplicateNames(): void
	{
		$this->writeTemporaryFile('one/a/plugin.json', '{"name": "same/name", "label": "A", "namespace": "a", "provider": "A\\\\Provider"}');
		$this->writeTemporaryFile('two/b/plugin.json', '{"name": "same/name", "label": "B", "namespace": "b", "provider": "B\\\\Provider"}');

		$this->expectException(ExtensionException::class);

		new PluginDiscovery([
			new LocalPluginFinder($this->temporaryDirectory() . '/one'),
			new LocalPluginFinder($this->temporaryDirectory() . '/two')
		])->discover();
	}

	public function testInvalidManifestsAreRejected(): void
	{
		$cases = [
			'{"name": "Bad Name", "label": "Bad", "namespace": "bad", "provider": "A\\\\B"}',
			'{"name": "slug", "label": "Slug", "namespace": "slug", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "not a class"}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "autoload": {"psr-4": {"A\\\\": "../escape"}}}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "autoload": {"psr-4": {"NoSlash": "src"}}}',
			'{"name": "ok/name", "namespace": "ok", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "blush", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "Not Valid", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok"}',
			'not json'
		];

		foreach ($cases as $index => $json) {
			$this->writeTemporaryFile("bad{$index}/ext/plugin.json", $json);

			try {
				new LocalPluginFinder($this->temporaryDirectory() . "/bad{$index}")->find();
				$this->fail("Expected manifest {$index} to be rejected.");
			} catch (ExtensionException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testReadsYamlManifestsAndJsonWins(): void
	{
		$this->writeTemporaryFile('plugins/gallery/plugin.yaml', <<<'YAML'
			name: acme/gallery
			label: Gallery
			namespace: gallery
			version: 1.0.0
			provider: Acme\Gallery\GalleryServiceProvider
			autoload:
			  psr-4:
			    Acme\Gallery\: src/
			YAML);
		$this->writeTemporaryFile('plugins/both/plugin.json', '{"name": "acme/json", "label": "JSON", "namespace": "json", "provider": "A\\\\B"}');
		$this->writeTemporaryFile('plugins/both/plugin.yml', "name: acme/yaml\nlabel: YAML\nnamespace: yaml\nprovider: A\\B\n");
		$this->writeTemporaryFile('plugins/none/readme.md', 'No manifest.');
		$this->writeTemporaryFile('plugins/old/extension.json', '{"name": "acme/old", "label": "Old", "namespace": "old", "provider": "A\\\\B"}');

		$manifests = new LocalPluginFinder($this->temporaryDirectory() . '/plugins')->find();

		$this->assertSame(['acme/json', 'acme/gallery'], array_map(
			static fn (PluginManifest $manifest): string => $manifest->name,
			$manifests
		));
		$this->assertSame(['Acme\Gallery\\' => 'src/'], $manifests[1]->autoload);
	}

	public function testInvalidYamlManifestsAreRejected(): void
	{
		$this->writeTemporaryFile('bad/ext/plugin.yaml', "- a list\n- not a map\n");

		$this->expectException(ExtensionException::class);

		new LocalPluginFinder($this->temporaryDirectory() . '/bad')->find();
	}

	public function testEnabledFiltersByConfig(): void
	{
		$discovered = $this->discover();

		$all      = Plugins::enabled($discovered, new PluginConfig());
		$disabled = Plugins::enabled($discovered, new PluginConfig(disabled: ['acme/disabled']));
		$only     = Plugins::enabled($discovered, new PluginConfig(enabled: ['fixture/hello', 'acme/disabled'], disabled: ['acme/disabled']));

		$this->assertCount(3, $all->all());
		$this->assertFalse($disabled->has('acme/disabled'));
		$this->assertSame(['fixture/hello'], array_map(static fn (PluginManifest $m): string => $m->name, $only->all()));
		$this->assertSame(['Fixture\Hello\HelloServiceProvider'], $only->providers());
		$this->assertNull($only->get('acme/composer-plugin'));
	}

	public function testEnablingAMissingPluginThrows(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('acme/missing');

		Plugins::enabled($this->discover(), new PluginConfig(enabled: ['acme/missing']));
	}

	public function testCacheRoundTrips(): void
	{
		$cache     = new PluginCache(new PhpArrayFile($this->temporaryDirectory() . '/plugins.php'));
		$manifests = $this->discover();

		$this->assertNull($cache->read());

		$cache->write($manifests);

		$this->assertEquals($manifests, $cache->read());
	}

	public function testLocalAutoloaderLoadsPluginClasses(): void
	{
		$autoloader = new LocalAutoloader();
		$autoloader->addPlugins(Plugins::enabled($this->discover(), new PluginConfig()));
		$autoloader->register();

		try {
			$this->assertTrue(class_exists('Fixture\Hello\HelloServiceProvider'));
			$this->assertFalse(class_exists('Fixture\Hello\Missing'));
		} finally {
			$autoloader->unregister();
		}
	}
}
