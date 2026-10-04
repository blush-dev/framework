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
		$this->writeTemporaryFile('extensions/acme/parent/theme.json', '{"name": "acme/parent", "label": "Parent", "namespace": "parent", "version": "1.0.0", "styles": ["css/parent.css"]}');
		$this->writeTemporaryFile('extensions/acme/parent/css/parent.css', 'body { color: red; }');
		$this->writeTemporaryFile('extensions/acme/child/theme.yaml', "name: acme/child\nlabel: Child\nnamespace: child\nparent: acme/parent\nstyles: [style.css, css/parent.css]\nsettings: {dark: {type: bool, default: true}}\n");
		$this->writeTemporaryFile('extensions/acme/child/style.css', 'body {}');
	}

	public function testTheDefaultThemeIsAlwaysInstalled(): void
	{
		$themes  = $this->themes();
		$default = $themes->find(Themes::DEFAULT);

		$this->assertNotNull($default);
		$this->assertSame('Default', $default->label);
		$this->assertSame('default', $default->namespace);
		$this->assertSame(Framework::path('resources/themes/default'), $default->path);
		$this->assertSame(['blush/default'], $themes->chain('blush/default')->names());
		$this->assertSame(['blush/default'], array_keys($themes->all()));
	}

	public function testReadsManifestsAndChains(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('extensions/acme/not-a-theme/readme.txt', '');

		$themes = $this->themes();
		$child  = $themes->find('acme/child');
		$chain  = $themes->chain('acme/child');

		$this->assertNotNull($child);
		$this->assertSame('acme/parent', $child->parent);
		$this->assertSame('Child', $child->label);
		$this->assertSame('child', $child->namespace);
		$this->assertSame(['style.css', 'css/parent.css'], $child->styles);
		$this->assertSame(['dark' => ['type' => 'bool', 'default' => true]], $child->settings());
		$this->assertSame(['acme/child', 'acme/parent', 'blush/default'], $chain->names());
		$this->assertSame(['child', 'parent', 'default'], $chain->namespaces());
		$this->assertCount(3, $chain);
		$this->assertSame('acme/child', $chain->active()->name);
		$this->assertSame(['blush/default', 'acme/child', 'acme/parent'], array_keys($themes->all()));
		$this->assertNull($themes->find('acme/not-a-theme'));
		$this->assertNull($themes->find('../etc'));
		$this->assertSame($this->temporaryDirectory() . '/extensions/acme/child/views', $chain->viewDirectories()[0]);
		$this->assertSame([Framework::path('resources/themes/default/lang')], $chain->langDirectories()['blush/default']);
		$this->assertSame(['acme/child', 'acme/parent', 'blush/default'], array_keys($chain->langDirectories()), 'Each theme\'s domain is its name (D-451).');
		$this->assertSame(['child' => 'acme/child', 'parent' => 'acme/parent', 'default' => 'blush/default'], $chain->namespaceDomains());
	}

	public function testBleedClassesComeFromTheChain(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('extensions/acme/parent/theme.json', '{"name": "acme/parent", "label": "Parent", "namespace": "parent", "bleed": {"wide": "stretch-wide", "full": "stretch-full"}}');
		$this->writeTemporaryFile('extensions/acme/child/theme.yaml', "name: acme/child\nlabel: Child\nnamespace: child\nparent: acme/parent\nbleed: {full: edge}\n");

		$themes = $this->themes();

		$this->assertSame(['wide' => 'bleed-wide', 'full' => 'bleed-full'], $themes->chain('blush/default')->bleedClasses());
		$this->assertSame(['wide' => 'stretch-wide', 'full' => 'stretch-full'], $themes->chain('acme/parent')->bleedClasses());
		$this->assertSame(['wide' => 'stretch-wide', 'full' => 'edge'], $themes->chain('acme/child')->bleedClasses(), 'The nearest theme naming one wins.');
	}

	public function testResolvesAssetsThroughTheChain(): void
	{
		$this->writeThemes();

		$chain  = $this->themes()->chain('acme/child');
		$assets = new ThemeAssets($chain);
		$hash   = hash_file('crc32b', $this->temporaryDirectory() . '/extensions/acme/parent/css/parent.css');

		$this->assertSame("/themes/acme/parent/css/parent.css?v={$hash}", $assets->url('css/parent.css'));
		$this->assertStringStartsWith('/themes/acme/child/style.css?v=', (string) $assets->url('style.css'));
		$this->assertNull($assets->url('missing.css'));
		$this->assertNull($assets->url('theme.yaml'));
		$this->assertNull($chain->asset('views/layouts/base.php'));
		$this->assertTrue(ThemeChain::isServable('fonts/a.woff2'));
		$this->assertFalse(ThemeChain::isServable('views/x.css'));
		$this->assertFalse(ThemeChain::isServable('../x.css'));
		$this->assertFalse(ThemeChain::isServable('.hidden/x.css'));
		$this->assertFalse(ThemeChain::isServable('style.php'));
		$this->assertFalse(ThemeChain::isServable('vite.config.js'));
		$this->assertFalse(ThemeChain::isServable('tools/postcss.config.mjs'));
		$this->assertTrue(ThemeChain::isServable('config.js'));
	}

	public function testBrokenChainsThrow(): void
	{
		$this->writeTemporaryFile('extensions/acme/a/theme.json', '{"name": "acme/a", "label": "A", "namespace": "a", "parent": "acme/b"}');
		$this->writeTemporaryFile('extensions/acme/b/theme.json', '{"name": "acme/b", "label": "B", "namespace": "b", "parent": "acme/a"}');
		$this->writeTemporaryFile('extensions/acme/orphan/theme.json', '{"name": "acme/orphan", "label": "Orphan", "namespace": "orphan", "parent": "acme/gone"}');

		$themes = $this->themes();
		$cases  = [
			'acme/a'       => 'loop back to "acme/a"',
			'acme/orphan'  => 'ancestor "acme/gone" is not installed',
			'acme/missing' => '"acme/missing" theme is not installed'
		];

		foreach ($cases as $name => $message) {
			try {
				$themes->chain($name);
				$this->fail("{$name} should not chain.");
			} catch (ThemeException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}
		}
	}

	public function testInvalidManifestsThrow(): void
	{
		$x     = '"name": "acme/bad", "label": "X", "namespace": "bad"';
		$cases = [
			'{"version": "1"}'                          => 'needs a "name"',
			'{"name": "bad", "label": "X", "namespace": "bad"}' => 'needs a "name"',
			'{"name": "acme/bad", "label": 5, "namespace": "bad"}' => '"label" must be a string',
			'{"name": "acme/bad", "label": "X", "namespace": ""}' => 'needs a "namespace"',
			'{"name": "acme/bad", "label": "X", "namespace": "app"}' => 'needs a "namespace"',
			"{{$x}, \"parent\": \"Bad Slug\"}"         => '"parent" must be a theme\'s name',
			"{{$x}, \"parent\": \"bad\"}"              => '"parent" must be a theme\'s name',
			"{{$x}, \"styles\": \"style.css\"}"        => '"styles" must be a list',
			"{{$x}, \"styles\": [\"../x.css\"]}"       => 'paths inside the theme',
			"{{$x}, \"version\": 2}"                   => '"version" must be a string',
			"{{$x}, \"bleed\": {\"wide\": \"a b\"}}"  => '"bleed" must map',
			"{{$x}, \"bleed\": {\"huge\": \"x\"}}"    => '"bleed" must map',
			"{{$x}, \"authors\": [{\"email\": \"a@b.c\"}]}" => 'needs a "name"',
			"{{$x}, \"authors\": {\"name\": \"Jane\"}}" => '"authors" must be a list',
			'{"name": "blush/default", "label": "X", "namespace": "bad"}' => 'move it to extensions/blush/default',
			'{broken'                                   => 'theme.json is invalid'
		];

		foreach ($cases as $json => $message) {
			$this->writeTemporaryFile('extensions/acme/bad/theme.json', $json);

			$themes = $this->themes();

			$this->assertFalse($themes->has('acme/bad'), "{$json} should not load.");
			$this->assertStringContainsString($message, $themes->invalid()['extensions/acme/bad'] ?? '', $json);
		}
	}

	public function testWithoutALabelOrNamespaceAThemeGoesByItsName(): void
	{
		$this->writeTemporaryFile('extensions/acme/plain/theme.json', '{}');
		$this->writeTemporaryFile('extensions/acme/plain/composer.json', '{"name": "acme/plain"}');

		$theme = $this->themes()->find('acme/plain');

		$this->assertNotNull($theme);
		$this->assertSame('acme/plain', $theme->label);
		$this->assertSame('acme-plain', $theme->namespace, 'And by its name, hyphenated, without a namespace.');
	}

	public function testConfigValidatesTheActiveName(): void
	{
		$this->assertSame('acme/nova', ThemeConfig::fromArray(['active' => 'acme/nova'])->active);
		$this->assertSame(['active' => 'blush/default'], new ThemeConfig()->toArray());

		$this->expectException(InvalidConfig::class);

		new ThemeConfig('nova');
	}

	public function testTheQueryStringSwitchesThemesInDevelopmentOnly(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/parent');\n");

		$production  = $this->boot()->container()->make(ThemeResolver::class);
		$development = $this->boot('development')->container()->make(ThemeResolver::class);

		$this->assertSame('acme/parent', $production->active()->active()->name);
		$this->assertSame('acme/parent', $production->forRequest(Request::create('/?theme=acme/child'))->active()->name);
		$this->assertSame('acme/child', $development->forRequest(Request::create('/?theme=acme/child'))->active()->name);
		$this->assertSame('acme/parent', $development->forRequest(Request::create('/?theme=nope'))->active()->name);
		$this->assertSame($development->chain('acme/child'), $development->chain('acme/child'));
	}

	public function testServesThemeAssets(): void
	{
		$this->writeThemes();
		$this->writeTemporaryFile('extensions/acme/child/icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
		$this->writeTemporaryFile('extensions/acme/child/views/single.php', '<?php echo "secret";');

		$app = $this->boot();
		$css = $this->get($app, '/themes/acme/parent/css/parent.css');
		$svg = $this->get($app, '/themes/acme/child/icon.svg');

		$this->assertSame(200, $css->getStatusCode());
		$this->assertSame('text/css', $css->getHeaderLine('Content-Type'));
		$this->assertSame('nosniff', $css->getHeaderLine('X-Content-Type-Options'));
		$this->assertSame('body { color: red; }', (string) $css->getBody());
		$this->assertSame('sandbox', $svg->getHeaderLine('Content-Security-Policy'));
		$this->assertSame(200, $this->get($app, '/themes/blush/default/style.css')->getStatusCode());

		foreach (['/themes/acme/child/views/single.php', '/themes/acme/child/theme.yaml', '/themes/acme/child/missing.css', '/themes/acme/nope/style.css', '/themes/nope/style.css', '/themes/acme/child/css/parent.css'] as $uri) {
			$this->assertSame(404, $this->get($app, $uri)->getStatusCode(), $uri);
		}
	}
}
