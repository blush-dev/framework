<?php

/**
 * Theme tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Config\InvalidConfig;
use Blush\Core\Application;
use Blush\Core\Framework;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeAssetController;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\ThemeResolver;
use Blush\Theme\ThemeRoutes;
use Blush\Theme\Themes;
use Blush\Theme\ThemeServiceProvider;

#[CoversClass(Themes::class)]
#[CoversClass(ThemeManifest::class)]
#[CoversClass(ThemeChain::class)]
#[CoversClass(ThemeConfig::class)]
#[CoversClass(ThemeResolver::class)]
#[CoversClass(ThemeAssetController::class)]
#[CoversClass(ThemeAssets::class)]
#[CoversClass(ThemeRoutes::class)]
#[CoversClass(ThemeServiceProvider::class)]
#[CoversClass(ThemeException::class)]
final class ThemesTest extends TestCase
{
	use BootsScratchSite;

	private function boot(string $environment = 'production'): Application
	{
		$app = $this->scratchApplication(['APP_ENV' => $environment]);
		$app->boot();

		return $app;
	}

	private function themes(): Themes
	{
		return $this->boot()->container()->make(Themes::class);
	}

	private function get(Application $app, string $uri): ResponseInterface
	{
		return $app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	private function writeThemes(): void
	{
		$this->writeTemporaryFile('user/themes/parent/theme.json', '{"name": "Parent", "version": "1.0.0", "styles": ["css/parent.css"]}');
		$this->writeTemporaryFile('user/themes/parent/css/parent.css', 'body { color: red; }');
		$this->writeTemporaryFile('user/themes/child/theme.yaml', "name: Child\nparent: parent\nstyles: [style.css, css/parent.css]\nsettings: {dark: {type: bool, default: true}}\n");
		$this->writeTemporaryFile('user/themes/child/style.css', 'body {}');
	}

	public function testTheDefaultThemeIsAlwaysInstalled(): void
	{
		$themes  = $this->themes();
		$default = $themes->find(Themes::DEFAULT);

		$this->assertNotNull($default);
		$this->assertSame('Default', $default->name);
		$this->assertSame(Framework::path('resources/themes/default'), $default->path);
		$this->assertSame(['default'], $themes->chain('default')->slugs());
		$this->assertSame(['default'], array_keys($themes->all()));
	}

	public function testReadsManifestsAndChains(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('user/themes/not-a-theme/readme.txt', '');

		$themes = $this->themes();
		$child  = $themes->find('child');
		$chain  = $themes->chain('child');

		$this->assertNotNull($child);
		$this->assertSame('parent', $child->parent);
		$this->assertSame(['style.css', 'css/parent.css'], $child->styles);
		$this->assertSame(['dark' => ['type' => 'bool', 'default' => true]], $child->settings());
		$this->assertSame(['child', 'parent', 'default'], $chain->slugs());
		$this->assertCount(3, $chain);
		$this->assertSame('child', $chain->active()->slug);
		$this->assertSame(['default', 'child', 'parent'], array_keys($themes->all()));
		$this->assertNull($themes->find('not-a-theme'));
		$this->assertNull($themes->find('../etc'));
		$this->assertSame($this->temporaryDirectory() . '/user/themes/child/views', $chain->viewDirectories()[0]);
		$this->assertSame(Framework::path('resources/themes/default/lang'), $chain->langDirectories()[2]);
	}

	public function testResolvesAssetsThroughTheChain(): void
	{
		$this->writeThemes();

		$chain  = $this->themes()->chain('child');
		$assets = new ThemeAssets($chain);
		$mtime = filemtime($this->temporaryDirectory() . '/user/themes/parent/css/parent.css');

		$this->assertSame("/themes/parent/css/parent.css?v={$mtime}", $assets->url('css/parent.css'));
		$this->assertStringStartsWith('/themes/child/style.css?v=', (string) $assets->url('style.css'));
		$this->assertNull($assets->url('missing.css'));
		$this->assertNull($assets->url('theme.yaml'));
		$this->assertNull($chain->asset('views/layouts/base.php'));
		$this->assertTrue(ThemeChain::isServable('fonts/a.woff2'));
		$this->assertFalse(ThemeChain::isServable('views/x.css'));
		$this->assertFalse(ThemeChain::isServable('../x.css'));
		$this->assertFalse(ThemeChain::isServable('.hidden/x.css'));
		$this->assertFalse(ThemeChain::isServable('style.php'));
	}

	public function testBrokenChainsThrow(): void
	{
		$this->writeTemporaryFile('user/themes/a/theme.json', '{"name": "A", "parent": "b"}');
		$this->writeTemporaryFile('user/themes/b/theme.json', '{"name": "B", "parent": "a"}');
		$this->writeTemporaryFile('user/themes/orphan/theme.json', '{"name": "Orphan", "parent": "gone"}');

		$themes = $this->themes();
		$cases  = [
			'a'       => 'loop back to "a"',
			'orphan'  => 'ancestor "gone" is not installed',
			'missing' => '"missing" theme is not installed'
		];

		foreach ($cases as $slug => $message) {
			try {
				$themes->chain($slug);
				$this->fail("{$slug} should not chain.");
			} catch (ThemeException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}
		}
	}

	public function testInvalidManifestsThrow(): void
	{
		$cases = [
			'{"version": "1"}'                     => 'needs a "name"',
			'{"name": "X", "parent": "Bad Slug"}'  => '"parent" must be a theme slug',
			'{"name": "X", "styles": "style.css"}' => '"styles" must be a list',
			'{"name": "X", "styles": ["../x.css"]}' => 'paths inside the theme',
			'{"name": "X", "version": 2}'          => '"version" must be a string',
			'{broken'                              => 'theme.json is invalid'
		];

		foreach ($cases as $json => $message) {
			$this->writeTemporaryFile('user/themes/bad/theme.json', $json);

			try {
				$this->themes()->find('bad');
				$this->fail("{$json} should not load.");
			} catch (ThemeException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}
		}
	}

	public function testConfigValidatesTheActiveSlug(): void
	{
		$this->assertSame('nova', ThemeConfig::fromArray(['active' => 'nova'])->active);
		$this->assertSame(['active' => 'default'], new ThemeConfig()->toArray());

		$this->expectException(InvalidConfig::class);

		new ThemeConfig('Not Valid');
	}

	public function testTheQueryStringSwitchesThemesInDevelopmentOnly(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'parent');\n");

		$production  = $this->boot()->container()->make(ThemeResolver::class);
		$development = $this->boot('development')->container()->make(ThemeResolver::class);

		$this->assertSame('parent', $production->active()->active()->slug);
		$this->assertSame('parent', $production->forRequest(Request::create('/?theme=child'))->active()->slug);
		$this->assertSame('child', $development->forRequest(Request::create('/?theme=child'))->active()->slug);
		$this->assertSame('parent', $development->forRequest(Request::create('/?theme=nope'))->active()->slug);
		$this->assertSame($development->chain('child'), $development->chain('child'));
	}

	public function testServesThemeAssets(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('user/themes/child/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
		$this->writeTemporaryFile('user/themes/child/views/single.php', '<?php echo "secret";');

		$app = $this->boot();
		$css = $this->get($app, '/themes/parent/css/parent.css');
		$svg = $this->get($app, '/themes/child/icon.svg');

		$this->assertSame(200, $css->getStatusCode());
		$this->assertSame('text/css', $css->getHeaderLine('Content-Type'));
		$this->assertSame('nosniff', $css->getHeaderLine('X-Content-Type-Options'));
		$this->assertSame('body { color: red; }', (string) $css->getBody());
		$this->assertSame('sandbox', $svg->getHeaderLine('Content-Security-Policy'));
		$this->assertSame(200, $this->get($app, '/themes/default/style.css')->getStatusCode());

		foreach (['/themes/child/views/single.php', '/themes/child/theme.yaml', '/themes/child/missing.css', '/themes/nope/style.css', '/themes/child/css/parent.css'] as $uri) {
			$this->assertSame(404, $this->get($app, $uri)->getStatusCode(), $uri);
		}
	}
}
