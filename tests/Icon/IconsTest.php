<?php

/**
 * Icon tests.
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
use Blush\Console\Commands\ListIcons;
use Blush\Console\Testing\CommandTester;
use Blush\Console\Console;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Icon\IconName;
use Blush\Icon\IconRegistry;
use Blush\Icon\Icons;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeResolver;
use Blush\Directive\DirectiveName;
use Blush\Directive\Icon;
use Blush\View\ViewFactory;

#[CoversClass(IconName::class)]
#[CoversClass(IconRegistry::class)]
#[CoversClass(Icons::class)]
#[CoversClass(Icon::class)]
#[CoversClass(ListIcons::class)]
final class IconsTest extends TestCase
{
	use BootsScratchSite;

	private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M1 1h22"/></svg>';

	private function app(): Application
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt", "parent": "blush/default"}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");

		$app = $this->scratchApplication();
		$app->boot();

		return $app;
	}

	private function svg(Application $app, string $name): string
	{
		$icons  = $app->container()->make(Icons::class);
		$chain  = $app->container()->make(ThemeResolver::class)->active();
		$parsed = IconName::parse($name);

		return $parsed === null ? '(invalid)' : ($icons->file($parsed, $chain) ?? '(none)');
	}

	public function testNamesAreNamespacedAndShortNamesAreCore(): void
	{
		$this->assertSame('blush/house', (string) IconName::parse('house'));
		$this->assertSame('jtcom/github', (string) IconName::parse('jtcom/github'));
		$this->assertTrue(IconName::parse('arrow-left')?->isCore());
		$this->assertSame('Map pin', new IconName('blush', 'map-pin')->label());

		foreach (['', 'House', 'a/b/c', '../etc', 'blush/../x', 'has space', '-lead'] as $bad) {
			$this->assertNull(IconName::parse($bad), $bad);
		}
	}

	public function testIconsResolveThroughThemesExtensionsAndCore(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/icons/badge.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/alt/icons/blush/star.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/alt/icons/blush/heart.svg', self::SVG);
		$this->writeTemporaryFile('extension-icons/tabs.svg', self::SVG);

		$app = $this->app();
		$app->container()->make(IconRegistry::class)->add('acme', $this->temporaryDirectory() . '/extension-icons/');

		$this->assertStringEndsWith('resources/icons/blush/house.svg', $this->svg($app, 'house'));
		$this->assertStringNotContainsString($this->temporaryDirectory(), $this->svg($app, 'house'));
		$this->assertStringEndsWith('extensions/acme/alt/icons/badge.svg', $this->svg($app, 'alt/badge'));
		$this->assertStringEndsWith('extensions/acme/alt/icons/blush/star.svg', $this->svg($app, 'star'));
		$this->assertStringEndsWith($this->temporaryDirectory() . '/extensions/acme/alt/icons/blush/heart.svg', $this->svg($app, 'heart'));
		$this->assertStringEndsWith('extension-icons/tabs.svg', $this->svg($app, 'acme/tabs'));
		$this->assertSame('(none)', $this->svg($app, 'badge'));
		$this->assertSame('(none)', $this->svg($app, 'nope/badge'));

		$all = $app->container()->make(Icons::class)->all($app->container()->make(ThemeResolver::class)->active());

		$this->assertArrayHasKey('alt/badge', $all);
		$this->assertArrayHasKey('acme/tabs', $all);
		$this->assertArrayHasKey('blush/house', $all);
		$this->assertCount(131 + 2, $all);
	}

	public function testTheMarkupIsAccessible(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/icons/broken.svg', '<div>not svg</div>');

		$app   = $this->app();
		$icon  = static fn (string $name, string $label = ''): Icon => $app->container()->build(Icon::class, ['name' => $name, 'label' => $label]);
		$house = $icon('house');

		$house->attach(new DirectiveName('blush', 'icon'), ['class' => 'extra', 'id' => 'home-icon']);

		$plain = $house->markup();

		$this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em"', $plain);
		$this->assertStringContainsString('class="directive-icon extra" id="home-icon"', $plain);
		$this->assertStringContainsString('aria-hidden="true"', $plain);
		$this->assertStringContainsString('focusable="false"', $plain);
		$this->assertStringNotContainsString('role=', $plain);

		$labeled = $icon('house', 'Home "sweet" <home>')->markup();

		$this->assertStringContainsString('role="img" aria-label="Home &quot;sweet&quot; &lt;home&gt;"', $labeled);
		$this->assertStringNotContainsString('aria-hidden', $labeled);

		$this->assertFalse($icon('nope')->shouldRender());
		$this->assertFalse($icon('alt/broken')->shouldRender());
		$this->assertFalse($icon('Not A Name')->shouldRender());
	}

	public function testIconsRenderInMarkdownAndTemplates(): void
	{
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\nGo :icon[Home]{name=house} or :icon[]{name=heart .loved}.\n");
		$this->writeTemporaryFile('extensions/acme/alt/views/partials/footer.php', '<?= $template->icon("rss", "Feed") ?>');

		$html = (string) $this->app()->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertMatchesRegularExpression('#<p>Go <svg [^>]*class="directive-icon"[^>]*role="img" aria-label="Home">.*?</svg> or <svg [^>]*class="directive-icon loved"[^>]*aria-hidden="true">.*?</svg>\.</p>#', $html);
		$this->assertMatchesRegularExpression('#<svg [^>]*aria-label="Feed"#', $html);
	}

	public function testLabelsAreTranslatedAndListed(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/icons/badge.svg', self::SVG);
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"icons": {"badge": {"label": "Member badge"}}}');

		$app   = $this->app();
		$views = $app->container()->make(ViewFactory::class)->forChain($app->container()->make(ThemeResolver::class)->active());

		$this->assertSame('Home', $views->iconText(new IconName('blush', 'house'), 'label'));
		$this->assertSame('Warning', $views->iconText(new IconName('blush', 'triangle-alert'), 'label'));
		$this->assertSame('Member badge', $views->iconText(new IconName('alt', 'badge'), 'label'));

		$result = new CommandTester($app->container()->make(Console::class))->run('icon:list');

		$this->assertMatchesRegularExpression('#\| alt/badge\s*\| Member badge\s*\| extensions/acme/alt/icons/badge\.svg#', $result->output);
		$this->assertMatchesRegularExpression('#\| blush/house\s*\| Home\s*\| \(core\) house\.svg#', $result->output);
	}
}
