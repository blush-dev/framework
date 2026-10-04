<?php

/**
 * Icon pack tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Icon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Icon\IconConfig;
use Blush\Icon\IconName;
use Blush\Icon\IconPack;
use Blush\Icon\IconPackCache;
use Blush\Icon\IconPackDiscovery;
use Blush\Icon\IconPacks;
use Blush\Icon\IconPackSource;
use Blush\Icon\Icons;
use Blush\Icon\IconServiceProvider;
use Blush\Support\PhpArrayFile;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeResolver;
use Blush\View\ViewFactory;

#[CoversClass(IconPack::class)]
#[CoversClass(IconPacks::class)]
#[CoversClass(IconPackDiscovery::class)]
#[CoversClass(IconPackCache::class)]
#[CoversClass(IconServiceProvider::class)]
final class IconPacksTest extends TestCase
{
	use BootsScratchSite;

	private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h22"/></svg>';

	private function packs(): void
	{
		$this->writeTemporaryFile('extensions/acme/brands/icons.json', '{"name": "acme/brands", "label": "Brand Logos", "namespace": "brands", "version": "1.0.0", "folder": "svg"}');
		$this->writeTemporaryFile('extensions/acme/brands/svg/github.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/brands/svg/mastodon.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/brands/lang/en.json', '{"icons": {"github": {"label": "GitHub"}}}');
		$this->writeTemporaryFile('extensions/acme/weather/icons.yaml', "name: acme/weather\nlabel: Weather\nnamespace: weather\n");
		$this->writeTemporaryFile('extensions/acme/weather/sun.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/broken/icons.json', '{"name": "acme/broken", "label": "Broken"}');
		$this->writeTemporaryFile('extensions/acme/not-a-pack/readme.md', 'No manifest.');
		$this->writeTemporaryFile('config/icons.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Icon\\IconConfig(enabled: ['acme/brands', 'acme/weather']);\n");
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [
			['name' => 'acme/arrows', 'type' => 'blush-icons', 'install-path' => '../acme/arrows'],
			['name' => 'acme/renamed', 'type' => 'blush-icons', 'install-path' => '../acme/renamed']
		]]));
		$this->writeTemporaryFile('vendor/acme/arrows/icons.json', '{"label": "Arrows", "namespace": "arrows"}');
		$this->writeTemporaryFile('vendor/acme/arrows/up.svg', self::SVG);
		$this->writeTemporaryFile('vendor/acme/renamed/icons.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');
	}

	private function app(): Application
	{
		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	public function testWhichPacksAreOn(): void
	{
		$this->packs();

		$packs = new IconPackDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();

		$this->assertSame(['acme/arrows', 'acme/brands'], array_keys($packs->withConfig(new IconConfig(enabled: ['acme/brands']))->enabled()), 'Composer\'s, and the local ones named (D-390).');
		$this->assertSame(['acme/weather'], array_keys($packs->withConfig(new IconConfig(enabled: ['acme/brands'], saved: ['acme/weather']))->enabled()), 'The admin\'s list is all of what\'s on (D-391).');
	}

	public function testDiscoversLocalAndComposerPacks(): void
	{
		$this->packs();

		$packs = new IconPackDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();

		$this->assertSame(['acme/arrows', 'acme/brands', 'acme/weather'], array_keys($packs->all()));

		$brands = $packs->find('acme/brands');

		$this->assertNotNull($brands);
		$this->assertSame('Brand Logos', $brands->label);
		$this->assertSame('brands', $brands->namespace);
		$this->assertSame('1.0.0', $brands->version);
		$this->assertSame(IconPackSource::Local, $brands->source);
		$this->assertStringEndsWith('extensions/acme/brands/svg', $brands->iconsPath());
		$this->assertSame(IconPackSource::Composer, $packs->find('acme/arrows')?->source, 'A Composer pack takes its package\'s name.');
		$this->assertStringEndsWith('extensions/acme/weather', $packs->byNamespace('weather')?->iconsPath() ?? '');
		$this->assertSame(['acme/renamed', 'extensions/acme/broken'], array_keys($packs->invalid()));
		$this->assertStringContainsString('needs a "namespace"', $packs->invalid()['extensions/acme/broken']);
	}

	public function testInvalidManifestsAreRejected(): void
	{
		$cases = [
			['label' => 'X', 'namespace' => 'x'],
			['name' => 'x', 'label' => 'X', 'namespace' => 'x'],
			['name' => 'acme/x', 'namespace' => 'x'],
			['name' => 'acme/x', 'label' => 'X', 'namespace' => 'blush'],
			['name' => 'acme/x', 'label' => 'X', 'namespace' => 'x', 'folder' => '../out'],
			['name' => 'acme/x', 'label' => 'X', 'namespace' => 'x', 'version' => 2]
		];

		foreach ($cases as $data) {
			try {
				IconPack::fromArray('/tmp/x', $data);
				$this->fail((string) json_encode($data) . ' should be rejected.');
			} catch (ExtensionException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testPacksSharingANamespaceAreBroken(): void
	{
		$this->writeTemporaryFile('extensions/acme/one/icons.json', '{"name": "acme/one", "label": "One", "namespace": "shared"}');
		$this->writeTemporaryFile('extensions/acme/two/icons.json', '{"name": "acme/two", "label": "Two", "namespace": "shared"}');
		$this->writeTemporaryFile('extensions/acme/twin/icons.json', '{"name": "acme/one", "label": "Twin", "namespace": "twin"}');

		$packs = new IconPackDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();

		$this->assertSame([], $packs->all());
		$this->assertEqualsCanonicalizing(['extensions/acme/one', 'extensions/acme/twin', 'extensions/acme/two'], array_keys($packs->invalid()));
		$this->assertStringContainsString('all have the namespace "shared"', $packs->invalid()['extensions/acme/one']);
		$this->assertStringContainsString('The icon pack in extensions/acme/twin is named "acme/one"; an extension\'s folder is its name, so move it to extensions/acme/one.', $packs->invalid()['extensions/acme/twin']);
	}

	public function testPackIconsResolveAndAreLabeled(): void
	{
		$this->packs();

		$app   = $this->app();
		$chain = $app->container()->make(ThemeResolver::class)->active();
		$icons = $app->container()->make(Icons::class);
		$views = $app->container()->make(ViewFactory::class)->forChain($chain);

		$this->assertStringEndsWith('extensions/acme/brands/svg/github.svg', $icons->file(new IconName('brands', 'github'), $chain) ?? '');
		$this->assertStringEndsWith('vendor/acme/arrows/up.svg', $icons->file(new IconName('arrows', 'up'), $chain) ?? '', 'A Composer pack is on without being named (D-390).');
		$this->assertArrayHasKey('weather/sun', $icons->all($chain));
		$this->assertSame('GitHub', $views->iconText(new IconName('brands', 'github'), 'label'));
	}

	public function testThemesRestyleAPacksIcons(): void
	{
		$this->packs();
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/acme/alt/icons/brands/github.svg', self::SVG);
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");

		$app   = $this->app();
		$chain = $app->container()->make(ThemeResolver::class)->active();

		$this->assertStringEndsWith('extensions/acme/alt/icons/brands/github.svg', $app->container()->make(Icons::class)->file(new IconName('brands', 'github'), $chain) ?? '');
	}

	public function testPacksCompileToACacheUsedOutsideDevelopment(): void
	{
		$this->packs();

		$bootstrap = new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production']);
		$bootstrap->compile();

		$packs = new IconPackCache(new PhpArrayFile($bootstrap->compiledPath(CompiledCache::IconPacks)))->read();

		$this->assertNotNull($packs);
		$this->assertSame(['acme/arrows', 'acme/brands', 'acme/weather'], array_keys($packs->all()));
		$this->assertSame('svg', $packs->find('acme/brands')?->folder);
		$this->assertArrayHasKey('extensions/acme/broken', $packs->invalid());

		$this->writeTemporaryFile('extensions/acme/later/icons.json', '{"name": "acme/later", "label": "Later", "namespace": "later"}');

		$this->assertNull($this->scratchApplication()->container()->make(IconPacks::class)->find('acme/later'));

		$bootstrap->clearCompiled(CompiledCache::IconPacks);

		$this->assertNotNull($this->scratchApplication()->container()->make(IconPacks::class)->find('acme/later'));
	}
}
