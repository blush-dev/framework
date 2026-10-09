<?php

/**
 * Directive tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

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
use Blush\Tests\WritesThemeViews;
use Blush\Embed\Fetcher;
use Blush\Tests\Fixtures\Embed\FixtureFetcher;
use Blush\Tests\Fixtures\Directive\Box;
use Blush\Tests\Fixtures\Directive\Orphan;
use Blush\Tests\Fixtures\Directive\Salutation;
use Blush\Tests\Fixtures\Directive\Stamp;
use Blush\Tests\Fixtures\Directive\Tag;
use Blush\Tests\Fixtures\Directive\Tone;
use Blush\Tests\Fixtures\Directive\Toned;
use Blush\Directive\DirectiveKind;
use Blush\Support\RegistrationException;
use Blush\Theme\ThemeResolver;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveDefinition;
use Blush\View\RenderableFactory;
use Blush\Directive\DirectiveName;
use Blush\Directive\DirectiveRegistrar;
use Blush\Directive\DirectiveListing;
use Blush\Directive\DirectiveRegistry;
use Blush\Directive\DirectiveType;
use Blush\Directive\Callout;
use Blush\Directive\Embed;
use Blush\Directive\PendingDirective;
use Blush\Directive\MarkdownDirectives;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewFinder;
use Blush\View\ViewNotFound;
use Blush\View\Views;

#[CoversClass(Directive::class)]
#[CoversClass(DirectiveContent::class)]
#[CoversClass(DirectiveDefinition::class)]
#[CoversClass(RenderableFactory::class)]
#[CoversClass(DirectiveName::class)]
#[CoversClass(DirectiveRegistrar::class)]
#[CoversClass(DirectiveRegistry::class)]
#[CoversClass(DirectiveType::class)]
#[CoversClass(DirectiveListing::class)]
#[CoversClass(Embed::class)]
#[CoversClass(PendingDirective::class)]
#[CoversClass(MarkdownDirectives::class)]
#[CoversClass(ViewFinder::class)]
#[CoversClass(Views::class)]
final class DirectivesTest extends TestCase
{
	use BootsScratchSite;
	use WritesThemeViews;

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
		$this->themeView("{$name}.php", $code);
	}

	public function testNamesAreNamespacedAndOnlyCoreOnesAreShort(): void
	{
		$this->assertSame('blush/callout', (string) DirectiveName::parse('callout'));
		$this->assertSame('blush/callout', (string) DirectiveName::parse('blush/callout'));
		$this->assertSame('acme/tabs', (string) DirectiveName::parse('acme/tabs'));
		$this->assertNull(DirectiveName::parse('tabs'));
		$this->assertNull(DirectiveName::parse('acme/tabs/more'));
		$this->assertNull(DirectiveName::parse('../x'));
		$this->assertNull(DirectiveName::parse('acme/'));

		$this->assertSame(['directives/callout', 'directives/blush-callout'], new DirectiveName('blush', 'callout')->views());
		$this->assertSame(['directives/acme-tabs'], new DirectiveName('acme', 'tabs')->views());
		$this->assertSame('Pricing table', new DirectiveName('acme', 'pricing-table')->label());

		// A file name takes the longest namespace that fits.
		$this->assertSame('my-plugin/tabs', (string) DirectiveName::fromFileName('my-plugin-tabs', ['my', 'my-plugin']));
		$this->assertSame('my/tabs', (string) DirectiveName::fromFileName('my-tabs', ['my', 'my-plugin']));
		$this->assertSame('blush/gallery', (string) DirectiveName::fromFileName('gallery', []));
		$this->assertSame('blush/gallery', (string) DirectiveName::fromFileName('blush-gallery', ['blush']));
		$this->assertNull(DirectiveName::fromFileName('tabs', ['my']));
	}

	public function testOnlyRegisteredDirectivesExist(): void
	{
		$this->view('directives/acme-box', '<div <?= $directive->attributes(["data-tone" => $directive->prop("tone", "plain")]) ?>><?= $directive->content() ?></div>');
		$this->view('page', '<?= $template->directive("acme/box", tone: "info")->content("<p>Body</p>") ?>');

		$views = $this->boot();

		$this->assertFalse($views->hasDirective('acme/box'), 'A template alone isn\'t a directive (D-532).');
		$this->assertTrue($views->hasDirective('callout'));
		$this->assertTrue($views->hasDirective('blush/callout'));

		$this->app->container()->make(DirectiveRegistry::class)->register('acme/box', Box::class);

		$this->assertTrue($views->hasDirective('acme/box'));
		$this->assertFalse($views->hasDirective('box'));
		$this->assertFalse($views->hasDirective('../x'));
		$this->assertSame('<div class="directive-box" data-tone="info"><p>Body</p></div>', $views->render('page'));
		$this->assertSame('<div class="directive-box extra" id="b" data-tone="plain"></div>', $views->directive('acme/box', ['class' => 'extra', 'id' => 'b'], '', new ViewContext()));
	}

	public function testCoreTemplatesMayUseEitherNameAndTheNearestWins(): void
	{
		// A child theme's blush-callout.php beats the framework's
		// callout.php, whichever name it uses.
		$this->view('directives/blush-callout', 'child: <?= $directive->content() ?>');

		$views = $this->boot();

		$this->assertSame('child: hi', $views->directive('callout', [], 'hi', new ViewContext()));
		$this->assertSame('child: hi', $views->directive('blush/callout', [], 'hi', new ViewContext()));
	}

	public function testBackedEnumPropsAreCastAndFallBackToTheirDefault(): void
	{
		$this->view('directives/acme-toned', '<?= e($directive->heading) ?>:<?= e($directive->tone->value) ?>:<?= $directive->level ?>:<?= $directive->open ? "open" : "shut" ?>');

		$views = $this->boot();
		$this->app->container()->make(DirectiveRegistry::class)->register('acme/toned', Toned::class);

		$this->assertSame('Hi:loud:3:open', $views->directive('acme/toned', ['heading' => 'Hi', 'tone' => 'loud', 'level' => '3', 'open' => 'true'], '', new ViewContext()));
		$this->assertSame('Hi:quiet:2:shut', $views->directive('acme/toned', ['heading' => 'Hi', 'tone' => 'bogus'], '', new ViewContext()));
		$this->assertSame('Hi:loud:2:shut', $views->directive('acme/toned', ['heading' => 'Hi', 'tone' => Tone::Loud], '', new ViewContext()));

		$this->expectException(ViewException::class);
		$views->directive('acme/toned', ['heading' => ['not', 'a', 'string']], '', new ViewContext());
	}

	public function testDefinitionsReadPropsAndContentFromTheClass(): void
	{
		$definition = new DirectiveDefinition(new DirectiveName('acme', 'toned'), Toned::class);
		$props      = $definition->props();

		$this->assertSame(DirectiveContent::Blocks, $definition->content());

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

		$this->assertSame(DirectiveKind::Container, $definition->kind());
		$this->assertSame([], new DirectiveDefinition(new DirectiveName('acme', 'stamp'), Stamp::class)->variants());
	}

	public function testListsEveryDirective(): void
	{
		$this->view('directives/acme-note', 'note');
		$this->view('directives/acme-box', 'not registered');
		$this->view('directives/cards/post', 'a subfolder is not a directive');
		$this->view('directives/callout', 'child callout');
		$this->view('directives/blush-gallery', 'child gallery');
		$this->view('directives/loose', 'not named for a directive');
		$this->view('directives/callout-warning', 'a variant\'s template');
		$this->writeTemporaryFile('user/lang/en/acme.json', '{"directives": {"note": {"label": "Note to self", "description": "Holds a note."}}}');

		$views    = $this->boot();
		$registry = $this->app->container()->make(DirectiveRegistry::class);
		$registry->register('acme/note', Box::class);
		$registry->register('acme/orphan', Orphan::class);

		$directives = [];

		foreach ($views->directives() as $directive) {
			$directives[(string) $directive->name] = $directive;
		}

		$this->assertSame(['acme/note', 'acme/orphan', 'blush/abbr', 'blush/audio', 'blush/badge', 'blush/button', 'blush/callout', 'blush/cite', 'blush/dfn', 'blush/embed', 'blush/figure', 'blush/file', 'blush/gallery', 'blush/grid', 'blush/group', 'blush/icon', 'blush/ins', 'blush/kbd', 'blush/menu', 'blush/meter', 'blush/progress', 'blush/row', 'blush/samp', 'blush/small', 'blush/stack', 'blush/time', 'blush/toc', 'blush/var', 'blush/video'], array_keys($directives));
		$this->assertTrue($directives['blush/callout']->isCore());
		$this->assertFalse($directives['acme/note']->isCore());
		$this->assertSame(Embed::class, $directives['blush/embed']->className());
		$this->assertSame(Callout::class, $directives['blush/callout']->className());
		$this->assertSame(Box::class, $directives['acme/note']->className());
		$this->assertCount(1, $directives['blush/callout']->files, 'Only the site\'s: core directives render themselves (D-382).');
		$this->assertStringEndsWith('extensions/test/site/views/directives/callout.php', (string) $directives['blush/callout']->file());
		$this->assertStringEndsWith('extensions/test/site/views/directives/blush-gallery.php', (string) $directives['blush/gallery']->file());
		$this->assertSame([], $directives['blush/meter']->files);
		$this->assertTrue($directives['blush/meter']->rendersItself());
		$this->assertFalse($directives['blush/meter']->isMissingTemplate());
		$this->assertFalse($directives['acme/orphan']->rendersItself(), 'Its render() can return null.');

		// Text comes from the namespace's catalog, or is made from the name.
		$this->assertSame('Callout', $directives['blush/callout']->label);
		$this->assertSame('Note to self', $directives['acme/note']->label);
		$this->assertSame('Holds a note.', $directives['acme/note']->description);
		$this->assertNull($directives['acme/orphan']->label);
		$this->assertSame('Orphan', $directives['acme/orphan']->displayLabel());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $directives['blush/callout']->variants));
		$this->assertSame('Warning', $views->variantText(new DirectiveName('blush', 'callout'), $directives['blush/callout']->variants[2], 'label'));

		$this->assertTrue($directives['acme/orphan']->isMissingTemplate());
		$this->assertFalse($directives['acme/note']->isMissingTemplate());

		// A variant's template is neither a directive nor a stray file; a
		// template for a directive no one registered is.
		$this->assertSame(['acme-box.php', 'loose.php'], array_map(basename(...), $views->strayDirectiveFiles()));
	}

	public function testPluginDirectivesUseTheirOwnCatalogs(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/fixture/hello/plugin.json', '{"name": "fixture/hello", "label": "Hello", "namespace": "hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\Directive\\\\OrphanProvider"}');
		$this->writeTemporaryFile('extensions/fixture/hello/lang/en.json', '{"directives": {"tabs": {"label": "Tabs"}}, "greeting": "Hello, {name}."}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['fixture/hello']);\n");

		$views = $this->boot();

		$this->assertSame('Tabs', $views->directiveText(new DirectiveName('hello', 'tabs'), 'label'));
		$this->assertNull($views->directiveText(new DirectiveName('other', 'tabs'), 'label'));

		// A plugin's directive reads its own catalog, by its vendor/name
		// domain, and the theme can reword it (D-451).
		$this->app->container()->make(DirectiveRegistry::class)->register('hello/greeting', Salutation::class);

		$this->assertSame('<p>Hello, Ada.</p>', $views->directive('hello/greeting', [], '', new ViewContext()));

		$this->writeTemporaryFile('user/lang/en/extensions/fixture/hello.json', '{"directives": {"tabs": {"label": "Site tabs"}}}');
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"greeting": "Howdy, {name}."}');

		$views = $this->boot();
		$this->app->container()->make(DirectiveRegistry::class)->register('hello/greeting', Salutation::class);

		$this->assertSame('Site tabs', $views->directiveText(new DirectiveName('hello', 'tabs'), 'label'), 'The site\'s override wins.');
		$this->assertSame('<p>Howdy, Ada.</p>', $views->directive('hello/greeting', [], '', new ViewContext()));
	}

	public function testCoreDirectivesAreRegisteredWithTheirDefinitions(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(DirectiveRegistry::class);

		$this->assertSame(['blush/abbr', 'blush/audio', 'blush/badge', 'blush/button', 'blush/callout', 'blush/cite', 'blush/dfn', 'blush/embed', 'blush/figure', 'blush/file', 'blush/gallery', 'blush/grid', 'blush/group', 'blush/icon', 'blush/ins', 'blush/kbd', 'blush/menu', 'blush/meter', 'blush/progress', 'blush/row', 'blush/samp', 'blush/small', 'blush/stack', 'blush/time', 'blush/toc', 'blush/var', 'blush/video'], array_keys($registry->all()));
		$embed   = $registry->get('embed');
		$callout = $registry->get('callout');

		$this->assertNotNull($embed);
		$this->assertNotNull($callout);
		$this->assertSame(Embed::class, $embed->class);
		$this->assertSame(DirectiveContent::Text, $embed->content());
		$this->assertSame(['url', 'title', 'alt', 'label'], array_map(static fn ($field): string => $field->name, $embed->props()));
		$this->assertSame(DirectiveContent::Blocks, $callout->content());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $callout->variants()));
		$this->assertSame('blush', $callout->variants()[0]->registrant);
		$this->assertSame(Callout::class, DirectiveType::Callout->className());
	}

	/**
	 * A directive is registered as a container, a leaf, or inline, and
	 * works only that way (D-531).
	 */
	public function testDirectivesAreRegisteredAsOneKind(): void
	{
		$registry = new DirectiveRegistry();
		new DirectiveRegistrar($registry)->register();

		$kinds = static fn (string $name): ?DirectiveKind => $registry->get($name)?->kind();

		$this->assertSame(DirectiveKind::Container, $kinds('gallery'));
		$this->assertSame(DirectiveKind::Leaf, $kinds('audio'));
		$this->assertSame(DirectiveKind::Inline, $kinds('button'));
		$this->assertSame(DirectiveKind::Inline, $kinds('time'));

		$registry->register('acme/tag', Tag::class);
		$registry->register('acme/note', Box::class);
		$this->assertSame(DirectiveKind::Inline, $kinds('acme/tag'));
		$this->assertSame(DirectiveKind::Container, $kinds('acme/note'));

		$odd = [
			'text in a container' => new class () extends Directive {
				public const DirectiveContent CONTENT = DirectiveContent::Text;

				public const ?DirectiveKind KIND = DirectiveKind::Container;

				public function render(): null
				{
					return null;
				}
			},
			'blocks in a leaf' => new class () extends Directive {
				public const DirectiveContent CONTENT = DirectiveContent::Blocks;

				public const ?DirectiveKind KIND = DirectiveKind::Leaf;

				public function render(): null
				{
					return null;
				}
			}
		];

		foreach ($odd as $case => $directive) {
			try {
				$registry->register('acme/odd', $directive::class);
				$this->fail("{$case} should be refused.");
			} catch (RegistrationException $error) {
				$this->assertStringContainsString('only a directive that wraps blocks is a container', $error->getMessage());
			}
		}

		// Every directive says how it's written (D-534).
		$kindless = new class () extends Directive {
			public function render(): null
			{
				return null;
			}
		};

		$this->expectException(RegistrationException::class);
		$this->expectExceptionMessage('must declare its KIND: DirectiveKind::Container, Leaf, or Inline.');
		$registry->register('acme/kindless', $kindless::class);
	}

	public function testDirectivesWorkOnlyAsTheirKind(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::button[Leaf button]{url=/a}

			Go :button[Inline button]{url=/b} now, at :time[noon]{datetime=12:00}.

			Inline :callout[callout] and :audio[audio]{src=/a.mp3}.

			::callout[Leaf callout]
			MD);

		$this->boot();

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringNotContainsString('Leaf button</span></a>', $html);
		$this->assertStringContainsString('Inline button</span></a>', $html);
		$this->assertStringContainsString('<time class="directive-time" datetime="12:00">noon</time>', $html);
		$this->assertStringContainsString('<p>Inline callout and audio.</p>', $html, 'An inline directive of another kind is its text.');
		$this->assertStringNotContainsString('directive-callout', $html);
		$this->assertStringContainsString('<p>Leaf callout</p>', $html);
	}

	public function testRegistrationEnforcesNamespaces(): void
	{
		$registry = new DirectiveRegistry(static fn (string $namespace): bool => $namespace === 'nova');
		new DirectiveRegistrar($registry)->register();

		// A provider may replace a core directive, by either name.
		$registry->register('callout', Stamp::class);
		$this->assertSame(Stamp::class, $registry->get('blush/callout')?->class);
		$registry->register('acme/note', Box::class);
		$this->assertSame(Box::class, $registry->get('acme/note')?->class);
		$this->assertSame(['blush', 'acme'], $registry->namespaces());

		$registry->registerIf('acme/note', Stamp::class);
		$this->assertSame(Box::class, $registry->get('acme/note')?->class);

		$registry->unregister('acme/note');
		$this->assertFalse($registry->isRegistered('acme/note'));

		$cases = [
			'tabs'           => '"tabs" needs its namespace, such as "vendor/tabs"; only core directives have short names.',
			'blush/tabs'     => '"blush/tabs" is in the "blush" namespace, which only core directives use.',
			'acme/tabs/more' => '"acme/tabs/more" is not a valid directive name.',
			'nova/badge'     => '"nova/badge" is in a theme\'s namespace, and themes can\'t register directives (D-532): register it from a plugin, or make it a component the theme\'s templates use.'
		];

		foreach ($cases as $name => $message) {
			try {
				$registry->register($name, Stamp::class);
				$this->fail("{$name} should be refused.");
			} catch (RegistrationException $error) {
				$this->assertSame($message, $error->getMessage());
			}
		}

		$this->expectException(RegistrationException::class);
		// @phpstan-ignore argument.type (verifies the runtime guard)
		$registry->register('acme/x', Tone::class);
	}

	public function testThemesCantRegisterDirectives(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');

		$this->boot();

		$this->expectException(RegistrationException::class);
		$this->expectExceptionMessage('themes can\'t register directives (D-532)');
		$this->app->container()->make(DirectiveRegistry::class)->register('nova/badge', Stamp::class);
	}

	public function testUnknownDirectivesThrowWhenATemplateAsksForThem(): void
	{
		$views = $this->boot();

		try {
			$views->directive('acme/nope', [], '', new ViewContext());
			$this->fail('An unregistered directive should throw.');
		} catch (ViewException $error) {
			$this->assertSame('No directive "acme/nope" is registered (D-532).', $error->getMessage());
		}

		try {
			$views->directive('nope', [], '', new ViewContext());
			$this->fail('A short third-party name should throw.');
		} catch (ViewException $error) {
			$this->assertSame('"nope" isn\'t a core directive, so it needs its namespace, such as "acme/nope" (D-171).', $error->getMessage());
		}

		$this->app->container()->make(DirectiveRegistry::class)->register('acme/note', Box::class);

		$this->expectException(ViewNotFound::class);
		$this->expectExceptionMessage('No view found for: directives/acme-note.');
		$views->directive('acme/note', [], '', new ViewContext());
	}

	public function testDirectivesRenderInEntries(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
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

			::acme/badge[Site badge]{tone=new}

			::acme/box[A template alone]

			:::badge
			A badge isn't a container.
			:::

			Read the :acme/badge[inline]{tone=tip} notes at https://example.com or 10:30.
			MD);
		$this->view('directives/acme-badge', '<span class="badge badge--<?= attr($directive->prop("tone")) ?>"><?= $directive->content() ?></span>');
		$this->view('directives/acme-box', 'box');

		$this->boot();
		$this->app->container()->make(DirectiveRegistry::class)->register('acme/badge', Tag::class);

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString("<aside class=\"directive-callout directive-callout--warning\" role=\"note\">\n\t\t\t<p class=\"directive-callout__title\">Heads up</p>\n\t\t<p>Back up <em>first</em>.</p></aside>", $html);
		$this->assertStringContainsString('<div class="directive-gallery directive-gallery--flex wide" style="--gallery-columns: 6">', $html);
		$this->assertStringContainsString('<div class="directive-gallery directive-gallery--grid" style="--gallery-columns: 2">', $html);
		$this->assertMatchesRegularExpression('#<figure class="directive-figure wide">\s*<img src="http://localhost/media/p.jpg" alt="A lake" />\s*<figcaption>A photo</figcaption>#', $html, 'The figure is the container; its image stands alone.');
		$this->assertMatchesRegularExpression('#<figure class="directive-figure">\s*<table>.*?</table>\s*<figcaption>Visitors</figcaption>#s', $html, 'A figure wraps anything, such as a table.');
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Rick"', $html);
		$this->assertStringContainsString('<p class="directive-embed directive-embed--link"><a href="https://example.com/talk">https://example.com/talk</a></p>', $html);

		// Without a title, the frame is named by its caption or provider.
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://player.vimeo.com/video/76979871?dnt=1" title="Our launch"', $html);
		$this->assertStringContainsString('<iframe class="directive-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Embedded content from YouTube"', $html);
		// A variant it doesn't have renders as Default.
		$this->assertStringContainsString("<aside class=\"directive-callout\" role=\"note\">\n\t\t<p>Plain note.</p></aside>", $html);
		$this->assertStringContainsString('<p>Kept as text</p>', $html);
		$this->assertStringContainsString('<p>Site badge</p>', $html, 'A directive works only as the kind it\'s registered as (D-531).');
		$this->assertStringContainsString('<p>A template alone</p>', $html, 'A directive no one registered is unknown (D-532).');
		$this->assertStringNotContainsString('directive-badge', $html, 'A directive works only in the forms it\'s registered for (D-530).');
		$this->assertStringContainsString('Read the <span class="badge badge--tip">inline</span> notes at', $html);
	}

	public function testDirectivesUseTheRequestsTheme(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/acme/alt/views/directives/callout.php', 'alt callout: <?= $directive->content() ?>');
		$this->writeTemporaryFile('user/content/index.md', "---\nid: d680e8a8-54a7-cbad-6d49-0c445cba2eba\ntitle: Home\n---\n:::callout\nHi\n:::\n");

		$this->boot('development');

		$kernel = $this->app->container()->make(Kernel::class);

		$this->assertStringContainsString('alt callout: <p>Hi</p>', (string) $kernel->handle(Request::create('/?theme=acme/alt'))->getBody());
	}

	public function testDirectivesRenderThemselvesUnlessTheChainHasATemplate(): void
	{
		$views    = $this->boot();
		$registry = $this->app->container()->make(DirectiveRegistry::class);
		$registry->register('acme/stamp', Stamp::class);

		$this->assertSame('<span class="directive-stamp">Paid &amp; done</span>', $views->directive('acme/stamp', ['text' => 'Paid & done'], '', new ViewContext()));
		$this->assertStringContainsString('<aside class="directive-callout"', $views->directive('callout', [], '<p>Hi</p>', new ViewContext()), 'A core directive renders the framework\'s template.');

		$this->view('directives/acme-stamp', 'child stamp: <?= e($directive->text) ?>');
		$this->view('directives/callout', 'child callout');

		$views = $this->boot();
		$this->app->container()->make(DirectiveRegistry::class)->register('acme/stamp', Stamp::class);

		$this->assertSame('child stamp: Paid', $views->directive('acme/stamp', ['text' => 'Paid'], '', new ViewContext()), 'A template in the chain wins.');
		$this->assertSame('child callout', $views->directive('callout', [], '', new ViewContext()));
	}
}
