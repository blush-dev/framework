<?php

/**
 * Component and context provider tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Component;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\TextField;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Embed\Fetcher;
use Blush\Tests\Fixtures\Embed\FixtureFetcher;
use Blush\Tests\Fixtures\Component\Card;
use Blush\Tests\Fixtures\View\Greeting;
use Blush\Tests\Fixtures\Component\Orphan;
use Blush\Tests\Fixtures\Component\Stamp;
use Blush\Tests\Fixtures\Component\Tone;
use Blush\Tests\Fixtures\Component\Toned;
use Blush\Support\RegistrationException;
use Blush\Theme\ThemeResolver;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentDefinition;
use Blush\Component\ComponentFactory;
use Blush\Component\ComponentName;
use Blush\Component\ComponentRegistrar;
use Blush\Component\ComponentListing;
use Blush\Component\ComponentRegistry;
use Blush\Component\ComponentType;
use Blush\Component\Callout;
use Blush\Component\Embed;
use Blush\Component\PendingComponent;
use Blush\Component\Slots;
use Blush\Component\ComponentDirectives;
use Blush\View\ContextProviders;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewFinder;
use Blush\View\ViewNotFound;
use Blush\View\Views;

#[CoversClass(Component::class)]
#[CoversClass(ComponentContent::class)]
#[CoversClass(ComponentDefinition::class)]
#[CoversClass(ComponentFactory::class)]
#[CoversClass(ComponentName::class)]
#[CoversClass(ComponentRegistrar::class)]
#[CoversClass(ComponentRegistry::class)]
#[CoversClass(ComponentType::class)]
#[CoversClass(ComponentListing::class)]
#[CoversClass(Embed::class)]
#[CoversClass(PendingComponent::class)]
#[CoversClass(Slots::class)]
#[CoversClass(ComponentDirectives::class)]
#[CoversClass(ContextProviders::class)]
#[CoversClass(ViewFinder::class)]
#[CoversClass(Views::class)]
final class ComponentsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	private function boot(string $environment = 'production'): Views
	{
		$this->app = $this->scratchApplication(['APP_ENV' => $environment]);
		$this->app->boot();

		$container = $this->app->container();
		$container->instance(Fetcher::class, new FixtureFetcher());

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	private function view(string $name, string $code): void
	{
		$this->writeTemporaryFile("resources/views/{$name}.php", $code);
	}

	public function testNamesAreNamespacedAndOnlyCoreOnesAreShort(): void
	{
		$this->assertSame('blush/callout', (string) ComponentName::parse('callout'));
		$this->assertSame('blush/callout', (string) ComponentName::parse('blush/callout'));
		$this->assertSame('acme/tabs', (string) ComponentName::parse('acme/tabs'));
		$this->assertNull(ComponentName::parse('tabs'));
		$this->assertNull(ComponentName::parse('acme/tabs/more'));
		$this->assertNull(ComponentName::parse('../x'));
		$this->assertNull(ComponentName::parse('acme/'));

		$this->assertSame(['components/callout', 'components/blush-callout'], new ComponentName('blush', 'callout')->views());
		$this->assertSame(['components/acme-tabs'], new ComponentName('acme', 'tabs')->views());
		$this->assertSame('Post archives', new ComponentName('jtcom', 'post-archives')->label());

		// A file name takes the longest namespace that fits.
		$this->assertSame('my-theme/card', (string) ComponentName::fromFileName('my-theme-card', ['my', 'my-theme']));
		$this->assertSame('my/card', (string) ComponentName::fromFileName('my-card', ['my', 'my-theme']));
		$this->assertSame('blush/gallery', (string) ComponentName::fromFileName('gallery', []));
		$this->assertSame('blush/gallery', (string) ComponentName::fromFileName('blush-gallery', ['blush']));
		$this->assertNull(ComponentName::fromFileName('card', ['my']));
	}

	public function testTemplateOnlyComponentsGetPropsAndSlots(): void
	{
		$this->view('components/app-box', '<div <?= $component->attributes(["data-tone" => $component->prop("tone", "plain")]) ?> data-n="<?= attr($component->prop("data-n", "")) ?>"><?= $component->content() ?>|<?= $component->slots->footer ?>|<?= $component->slots->has("header") ? "has header" : "no header" ?></div>');
		$this->view('page', '<?= $template->component("app/box", tone: "info")->content("<p>Body</p>")->slot("footer", "<small>Foot</small>") ?>');

		$views = $this->boot();

		$this->assertSame('<div class="component-box" data-tone="info" data-n=""><p>Body</p>|<small>Foot</small>|no header</div>', $views->render('page'));
		$this->assertSame('<div class="component-box extra" id="b" data-tone="plain" data-n="3">|' . '|no header</div>', $views->component('app/box', ['data-n' => '3', 'class' => 'extra', 'id' => 'b'], '', new Slots(), new ViewContext()));
		$this->assertTrue($views->hasComponent('app/box'));
		$this->assertTrue($views->hasComponent('callout'));
		$this->assertTrue($views->hasComponent('blush/callout'));
		$this->assertFalse($views->hasComponent('box'));
		$this->assertFalse($views->hasComponent('app/nope'));
		$this->assertFalse($views->hasComponent('../x'));
	}

	public function testCoreTemplatesMayUseEitherNameAndTheNearestWins(): void
	{
		// The site's blush-callout.php beats the default theme's
		// callout.php, whichever name it uses.
		$this->view('components/blush-callout', 'site: <?= $component->content() ?>');

		$views = $this->boot();

		$this->assertSame('site: hi', $views->component('callout', [], 'hi', new Slots(), new ViewContext()));
		$this->assertSame('site: hi', $views->component('blush/callout', [], 'hi', new Slots(), new ViewContext()));
	}

	public function testClassBackedComponentsBuildTheirProps(): void
	{
		$this->view('components/app-card', '<?= e($component->heading) ?>:<?= $component->columns + 1 ?>:<?= isset($heading) ? "leak" : "sealed" ?>:<?= e($component->secret()) ?>:<?= e($component->prop("extra")) ?>');
		$this->view('components/card-wide', 'wide <?= e($component->title) ?>');

		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('app/card', Card::class);

		$this->assertSame('HI:3:sealed:hidden:x', $views->component('app/card', ['title' => 'hi', 'columns' => '2', 'extra' => 'x'], '', new Slots(), new ViewContext()));
		$this->assertSame('wide yo', $views->component('app/card', ['title' => 'yo', 'wide' => 'yes'], '', new Slots(), new ViewContext()));
		$this->assertSame('', $views->component('app/card', ['title' => 'skip'], '', new Slots(), new ViewContext()));
		$this->assertTrue($views->hasComponent('app/card'));

		$this->expectException(ViewException::class);
		$views->component('app/card', ['title' => ['not', 'a', 'string']], '', new Slots(), new ViewContext());
	}

	public function testBackedEnumPropsAreCastAndFallBackToTheirDefault(): void
	{
		$this->view('components/app-toned', '<?= e($component->heading) ?>:<?= e($component->tone->value) ?>:<?= $component->level ?>:<?= $component->open ? "open" : "shut" ?>');

		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('app/toned', Toned::class);

		$this->assertSame('Hi:loud:3:open', $views->component('app/toned', ['heading' => 'Hi', 'tone' => 'loud', 'level' => '3', 'open' => 'true'], '', new Slots(), new ViewContext()));
		$this->assertSame('Hi:quiet:2:shut', $views->component('app/toned', ['heading' => 'Hi', 'tone' => 'bogus'], '', new Slots(), new ViewContext()));
		$this->assertSame('Hi:loud:2:shut', $views->component('app/toned', ['heading' => 'Hi', 'tone' => Tone::Loud], '', new Slots(), new ViewContext()));
	}

	public function testDefinitionsReadPropsAndContentFromTheClass(): void
	{
		$definition = new ComponentDefinition(new ComponentName('app', 'toned'), Toned::class);
		$props      = $definition->props();

		$this->assertSame(ComponentContent::Blocks, $definition->content());

		// Services and private state aren't props.
		$this->assertSame(['heading', 'tone', 'level', 'open'], array_map(static fn ($field): string => $field->name, $props));
		$this->assertInstanceOf(TextField::class, $props[0]);
		$this->assertTrue($props[0]->required);
		$this->assertInstanceOf(EnumField::class, $props[1]);
		$this->assertSame(['quiet', 'loud'], $props[1]->options);
		$this->assertSame('quiet', $props[1]->default);
		$this->assertInstanceOf(NumberField::class, $props[2]);
		$this->assertTrue($props[2]->integer);
		$this->assertSame(2, $props[2]->default);
		$this->assertInstanceOf(BoolField::class, $props[3]);

		$explicit = new ComponentDefinition(new ComponentName('app', 'toned'), Toned::class, ComponentContent::Text, []);

		$this->assertSame(ComponentContent::Text, $explicit->content());
		$this->assertSame([], $explicit->props());
		$this->assertSame(ComponentContent::None, new ComponentDefinition(new ComponentName('app', 'plain'))->content());
		$this->assertSame([], new ComponentDefinition(new ComponentName('app', 'plain'))->props());
	}

	public function testListsEveryComponentTheChainCanRender(): void
	{
		$this->view('components/app-box', 'box');
		$this->view('components/cards/post', 'a subfolder is not a component');
		$this->view('components/callout', 'site callout');
		$this->view('components/blush-gallery', 'site gallery');
		$this->view('components/loose', 'not named for a component');
		$this->view('components/callout-warning', 'a variant\'s template');
		$this->writeTemporaryFile('resources/lang/en.json', '{"components": {"box": {"label": "Box of things", "description": "Holds things."}}}');

		$views    = $this->boot();
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('app/card', Card::class);
		$registry->register('app/orphan', Orphan::class);

		$components = [];

		foreach ($views->components() as $component) {
			$components[(string) $component->name] = $component;
		}

		$this->assertSame(['app/box', 'app/card', 'app/orphan', 'blush/abbr', 'blush/audio', 'blush/badge', 'blush/button', 'blush/callout', 'blush/cite', 'blush/dfn', 'blush/embed', 'blush/figure', 'blush/file', 'blush/gallery', 'blush/grid', 'blush/group', 'blush/icon', 'blush/ins', 'blush/kbd', 'blush/menu', 'blush/meter', 'blush/progress', 'blush/row', 'blush/samp', 'blush/small', 'blush/stack', 'blush/time', 'blush/toc', 'blush/var', 'blush/video'], array_keys($components));
		$this->assertTrue($components['blush/callout']->isCore());
		$this->assertFalse($components['app/box']->isCore());
		$this->assertSame(Embed::class, $components['blush/embed']->className());
		$this->assertSame(Callout::class, $components['blush/callout']->className());
		$this->assertTrue($components['blush/callout']->isRegistered());
		$this->assertFalse($components['app/box']->isRegistered());
		$this->assertCount(1, $components['blush/callout']->files, 'Only the site\'s: core components render themselves (D-382).');
		$this->assertStringEndsWith('resources/views/components/callout.php', (string) $components['blush/callout']->file());
		$this->assertStringEndsWith('resources/views/components/blush-gallery.php', (string) $components['blush/gallery']->file());
		$this->assertSame([], $components['blush/meter']->files);
		$this->assertTrue($components['blush/meter']->rendersItself());
		$this->assertFalse($components['blush/meter']->isMissingTemplate());
		$this->assertFalse($components['app/orphan']->rendersItself(), 'Its render() can return null.');
		$this->assertNull($components['app/card']->file());

		// Text comes from the namespace's catalog, or is made from the name.
		$this->assertSame('Callout', $components['blush/callout']->label);
		$this->assertSame('Box of things', $components['app/box']->label);
		$this->assertSame('Holds things.', $components['app/box']->description);
		$this->assertNull($components['app/card']->label);
		$this->assertSame('Card', $components['app/card']->displayLabel());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $components['blush/callout']->variants));
		$this->assertSame('Warning', $views->variantText(new ComponentName('blush', 'callout'), $components['blush/callout']->variants[2], 'label'));

		// A class that picks its own view isn't missing a template; one
		// that relies on its components/ template is.
		$this->assertFalse($components['app/card']->isMissingTemplate());
		$this->assertTrue($components['app/orphan']->isMissingTemplate());
		$this->assertFalse($components['app/box']->isMissingTemplate());

		// A variant's template is neither a component nor a stray file.
		$this->assertSame(['loose.php'], array_map(basename(...), $views->strayComponentFiles()));
	}

	public function testThemeAndPluginComponentsUseTheirOwnCatalogs(): void
	{
		$this->writeTemporaryFile('user/themes/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('user/themes/alt/lang/en.json', '{"components": {"card": {"label": "Alt card"}}}');
		$this->writeTemporaryFile('user/plugins/hello/plugin.json', '{"name": "fixture/hello", "label": "Hello", "namespace": "hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\Component\\\\OrphanProvider"}');
		$this->writeTemporaryFile('user/plugins/hello/lang/en.json', '{"components": {"tabs": {"label": "Tabs"}}}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['fixture/hello']);\n");

		$views = $this->boot();

		$this->assertSame('Alt card', $views->componentText(new ComponentName('alt', 'card'), 'label'));
		$this->assertSame('Tabs', $views->componentText(new ComponentName('hello', 'tabs'), 'label'));
		$this->assertNull($views->componentText(new ComponentName('other', 'tabs'), 'label'));
	}

	public function testCoreComponentsAreRegisteredWithTheirDefinitions(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(ComponentRegistry::class);

		$this->assertSame(['blush/abbr', 'blush/audio', 'blush/badge', 'blush/button', 'blush/callout', 'blush/cite', 'blush/dfn', 'blush/embed', 'blush/figure', 'blush/file', 'blush/gallery', 'blush/grid', 'blush/group', 'blush/icon', 'blush/ins', 'blush/kbd', 'blush/menu', 'blush/meter', 'blush/progress', 'blush/row', 'blush/samp', 'blush/small', 'blush/stack', 'blush/time', 'blush/toc', 'blush/var', 'blush/video'], array_keys($registry->all()));
		$embed   = $registry->get('embed');
		$callout = $registry->get('callout');

		$this->assertNotNull($embed);
		$this->assertNotNull($callout);
		$this->assertSame(Embed::class, $embed->class);
		$this->assertSame(ComponentContent::Text, $embed->content());
		$this->assertSame(['url', 'title', 'label'], array_map(static fn ($field): string => $field->name, $embed->props()));
		$this->assertSame(ComponentContent::Blocks, $callout->content());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $callout->variants()));
		$this->assertSame('blush', $callout->variants()[0]->registrant);
		$this->assertSame(Callout::class, ComponentType::Callout->className());
	}

	public function testRegistrationEnforcesNamespaces(): void
	{
		$registry = new ComponentRegistry();
		new ComponentRegistrar($registry)->register();

		// A provider may replace a core component, by either name.
		$registry->register('callout', Card::class);
		$this->assertSame(Card::class, $registry->get('blush/callout')?->class);
		$registry->register('app/note', content: ComponentContent::Blocks);
		$this->assertNull($registry->get('app/note')?->class);
		$this->assertSame(['blush', 'app'], $registry->namespaces());

		$registry->registerIf('app/note', Card::class);
		$this->assertNull($registry->get('app/note')?->class);

		$registry->unregister('app/note');
		$this->assertFalse($registry->isRegistered('app/note'));

		$cases = [
			'tabs'           => '"tabs" needs its namespace, such as "vendor/tabs"; only core components have short names.',
			'blush/tabs'     => '"blush/tabs" is in the "blush" namespace, which only core components use.',
			'acme/tabs/more' => '"acme/tabs/more" is not a valid component name.'
		];

		foreach ($cases as $name => $message) {
			try {
				$registry->register($name);
				$this->fail("{$name} should be refused.");
			} catch (RegistrationException $error) {
				$this->assertSame($message, $error->getMessage());
			}
		}

		$this->expectException(RegistrationException::class);
		// @phpstan-ignore argument.type (verifies the runtime guard)
		$registry->register('app/x', Tone::class);
	}

	public function testMissingAndInvalidComponentsThrow(): void
	{
		$views = $this->boot();

		try {
			$views->component('app/nope', [], '', new Slots(), new ViewContext());
			$this->fail('A missing component should throw.');
		} catch (ViewNotFound $error) {
			$this->assertSame('No view found for: components/app-nope.', $error->getMessage());
		}

		try {
			$views->component('nope', [], '', new Slots(), new ViewContext());
			$this->fail('A short third-party name should throw.');
		} catch (ViewException $error) {
			$this->assertSame('"nope" isn\'t a core component, so it needs its namespace, such as "default/nope" (D-171).', $error->getMessage());
		}

		$this->expectExceptionMessage('"../x" is not a valid component name.');
		$views->component('../x', [], '', new Slots(), new ViewContext());
	}

	public function testContextProvidersAddDefaults(): void
	{
		$this->view('parts/hello', '<?= e($greeting) ?>/<?= e($name) ?>');
		$this->view('other', '<?= isset($greeting) ? "leak" : "none" ?>');

		$views     = $this->boot();
		$providers = $this->app->container()->make(ContextProviders::class);
		$greeting  = new Greeting();

		$providers->add('parts/*', $greeting);
		$providers->add('nothing', Greeting::class);

		$this->assertSame('Hello from parts/hello/given', $views->partial('parts/hello', ['name' => 'given'], new ViewContext()));
		$this->assertSame('Hello from parts/hello/provided', $views->partial('parts/hello', [], new ViewContext()));
		$this->assertSame('none', $views->partial('other', [], new ViewContext()));
		$this->assertSame(2, $greeting->calls);
		$this->assertTrue($providers->has('parts/hello'));
		$this->assertFalse($providers->has('parts/deep/hello'));

		$providers->add('other', Greeting::class);

		$this->assertSame('leak', $views->partial('other', [], new ViewContext()));
	}

	public function testDirectivesRenderCoreComponentsInEntries(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			title: Home
			---
			:::callout[Heads up]{variant=warning}
			Back up *first*.
			:::

			:::gallery{columns=9 .wide}
			![](/a.jpg) ![](/b.jpg)
			:::

			:::gallery{columns=2 layout=grid}
			![](/c.jpg)
			:::

			:::figure[A photo]{.wide}
			![A lake](/media/p.jpg)
			:::

			:::figure[Visitors]
			| Month | Visitors |
			| ----- | -------- |
			| May   | 1,204    |
			:::

			::embed[My video]{url="https://youtu.be/dQw4w9WgXcQ" title="Rick"}

			::embed{url="https://example.com/talk"}

			::embed[Our launch]{url="https://vimeo.com/76979871"}

			::embed{url="https://youtu.be/dQw4w9WgXcQ"}

			:::callout{variant=bogus}
			Plain note.
			:::

			::unknown[Kept as text]

			::app/badge[Site badge]{tone=new}

			:::badge
			A short name that isn't core.
			:::

			Read the :app/badge[inline]{tone=tip} notes at https://example.com or 10:30.
			MD);
		$this->view('components/app-badge', '<span class="badge badge--<?= attr($component->prop("tone")) ?>"><?= $component->content() ?></span>');

		$this->boot();

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString("<aside class=\"component-callout component-callout--warning\" role=\"note\">\n\t\t\t<p class=\"component-callout__title\">Heads up</p>\n\t\t<p>Back up <em>first</em>.</p></aside>", $html);
		$this->assertStringContainsString('<div class="component-gallery component-gallery--flex wide" style="--gallery-columns: 6">', $html);
		$this->assertStringContainsString('<div class="component-gallery component-gallery--grid" style="--gallery-columns: 2">', $html);
		$this->assertMatchesRegularExpression('#<figure class="component-figure wide">\s*<img src="http://localhost/media/p.jpg" alt="A lake" />\s*<figcaption>A photo</figcaption>#', $html, 'The figure is the container; its image stands alone.');
		$this->assertMatchesRegularExpression('#<figure class="component-figure">\s*<table>.*?</table>\s*<figcaption>Visitors</figcaption>#s', $html, 'A figure wraps anything, such as a table.');
		$this->assertStringContainsString('<iframe class="component-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Rick"', $html);
		$this->assertStringContainsString('<p class="component-embed component-embed--link"><a href="https://example.com/talk">https://example.com/talk</a></p>', $html);

		// Without a title, the frame is named by its caption or provider.
		$this->assertStringContainsString('<iframe class="component-embed__frame" src="https://player.vimeo.com/video/76979871?dnt=1" title="Our launch"', $html);
		$this->assertStringContainsString('<iframe class="component-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Embedded content from YouTube"', $html);
		// A variant it doesn't have renders as Default.
		$this->assertStringContainsString("<aside class=\"component-callout\" role=\"note\">\n\t\t<p>Plain note.</p></aside>", $html);
		$this->assertStringContainsString('<p>Kept as text</p>', $html);
		$this->assertStringContainsString('<span class="badge badge--new">Site badge</span>', $html);
		$this->assertStringContainsString('<p>A short name that isn\'t core.</p>', $html);
		$this->assertStringContainsString('Read the <span class="badge badge--tip">inline</span> notes at', $html);
	}

	public function testDirectivesUseTheRequestsTheme(): void
	{
		$this->writeTemporaryFile('user/themes/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('user/themes/alt/views/components/callout.php', 'alt callout: <?= $component->content() ?>');
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n:::callout\nHi\n:::\n");

		$this->boot('development');

		$kernel = $this->app->container()->make(Kernel::class);

		$this->assertStringContainsString('alt callout: <p>Hi</p>', (string) $kernel->handle(Request::create('/?theme=acme/alt'))->getBody());
	}

	public function testComponentsOfThemesOutsideTheChainRenderThemselvesOrNothing(): void
	{
		$this->writeTemporaryFile('user/themes/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');
		$this->writeTemporaryFile('user/themes/nova/views/components/nova-badge.php', 'nova badge');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/nova');\n");
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\nBefore\n\n::nova/badge\n\n::nova/stamp{text=Stamped}\n\nAfter\n");

		$this->boot('development');

		// As the active theme's provider would.
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('nova/badge');
		$registry->register('nova/stamp', Stamp::class);

		$kernel = $this->app->container()->make(Kernel::class);

		$this->assertStringContainsString('nova badge', (string) $kernel->handle(Request::create('/'))->getBody());

		$preview = $kernel->handle(Request::create('/?theme=blush/default'));
		$body    = (string) $preview->getBody();

		$this->assertSame(200, $preview->getStatusCode(), $body);
		$this->assertStringContainsString('After', $body);
		$this->assertStringNotContainsString('nova badge', $body, 'The previewed chain doesn\'t have Nova\'s template.');
		$this->assertStringContainsString('<span class="component-stamp">Stamped</span>', $body, 'A component with markup of its own still renders (D-382).');
	}

	public function testComponentsRenderThemselvesUnlessTheChainHasATemplate(): void
	{
		$views    = $this->boot();
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('app/stamp', Stamp::class);

		$this->assertSame('<span class="component-stamp">Paid &amp; done</span>', $views->component('app/stamp', ['text' => 'Paid & done'], '', new Slots(), new ViewContext()));
		$this->assertStringContainsString('<aside class="component-callout"', $views->component('callout', [], '<p>Hi</p>', new Slots(), new ViewContext()), 'A core component renders the framework\'s template.');

		$this->view('components/app-stamp', 'site stamp: <?= e($component->text) ?>');
		$this->view('components/callout', 'site callout');

		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('app/stamp', Stamp::class);

		$this->assertSame('site stamp: Paid', $views->component('app/stamp', ['text' => 'Paid'], '', new Slots(), new ViewContext()), 'A template in the chain wins.');
		$this->assertSame('site callout', $views->component('callout', [], '', new Slots(), new ViewContext()));
	}
}
