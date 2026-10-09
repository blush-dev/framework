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
use Blush\Tests\SavedSettings;
use Blush\Theme\SettingsResolver;
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
use Blush\Tests\WritesContentConfig;

#[CoversClass(ThemeDiscovery::class)]
#[CoversClass(ThemeCache::class)]
#[CoversClass(ThemeManifest::class)]
#[CoversClass(ThemeSource::class)]
#[CoversClass(ThemeAssets::class)]
#[CoversClass(SettingsResolver::class)]
#[CoversClass(ThemeSettings::class)]
#[CoversClass(ComposerPackages::class)]
#[CoversClass(Bootstrap::class)]
#[CoversClass(LocalAutoloader::class)]
final class ThemeSystemTest extends TestCase
{
	use BootsScratchSite;
	use SavedSettings;
	use WritesContentConfig;

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

	private function activeTheme(string $name): void
	{
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: '{$name}');\n");
	}

	private function get(string $uri): string
	{
		return (string) ($this->app ?? $this->boot())->container()->make(Kernel::class)->handle(Request::create($uri))->getBody();
	}

	private function composerTheme(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', json_encode(['packages' => [
			['name' => 'acme/nova-theme', 'type' => 'blush-theme', 'install-path' => '../acme/nova-theme'],
			['name' => 'acme/renamed', 'type' => 'blush-theme', 'install-path' => '../acme/renamed'],
			['name' => 'acme/local-wins', 'type' => 'blush-theme', 'install-path' => '../acme/local-wins'],
			['name' => 'acme/library', 'type' => 'library']
		]], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('vendor/acme/nova-theme/theme.json', '{"label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('vendor/acme/renamed/theme.json', '{"name":"acme/dusk","label":"Dusk","namespace":"dusk"}');
		$this->writeTemporaryFile('vendor/acme/local-wins/theme.json', '{"name": "acme/local-wins", "label": "From Composer", "namespace": "local-wins"}');
		$this->writeTemporaryFile('extensions/acme/local-wins/theme.json', '{"name": "acme/local-wins", "label": "From extensions", "namespace": "local-wins"}');
		$this->writeTemporaryFile('extensions/blush/default/theme.json', '{"name": "blush/default", "label": "Not the default", "namespace": "not-default"}');
		$this->writeTemporaryFile('extensions/acme/broken/theme.json', '{"name": 5}');
	}

	public function testDiscoversLocalComposerAndFrameworkThemes(): void
	{
		$this->composerTheme();

		$themes = new ThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover();
		$all    = $themes->all();

		$this->assertSame(['blush/default', 'acme/local-wins', 'acme/nova-theme'], array_keys($all));
		$this->assertSame(ThemeSource::Framework, $all['blush/default']->source);
		$this->assertSame('Default', $all['blush/default']->label);
		$this->assertSame(ThemeSource::Composer, $all['acme/nova-theme']->source);
		$this->assertSame('Nova', $all['acme/nova-theme']->label, 'A Composer theme takes its package\'s name.');
		$this->assertSame('From extensions', $all['acme/local-wins']->label);
		$this->assertSame(ThemeSource::Local, $all['acme/local-wins']->source);
		$this->assertSame(['acme/renamed', 'extensions/acme/broken', 'extensions/blush/default'], array_keys($themes->invalid()));
		$this->assertStringContainsString('package\'s', $themes->invalid()['acme/renamed']);
		$this->assertStringContainsString('"name"', $themes->invalid()['extensions/acme/broken']);
		$this->assertStringContainsString('framework default theme', $themes->invalid()['extensions/blush/default']);
		$this->assertFalse($themes->has('acme/broken'));
		$this->assertNull($themes->find('acme/broken'));
	}

	public function testAComposerThemeNeedsNoManifestFile(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', json_encode(['packages' => [
			['name' => 'acme/plain', 'type' => 'blush-theme', 'install-path' => '../acme/plain']
		]], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('vendor/acme/plain/composer.json', '{"name": "acme/plain", "type": "blush-theme", "extra": {"blush": {"label": "Plain", "namespace": "plain"}}}');

		$theme = new ThemeDiscovery(Paths::fromRoot($this->temporaryDirectory()))->discover()->find('acme/plain');

		$this->assertSame(['Plain', 'plain', ThemeSource::Composer], [$theme?->label, $theme?->namespace, $theme?->source], 'Its type says it\'s a theme, and extra.blush the rest (D-432).');
	}

	public function testThemesCompileToACacheUsedOutsideDevelopment(): void
	{
		$this->composerTheme();

		$bootstrap = new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production']);
		$bootstrap->compile();

		$cache  = new ThemeCache(new PhpArrayFile($bootstrap->compiledPath(CompiledCache::Themes)));
		$themes = $cache->read();

		$this->assertNotNull($themes);
		$this->assertSame(['blush/default', 'acme/local-wins', 'acme/nova-theme'], array_keys($themes->all()));
		$this->assertArrayHasKey('extensions/acme/broken', $themes->invalid());

		$this->writeTemporaryFile('extensions/acme/later/theme.json', '{"name": "acme/later", "label": "Later", "namespace": "later"}');

		$this->assertFalse($this->boot()->container()->make(Themes::class)->has('acme/later'));

		$this->app?->container()->make(LocalAutoloader::class)->unregister();
		$bootstrap->clearCompiled(CompiledCache::Themes);

		$this->assertTrue($this->boot()->container()->make(Themes::class)->has('acme/later'));
	}

	public function testThemeProvidersRegisterAndAutoload(): void
	{
		$this->writeTemporaryFile('extensions/acme/pro/theme.json', json_encode([
			'name'      => 'acme/pro',
			'label'     => 'Pro',
			'namespace' => 'pro',
			'parent'    => 'acme/base',
			'provider'  => 'ProTheme\\ProThemeProvider',
			'autoload'  => ['psr-4' => ['ProTheme\\' => 'src/']]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/acme/pro/src/ProThemeProvider.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			namespace ProTheme;

			use Blush\Core\ServiceProvider;
			use Blush\View\ContextProviders;

			final class ProThemeProvider extends ServiceProvider
			{
				public function boot(): void
				{
					$this->container->make(ContextProviders::class)->add('partials/footer', FooterNote::class);
				}
			}
			PHP);
		$this->writeTemporaryFile('extensions/acme/pro/src/FooterNote.php', <<<'PHP'
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
		$this->writeTemporaryFile('extensions/acme/pro/views/partials/footer.php', '<footer><?= e($note ?? "no note") ?></footer>');
		$this->writeTemporaryFile('extensions/acme/base/theme.json', '{"name": "acme/base", "label": "Base", "namespace": "base", "parent": "blush/default", "provider": "Missing\\\\Provider"}');
		$this->activeTheme('acme/pro');

		$this->assertStringContainsString('<footer>Pro note</footer>', $this->get('/'));
		$this->assertSame(['Missing\\Provider', 'ProTheme\\ProThemeProvider'], $this->app?->container()->make(ThemeResolver::class)->active()->providers());
	}

	public function testSettingsResolveThroughTheChain(): void
	{
		$this->writeTemporaryFile('extensions/acme/kid/theme.json', json_encode([
			'name'      => 'acme/kid',
			'label'     => 'Kid',
			'namespace' => 'kid',
			'settings'  => [
				'layout'   => ['type' => 'enum', 'options' => ['grid', 'list'], 'default' => 'list'],
				'excerpts' => ['type' => 'bool', 'default' => false],
				'columns'  => ['type' => 'number', 'default' => 2]
			]
		], JSON_THROW_ON_ERROR));
		// The active theme's own group of settings (D-673).
		$this->writeSettings('{"acme/kid": {"layout": "grid", "columns": "many", "unknown": 1}}');

		$app      = $this->boot();
		$resolver = $app->container()->make(SettingsResolver::class);
		$kid      = $resolver->for($app->container()->make(Themes::class)->chain('acme/kid'));

		$this->assertSame('grid', $kid->get('layout'));
		$this->assertFalse($kid->get('excerpts'));
		$this->assertSame(2, $kid->get('columns'));
		$this->assertNull($kid->get('unknown'));
		$this->assertSame('fallback', $kid->get('missing', 'fallback'));
		$this->assertSame(['columns'], array_map(static fn ($violation): string => $violation->field, $kid->violations));
		$this->assertSame(Severity::Error, $kid->violations[0]->severity);
		$this->assertSame([], $resolver->for($app->container()->make(Themes::class)->chain('blush/default'))->values, 'The default theme has no settings.');

		$this->writeTemporaryFile('extensions/acme/bad/theme.json', '{"name": "acme/bad", "label": "Bad", "namespace": "bad", "settings": {"x": {"type": "nope"}}}');

		$this->expectException(ThemeException::class);
		$this->boot()->container()->make(SettingsResolver::class)->for($this->app?->container()->make(Themes::class)->chain('acme/bad') ?? throw new LogicException());
	}

	public function testASettingReachesTemplates(): void
	{
		$this->contentConfig(['types' => ['post' => ['path' => 'posts']], 'home' => 'post']);
		$this->writeTemporaryFile('user/content/posts/hello.md', "---\nid: b12471fd-d907-a48f-cf04-4812e1eea8d8\ntitle: Hello\n---\nThe excerpt text.");
		$this->writeTemporaryFile('extensions/acme/noted/theme.json', '{"name": "acme/noted", "label": "Noted", "namespace": "noted", "settings": {"note": {"type": "text", "default": "Plain note"}}}');
		$this->writeTemporaryFile('extensions/acme/noted/views/partials/entry-summary.php', "<p><?= e((string) \$template->setting('note')) ?></p>");
		$this->activeTheme('acme/noted');

		$this->assertStringContainsString('Plain note', $this->get('/'));

		$this->writeSettings('{"acme/noted": {"note": "Site note"}}');

		// Site data reaches a cached site on publish, which moves the
		// content version on.
		$this->assertStringContainsString('Plain note', $this->get('/'));
		$this->app?->container()->make(ContentVersion::class)->bump();
		$this->app = null;

		$this->assertStringContainsString('Site note', $this->get('/'));
	}

	public function testASiteSettingReachesTemplates(): void
	{
		$this->contentConfig(['types' => ['post' => ['path' => 'posts']], 'home' => 'post']);
		$this->writeTemporaryFile('user/content/posts/hello.md', "---\nid: b12471fd-d907-a48f-cf04-4812e1eea8d8\ntitle: Hello\n---\nThe excerpt text.");
		$this->writeTemporaryFile('user/data/fields/brand.json', '{"targets":["settings:general"],"fields":{"tagline":{"default":"Plain tagline"}}}');
		$this->writeTemporaryFile('extensions/acme/noted/theme.json', '{"name": "acme/noted", "label": "Noted", "namespace": "noted"}');
		$this->writeTemporaryFile('extensions/acme/noted/views/partials/entry-summary.php', "<p><?= e((string) \$template->site('tagline')) ?>|<?= e((string) \$template->site('missing', 'none')) ?></p>");
		$this->activeTheme('acme/noted');

		$this->assertStringContainsString('Plain tagline|none', $this->get('/'), 'A field\'s default, then the fallback.');

		// The admin's save moves the content version on, so cached pages go.
		$this->writeSettings('{"site": {"tagline": "Saved tagline"}}');
		$this->app?->container()->make(ContentVersion::class)->bump();
		$this->app = null;

		$this->assertStringContainsString('Saved tagline|none', $this->get('/'));
	}

	public function testAnEntryStylesheetReachesThePage(): void
	{
		$this->writeTemporaryFile('extensions/acme/tinted/theme.json', '{"name": "acme/tinted", "label": "Tinted", "namespace": "tinted"}');
		$this->writeTemporaryFile('user/content/index.md', "---\nid: d680e8a8-54a7-cbad-6d49-0c445cba2eba\ntitle: Home\nstylesheet: extra.css\n---\nHi");
		$this->writeTemporaryFile('extensions/acme/tinted/extra.css', '');
		$this->activeTheme('acme/tinted');

		$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="http://localhost/themes/acme/tinted/extra.css\?v=[0-9a-f]{8}">#', $this->get('/'));
	}

	public function testPublicManifestsBuildFromResources(): void
	{
		$this->writeTemporaryFile('extensions/acme/vite/theme.json', '{"name": "acme/vite", "label": "Vite", "namespace": "vite", "styles": ["resources/scss/style.scss"], "scripts": ["resources/js/app.js"]}');
		$this->writeTemporaryFile('extensions/acme/vite/public/.vite/manifest.json', json_encode([
			'resources/js/app.js'         => ['file' => 'assets/app-4f2a.js'],
			'resources/scss/style.scss'   => ['file' => 'assets/style-77aa.css'],
			'resources/fonts/karla.woff2' => ['file' => 'assets/karla-1b2c.woff2']
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/acme/vite/resources/js/app.js', 'source');
		$this->writeTemporaryFile('extensions/acme/vite/public/assets/app-4f2a.js', 'built');
		$this->writeTemporaryFile('extensions/acme/vite/public/img/icon.png', 'png');
		$this->activeTheme('acme/vite');

		$html   = $this->get('/');
		$assets = new ThemeAssets($this->app?->container()->make(Themes::class)->chain('acme/vite') ?? throw new LogicException());

		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/acme/vite/public/assets/style-77aa.css">', $html);
		$this->assertStringContainsString('<script src="http://localhost/themes/acme/vite/public/assets/app-4f2a.js?v=' . hash('crc32b', 'built') . '" type="module"></script>', $html);
		$this->assertSame('/themes/acme/vite/public/assets/karla-1b2c.woff2', $assets->url('resources/fonts/karla.woff2'));
		$this->assertSame('/themes/acme/vite/public/img/icon.png?v=' . hash('crc32b', 'png'), $assets->url('public/img/icon.png'));
		$this->assertFalse(ThemeChain::isServable('resources/js/app.js'));
		$this->assertTrue(ThemeChain::isServable('public/assets/app-4f2a.js'));
	}

	public function testBuildManifestsResolveAssets(): void
	{
		$this->writeTemporaryFile('extensions/acme/built/theme.json', '{"name": "acme/built", "label": "Built", "namespace": "built", "styles": ["src/main.css"], "scripts": ["src/main.js"]}');
		$this->writeTemporaryFile('extensions/acme/built/dist/.vite/manifest.json', json_encode([
			'src/main.js'  => ['file' => 'assets/main-4f2a.js', 'css' => ['assets/main-9c1b.css', '../escape.css']],
			'src/main.css' => ['file' => 'assets/style-77aa.css']
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('extensions/acme/built/logo.svg', '<svg/>');
		$this->activeTheme('acme/built');

		$html   = $this->get('/');
		$assets = new ThemeAssets($this->app?->container()->make(Themes::class)->chain('acme/built') ?? throw new LogicException());

		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/acme/built/dist/assets/main-9c1b.css">', $html);
		$this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/themes/acme/built/dist/assets/style-77aa.css">', $html);
		$this->assertStringContainsString('<script src="http://localhost/themes/acme/built/dist/assets/main-4f2a.js" type="module"></script>', $html);
		$this->assertStringNotContainsString('escape.css', $html);
		$this->assertTrue($assets->isBuilt('src/main.js'));
		$this->assertFalse($assets->isBuilt('logo.svg'));
		$this->assertStringStartsWith('/themes/acme/built/logo.svg?v=', (string) $assets->url('logo.svg'));
		$this->assertNull($assets->url('style.css'), 'The default theme isn\'t in the chain (D-632).');
		$this->assertSame([], $assets->css('logo.svg'));

		$this->writeTemporaryFile('extensions/acme/built/dist/.vite/manifest.json', '{broken');

		$this->expectException(ThemeException::class);
		new ThemeAssets($assets->chain)->url('src/main.js');
	}
}
