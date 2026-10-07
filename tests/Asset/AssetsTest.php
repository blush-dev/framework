<?php

/**
 * Asset tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Asset\Asset;
use Blush\Asset\AssetCollector;
use Blush\Asset\AssetController;
use Blush\Asset\AssetException;
use Blush\Asset\AssetRegistrar;
use Blush\Asset\AssetRegistry;
use Blush\Asset\AssetRoutes;
use Blush\Asset\Assets;
use Blush\Asset\AssetUrls;
use Blush\Asset\Script;
use Blush\Asset\Style;
use Blush\Cache\RenderedBodies;
use Blush\Core\Application;
use Blush\Directive\Media\Audio;
use Blush\Directive\Media\Video;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Plugin\Plugins;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Theme\ThemeAssetProvider;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\View\Events\PageRendering;
use Blush\View\Head;
use Blush\View\Template;
use Blush\View\Views;

#[CoversClass(Asset::class)]
#[CoversClass(AssetCollector::class)]
#[CoversClass(AssetController::class)]
#[CoversClass(AssetRegistrar::class)]
#[CoversClass(AssetRegistry::class)]
#[CoversClass(AssetRoutes::class)]
#[CoversClass(Assets::class)]
#[CoversClass(AssetUrls::class)]
#[CoversClass(Head::class)]
#[CoversClass(Views::class)]
#[CoversClass(Template::class)]
#[CoversClass(PageRendering::class)]
#[CoversClass(RenderedBodies::class)]
#[CoversClass(Audio::class)]
#[CoversClass(Video::class)]
#[CoversClass(ThemeAssetProvider::class)]
#[CoversClass(ThemeChain::class)]
#[CoversClass(ThemeManifest::class)]
final class AssetsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('_posts/2009-01-01.episode.md', "title: Episode\npublished: 2009-01-01 12:00:00", "Listen:\n\n::audio[Episode 1]{src=https://example.com/episode.mp3}\n");
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(pages: false);\n");
		$this->writeTemporaryFile('extensions/acme/stats/plugin.json', '{"name": "acme/stats", "label": "Stats", "namespace": "stats"}');
		$this->writeTemporaryFile('extensions/acme/stats/js/stats.js', 'console.log("stats");');
		$this->writeTemporaryFile('extensions/acme/stats/src/secret.js', 'private');
		$this->writeTemporaryFile('extensions/acme/off/plugin.json', '{"name": "acme/off", "label": "Off", "namespace": "off"}');
		$this->writeTemporaryFile('extensions/acme/off/off.js', 'off');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/stats']);\n");

		$this->app = $this->site();
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	private function html(string $uri): string
	{
		return (string) $this->get($uri)->getBody();
	}

	private function registry(): AssetRegistry
	{
		return $this->app->container()->make(AssetRegistry::class);
	}

	public function testOrdersAssetsAfterWhatTheyRequireEachOnce(): void
	{
		$registry = new AssetRegistry();

		$registry->register(new Asset('acme/a', requires: ['acme/b', 'acme/missing']));
		$registry->register(new Asset('acme/b', requires: ['acme/c']));
		$registry->register(new Asset('acme/c', requires: ['acme/a']));
		$registry->registerIf(new Asset('acme/b'));

		$this->assertSame(['acme/c', 'acme/b', 'acme/a'], array_map(static fn (Asset $asset): string => $asset->handle, $registry->ordered(['acme/a', 'acme/b'])), 'A loop is broken where it closes.');
		$this->assertSame(['acme/c'], $registry->get('acme/b')?->requires, 'registerIf() never replaces.');
	}

	public function testHandlesAreVendorNames(): void
	{
		$this->expectException(AssetException::class);

		new Asset('gallery');
	}

	public function testTheHeadPrintsAssetsAndFooterScripts(): void
	{
		$registry = new AssetRegistry();
		$head     = new Head('Site', assets: new Assets($registry, new AssetUrls(new Themes([]), new Plugins())));

		$registry->register(new Asset('acme/base', styles: [new Style('/base.css')]));
		$registry->register(new Asset('acme/app', [new Style('/app.css', attributes: ['media' => 'screen'])], [
			new Script('/head.js', attributes: ['async' => true, 'defer' => false]),
			new Script('/foot.js', footer: true)
		], ['acme/base']));

		$head->enqueue('acme/app', 'acme/unknown')->inlineScript('late', 'go();', footer: true);

		$html = $head->render();

		$this->assertMatchesRegularExpression('#base\.css.*app\.css" media="screen".*head\.js" async>#s', $html);
		$this->assertStringNotContainsString('foot.js', $html);
		$this->assertSame("\t<script id=\"late\">\n\t\tgo();\n\t</script>\n\t<script src=\"/foot.js\" defer></script>", $head->foot(), 'Assets\' files are added when the head prints.');
		$this->assertSame(['acme/app', 'acme/unknown'], $head->enqueued());
		$this->assertFalse($head->dequeue('acme/app')->isEnqueued('acme/app'));
	}

	public function testAHeldHeadIsFilledOnceThePageHasRendered(): void
	{
		$head = new Head('Site');

		$this->assertTrue($head->hold());
		$this->assertFalse($head->hold(), 'Only the outer render fills it.');

		$page = "<html><head>{$head}</head><body><p>Hi</p></body></html>";

		$head->style('/late.css')->script('/late.js', footer: true);

		$html = $head->fill($page);

		$this->assertStringContainsString('<link rel="stylesheet" href="/late.css"></head>', $html);
		$this->assertStringEndsWith("<p>Hi</p>\t<script src=\"/late.js\" defer></script>\n</body></html>", $html);
		$this->assertStringNotContainsString('<!--blush-head-', $html);
		$this->assertStringContainsString('<title>Site</title>', (string) $head, 'Filled, it prints as itself again.');
	}

	public function testAudioLoadsThePlayerFromACachedBodyToo(): void
	{
		foreach (['first render', 'cached body'] as $message) {
			$html = $this->html('/archives/episode');

			$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="http://localhost/blush/css/player\.css\?v=[0-9a-f]{8}">#', $html, $message);
			$this->assertMatchesRegularExpression('#<script src="http://localhost/blush/js/player\.js\?v=[0-9a-f]{8}" type="module"></script>#', $html, $message);
			$this->assertStringContainsString('<blush-audio-player label-play="Play" label-pause="Pause" label-seek="Seek">', $html, $message);
		}

		$this->assertStringNotContainsString('player.js', $this->html('/about'), 'Only pages that play something load the player.');
	}

	public function testABodyAsksForItsAssetsOnlyWhenItsHtmlIs(): void
	{
		foreach (['development', 'production'] as $environment) {
			$app       = $this->site($environment);
			$collector = $app->container()->make(AssetCollector::class);
			$entry     = $this->repository($app)->named('post', 'episode');

			$this->assertSame([], $collector->collect(static fn (): string => (string) $entry?->excerpt())[1], $environment);
			$this->assertSame([AssetRegistrar::PLAYER], $collector->collect(static fn (): string => (string) $entry?->body())[1], $environment);
		}
	}

	public function testAThemeCanBlankAnAsset(): void
	{
		$this->registry()->register(new Asset(AssetRegistrar::PLAYER));

		$this->assertStringNotContainsString('player.js', $this->html('/archives/episode'));
	}

	public function testListenersLoadAssetsOnThePagesThatNeedThem(): void
	{
		$this->registry()->register(new Asset('acme/stats', scripts: [new Script('js/stats.js', 'acme/stats', footer: true)]));
		$this->registry()->register(new Asset('acme/off', scripts: [new Script('off.js', 'acme/off')]));

		$this->app->container()->make(ListenerRegistry::class)->listen(PageRendering::class, static function (PageRendering $event): void {
			if ($event->entry?->title === 'spring' || $event->isError()) {
				$event->enqueue('acme/stats', 'acme/off');
				$event->addClass($event->isError() ? 'is-tracked-error' : 'is-tracked');
			}
		});

		$html = $this->html('/archives/spring');

		$this->assertMatchesRegularExpression('#<script src="http://localhost/extensions/acme/stats/js/stats\.js\?v=[0-9a-f]{8}" defer></script>\n</body>#', $html);
		$this->assertStringContainsString('is-tracked', $html);
		$this->assertStringNotContainsString('off.js', $html, 'A plugin that\'s off serves nothing.');
		$this->assertStringNotContainsString('stats.js', $this->html('/about'));
		$this->assertStringContainsString('is-tracked-error', $this->html('/nowhere'));
	}

	public function testServesCoresAndPluginsFiles(): void
	{
		$core = $this->get('/blush/js/player.js');

		$this->assertSame(200, $core->getStatusCode());
		$this->assertSame('text/javascript', $core->getHeaderLine('Content-Type'));
		$this->assertSame('no-cache', $core->getHeaderLine('Cache-Control'));
		$this->assertSame('public, max-age=31536000, immutable', $this->get('/blush/css/player.css?v=1')->getHeaderLine('Cache-Control'));
		$this->assertSame('console.log("stats");', (string) $this->get('/extensions/acme/stats/js/stats.js')->getBody());

		foreach (['/extensions/acme/stats/src/secret.js', '/extensions/acme/stats/plugin.json', '/extensions/acme/off/off.js', '/blush/js/nothing.js'] as $uri) {
			$this->assertSame(404, $this->get($uri)->getStatusCode(), $uri);
		}
	}

	/**
	 * Makes `acme/nova` (a child of the default theme) the active theme,
	 * with a manifest's `assets`, and, when given, a provider's `boot()`.
	 */
	private function novaTheme(string $boot = ''): void
	{
		$manifest = [
			'name'   => 'acme/nova',
			'parent' => 'blush/default',
			'assets' => [
				'acme/lightbox' => [
					'styles'   => ['css/lightbox.css'],
					'scripts'  => [['path' => 'js/lightbox.js', 'footer' => true, 'attributes' => ['type' => 'module']], 'https://cdn.example.com/zoom.js'],
					'requires' => ['acme/base']
				],
				'acme/base'     => ['styles' => [['path' => 'css/base.css', 'attributes' => ['media' => 'screen']]]],
				'blush/player'  => []
			]
		];

		if ($boot !== '') {
			$manifest += ['provider' => 'Nova\\Provider', 'autoload' => ['psr-4' => ['Nova\\' => 'src/']]];

			$this->writeTemporaryFile('extensions/acme/nova/src/Provider.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Nova;\n\nuse Blush\\Asset\\Asset;\nuse Blush\\Asset\\AssetRegistry;\nuse Blush\\Asset\\Style;\n\nfinal class Provider extends \\Blush\\Core\\ServiceProvider\n{\n\tpublic function boot(): void\n\t{\n\t\t{$boot}\n\t}\n}\n");
		}

		$this->writeTemporaryFile('extensions/acme/nova/theme.json', (string) json_encode($manifest));
		$this->writeTemporaryFile('extensions/acme/nova/css/lightbox.css', '.lightbox {}');
		$this->writeTemporaryFile('extensions/acme/nova/css/base.css', '.base {}');
		$this->writeTemporaryFile('extensions/acme/nova/css/provider.css', '.provider {}');
		$this->writeTemporaryFile('extensions/acme/nova/js/lightbox.js', 'lightbox();');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");

		$this->app = $this->site();

		$this->app->container()->make(ListenerRegistry::class)->listen(PageRendering::class, static function (PageRendering $event): void {
			$event->enqueue('acme/lightbox');
		});
	}

	public function testThemesRegisterAssetsInTheirManifests(): void
	{
		$this->novaTheme();

		$html = $this->html('/archives/episode');

		$this->assertMatchesRegularExpression('#href="http://localhost/themes/acme/nova/css/base\.css\?v=[0-9a-f]{8}" media="screen">\n\t<link rel="stylesheet" href="http://localhost/themes/acme/nova/css/lightbox\.css\?v=[0-9a-f]{8}">#', $html, 'What it requires loads first.');
		$this->assertStringContainsString('<script src="https://cdn.example.com/zoom.js" defer></script>', $html, 'A full URL is used as given.');
		$this->assertMatchesRegularExpression('#<script src="http://localhost/themes/acme/nova/js/lightbox\.js\?v=[0-9a-f]{8}" type="module"></script>\n</body>#', $html);
		$this->assertStringNotContainsString('player.js', $html, 'Its blank blush/player replaces core\'s.');
	}

	public function testAThemesProviderWinsOverItsManifest(): void
	{
		$this->novaTheme("\$this->container->make(AssetRegistry::class)->register(new Asset('acme/lightbox', [new Style('css/provider.css', 'acme/nova')]));");

		$html = $this->html('/about');

		$this->assertStringContainsString('/themes/acme/nova/css/provider.css?v=', $html);
		$this->assertStringNotContainsString('lightbox', $html);
	}

	public function testAThemesAssetsMustBeWellFormed(): void
	{
		$cases = [
			'not a map'        => ['acme/a'],
			'a bad handle'     => ['gallery' => []],
			'an unknown key'   => ['acme/a' => ['style' => ['a.css']]],
			'a path outside'   => ['acme/a' => ['styles' => ['../a.css']]],
			'a style\'s footer' => ['acme/a' => ['styles' => [['path' => 'a.css', 'footer' => true]]]],
			'bad attributes'   => ['acme/a' => ['scripts' => [['path' => 'a.js', 'attributes' => ['defer' => 1]]]]],
			'bad requires'     => ['acme/a' => ['requires' => ['b']]]
		];

		foreach ($cases as $case => $assets) {
			try {
				ThemeManifest::fromArray('/themes/a', ['name' => 'acme/a', 'assets' => $assets]);
				$this->fail("Accepted {$case}.");
			} catch (ThemeException $error) {
				$this->assertStringContainsString('"acme/a" theme\'s', $error->getMessage(), $case);
			}
		}
	}
}
