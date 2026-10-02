<?php

/**
 * Theme system tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Theme;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Cache\ContentVersion;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\LocalAutoloader;
use Blush\Field\Severity;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Support\ComposerPackages;
use Blush\Support\PhpArrayFile;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\SettingsResolver;
use Blush\Theme\SiteThemeData;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeCache;
use Blush\Theme\ThemeDiscovery;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\ThemeResolver;
use Blush\Theme\Themes;
use Blush\Theme\ThemeSettings;
use Blush\Theme\ThemeSource;

#[CoversClass(ThemeDiscovery::class)]
#[CoversClass(ThemeCache::class)]
#[CoversClass(ThemeManifest::class)]
#[CoversClass(ThemeSource::class)]
#[CoversClass(ThemeAssets::class)]
#[CoversClass(SettingsResolver::class)]
#[CoversClass(ThemeSettings::class)]
#[CoversClass(SiteThemeData::class)]
#[CoversClass(ComposerPackages::class)]
#[CoversClass(Bootstrap::class)]
#[CoversClass(LocalAutoloader::class)]
final class ThemeSystemTest extends TestCase
{
	use BootsScratchSite;

	private ?Application $app = null;

	protected function tearDown(): void
	{
		$this->app?->container()->make(LocalAutoloader::class)->unregister();
	}

	private function boot(string $environment = 'production'): Application
	{
		$this->app = $this->scratchApplication(['APP_ENV' => $environment]);
		$this->app->boot();

		return $this->app;
	}

	private function activeTheme(string $slug): void
	{
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: '{$slug}');\n");
	}

	private function get(string $uri): string
	{
		return (string) ($this->app ?? $this->boot())->container()->make(Kernel::class)->handle(Request::create($uri))->getBody();
	}

	private function composerTheme(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', json_encode(['packages' => [
			['name' => 'acme/nova-theme', 'type' => 'blush-theme', 'install-path' => '../acme/nova-theme'],
			['name' => 'acme/renamed', 'type' => 'blush-theme', 'install-path' => '../acme/renamed', 'extra' => ['blush' => ['slug' => 'dusk']]],
			['name' => 'acme/local-wins', 'type' => 'blush-theme', 'install-path' => '../acme/local-wins'],
			['name' => 'acme/library', 'type' => 'library']
		]], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('vendor/acme/nova-theme/theme.json', '{"name": "Nova"}');
		$this->writeTemporaryFile('vendor/acme/renamed/theme.yaml', 'name: Dusk');
		$this->writeTemporaryFile('vendor/acme/local-wins/theme.json', '{"name": "From Composer"}');
		$this->writeTemporaryFile('user/themes/local-wins/theme.json', '{"name": "From user/themes"}');
		$this->writeTemporaryFile('user/themes/default/theme.json', '{"name": "Not the default"}');
		$this->writeTemporaryFile('user/themes/broken/theme.json', '{"name": 5}');
	}

	public function testDiscoversLocalComposerAndFrameworkThemes(): void
	{
		$this->composerTheme();

		$themes = new ThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();
		$all    = $themes->all();

		$this->assertSame(['default', 'dusk', 'local-wins', 'nova-theme'], array_keys($all));
		$this->assertSame(ThemeSource::Framework, $all['default']->source);
		$this->assertSame('Default', $all['default']->name);
		$this->assertSame(ThemeSource::Composer, $all['nova-theme']->source);
		$this->assertSame('Dusk', $all['dusk']->name);
		$this->assertSame('From user/themes', $all['local-wins']->name);
		$this->assertSame(ThemeSource::Local, $all['local-wins']->source);
		$this->assertStringContainsString('"name"', $themes->invalid()['broken']);
		$this->assertFalse($themes->has('broken'));

		$this->expectException(ThemeException::class);
		$themes->find('broken');
	}

	public function testThemesCompileToACacheUsedOutsideDevelopment(): void
	{
		$this->composerTheme();

		$bootstrap = new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production']);
		$bootstrap->compile();

		$cache  = new ThemeCache(new PhpArrayFile($bootstrap->compiledPath(CompiledCache::Themes)));
		$themes = $cache->read();

		$this->assertNotNull($themes);
		$this->assertSame(['default', 'dusk', 'local-wins', 'nova-theme'], array_keys($themes->all()));
		$this->assertArrayHasKey('broken', $themes->invalid());

		$this->writeTemporaryFile('user/themes/later/theme.json', '{"name": "Later"}');

		$this->assertFalse($this->boot()->container()->make(Themes::class)->has('later'));

		$this->app?->container()->make(LocalAutoloader::class)->unregister();
		$bootstrap->clearCompiled(CompiledCache::Themes);

		$this->assertTrue($this->boot()->container()->make(Themes::class)->has('later'));
	}

	public function testThemeProvidersRegisterAndAutoload(): void
	{
		$this->writeTemporaryFile('user/themes/pro/theme.json', json_encode([
			'name'     => 'Pro',
			'parent'   => 'base',
			'provider' => 'ProTheme\\ProThemeProvider',
			'autoload' => ['psr-4' => ['ProTheme\\' => 'src/']]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/themes/pro/src/ProThemeProvider.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			namespace ProTheme;

			use Blush\Core\ServiceProvider;
			use Blush\View\ContextProviders;

			final class ProThemeProvider extends ServiceProvider
			{
				public function boot(): void
				{
					$this->container->make(ContextProviders::class)->add('parts/footer', FooterNote::class);
				}
			}
			PHP);
		$this->writeTemporaryFile('user/themes/pro/src/FooterNote.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			namespace ProTheme;

			use Blush\View\ContextProvider;

			final class FooterNote implements ContextProvider
			{
				public function provide(string $view, array $data): array
				{
					return ['note' => 'Pro note'];
				}
			}
			PHP);
		$this->writeTemporaryFile('user/themes/pro/views/parts/footer.php', '<footer><?= e($note ?? "no note") ?></footer>');
		$this->writeTemporaryFile('user/themes/base/theme.json', '{"name": "Base", "provider": "Missing\\\\Provider"}');
		$this->activeTheme('pro');

		$this->assertStringContainsString('<footer>Pro note</footer>', $this->get('/'));
		$this->assertSame(['Missing\\Provider', 'ProTheme\\ProThemeProvider'], $this->app?->container()->make(ThemeResolver::class)->active()->providers());
	}

	public function testSettingsResolveThroughTheChain(): void
	{
		$this->writeTemporaryFile('user/themes/kid/theme.json', json_encode([
			'name'     => 'Kid',
			'settings' => [
				'layout'   => ['type' => 'enum', 'options' => ['grid', 'list'], 'default' => 'list'],
				'excerpts' => ['type' => 'bool', 'default' => false],
				'columns'  => ['type' => 'number', 'default' => 2]
			]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/data/theme.json', '{"settings": {"layout": "grid", "columns": "many", "unknown": 1}}');

		$app      = $this->boot();
		$resolver = $app->container()->make(SettingsResolver::class);
		$kid      = $resolver->for($app->container()->make(Themes::class)->chain('kid'));

		$this->assertSame('grid', $kid->get('layout'));
		$this->assertFalse($kid->get('excerpts'));
		$this->assertSame(2, $kid->get('columns'));
		$this->assertNull($kid->get('unknown'));
		$this->assertSame('fallback', $kid->get('missing', 'fallback'));
		$this->assertSame(['columns'], array_map(static fn ($violation): string => $violation->field, $kid->violations));
		$this->assertSame(Severity::Error, $kid->violations[0]->severity);
		$this->assertSame([], $resolver->for($app->container()->make(Themes::class)->chain('default'))->values, 'The default theme has no settings.');

		$this->writeTemporaryFile('user/themes/bad/theme.json', '{"name": "Bad", "settings": {"x": {"type": "nope"}}}');

		$this->expectException(ThemeException::class);
		$this->boot()->container()->make(SettingsResolver::class)->for($this->app?->container()->make(Themes::class)->chain('bad') ?? throw new LogicException());
	}

	public function testASettingReachesTemplates(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => 'posts']], 'home' => 'post']);\n");
		$this->writeTemporaryFile('user/content/posts/hello.md', "---\ntitle: Hello\n---\nThe excerpt text.");
		$this->writeTemporaryFile('user/themes/noted/theme.json', '{"name": "Noted", "settings": {"note": {"type": "text", "default": "Plain note"}}}');
		$this->writeTemporaryFile('user/themes/noted/views/parts/entry-summary.php', "<p><?= e((string) \$template->setting('note')) ?></p>");
		$this->activeTheme('noted');

		$this->assertStringContainsString('Plain note', $this->get('/'));

		$this->writeTemporaryFile('user/data/theme.json', '{"settings": {"note": "Site note"}}');

		// Site data reaches a cached site on publish, which moves the
		// content version on.
		$this->assertStringContainsString('Plain note', $this->get('/'));
		$this->app?->container()->make(ContentVersion::class)->bump();
		$this->app = null;

		$this->assertStringContainsString('Site note', $this->get('/'));
	}

	public function testASiteSettingReachesTemplates(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => 'posts']], 'home' => 'post']);\n");
		$this->writeTemporaryFile('user/content/posts/hello.md', "---\ntitle: Hello\n---\nThe excerpt text.");
		$this->writeTemporaryFile('user/data/fields/brand.yaml', "targets: [settings:general]\nfields:\n  tagline:\n    default: Plain tagline\n");
		$this->writeTemporaryFile('user/themes/noted/theme.json', '{"name": "Noted"}');
		$this->writeTemporaryFile('user/themes/noted/views/parts/entry-summary.php', "<p><?= e((string) \$template->site('tagline')) ?>|<?= e((string) \$template->site('missing', 'none')) ?></p>");
		$this->activeTheme('noted');

		$this->assertStringContainsString('Plain tagline|none', $this->get('/'), 'A field\'s default, then the fallback.');

		// The admin's save moves the content version on, so cached pages go.
		$this->writeTemporaryFile('user/data/settings.json', '{"site": {"tagline": "Saved tagline"}}');
		$this->app?->container()->make(ContentVersion::class)->bump();
		$this->app = null;

		$this->assertStringContainsString('Saved tagline|none', $this->get('/'));
	}

	public function testAnEntryStylesheetReachesThePage(): void
	{
		$this->writeTemporaryFile('user/themes/tinted/theme.json', '{"name": "Tinted"}');
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\nstylesheet: extra.css\n---\nHi");
		$this->writeTemporaryFile('user/themes/tinted/extra.css', '');
		$this->activeTheme('tinted');

		$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="http://localhost/themes/tinted/extra.css\?v=[0-9a-f]{8}">#', $this->get('/'));
	}

	public function testPublicManifestsBuildFromResources(): void
	{
		$this->writeTemporaryFile('user/themes/vite/theme.json', '{"name": "Vite", "styles": ["resources/scss/style.scss"], "scripts": ["resources/js/app.js"]}');
		$this->writeTemporaryFile('user/themes/vite/public/.vite/manifest.json', json_encode([
			'resources/js/app.js'         => ['file' => 'assets/app-4f2a.js'],
			'resources/scss/style.scss'   => ['file' => 'assets/style-77aa.css'],
			'resources/fonts/karla.woff2' => ['file' => 'assets/karla-1b2c.woff2']
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/themes/vite/resources/js/app.js', 'source');
		$this->writeTemporaryFile('user/themes/vite/public/assets/app-4f2a.js', 'built');
		$this->writeTemporaryFile('user/themes/vite/public/img/icon.png', 'png');
		$this->activeTheme('vite');

		$html   = $this->get('/');
		$assets = new ThemeAssets($this->app?->container()->make(Themes::class)->chain('vite') ?? throw new LogicException());

		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/vite/public/assets/style-77aa.css">', $html);
		$this->assertStringContainsString('<script src="http://localhost/themes/vite/public/assets/app-4f2a.js?v=' . hash('crc32b', 'built') . '" type="module"></script>', $html);
		$this->assertSame('/themes/vite/public/assets/karla-1b2c.woff2', $assets->url('resources/fonts/karla.woff2'));
		$this->assertSame('/themes/vite/public/img/icon.png?v=' . hash('crc32b', 'png'), $assets->url('public/img/icon.png'));
		$this->assertFalse(ThemeChain::isServable('resources/js/app.js'));
		$this->assertTrue(ThemeChain::isServable('public/assets/app-4f2a.js'));
	}

	public function testBuildManifestsResolveAssets(): void
	{
		$this->writeTemporaryFile('user/themes/built/theme.json', '{"name": "Built", "styles": ["src/main.css"], "scripts": ["src/main.js"]}');
		$this->writeTemporaryFile('user/themes/built/dist/.vite/manifest.json', json_encode([
			'src/main.js'  => ['file' => 'assets/main-4f2a.js', 'css' => ['assets/main-9c1b.css', '../escape.css']],
			'src/main.css' => ['file' => 'assets/style-77aa.css']
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/themes/built/logo.svg', '<svg/>');
		$this->activeTheme('built');

		$html   = $this->get('/');
		$assets = new ThemeAssets($this->app?->container()->make(Themes::class)->chain('built') ?? throw new LogicException());

		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/built/dist/assets/main-9c1b.css">', $html);
		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/built/dist/assets/style-77aa.css">', $html);
		$this->assertStringContainsString('<script src="http://localhost/themes/built/dist/assets/main-4f2a.js" type="module"></script>', $html);
		$this->assertStringNotContainsString('escape.css', $html);
		$this->assertTrue($assets->isBuilt('src/main.js'));
		$this->assertFalse($assets->isBuilt('logo.svg'));
		$this->assertStringStartsWith('/themes/built/logo.svg?v=', (string) $assets->url('logo.svg'));
		$this->assertStringStartsWith('/themes/default/style.css?v=', (string) $assets->url('style.css'));
		$this->assertSame([], $assets->css('logo.svg'));

		$this->writeTemporaryFile('user/themes/built/dist/.vite/manifest.json', '{broken');

		$this->expectException(ThemeException::class);
		new ThemeAssets($assets->chain)->url('src/main.js');
	}
}
