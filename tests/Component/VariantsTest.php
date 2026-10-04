<?php

/**
 * Component variant tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Component;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Component\ComponentName;
use Blush\Component\ComponentRegistry;
use Blush\Component\ComponentVariants;
use Blush\Component\Events\ComponentVariantsCollecting;
use Blush\Component\Slots;
use Blush\Component\Variant;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Support\RegistrationException;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\Theme\Themes;
use Blush\Theme\ThemeManifest;
use Blush\View\ViewContext;
use Blush\View\ViewFactory;
use Blush\View\Views;

#[CoversClass(Variant::class)]
#[CoversClass(ComponentVariants::class)]
#[CoversClass(ComponentVariantsCollecting::class)]
final class VariantsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	private function boot(?string $theme = null): Views
	{
		if ($theme !== null) {
			$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: '{$theme}');\n");
		}

		$this->app = $this->scratchApplication();
		$this->app->boot();

		$container = $this->app->container();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	/**
	 * @param array<string, mixed> $props
	 */
	private static function render(Views $views, string $name, array $props = []): string
	{
		return $views->component($name, $props, '<p>Body</p>', new Slots(), new ViewContext());
	}

	/**
	 * @param list<Variant> $variants
	 * @return list<string>
	 */
	private static function names(array $variants): array
	{
		return array_map(static fn (Variant $variant): string => $variant->name, $variants);
	}

	public function testDefaultIsNotAVariantAndNamesAreChecked(): void
	{
		$this->assertFalse(Variant::isValidName('default'));
		$this->assertFalse(Variant::isValidName('Wide'));
		$this->assertTrue(Variant::isValidName('wide-left'));
		$this->assertSame('tight', new Variant('compact', 'app', 'tight')->modifier());

		$this->expectException(InvalidArgumentException::class);
		new Variant('default', 'app');
	}

	public function testRegistrationRejectsADefaultVariant(): void
	{
		$registry = new ComponentRegistry();

		$registry->register('app/note', variants: ['wide']);
		$this->assertSame(['wide'], self::names($registry->get('app/note')?->variants() ?? []));
		$this->assertSame('app', $registry->get('app/note')?->variants()[0]->registrant);

		$this->expectException(RegistrationException::class);
		$registry->register('app/box', variants: ['default']);
	}

	public function testVariantsAddTheirModifierAndDefaultAddsNothing(): void
	{
		$views = $this->boot();

		$this->assertStringContainsString('<aside class="component-callout component-callout--warning" role="note">', self::render($views, 'callout', ['variant' => 'warning']));
		$this->assertStringContainsString('<aside class="component-callout" role="note">', self::render($views, 'callout'));
		$this->assertStringContainsString('<aside class="component-callout" role="note">', self::render($views, 'callout', ['variant' => 'default']), 'Writing default is writing nothing.');
		$this->assertStringContainsString('<aside class="component-callout" role="note">', self::render($views, 'callout', ['variant' => 'shiny']), 'A variant it doesn\'t have renders as Default.');
	}

	public function testListenersAddAndRemoveVariantsOncePerComponent(): void
	{
		$views = $this->boot();
		$calls = 0;

		$this->app->container()->make(ListenerRegistry::class)->listen(ComponentVariantsCollecting::class, static function (ComponentVariantsCollecting $event) use (&$calls): void {
			if ($event->is('callout')) {
				$calls++;
				$event->add('bordered', 'app');
				$event->add('boxed', 'app', 'box');
				$event->add('themed', 'other-theme');
				$event->remove('tip');
			}
		});

		$name = new ComponentName('blush', 'callout');

		$this->assertSame(['info', 'warning', 'danger', 'bordered', 'boxed', 'themed'], self::names($views->variants($name)), 'A theme that isn\'t installed isn\'t outside the chain.');
		$this->assertStringContainsString('component-callout--box', self::render($views, 'callout', ['variant' => 'boxed']));
		$this->assertStringContainsString('<aside class="component-callout" role="note">', self::render($views, 'callout', ['variant' => 'tip']));
		$this->assertSame(1, $calls);
	}

	public function testAThemesVariantsApplyOnlyWhileItIsActive(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt", "variants": {"callout": ["bordered", {"name": "compact", "modifier": "tight"}, "Bad Name"]}}');
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"components": {"callout": {"variants": {"bordered": {"label": "Bordered", "description": "A rule down the left."}}}}}');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');

		$views = $this->boot('acme/alt');

		$this->app->container()->make(ListenerRegistry::class)->listen(ComponentVariantsCollecting::class, static function (ComponentVariantsCollecting $event): void {
			$event->add('from-alt', 'alt');
		});

		$name     = new ComponentName('blush', 'callout');
		$variants = $views->variants($name);

		$this->assertSame(['info', 'tip', 'warning', 'danger', 'from-alt', 'bordered', 'compact'], self::names($variants), 'An invalid name is skipped.');
		$this->assertSame('alt', $variants[5]->registrant);
		$this->assertSame('Bordered', $views->variantText($name, $variants[5], 'label'));
		$this->assertSame('A rule down the left.', $views->variantText($name, $variants[5], 'description'));
		$this->assertStringContainsString('component-callout--tight', self::render($views, 'callout', ['variant' => 'compact']));

		// Under another theme, alt's variants are gone, a listener's too.
		$container = $this->app->container();
		$other     = $container->make(ViewFactory::class)->forChain($container->make(Themes::class)->chain('acme/other'));

		$this->assertSame(['info', 'tip', 'warning', 'danger'], self::names($other->variants($name)));
	}

	public function testImagesHaveTheThemesVariants(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt", "variants": {"image": ["inline-left", "polaroid", "Bad Name"]}}');
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"images": {"variants": {"polaroid": {"label": "Polaroid", "description": "A white border."}}}}');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');

		$views    = $this->boot('acme/alt');
		$variants = $views->imageVariants();

		$this->assertSame(['inline-left', 'polaroid'], self::names($variants), 'An invalid name is skipped, and the default theme\'s aren\'t styled here.');
		$this->assertSame('alt', $variants[0]->registrant);
		$this->assertSame('Float Left', $views->imageVariantText($variants[0], 'label'), 'The theme catalogs fall back through the chain.');
		$this->assertSame('A white border.', $views->imageVariantText($variants[1], 'description'));

		$container = $this->app->container();
		$themes    = $container->make(Themes::class);
		$factory   = $container->make(ViewFactory::class);

		$this->assertSame([], $factory->forChain($themes->chain('acme/other'))->imageVariants());

		$default = $factory->forChain($themes->chain('blush/default'))->imageVariants();

		$this->assertSame(['inline-left', 'inline-right'], self::names($default));
		$this->assertSame('default', $default[1]->registrant);
	}

	public function testAVariantsOwnTemplateWins(): void
	{
		$this->writeTemporaryFile('resources/views/components/callout-warning.php', 'warning template: <?= $component->variant ?>');

		$views = $this->boot();

		$this->assertSame('warning template: warning', self::render($views, 'callout', ['variant' => 'warning']));
		$this->assertStringContainsString('<aside class="component-callout component-callout--danger"', self::render($views, 'callout', ['variant' => 'danger']));
		$this->assertArrayHasKey('callout-warning', $views->variantFiles());
	}

	public function testManifestsListVariantsByComponent(): void
	{
		$manifest = ThemeManifest::fromArray('/tmp/alt', ['name' => 'acme/alt', 'label' => 'Alt', 'namespace' => 'alt', 'variants' => ['blush/callout' => ['bordered']]]);
		$name     = new ComponentName('blush', 'callout');

		$this->assertSame(['bordered'], self::names(ComponentVariants::fromManifest($manifest->variants(), $name, 'alt')));
		$this->assertSame([], ComponentVariants::fromManifest($manifest->variants(), new ComponentName('blush', 'button'), 'alt'));

		$this->expectException(ThemeException::class);
		ThemeManifest::fromArray('/tmp/alt', ['name' => 'acme/alt', 'label' => 'Alt', 'namespace' => 'alt', 'variants' => ['bordered']]);
	}
}
