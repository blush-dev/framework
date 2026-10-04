<?php

/**
 * Local extension tests.
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
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Extension\Autoload;
use Blush\Extension\ComposerJson;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\LocalAutoloader;
use Blush\Extension\LocalExtension;
use Blush\Extension\LocalExtensions;
use Blush\Icon\IconPackDiscovery;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\Plugins;
use Blush\Theme\ThemeDiscovery;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(LocalExtensions::class)]
#[CoversClass(LocalExtension::class)]
#[CoversClass(ComposerJson::class)]
#[CoversClass(Autoload::class)]
#[CoversClass(LocalAutoloader::class)]
final class LocalExtensionsTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function paths(): Paths
	{
		return Paths::fromRoot($this->temporaryDirectory());
	}

	public function testFindsEachKindAtItsName(): void
	{
		$this->writeTemporaryFile('extensions/acme/hello/plugin.json', '{"name": "acme/hello", "label": "Hello", "namespace": "hello", "provider": "Acme\\\\Hello\\\\Provider"}');
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/other/hello/icons.json', '{"name": "other/hello", "label": "Other Hello", "namespace": "other-hello"}');
		$this->writeTemporaryFile('extensions/acme/notes/readme.md', 'No manifest, so nothing.');
		$this->writeTemporaryFile('extensions/.install-1a2b/acme/hidden/plugin.json', '{"name": "acme/hidden"}');
		$this->writeTemporaryFile('extensions/stray.json', '{}');

		$found = LocalExtensions::forPaths($this->paths());

		$this->assertSame(['acme/hello'], array_map(static fn (LocalExtension $extension): string => $extension->name, $found->of(ExtensionKind::Plugin)));
		$this->assertSame(['acme/nova'], array_map(static fn (LocalExtension $extension): string => $extension->name, $found->of(ExtensionKind::Theme)));
		$this->assertSame(['other/hello'], array_map(static fn (LocalExtension $extension): string => $extension->name, $found->of(ExtensionKind::IconPack)), 'Two vendors\' hello live side by side.');
		$this->assertSame('extensions/acme/hello', $found->of(ExtensionKind::Plugin)[0]->where);

		$this->assertSame(['acme/hello'], array_map(static fn ($plugin): string => $plugin->name, PluginDiscovery::forPaths($this->paths())->discover()->manifests));
		$this->assertTrue(new ThemeDiscovery($this->paths())->discover()->has('acme/nova'));
		$this->assertNotNull(new IconPackDiscovery($this->paths())->discover()->find('other/hello'));
	}

	public function testAFolderIsItsName(): void
	{
		$this->writeTemporaryFile('extensions/acme/helo/theme.json', '{"name": "acme/hello", "label": "Hello", "namespace": "hello"}');

		$themes = new ThemeDiscovery($this->paths())->discover();

		$this->assertFalse($themes->has('acme/hello'));
		$this->assertSame('The theme in extensions/acme/helo is named "acme/hello"; an extension\'s folder is its name, so move it to extensions/acme/hello.', $themes->invalid()['extensions/acme/helo'] ?? null);
	}

	public function testAFolderIsOneKind(): void
	{
		$this->writeTemporaryFile('extensions/acme/both/plugin.json', '{"name": "acme/both", "label": "Both", "namespace": "both", "provider": "A\\\\B"}');
		$this->writeTemporaryFile('extensions/acme/both/theme.json', '{"name": "acme/both", "label": "Both", "namespace": "both"}');

		$plugins = PluginDiscovery::forPaths($this->paths())->discover();
		$themes  = new ThemeDiscovery($this->paths())->discover();

		$this->assertSame([], $plugins->manifests);
		$this->assertSame('It holds plugin.json and theme.json, but an extension is one kind, with one manifest.', $plugins->broken[0]->reason ?? null);
		$this->assertFalse($themes->has('acme/both'));
		$this->assertArrayHasKey('extensions/acme/both', $themes->invalid(), 'Broken for each kind it claims.');
	}

	public function testComposerJsonFillsTheKeysItShares(): void
	{
		$this->writeTemporaryFile('extensions/acme/hello/plugin.json', '{"label": "Hello", "namespace": "hello", "provider": "Acme\\\\Hello\\\\Provider", "description": "From the manifest."}');
		$this->writeTemporaryFile('extensions/acme/hello/composer.json', (string) json_encode([
			'name'        => 'acme/hello',
			'description' => 'From composer.json.',
			'version'     => '1.4.0',
			'license'     => ['MIT', 'GPL-2.0-or-later'],
			'authors'     => [['name' => 'Jane Doe'], ['email' => 'no-name@example.test']],
			'autoload'    => ['psr-4' => ['Acme\\Hello\\' => 'src/'], 'files' => ['src/helpers.php']],
			'require'     => ['php' => '>=8.5', 'blush-dev/framework' => '^2.0'],
			'label'       => 'Never from composer.json'
		]));

		$plugin = PluginDiscovery::forPaths($this->paths())->discover()->manifests[0] ?? null;

		$this->assertNotNull($plugin);
		$this->assertSame('acme/hello', $plugin->name);
		$this->assertSame('Hello', $plugin->label, 'Blush\'s own keys never come from composer.json.');
		$this->assertSame('From the manifest.', $plugin->description, 'The manifest wins.');
		$this->assertSame('1.4.0', $plugin->version);
		$this->assertSame('MIT or GPL-2.0-or-later', $plugin->license);
		$this->assertSame(['Jane Doe'], array_map(static fn ($author): string => $author->name, $plugin->authors), 'Leniently, as Composer has them.');
		$this->assertEquals(new Autoload(['Acme\\Hello\\' => 'src/'], ['src/helpers.php']), $plugin->autoload);
		$this->assertSame(['php' => '>=8.5', 'blush-dev/framework' => '^2.0'], $plugin->require);
		$this->assertSame(['name' => 'kept'], ComposerJson::fill(['name' => 'kept'], $this->temporaryDirectory() . '/nowhere'), 'No composer.json is nothing.');
	}

	public function testAutoloadIsComposersShapeInsideTheExtension(): void
	{
		$autoload = Autoload::fromArray(['psr-4' => ['Acme\\' => 'src', 'Acme\\Root\\' => ''], 'files' => ['src/helpers.php']]);

		$this->assertSame(['Acme\\' => 'src/', 'Acme\\Root\\' => ''], $autoload->psr4);
		$this->assertSame(['src/helpers.php'], $autoload->files);
		$this->assertSame(['psr-4' => ['Acme\\' => 'src/', 'Acme\\Root\\' => ''], 'files' => ['src/helpers.php']], $autoload->toArray());
		$this->assertSame([], Autoload::fromArray(null)->toArray());

		$cases = [
			'"autoload" must be an object'            => ['src/'],
			'must be a namespace ending in a backslash' => ['psr-4' => ['Acme' => 'src/']],
			'folders inside the extension'            => ['psr-4' => ['Acme\\' => '../src/']],
			'"autoload.files" must be a list'         => ['files' => ['a' => 'x.php']],
			'"autoload.files" must list files inside' => ['files' => ['src/../../x.php']]
		];

		foreach ($cases as $message => $data) {
			try {
				Autoload::fromArray($data);
				$this->fail("Accepted: {$message}");
			} catch (ExtensionException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}
		}
	}

	public function testFilesAreLoadedOnceWhenTheExtensionRuns(): void
	{
		$this->writeTemporaryFile('extensions/acme/files/plugin.json', (string) json_encode([
			'name'      => 'acme/files',
			'label'     => 'Files',
			'namespace' => 'files',
			'provider'  => 'Blush\\Core\\ServiceProvider',
			'autoload'  => ['files' => ['src/helpers.php']]
		]));
		$this->writeTemporaryFile('extensions/acme/files/src/helpers.php', "<?php\n\ndeclare(strict_types=1);\n\n\$GLOBALS['blush_test_files_loaded'] = (\$GLOBALS['blush_test_files_loaded'] ?? 0) + 1;\n");

		$discovered = PluginDiscovery::forPaths($this->paths())->discover();
		$off        = new LocalAutoloader();

		$off->addPlugins(Plugins::enabled($discovered->manifests, new PluginConfig()));
		$off->register();

		$this->assertArrayNotHasKey('blush_test_files_loaded', $GLOBALS, 'Not while it\'s off.');

		foreach ([1, 2] as $boot) {
			$on = new LocalAutoloader();
			$on->addPlugins(Plugins::enabled($discovered->manifests, new PluginConfig(enabled: ['acme/files'])));
			$on->register();
			$on->unregister();
		}

		$this->assertSame(1, $GLOBALS['blush_test_files_loaded'] ?? null, 'Once, however often it boots.');

		unset($GLOBALS['blush_test_files_loaded']);
	}

	public function testHelpersForAnExtensionsFolder(): void
	{
		$paths = $this->paths();

		$this->assertSame("{$paths->extensions}/acme/hello", LocalExtensions::path($paths, 'acme/hello'));
		$this->assertTrue(LocalExtensions::contains($paths, "{$paths->extensions}/acme/hello"));
		$this->assertFalse(LocalExtensions::contains($paths, "{$paths->extensions}/acme"));
		$this->assertSame('acme/hello', LocalExtensions::nameAt($paths, 'extensions/acme/hello'));
		$this->assertNull(LocalExtensions::nameAt($paths, 'extensions/acme'));
		$this->assertNull(LocalExtensions::nameAt($paths, 'extensions/acme/hello/src'));
		$this->assertNull(LocalExtensions::nameAt($paths, 'extensions/.install-1/hello'));
		$this->assertNull(LocalExtensions::nameAt($paths, 'acme/hello'));

		$this->writeTemporaryFile('extensions/acme/one/plugin.json', '{}');
		$this->writeTemporaryFile('extensions/acme/two/plugin.json', '{}');

		unlink("{$paths->extensions}/acme/one/plugin.json");
		rmdir("{$paths->extensions}/acme/one");
		LocalExtensions::prune($paths, "{$paths->extensions}/acme/one");

		$this->assertDirectoryExists("{$paths->extensions}/acme", 'A vendor with another extension stays.');

		unlink("{$paths->extensions}/acme/two/plugin.json");
		rmdir("{$paths->extensions}/acme/two");
		LocalExtensions::prune($paths, "{$paths->extensions}/acme/two");

		$this->assertDirectoryDoesNotExist("{$paths->extensions}/acme", 'An empty vendor goes.');
	}

	public function testBootstrapRunsAPluginFromItsFolder(): void
	{
		$this->writeTemporaryFile('extensions/acme/booted/plugin.json', (string) json_encode([
			'name'      => 'acme/booted',
			'label'     => 'Booted',
			'namespace' => 'booted',
			'provider'  => 'Acme\\Booted\\BootedServiceProvider',
			'autoload'  => ['psr-4' => ['Acme\\Booted\\' => 'src/']],
			'require'   => ['blush-dev/framework' => '^2.0']
		]));
		$this->writeTemporaryFile('extensions/acme/booted/src/BootedServiceProvider.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Acme\\Booted;\n\nfinal class BootedServiceProvider extends \\Blush\\Core\\ServiceProvider\n{\n}\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/booted']);\n");

		$app = new Bootstrap($this->paths(), ['APP_ENV' => 'development'])->createApplication();

		$this->assertTrue($app->container()->make(Plugins::class)->has('acme/booted'));
		$this->assertTrue(class_exists('Acme\\Booted\\BootedServiceProvider', false));
	}
}
