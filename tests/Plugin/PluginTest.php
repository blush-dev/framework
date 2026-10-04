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
use Blush\Extension\Autoload;
use Blush\Extension\ExtensionException;
use Blush\Extension\LocalAutoloader;
use Blush\Extension\LocalExtensions;
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
	 * A finder over a folder of `{vendor}/{name}` folders.
	 */
	private static function finder(string $folder, string $root = ''): LocalPluginFinder
	{
		return new LocalPluginFinder(new LocalExtensions($folder, $root), $root);
	}

	/**
	 * @return list<PluginManifest>
	 */
	private function discover(): array
	{
		return PluginDiscovery::forPaths(Paths::fromRoot(self::SITE))->discover()->manifests;
	}

	public function testFindsLocalPlugins(): void
	{
		$manifests = self::finder(self::SITE . '/extensions')->find()->manifests;

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
		$this->assertSame(['Fixture\Hello\\' => 'src/'], $hello->autoload->psr4);
		$this->assertSame(['blush-dev/framework' => '^2.0'], $hello->require);
		$this->assertStringEndsWith('extensions/fixture/hello', $hello->path);
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

	public function testWithoutALabelAPluginIsShownByItsName(): void
	{
		$this->writeTemporaryFile('extensions/acme/plain/plugin.json', '{"namespace": "plain", "provider": "A\\\\B", "label": " "}');
		$this->writeTemporaryFile('extensions/acme/plain/composer.json', '{"name": "acme/plain"}');
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode([
			['name' => 'acme/bare', 'type' => 'blush-plugin', 'extra' => ['blush' => ['namespace' => 'bare', 'provider' => 'Acme\Bare\Provider']]]
		]));

		$local    = self::finder($this->temporaryDirectory() . '/extensions')->find();
		$composer = new ComposerPluginFinder($this->temporaryDirectory() . '/vendor')->find()->manifests;

		$this->assertSame([], $local->broken);
		$this->assertSame('acme/plain', $local->manifests[0]->label ?? null, 'A blank label is no label, and the name may come from composer.json.');
		$this->assertSame('acme/bare', $composer[0]->label, 'Composer packages need no extra.blush.label.');
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
		$this->writeTemporaryFile('one/same/name/plugin.json', '{"name": "same/name", "label": "A", "namespace": "a", "provider": "A\\\\Provider"}');
		$this->writeTemporaryFile('two/same/name/plugin.json', '{"name": "same/name", "label": "B", "namespace": "b", "provider": "B\\\\Provider"}');

		$this->expectException(ExtensionException::class);

		new PluginDiscovery([
			self::finder($this->temporaryDirectory() . '/one'),
			self::finder($this->temporaryDirectory() . '/two')
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
			'{"name": "ok/name", "label": 5, "namespace": "ok", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "blush", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "Not Valid", "provider": "A\\\\B"}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok"}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "authors": [{"email": "a@example.test"}]}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "license": ["MIT"]}',
			'{"name": "ok/name", "label": "OK", "namespace": "ok", "provider": "A\\\\B", "autoload": {"files": ["../outside.php"]}}',
			'{"name": "ok/other", "label": "OK", "namespace": "ok", "provider": "A\\\\B"}',
			'not json'
		];

		foreach ($cases as $index => $json) {
			$this->writeTemporaryFile("bad{$index}/ok/name/plugin.json", $json);

			$found = self::finder($this->temporaryDirectory() . "/bad{$index}", $this->temporaryDirectory())->find();

			$this->assertSame([], $found->manifests, "Expected manifest {$index} to be broken.");
			$this->assertSame("bad{$index}/ok/name", $found->broken[0]->where ?? null);
			$this->assertStringNotContainsString($this->temporaryDirectory(), $found->broken[0]->reason ?? '', 'Paths are from the root.');
		}

		$this->assertSame('ok/name', self::finder($this->temporaryDirectory() . '/bad2')->find()->broken[0]->name ?? null, 'A manifest that parses gives its name.');
		$this->assertSame('', self::finder($this->temporaryDirectory() . '/bad14')->find()->broken[0]->name ?? null);
		$this->assertStringContainsString('is named "ok/other"; an extension\'s folder is its name, so move it to bad13/ok/other.', self::finder($this->temporaryDirectory() . '/bad13', $this->temporaryDirectory())->find()->broken[0]->reason ?? '');
	}

	public function testReadsYamlManifestsAndJsonWins(): void
	{
		$this->writeTemporaryFile('plugins/acme/gallery/plugin.yaml', <<<'YAML'
			name: acme/gallery
			label: Gallery
			namespace: gallery
			version: 1.0.0
			provider: Acme\Gallery\GalleryServiceProvider
			autoload:
			  psr-4:
			    Acme\Gallery\: src/
			YAML);
		$this->writeTemporaryFile('plugins/acme/json/plugin.json', '{"name": "acme/json", "label": "JSON", "namespace": "json", "provider": "A\\\\B"}');
		$this->writeTemporaryFile('plugins/acme/json/plugin.yml', "name: acme/yaml\nlabel: YAML\nnamespace: yaml\nprovider: A\\B\n");
		$this->writeTemporaryFile('plugins/acme/none/readme.md', 'No manifest.');
		$this->writeTemporaryFile('plugins/acme/old/extension.json', '{"name": "acme/old", "label": "Old", "namespace": "old", "provider": "A\\\\B"}');
		$this->writeTemporaryFile('plugins/.install-123/acme/plugin.json', '{"name": "acme/hidden", "label": "Hidden", "namespace": "hidden", "provider": "A\\\\B"}');

		$manifests = self::finder($this->temporaryDirectory() . '/plugins')->find()->manifests;

		$this->assertSame(['acme/gallery', 'acme/json'], array_map(
			static fn (PluginManifest $manifest): string => $manifest->name,
			$manifests
		));
		$this->assertSame(['Acme\Gallery\\' => 'src/'], $manifests[0]->autoload->psr4);
	}

	public function testInvalidYamlManifestsAreBroken(): void
	{
		$this->writeTemporaryFile('bad/acme/ext/plugin.yaml', "- a list\n- not a map\n");

		$this->assertCount(1, self::finder($this->temporaryDirectory() . '/bad')->find()->broken);
	}

	public function testBrokenPluginsNeverRun(): void
	{
		$broken = [
			new BrokenPlugin('extensions/acme/gallery', 'The manifest is invalid.', 'acme/gallery', PluginSource::Local),
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
		$this->expectExceptionMessage('acme/gallery. extensions/acme/gallery is broken: The manifest is invalid.');

		Plugins::enabled($this->discover(), new PluginConfig(enabled: ['acme/gallery']), broken: [
			new BrokenPlugin('extensions/acme/gallery', 'The manifest is invalid.', '', PluginSource::Local)
		]);
	}

	public function testDiscoveryKeepsBrokenPlugins(): void
	{
		$this->writeTemporaryFile('extensions/acme/good/plugin.json', '{"name": "acme/good", "label": "Good", "namespace": "good", "provider": "A\\\\Provider"}');
		$this->writeTemporaryFile('extensions/acme/bad/plugin.json', '{broken');

		$found = PluginDiscovery::forPaths(Paths::fromRoot($this->temporaryDirectory()))->discover();

		$this->assertSame(['acme/good'], array_map(static fn (PluginManifest $m): string => $m->name, $found->manifests));
		$this->assertSame(['extensions/acme/bad'], array_map(static fn (BrokenPlugin $p): string => $p->where, $found->broken));
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
		$discovered = new DiscoveredPlugins($this->discover(), [new BrokenPlugin('extensions/acme/bad', 'Broken.', '', PluginSource::Local)]);

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
