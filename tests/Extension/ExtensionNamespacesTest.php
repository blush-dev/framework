<?php

/**
 * Extension kind and namespace tests.
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
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionKind;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ManifestFile;
use Blush\Icon\IconPacks;
use Blush\Plugin\PluginDiscovery;
use Blush\Theme\ThemeDiscovery;
use Blush\Theme\Themes;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(ExtensionKind::class)]
#[CoversClass(ExtensionName::class)]
#[CoversClass(ExtensionNamespace::class)]
#[CoversClass(ManifestFile::class)]
#[CoversClass(Bootstrap::class)]
final class ExtensionNamespacesTest extends TestCase
{
	use TemporaryDirectory;

	private function paths(): Paths
	{
		return Paths::fromRoot($this->temporaryDirectory());
	}

	private function plugin(string $folder, string $name, string $namespace): void
	{
		$this->writeTemporaryFile("extensions/{$name}/plugin.json", (string) json_encode([
			'name'      => $name,
			'label'     => ucfirst($folder),
			'namespace' => $namespace,
			'provider'  => 'Acme\\Provider'
		]));
	}

	public function testKindsHaveTheirOwnManifestsAndPackageTypes(): void
	{
		$this->assertSame(['plugin', 'theme', 'icons'], array_map(static fn (ExtensionKind $kind): string => $kind->manifest(), ExtensionKind::cases()));
		$this->assertSame(['blush-plugin', 'blush-theme', 'blush-icons'], array_map(static fn (ExtensionKind $kind): string => $kind->packageType(), ExtensionKind::cases()));
		$this->assertTrue(ExtensionKind::Theme->runsCode(), 'Themes can run PHP.');
		$this->assertFalse(ExtensionKind::IconPack->runsCode());
	}

	public function testNamesAreVendorSlashName(): void
	{
		foreach (['acme/gallery', 'justintadlock/jtcom', 'a.b/c-d'] as $name) {
			$this->assertTrue(ExtensionName::isValid($name), $name);
		}

		foreach (['gallery', 'Acme/Gallery', 'acme/', '/gallery', 'a/b/c', 'acme gallery'] as $name) {
			$this->assertFalse(ExtensionName::isValid($name), $name);
		}

		$this->assertTrue(ExtensionNamespace::isValid('jtcom'));
		$this->assertFalse(ExtensionNamespace::isValid('Jt Com'));
		$this->assertSame('acme-gallery', ExtensionNamespace::fromName('acme/gallery'));
		$this->assertSame('a-b-c-d', ExtensionNamespace::fromName('a.b/c-d'));
		$this->assertTrue(ExtensionNamespace::isValid(ExtensionNamespace::fromName('a.b/c_d--e')));

		foreach (['blush', 'app', 'theme', 'default'] as $reserved) {
			$this->assertTrue(ExtensionNamespace::isReserved($reserved), $reserved);
		}
	}

	public function testManifestsAreJsonOrYamlMaps(): void
	{
		$this->writeTemporaryFile('one/plugin.json', '{"name": "acme/one"}');
		$this->writeTemporaryFile('one/plugin.yaml', 'name: acme/yaml');
		$this->writeTemporaryFile('two/icons.yml', '- a list');

		$files = ManifestFile::find($this->temporaryDirectory() . '/one', ExtensionKind::Plugin);

		$this->assertSame(['plugin.json', 'plugin.yaml'], array_map(basename(...), $files));
		$this->assertSame(['name' => 'acme/one'], ManifestFile::read($files[0]));
		$this->assertSame([], ManifestFile::find($this->temporaryDirectory() . '/one', ExtensionKind::Theme));

		$this->writeTemporaryFile('three/theme.json', '{}');

		$this->assertSame([], ManifestFile::read($this->temporaryDirectory() . '/three/theme.json'), 'An empty manifest leaves everything to composer.json and the defaults.');

		$this->expectException(ExtensionException::class);

		ManifestFile::read($this->temporaryDirectory() . '/two/icons.yml');
	}

	public function testTwoPluginsCantShareANamespace(): void
	{
		$this->plugin('one', 'acme/one', 'shared');
		$this->plugin('two', 'acme/two', 'shared');

		$this->expectException(ExtensionException::class);
		$this->expectExceptionMessage('Two plugins have the namespace "shared"');

		PluginDiscovery::forPaths($this->paths())->discover();
	}

	public function testThemesSharingANamespaceAreBroken(): void
	{
		$this->writeTemporaryFile('extensions/acme/one/theme.json', '{"name": "acme/one", "label": "One", "namespace": "shared"}');
		$this->writeTemporaryFile('extensions/acme/two/theme.json', '{"name": "acme/two", "label": "Two", "namespace": "shared"}');
		$this->writeTemporaryFile('extensions/acme/three/theme.json', '{"name": "acme/three", "label": "Three", "namespace": "default"}');

		$themes = new ThemeDiscovery($this->paths())->discover();

		$this->assertSame([Themes::DEFAULT], array_keys($themes->all()));
		$this->assertStringContainsString('all have the namespace "shared"', $themes->invalid()['extensions/acme/one'] ?? '');
		$this->assertStringContainsString('all have the namespace "shared"', $themes->invalid()['extensions/acme/two'] ?? '');
		$this->assertStringContainsString('needs a "namespace"', $themes->invalid()['extensions/acme/three'] ?? '', 'The default theme\'s namespace is reserved.');
	}

	public function testPluginsThenThemesThenIconPacksClaimNamespaces(): void
	{
		$this->plugin('gallery', 'acme/gallery', 'gallery');
		$this->writeTemporaryFile('extensions/acme/gallery-theme/theme.json', '{"name": "acme/gallery-theme", "label": "Gallery", "namespace": "gallery"}');
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/nova-icons/icons.json', '{"name": "acme/nova-icons", "label": "Nova Icons", "namespace": "nova"}');
		$this->writeTemporaryFile('extensions/acme/gallery-icons/icons.json', '{"name": "acme/gallery-icons", "label": "Gallery Icons", "namespace": "gallery"}');
		$this->writeTemporaryFile('extensions/acme/brands/icons.json', '{"name": "acme/brands", "label": "Brands", "namespace": "brands"}');

		$container = new Bootstrap($this->paths())->createApplication()->container();
		$themes    = $container->make(Themes::class);
		$packs     = $container->make(IconPacks::class);

		$this->assertSame([Themes::DEFAULT, 'acme/nova'], array_keys($themes->all()));
		$this->assertSame('Its namespace, "gallery", is the plugin acme/gallery\'s.', $themes->invalid()['extensions/acme/gallery-theme'] ?? null, 'An installed plugin claims it, even turned off (as local ones are until named, D-390).');
		$this->assertSame(['acme/brands'], array_keys($packs->all()));
		$this->assertSame('Its namespace, "nova", is the theme acme/nova\'s.', $packs->invalid()['extensions/acme/nova-icons'] ?? null);
		$this->assertSame('Its namespace, "gallery", is the plugin acme/gallery\'s.', $packs->invalid()['extensions/acme/gallery-icons'] ?? null);
	}

	public function testANameIsOneExtensionsAcrossKinds(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [
			['name' => 'acme/nova', 'type' => 'blush-icons', 'install-path' => '../acme/nova']
		]]));
		$this->writeTemporaryFile('vendor/acme/nova/icons.json', '{"label": "Nova Icons", "namespace": "nova-icons"}');

		$packs = new Bootstrap($this->paths())->createApplication()->container()->make(IconPacks::class);

		$this->assertSame([], array_keys($packs->all()));
		$this->assertSame('Its name, "acme/nova", is a theme\'s too.', $packs->invalid()['acme/nova'] ?? null, 'So a requirement of acme/nova names one extension (D-431).');
	}
}
