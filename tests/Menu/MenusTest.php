<?php

/**
 * Menu tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Menu;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Directive\Menu as MenuDirective;
use Blush\Core\Application;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Menu\Link\CollectionLink;
use Blush\Menu\Link\EntryLink;
use Blush\Menu\Link\MenuLinkFactory;
use Blush\Menu\Link\MenuLinkRegistry;
use Blush\Menu\Link\RouteLink;
use Blush\Menu\Link\TermLink;
use Blush\Menu\Link\UrlLink;
use Blush\Menu\Menu;
use Blush\Menu\MenuItem;
use Blush\Menu\MenuLoader;
use Blush\Menu\MenuLocation;
use Blush\Menu\Menus;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeChecker;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\ThemeResolver;

#[CoversClass(Menus::class)]
#[CoversClass(Menu::class)]
#[CoversClass(MenuItem::class)]
#[CoversClass(MenuLoader::class)]
#[CoversClass(MenuLocation::class)]
#[CoversClass(MenuLinkFactory::class)]
#[CoversClass(MenuLinkRegistry::class)]
#[CoversClass(EntryLink::class)]
#[CoversClass(TermLink::class)]
#[CoversClass(CollectionLink::class)]
#[CoversClass(RouteLink::class)]
#[CoversClass(UrlLink::class)]
#[CoversClass(MenuDirective::class)]
final class MenusTest extends TestCase
{
	use BootsScratchSite;

	private const string PRIMARY = <<<'JSON'
		{
			"items": [
				{"entry": "page/about"},
				{"entry": "page/contact"},
				{
					"label": "More",
					"children": [
						{"entry": "page/team", "label": "The team", "description": "Who we are", "badge": "New"},
						{"route": "sitemap.type", "params": {"type": "page"}, "label": "Sitemap"}
					]
				},
				{"url": "https://example.org/", "label": "Elsewhere", "rel": "me", "icon": "house"}
			]
		}
		JSON;

	private function app(): Application
	{
		$this->writeTemporaryFile('user/content/about.md', "---\nid: 6083a88e-e341-1b0d-17ce-02d738f69d47\ntitle: About us\n---\nAbout.");
		$this->writeTemporaryFile('user/content/team.md', "---\nid: 0032aa43-ab4d-0a52-da10-e3dd5643e52b\ntitle: Team\n---\nTeam.");
		$this->writeTemporaryFile('user/content/contact.md', "---\nid: 3c4864f0-0d23-f7ea-3551-1ec930ce1d9c\ntitle: Contact\nstatus: draft\n---\nDraft.");
		$this->writeTemporaryFile('user/content/bonjour.md', "---\nid: 0b4c7c6d-50bf-525a-c913-1b8255ae1c3a\ntitle: Bonjour\nlocale: fr_CA\n---\nBonjour.");

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	private function page(Application $app, string $path): string
	{
		return (string) $app->container()->make(Kernel::class)->handle(Request::create($path))->getBody();
	}

	private function menu(Application $app, string $location, string $locale = ''): Menu
	{
		$menu = $app->container()->make(Menus::class)->forLocation($app->container()->make(ThemeResolver::class)->active(), $location, $locale);

		$this->assertNotNull($menu);

		return $menu;
	}

	/**
	 * @param  list<MenuItem> $items
	 * @return list<mixed>
	 */
	private static function shape(array $items): array
	{
		return array_map(
			static fn (MenuItem $item): mixed => $item->children === [] ? "{$item->label} {$item->url}" : [$item->label => self::shape($item->children)],
			$items
		);
	}

	public function testResolvesLinksAndLeavesOutWhatDoesNotResolve(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', self::PRIMARY);

		$menu = $this->menu($this->app(), 'primary');

		$this->assertSame('primary', $menu->location);
		$this->assertSame('Primary', $menu->label);
		$this->assertSame(
			['About us /about', ['More' => ['The team /team', 'Sitemap /sitemap/page']], 'Elsewhere https://example.org/'],
			self::shape($menu->items)
		);

		$team = $menu->items[1]->children[0];

		$this->assertSame('Who we are', $team->description);
		$this->assertSame('New', $team->badge);
		$this->assertSame('me', $menu->items[2]->rel);
		$this->assertSame('house', $menu->items[2]->icon);
		$this->assertFalse($menu->items[1]->isLink());
	}

	public function testRendersInTheDefaultThemeWithTheCurrentItem(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', self::PRIMARY);

		$html = $this->page($this->app(), '/team');

		$this->assertStringContainsString('<nav class="directive-menu directive-menu--primary" aria-label="Primary">', $html);
		$this->assertStringContainsString('<li class="directive-menu__item directive-menu__item--current">', $html);
		$this->assertStringContainsString('<a class="directive-menu__link" href="/team" aria-current="page">', $html);
		$this->assertStringContainsString('<li class="directive-menu__item directive-menu__item--ancestor directive-menu__item--parent">', $html);
		$this->assertStringContainsString('<span class="directive-menu__heading">', $html);
		$this->assertStringContainsString('<button class="directive-menu__toggle" type="button" aria-expanded="true" aria-controls="menu-primary-2" aria-label="More submenu" hidden></button>', $html);
		$this->assertStringContainsString('<ul class="directive-menu__list directive-menu__submenu" id="menu-primary-2">', $html);
		$this->assertStringContainsString('<a class="directive-menu__link" href="https://example.org/" rel="me">', $html);
		$this->assertStringContainsString('<span class="directive-menu__badge">New</span>', $html);
		$this->assertStringContainsString('directive-icon', $html);
		$this->assertStringNotContainsString('Contact', $html);
	}

	public function testMarksTheCurrentItemFromAFullUrlOnTheSite(): void
	{
		$item = new MenuItem('About', 'https://example.com/about/?x=1');
		$menu = new Menu('primary', 'primary', '', [new MenuItem('Group', null, children: [$item])])->forPath('/about', 'https://example.com');

		$this->assertTrue($menu->items[0]->children[0]->current);
		$this->assertTrue($menu->items[0]->ancestor);
		$this->assertSame($menu->items[0]->children[0], $menu->current());
		$this->assertFalse(new MenuItem('Off', 'https://other.test/about')->forPath('/about', 'https://example.com')->current);
		$this->assertTrue(new MenuItem('Home', '/')->forPath('/', '')->current);
		$this->assertFalse(new MenuItem('Home', '/')->forPath('', '')->current);
		$this->assertSame('aria-current="page"', (string) $menu->items[0]->children[0]->ariaCurrent());
		$this->assertSame('aria-current="true"', (string) $menu->items[0]->ariaCurrent(), 'An item above the page marks its section.');
		$this->assertSame('', (string) new MenuItem('Off', '/off')->ariaCurrent());
	}

	public function testPrintsNothingWithoutAMenu(): void
	{
		$html = $this->page($this->app(), '/about');

		$this->assertStringNotContainsString('directive-menu', $html);
	}

	public function testPicksTextInThePageLocale(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', <<<'JSON'
			{
				"label": {
					"en": "Main",
					"fr": "Principal"
				},
				"items": [
					{
						"entry": "page/about",
						"label": {
							"en": "About",
							"fr": "À propos"
						}
					},
					{
						"url": "/elsewhere",
						"label": {
							"en_US": "Elsewhere",
							"de": "Anderswo"
						}
					}
				]
			}
			JSON);

		$app = $this->app();

		$this->assertSame(['About /about', 'Elsewhere /elsewhere'], self::shape($this->menu($app, 'primary')->items));
		$this->assertSame(['À propos /about', 'Elsewhere /elsewhere'], self::shape($this->menu($app, 'primary', 'fr_CA')->items));
		$this->assertSame('Principal', $this->menu($app, 'primary', 'fr_CA')->label);
		$this->assertStringContainsString('aria-label="Principal"', $this->page($app, '/bonjour'));
	}

	public function testMapsALocationToAnotherMenu(): void
	{
		$this->writeTemporaryFile('user/data/menus/main.json', '[{"entry":"page/about"}]');
		$this->writeTemporaryFile('user/data/theme.json', '{"menus": {"primary": "main"}}');

		$menu = $this->menu($this->app(), 'primary');

		$this->assertSame('main', $menu->name);
		$this->assertSame(['About us /about'], self::shape($menu->items));
	}

	public function testThemeLocationsSetDepthFieldsAndLabels(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', json_encode([
			'name'      => 'acme/nova',
			'label'     => 'Nova',
			'namespace' => 'nova',
			'menus'     => [
				'primary' => ['label' => 'Main', 'depth' => 1, 'fields' => ['columns' => ['type' => 'number', 'integer' => true, 'default' => 1]]]
			]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");
		$this->writeTemporaryFile('user/data/menus/primary.json', '[{"entry":"page/about","columns":3,"children":[{"entry":"page/team"}]},{"entry":"page/team"}]');

		$menu = $this->menu($this->app(), 'primary');

		$this->assertSame('Main', $menu->label);
		$this->assertSame(['About us /about', 'Team /team'], self::shape($menu->items));
		$this->assertSame(3, $menu->items[0]->field('columns'));
		$this->assertSame(1, $menu->items[1]->field('columns'));
	}

	public function testChecksMenus(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', <<<'JSON'
			{
				"items": [
					{
						"entry": "page/missing"
					},
					{
						"entry": "page/about",
						"url": "/about"
					},
					{
						"route": "sitemap"
					},
					{
						"url": "javascript:alert(1)",
						"label": "Bad"
					},
					{
						"entry": "page/about",
						"colour": "red"
					},
					{
						"label": "Empty"
					},
					"just text"
				]
			}
			JSON);
		$this->writeTemporaryFile('user/data/menus/extra.json', '{"$schema":"menu.schema.json","items":"x","title":"x"}');

		$app      = $this->app();
		$problems = $app->container()->make(Menus::class)->check($app->container()->make(ThemeResolver::class)->active());
		$messages = array_map(static fn (Violation $problem): string => "{$problem->severity->value} {$problem}", $problems);

		$this->assertSame([
			'warning menu primary: item 1: No entry "page/missing".',
			'warning menu primary: item 2: has more than one link (entry, url); use one.',
			'warning menu primary: item 3: needs a "label".',
			'warning menu primary: item 4: "url" isn\'t a safe URL.',
			'warning menu primary: item 5: "colour" isn\'t a menu item key or a field the location declares.',
			'warning menu primary: item 6: has no link and no children.',
			'warning menu primary: item 7: must be a map of keys to values.',
			'notice menu extra: No location of the "blush/default" theme shows it.',
			'warning menu extra: "title" isn\'t a menu key; a menu has "label" and "items".',
			'warning menu extra: "items" must be a list.'
		], $messages);
		$this->assertSame([], array_filter($problems, static fn (Violation $problem): bool => $problem->severity === Severity::Error));
	}

	public function testRendersFromAMarkdownDirective(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', self::PRIMARY);
		$this->writeTemporaryFile('user/content/links.md', "---\nid: 42406911-3a08-a425-6349-764ee38dbbbf\ntitle: Links\n---\n::menu{name=primary label=\"Site links\"}\n");

		$html = $this->page($this->app(), '/links');

		$this->assertStringContainsString('<nav class="directive-menu directive-menu--primary" aria-label="Site links">', $html);
	}

	public function testChecksLocationDeclarations(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova", "menus": {"primary": {"depth": 0}}, "regions": {"side": {"items": {"a": 1}}}}');

		$app    = $this->app();
		$report = $app->container()->make(ThemeChecker::class)->check('acme/nova');
		$errors = array_map(strval(...), $report->violations);

		$this->assertContains('menus: The "acme/nova" theme\'s menu location "primary" has a "depth" that isn\'t a whole number from 1.', $errors);
		$this->assertContains('regions: The "acme/nova" theme\'s region location "side" has "items" that aren\'t a list.', $errors);
		$this->assertTrue($report->hasErrors());
	}

	public function testManifestLocationsMustBeMaps(): void
	{
		$this->expectException(ThemeException::class);
		$this->expectExceptionMessage('"menus" must map location names');

		ThemeManifest::fromArray('/tmp/nova', ['name' => 'acme/nova', 'label' => 'Nova', 'namespace' => 'nova', 'menus' => ['primary']]);
	}
}
