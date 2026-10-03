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
use Blush\Config\InvalidConfig;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\LocalAutoloader;
use Blush\Plugin\BrokenPlugin;
use Blush\Plugin\ComposerPluginFinder;
use Blush\Plugin\DiscoveredPlugins;
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
#[CoversClass(BrokenPlugin::class)]
#[CoversClass(DiscoveredPlugins::class)]
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
		return PluginDiscovery::forPaths(Paths::fromRoot(self::SITE))->discover()->manifests;
	}

	public function testFindsLocalPlugins(): void
	{
		$manifests = new LocalPluginFinder(self::SITE . '/user/plugins')->find()->manifests;

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
		$manifests = new ComposerPluginFinder(self::SITE . '/vendor')->find()->manifests;

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

		$manifests = new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find()->manifests;

		$this->assertSame('acme/one', $manifests[0]->name);
	}

	public function testComposerPluginsWithoutTheirManifestAreBroken(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			'packages' => [['name' => 'acme/none', 'type' => 'blush-plugin', 'extra' => ['blush' => ['label' => 'None', 'namespace' => 'none']]]]
		]));

		$found = new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find();

		$this->assertSame([], $found->manifests);
		$this->assertCount(1, $found->broken);
		$this->assertSame('acme/none', $found->broken[0]->where);
		$this->assertSame('acme/none', $found->broken[0]->name);
		$this->assertSame(PluginSource::Composer, $found->broken[0]->source);
		$this->assertStringContainsString('extra.blush.provider', $found->broken[0]->reason);
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

	public function testInvalidManifestsAreBroken(): void
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
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "authors": [{"email": "a@example.test"}]}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "license": ["MIT"]}',
			'not json'
		];

		foreach ($cases as $index => $json) {
			$this->writeTemporaryFile("bad{$index}/ext/plugin.json", $json);

			$found = new LocalPluginFinder($this->temporaryDirectory() . "/bad{$index}", $this->temporaryDirectory())->find();

			$this->assertSame([], $found->manifests, "Expected manifest {$index} to be broken.");
			$this->assertSame("bad{$index}/ext", $found->broken[0]->where ?? null);
			$this->assertStringNotContainsString($this->temporaryDirectory(), $found->broken[0]->reason ?? '', 'Paths are from the root.');
		}

		$this->assertSame('ok/name', new LocalPluginFinder($this->temporaryDirectory() . '/bad2')->find()->broken[0]->name ?? null, 'A manifest that parses gives its name.');
		$this->assertSame('', new LocalPluginFinder($this->temporaryDirectory() . '/bad12')->find()->broken[0]->name ?? null);
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

		$manifests = new LocalPluginFinder($this->temporaryDirectory() . '/plugins')->find()->manifests;

		$this->assertSame(['acme/json', 'acme/gallery'], array_map(
			static fn (PluginManifest $manifest): string => $manifest->name,
			$manifests
		));
		$this->assertSame(['Acme\Gallery\\' => 'src/'], $manifests[1]->autoload);
	}

	public function testInvalidYamlManifestsAreBroken(): void
	{
		$this->writeTemporaryFile('bad/ext/plugin.yaml', "- a list\n- not a map\n");

		$this->assertCount(1, new LocalPluginFinder($this->temporaryDirectory() . '/bad')->find()->broken);
	}

	public function testBrokenPluginsNeverRun(): void
	{
		$broken = [
			new BrokenPlugin('user/plugins/gallery', 'The manifest is invalid.', 'acme/gallery', PluginSource::Local),
			new BrokenPlugin('acme/shop', 'No "extra.blush.provider".', 'acme/shop', PluginSource::Composer)
		];

		$plugins = Plugins::enabled($this->discover(), new PluginConfig(enabled: ['acme/gallery']), broken: $broken);

		$this->assertSame(['acme/composer-plugin'], array_map(static fn (PluginManifest $m): string => $m->name, $plugins->all()), 'Turned on, or on by default, a broken one doesn\'t run.');
		$this->assertSame($broken, $plugins->broken());
		$this->assertCount(3, $plugins->installed());
	}

	public function testAMissingPluginNamesBrokenOnesWithoutAName(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('acme/gallery. user/plugins/gallery is broken: The manifest is invalid.');

		Plugins::enabled($this->discover(), new PluginConfig(enabled: ['acme/gallery']), broken: [
			new BrokenPlugin('user/plugins/gallery', 'The manifest is invalid.', '', PluginSource::Local)
		]);
	}

	public function testDiscoveryKeepsBrokenPlugins(): void
	{
		$this->writeTemporaryFile('user/plugins/good/plugin.json', '{"name": "acme/good", "label": "Good", "namespace": "good", "provider": "A\\\\Provider"}');
		$this->writeTemporaryFile('user/plugins/bad/plugin.json', '{broken');

		$found = PluginDiscovery::forPaths(Paths::fromRoot($this->temporaryDirectory()))->discover();

		$this->assertSame(['acme/good'], array_map(static fn (PluginManifest $m): string => $m->name, $found->manifests));
		$this->assertSame(['user/plugins/bad'], array_map(static fn (BrokenPlugin $p): string => $p->where, $found->broken));
		$this->assertSame(2, $found->count());
	}

	public function testOnlyComposerPluginsAndNamedOnesAreOn(): void
	{
		$discovered = $this->discover();

		$none  = Plugins::enabled($discovered, new PluginConfig());
		$named = Plugins::enabled($discovered, new PluginConfig(enabled: ['fixture/hello']));

		$this->assertSame(['acme/composer-plugin'], array_map(static fn (PluginManifest $m): string => $m->name, $none->all()), 'Nothing local is on by default (D-390).');
		$this->assertSame(['acme/composer-plugin', 'fixture/hello'], array_map(static fn (PluginManifest $m): string => $m->name, $named->all()));
		$this->assertFalse($named->has('acme/disabled'));
		$this->assertCount(3, $named->installed());
	}

	public function testTheAdminsSavedListIsAllOfWhatIsOn(): void
	{
		$discovered = $this->discover();

		$saved = Plugins::enabled($discovered, new PluginConfig(enabled: ['fixture/hello'], saved: ['acme/disabled']));
		$empty = Plugins::enabled($discovered, new PluginConfig(enabled: ['fixture/hello'], saved: []));

		$this->assertSame(['acme/disabled'], array_map(static fn (PluginManifest $m): string => $m->name, $saved->all()), 'It replaces config\'s list, and leaves Composer\'s out (D-391).');
		$this->assertSame([], $empty->all());
	}

	public function testConfigNeverSaysWhatIsOff(): void
	{
		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('disabled');

		PluginConfig::fromArray(['disabled' => ['acme/disabled']]);
	}

	public function testEnablingAMissingPluginThrows(): void
	{
		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('acme/missing');

		Plugins::enabled($this->discover(), new PluginConfig(enabled: ['acme/missing']));
	}

	public function testCacheRoundTrips(): void
	{
		$file       = new PhpArrayFile($this->temporaryDirectory() . '/plugins.php');
		$cache      = new PluginCache($file);
		$discovered = new DiscoveredPlugins($this->discover(), [new BrokenPlugin('user/plugins/bad', 'Broken.', '', PluginSource::Local)]);

		$this->assertNull($cache->read());

		$cache->write($discovered);

		$this->assertEquals($discovered, $cache->read());

		$file->write([['name' => 'acme/old']]);

		$this->assertNull($cache->read(), 'A cache in the older shape is discovered again.');
	}

	public function testLocalAutoloaderLoadsPluginClasses(): void
	{
		$autoloader = new LocalAutoloader();
		$autoloader->addPlugins(Plugins::enabled($this->discover(), new PluginConfig(enabled: ['fixture/hello'])));
		$autoloader->register();

		try {
			$this->assertTrue(class_exists('Fixture\Hello\HelloServiceProvider'));
			$this->assertFalse(class_exists('Fixture\Hello\Missing'));
		} finally {
			$autoloader->unregister();
		}
	}
}
