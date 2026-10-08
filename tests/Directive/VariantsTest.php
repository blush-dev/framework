<?php

/**
 * Directive variant tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Directive\DirectiveName;
use Blush\Directive\DirectiveRegistry;
use Blush\Directive\DirectiveVariants;
use Blush\Directive\Events\DirectiveVariantsCollecting;
use Blush\Directive\Variant;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Support\RegistrationException;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesThemeViews;
use Blush\Tests\Fixtures\Directive\Box;
use Blush\Tests\Fixtures\Directive\Defaulted;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\Theme\Themes;
use Blush\Theme\ThemeManifest;
use Blush\View\ViewContext;
use Blush\View\ViewFactory;
use Blush\View\Views;

#[CoversClass(Variant::class)]
#[CoversClass(DirectiveVariants::class)]
#[CoversClass(DirectiveVariantsCollecting::class)]
final class VariantsTest extends TestCase
{
	use BootsScratchSite;
	use WritesThemeViews;

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
		return $views->directive($name, $props, '<p>Body</p>', new ViewContext());
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
		$this->assertSame('tight', new Variant('compact', 'acme', 'tight')->modifier());

		$this->expectException(InvalidArgumentException::class);
		new Variant('default', 'acme');
	}

	public function testRegistrationRejectsADefaultVariant(): void
	{
		$registry = new DirectiveRegistry();

		$registry->register('acme/note', Box::class);
		$this->assertSame(['wide'], self::names($registry->get('acme/note')?->variants() ?? []));
		$this->assertSame('acme', $registry->get('acme/note')?->variants()[0]->registrant);

		$this->expectException(RegistrationException::class);
		$registry->register('acme/box', Defaulted::class);
	}

	public function testVariantsAddTheirModifierAndDefaultAddsNothing(): void
	{
		$views = $this->boot();

		$this->assertStringContainsString('<aside class="directive-callout directive-callout--warning" role="note">', self::render($views, 'callout', ['variant' => 'warning']));
		$this->assertStringContainsString('<aside class="directive-callout" role="note">', self::render($views, 'callout'));
		$this->assertStringContainsString('<aside class="directive-callout" role="note">', self::render($views, 'callout', ['variant' => 'default']), 'Writing default is writing nothing.');
		$this->assertStringContainsString('<aside class="directive-callout" role="note">', self::render($views, 'callout', ['variant' => 'shiny']), 'A variant it doesn\'t have renders as Default.');
	}

	public function testListenersAddAndRemoveVariantsOncePerDirective(): void
	{
		$views = $this->boot();
		$calls = 0;

		$this->app->container()->make(ListenerRegistry::class)->listen(DirectiveVariantsCollecting::class, static function (DirectiveVariantsCollecting $event) use (&$calls): void {
			if ($event->is('callout')) {
				$calls++;
				$event->add('bordered', 'acme');
				$event->add('boxed', 'acme', 'box');
				$event->add('themed', 'other-theme');
				$event->remove('tip');
			}
		});

		$name = new DirectiveName('blush', 'callout');

		$this->assertSame(['info', 'warning', 'danger', 'bordered', 'boxed', 'themed'], self::names($views->variants($name)), 'A theme that isn\'t installed isn\'t outside the chain.');
		$this->assertStringContainsString('directive-callout--box', self::render($views, 'callout', ['variant' => 'boxed']));
		$this->assertStringContainsString('<aside class="directive-callout" role="note">', self::render($views, 'callout', ['variant' => 'tip']));
		$this->assertSame(1, $calls);
	}

	public function testAThemesVariantsApplyOnlyWhileItIsActive(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt", "variants": {"callout": ["bordered", {"name": "compact", "modifier": "tight"}, "Bad Name"]}}');
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"directives": {"callout": {"variants": {"bordered": {"label": "Bordered", "description": "A rule down the left."}}}}}');
		$this->writeTemporaryFile('extensions/acme/other/theme.json', '{"name": "acme/other", "label": "Other", "namespace": "other"}');

		$views = $this->boot('acme/alt');

		$this->app->container()->make(ListenerRegistry::class)->listen(DirectiveVariantsCollecting::class, static function (DirectiveVariantsCollecting $event): void {
			$event->add('from-alt', 'alt');
		});

		$name     = new DirectiveName('blush', 'callout');
		$variants = $views->variants($name);

		$this->assertSame(['info', 'tip', 'warning', 'danger', 'from-alt', 'bordered', 'compact'], self::names($variants), 'An invalid name is skipped.');
		$this->assertSame('alt', $variants[5]->registrant);
		$this->assertSame('Bordered', $views->variantText($name, $variants[5], 'label'));
		$this->assertSame('A rule down the left.', $views->variantText($name, $variants[5], 'description'));
		$this->assertStringContainsString('directive-callout--tight', self::render($views, 'callout', ['variant' => 'compact']));

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
		$this->assertNull($views->imageVariantText($variants[0], 'label'), 'The default theme isn\'t in the chain to label it (D-632).');
		$this->assertSame('A white border.', $views->imageVariantText($variants[1], 'description'));

		$container = $this->app->container();
		$themes    = $container->make(Themes::class);
		$factory   = $container->make(ViewFactory::class);

		$this->assertSame([], $factory->forChain($themes->chain('acme/other'))->imageVariants());

		$default = $factory->forChain($themes->chain('blush/default'))->imageVariants();

		$this->assertSame(['inline-left', 'inline-right'], self::names($default));
		$this->assertSame('default', $default[1]->registrant);

		$this->writeTemporaryFile('extensions/acme/kid/theme.json', '{"name": "acme/kid", "label": "Kid", "namespace": "kid", "parent": "acme/alt"}');
		$this->writeTemporaryFile('extensions/acme/heir/theme.json', '{"name": "acme/heir", "label": "Heir", "namespace": "heir", "parent": "blush/default"}');

		$views = $this->boot('acme/kid');

		$this->assertSame(['inline-left', 'polaroid'], self::names($views->imageVariants()), 'A parent\'s variants are kept, the last in a chain too (D-632).');
		$container = $this->app->container();
		$heir      = $container->make(ViewFactory::class)->forChain($container->make(Themes::class)->chain('acme/heir'));

		$this->assertSame([], self::names($heir->imageVariants()), 'The default theme\'s only while it\'s active.');
	}

	public function testAVariantsOwnTemplateWins(): void
	{
		$this->themeView('directives/callout-warning.php', 'warning template: <?= $directive->variant ?>');

		$views = $this->boot();

		$this->assertSame('warning template: warning', self::render($views, 'callout', ['variant' => 'warning']));
		$this->assertStringContainsString('<aside class="directive-callout directive-callout--danger"', self::render($views, 'callout', ['variant' => 'danger']));
		$this->assertArrayHasKey('callout-warning', $views->variantFiles());
	}

	public function testManifestsListVariantsByDirective(): void
	{
		$manifest = ThemeManifest::fromArray('/tmp/alt', ['name' => 'acme/alt', 'label' => 'Alt', 'namespace' => 'alt', 'variants' => ['blush/callout' => ['bordered']]]);
		$name     = new DirectiveName('blush', 'callout');

		$this->assertSame(['bordered'], self::names(DirectiveVariants::fromManifest($manifest->variants(), $name, 'alt')));
		$this->assertSame([], DirectiveVariants::fromManifest($manifest->variants(), new DirectiveName('blush', 'button'), 'alt'));

		$this->expectException(ThemeException::class);
		ThemeManifest::fromArray('/tmp/alt', ['name' => 'acme/alt', 'label' => 'Alt', 'namespace' => 'alt', 'variants' => ['bordered']]);
	}
}
