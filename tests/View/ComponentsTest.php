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

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\View\Card;
use Blush\Tests\Fixtures\View\Greeting;
use Blush\Tests\Fixtures\View\Orphan;
use Blush\Tests\Fixtures\View\Tone;
use Blush\Tests\Fixtures\View\Toned;
use Blush\Content\Schema\Fields\BoolField;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\NumberField;
use Blush\Content\Schema\Fields\TextField;
use Blush\Support\RegistrationException;
use Blush\Theme\ThemeResolver;
use Blush\View\Component\Component;
use Blush\View\Component\ComponentContent;
use Blush\View\Component\ComponentDefinition;
use Blush\View\Component\ComponentFactory;
use Blush\View\Component\ComponentName;
use Blush\View\Component\ComponentRegistrar;
use Blush\View\Component\ComponentListing;
use Blush\View\Component\ComponentRegistry;
use Blush\View\Component\ComponentType;
use Blush\View\Component\Embed;
use Blush\View\Component\PendingComponent;
use Blush\View\Component\Slots;
use Blush\View\ComponentDirectives;
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
		$this->view('components/app-box', '<div class="<?= attr($tone ?? "plain") ?>" data-n="<?= attr($props["data-n"] ?? "") ?>"><?= $slot ?>|<?= $slots->footer ?>|<?= isset($slots->header) ? "has header" : "no header" ?></div>');
		$this->view('page', '<?= $template->component("app/box", tone: "info")->content("<p>Body</p>")->slot("footer", "<small>Foot</small>") ?>');

		$views = $this->boot();

		$this->assertSame('<div class="info" data-n=""><p>Body</p>|<small>Foot</small>|no header</div>', $views->render('page'));
		$this->assertSame('<div class="plain" data-n="3">|' . '|no header</div>', $views->component('app/box', ['data-n' => '3'], '', new Slots(), new ViewContext()));
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
		$this->view('components/blush-callout', 'site: <?= $slot ?>');

		$views = $this->boot();

		$this->assertSame('site: hi', $views->component('callout', [], 'hi', new Slots(), new ViewContext()));
		$this->assertSame('site: hi', $views->component('blush/callout', [], 'hi', new Slots(), new ViewContext()));
	}

	public function testClassBackedComponentsBuildTheirProps(): void
	{
		$this->view('components/app-card', '<?= e($heading) ?>:<?= $columns + 1 ?>:<?= isset($secret) ? "leak" : "sealed" ?>:<?= e($component->secret()) ?>:<?= e($props["extra"] ?? "") ?>');
		$this->view('components/card-wide', 'wide <?= e($title) ?>');

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
		$this->view('components/app-toned', '<?= e($heading) ?>:<?= e($tone->value) ?>:<?= $level ?>:<?= $open ? "open" : "shut" ?>');

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
		$this->writeTemporaryFile('resources/lang/en.json', '{"components": {"box": {"label": "Box of things", "description": "Holds things."}}}');

		$views    = $this->boot();
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('app/card', Card::class);
		$registry->register('app/orphan', Orphan::class);

		$components = [];

		foreach ($views->components() as $component) {
			$components[(string) $component->name] = $component;
		}

		$this->assertSame(['app/box', 'app/card', 'app/orphan', 'blush/callout', 'blush/embed', 'blush/figure', 'blush/gallery'], array_keys($components));
		$this->assertTrue($components['blush/callout']->isCore());
		$this->assertFalse($components['app/box']->isCore());
		$this->assertSame(Embed::class, $components['blush/embed']->className());
		$this->assertNull($components['blush/callout']->className());
		$this->assertTrue($components['blush/callout']->isRegistered());
		$this->assertFalse($components['app/box']->isRegistered());
		$this->assertCount(2, $components['blush/callout']->files);
		$this->assertStringEndsWith('resources/views/components/callout.php', (string) $components['blush/callout']->file());
		$this->assertStringEndsWith('resources/views/components/blush-gallery.php', (string) $components['blush/gallery']->file());
		$this->assertStringEndsWith('themes/default/views/components/gallery.php', $components['blush/gallery']->files[1]);
		$this->assertNull($components['app/card']->file());

		// Text comes from the namespace's catalog, or is made from the name.
		$this->assertSame('Callout', $components['blush/callout']->label);
		$this->assertSame('Box of things', $components['app/box']->label);
		$this->assertSame('Holds things.', $components['app/box']->description);
		$this->assertNull($components['app/card']->label);
		$this->assertSame('Card', $components['app/card']->displayLabel());
		$this->assertSame('Warning', $views->componentText(new ComponentName('blush', 'callout'), 'props.tone.choices.warning'));

		// A class that picks its own view isn't missing a template; one
		// that relies on its components/ template is.
		$this->assertFalse($components['app/card']->isMissingTemplate());
		$this->assertTrue($components['app/orphan']->isMissingTemplate());
		$this->assertFalse($components['app/box']->isMissingTemplate());

		$this->assertSame(['loose.php'], array_map(basename(...), $views->strayComponentFiles()));
	}

	public function testThemeAndExtensionComponentsUseTheirOwnCatalogs(): void
	{
		$this->writeTemporaryFile('user/themes/alt/theme.json', '{"name": "Alt"}');
		$this->writeTemporaryFile('user/themes/alt/lang/en.json', '{"components": {"card": {"label": "Alt card"}}}');
		$this->writeTemporaryFile('user/extensions/hello/extension.json', '{"name": "fixture/hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\View\\\\OrphanProvider"}');
		$this->writeTemporaryFile('user/extensions/hello/lang/en.json', '{"components": {"tabs": {"label": "Tabs"}}}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'alt');\n");

		$views = $this->boot();

		$this->assertSame('Alt card', $views->componentText(new ComponentName('alt', 'card'), 'label'));
		$this->assertSame('Tabs', $views->componentText(new ComponentName('fixture', 'tabs'), 'label'));
		$this->assertNull($views->componentText(new ComponentName('other', 'tabs'), 'label'));
	}

	public function testCoreComponentsAreRegisteredWithTheirDefinitions(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(ComponentRegistry::class);

		$this->assertSame(['blush/callout', 'blush/embed', 'blush/figure', 'blush/gallery'], array_keys($registry->all()));
		$embed   = $registry->get('embed');
		$callout = $registry->get('callout');

		$this->assertNotNull($embed);
		$this->assertNotNull($callout);
		$this->assertSame(Embed::class, $embed->class);
		$this->assertSame(ComponentContent::Text, $embed->content());
		$this->assertSame(['url', 'title'], array_map(static fn ($field): string => $field->name, $embed->props()));
		$this->assertSame(ComponentContent::Blocks, $callout->content());
		$this->assertSame('note', $callout->props()[0]->default);
		$this->assertNull(ComponentType::Callout->className());
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

	public function testEmbedsKnownVideoServices(): void
	{
		$cases = [
			'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1' => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://youtu.be/dQw4w9WgXcQ'                   => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://youtube.com/shorts/dQw4w9WgXcQ'         => ['youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
			'https://vimeo.com/76979871'                     => ['vimeo', 'https://player.vimeo.com/video/76979871?dnt=1'],
			'https://example.com/video'                      => ['', null],
			'javascript:alert(1)'                            => ['', null],
			'https://youtu.be/bad"id'                        => ['', null]
		];

		foreach ($cases as $url => [$provider, $src]) {
			$embed = new Embed($url);

			$this->assertSame($provider, $embed->provider, $url);
			$this->assertSame($src, $embed->src, $url);
		}

		$this->assertFalse(new Embed()->shouldRender());
		$this->assertSame(['provider' => '', 'src' => null, 'url' => 'x', 'title' => 't'], new Embed('x', 't')->data());
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
			:::callout[Heads up]{tone=warning}
			Back up *first*.
			:::

			:::gallery{columns=9 .wide}
			![](/a.jpg) ![](/b.jpg)
			:::

			::figure[A photo]{src="/media/p.jpg" alt="A lake"}

			::embed[My video]{url="https://youtu.be/dQw4w9WgXcQ" title="Rick"}

			::embed{url="https://example.com/talk"}

			:::callout{tone=bogus}
			Plain note.
			:::

			::unknown[Kept as text]

			::app/badge[Site badge]{tone=new}

			:::badge
			A short name that isn't core.
			:::

			Read the :app/badge[inline]{tone=tip} notes at https://example.com or 10:30.
			MD);
		$this->view('components/app-badge', '<span class="badge badge--<?= attr($tone) ?>"><?= $slot ?></span>');

		$this->boot();

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString("<aside class=\"callout callout--warning\" role=\"note\">\n\t\t\t<p class=\"callout__title\">Heads up</p>\n\t\t<p>Back up <em>first</em>.</p></aside>", $html);
		$this->assertStringContainsString('<div class="gallery wide" style="--gallery-columns: 6">', $html);
		$this->assertStringContainsString('<img src="/media/p.jpg" alt="A lake" loading="lazy">', $html);
		$this->assertStringContainsString('<figcaption>A photo</figcaption>', $html);
		$this->assertStringContainsString('<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Rick"', $html);
		$this->assertStringContainsString('<p class="embed embed--link"><a href="https://example.com/talk">https://example.com/talk</a></p>', $html);
		$this->assertStringContainsString('callout--note', $html);
		$this->assertStringContainsString('<p>Kept as text</p>', $html);
		$this->assertStringContainsString('<span class="badge badge--new">Site badge</span>', $html);
		$this->assertStringContainsString('<p>A short name that isn\'t core.</p>', $html);
		$this->assertStringContainsString('Read the <span class="badge badge--tip">inline</span> notes at', $html);
	}

	public function testDirectivesUseTheRequestsTheme(): void
	{
		$this->writeTemporaryFile('user/themes/alt/theme.json', '{"name": "Alt"}');
		$this->writeTemporaryFile('user/themes/alt/views/components/callout.php', 'alt callout: <?= $slot ?>');
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n:::callout\nHi\n:::\n");

		$this->boot('development');

		$kernel = $this->app->container()->make(Kernel::class);

		$this->assertStringContainsString('alt callout: <p>Hi</p>', (string) $kernel->handle(Request::create('/?theme=alt'))->getBody());
	}
}
