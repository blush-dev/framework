<?php

/**
 * Region tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Region;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Region\Item\ComponentItem;
use Blush\Region\Item\DirectiveItem;
use Blush\Region\Item\EntryItem;
use Blush\Region\Item\MarkdownItem;
use Blush\Region\Item\RegionItemFactory;
use Blush\Region\Item\ViewItem;
use Blush\Region\RegionLoader;
use Blush\Region\RegionLocation;
use Blush\Region\Regions;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesThemeViews;
use Blush\Theme\ThemeResolver;

#[CoversClass(Regions::class)]
#[CoversClass(RegionLoader::class)]
#[CoversClass(RegionLocation::class)]
#[CoversClass(RegionItemFactory::class)]
#[CoversClass(ComponentItem::class)]
#[CoversClass(DirectiveItem::class)]
#[CoversClass(EntryItem::class)]
#[CoversClass(MarkdownItem::class)]
#[CoversClass(ViewItem::class)]
final class RegionsTest extends TestCase
{
	use BootsScratchSite;
	use WritesThemeViews;

	private function app(): Application
	{
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\nAbout.");
		$this->writeTemporaryFile('user/content/bonjour.md', "---\ntitle: Bonjour\nlocale: fr\n---\nBonjour.");
		$this->writeTemporaryFile('user/content/_regions/blurb.md', "---\ntitle: Blurb\n---\nA *blurb* from an entry.");

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		return $app;
	}

	private function page(Application $app, string $path): string
	{
		return (string) $app->container()->make(Kernel::class)->handle(Request::create($path))->getBody();
	}

	public function testRendersEveryItemKindInTheFooter(): void
	{
		$this->writeTemporaryFile('user/data/menus/social.json', '[{"url":"https://example.org/","label":"Example"},{"entry":"page/about"}]');
		$this->writeTemporaryFile('user/data/regions/footer.json', <<<'JSON'
			{
				"items": [
					{
						"directive": "menu",
						"name": "social"
					},
					{
						"markdown": {
							"en": "Powered by **words**.",
							"fr": "Propulsé par des **mots**."
						}
					},
					{
						"entry": "page/_regions/blurb"
					},
					{
						"view": "partials/hello",
						"name": {
							"en": "friend",
							"fr": "ami"
						}
					},
					{
						"directive": "callout",
						"variant": "info"
					},
					{
						"component": "site/badge",
						"text": {
							"en": "New",
							"fr": "Nouveau"
						}
					},
					{
						"view": "partials/missing"
					}
				]
			}
			JSON);
		$this->themeView('partials/hello.php', "<?php declare(strict_types=1); ?><p class=\"hello\">Hello, <?= e(\$name) ?>.</p>");
		$this->themeView('components/site-badge.php', '<span <?= $component->attributes() ?>><?= e($component->prop("text")) ?></span>');

		$app  = $this->app();
		$html = $this->page($app, '/about');

		$this->assertStringContainsString('<div class="site-footer__region">', $html);
		$this->assertStringContainsString('<nav class="directive-menu directive-menu--social" aria-label="Menu">', $html);
		$this->assertStringContainsString('<a class="directive-menu__link" href="/about" aria-current="page">', $html);
		$this->assertStringContainsString('<p>Powered by <strong>words</strong>.</p>', $html);
		$this->assertStringContainsString('<p>A <em>blurb</em> from an entry.</p>', $html);
		$this->assertStringContainsString('<p class="hello">Hello, friend.</p>', $html);
		$this->assertStringContainsString('directive-callout--info', $html);
		$this->assertStringContainsString('<span class="component-badge">New</span>', $html);

		$french = $this->page($app, '/bonjour');

		$this->assertStringContainsString('<p>Propulsé par des <strong>mots</strong>.</p>', $french);
		$this->assertStringContainsString('<p class="hello">Hello, ami.</p>', $french);
		$this->assertStringContainsString('<span class="component-badge">Nouveau</span>', $french);
	}

	public function testPrintsNothingForAnEmptyRegion(): void
	{
		$html = $this->page($this->app(), '/about');

		$this->assertStringNotContainsString('site-footer__region', $html);
	}

	public function testThemeDefaultsUntilTheSiteFillsTheRegion(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', json_encode([
			'name'      => 'acme/nova',
			'label'     => 'Nova',
			'namespace' => 'nova',
			'regions'   => ['footer' => ['label' => 'Footer', 'items' => [['markdown' => 'Theme default.']]]]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");

		$this->assertStringContainsString('<p>Theme default.</p>', $this->page($this->app(), '/about'));

		$this->writeTemporaryFile('user/data/regions/bottom.json', '[{"markdown":"Site text."}]');
		$this->writeTemporaryFile('user/data/theme.json', '{"regions": {"footer": "bottom"}}');

		$html = $this->page($this->app(), '/about');

		$this->assertStringContainsString('<p>Site text.</p>', $html);
		$this->assertStringNotContainsString('Theme default.', $html);
	}

	public function testChecksRegions(): void
	{
		$this->writeTemporaryFile('user/data/regions/footer.json', <<<'JSON'
			[
				{
					"markdown": "Fine."
				},
				{
					"markdown": [
						1,
						2
					]
				},
				{
					"component": "../x"
				},
				{
					"directive": "acme/"
				},
				{
					"view": "partials/a",
					"entry": "page/about"
				},
				{
					"nothing": "here"
				}
			]
			JSON);
		$this->writeTemporaryFile('user/data/regions/aside.json', '{"$schema":"region.schema.json","items":[],"title":"x"}');

		$app      = $this->app();
		$problems = $app->container()->make(Regions::class)->check($app->container()->make(ThemeResolver::class)->active());

		$this->assertSame([
			'warning region aside: "title" isn\'t a region key; a region has "items".',
			'notice region aside: No location of the "blush/default" theme shows it.',
			'warning region footer: item 2: "markdown" must be Markdown text, or a map of locales to it.',
			'warning region footer: item 3: "component" must be a component name, such as "acme/card".',
			'warning region footer: item 4: "directive" must be a directive name, such as "menu" or "acme/tabs".',
			'warning region footer: item 5: must have exactly one of "component", "directive", "entry", "markdown", "view".',
			'warning region footer: item 6: must have exactly one of "component", "directive", "entry", "markdown", "view".'
		], array_map(static fn (Violation $problem): string => "{$problem->severity->value} {$problem}", $problems));
	}
}
