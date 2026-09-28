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
use Blush\Theme\ThemeResolver;
use Blush\View\Component\Component;
use Blush\View\Component\ComponentFactory;
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
use Blush\View\ViewNotFound;
use Blush\View\Views;

#[CoversClass(Component::class)]
#[CoversClass(ComponentFactory::class)]
#[CoversClass(ComponentRegistrar::class)]
#[CoversClass(ComponentRegistry::class)]
#[CoversClass(ComponentType::class)]
#[CoversClass(ComponentListing::class)]
#[CoversClass(Embed::class)]
#[CoversClass(PendingComponent::class)]
#[CoversClass(Slots::class)]
#[CoversClass(ComponentDirectives::class)]
#[CoversClass(ContextProviders::class)]
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

	public function testTemplateOnlyComponentsGetPropsAndSlots(): void
	{
		$this->view('components/box', '<div class="<?= attr($tone ?? "plain") ?>" data-n="<?= attr($props["data-n"] ?? "") ?>"><?= $slot ?>|<?= $slots->footer ?>|<?= isset($slots->header) ? "has header" : "no header" ?></div>');
		$this->view('page', '<?= $template->component("box", tone: "info")->content("<p>Body</p>")->slot("footer", "<small>Foot</small>") ?>');

		$views = $this->boot();

		$this->assertSame('<div class="info" data-n=""><p>Body</p>|<small>Foot</small>|no header</div>', $views->render('page'));
		$this->assertSame('<div class="plain" data-n="3">|' . '|no header</div>', $views->component('box', ['data-n' => '3'], '', new Slots(), new ViewContext()));
		$this->assertTrue($views->hasComponent('box'));
		$this->assertTrue($views->hasComponent('callout'));
		$this->assertFalse($views->hasComponent('nope'));
		$this->assertFalse($views->hasComponent('../x'));
	}

	public function testClassBackedComponentsBuildTheirProps(): void
	{
		$this->view('components/card', '<?= e($heading) ?>:<?= $columns + 1 ?>:<?= isset($secret) ? "leak" : "sealed" ?>:<?= e($component->secret()) ?>:<?= e($props["extra"] ?? "") ?>');
		$this->view('components/card-wide', 'wide <?= e($title) ?>');

		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('card', Card::class);

		$this->assertSame('HI:3:sealed:hidden:x', $views->component('card', ['title' => 'hi', 'columns' => '2', 'extra' => 'x'], '', new Slots(), new ViewContext()));
		$this->assertSame('wide yo', $views->component('card', ['title' => 'yo', 'wide' => 'yes'], '', new Slots(), new ViewContext()));
		$this->assertSame('', $views->component('card', ['title' => 'skip'], '', new Slots(), new ViewContext()));
		$this->assertTrue($views->hasComponent('card'));

		$this->expectException(ViewException::class);
		$views->component('card', ['title' => ['not', 'a', 'string']], '', new Slots(), new ViewContext());
	}

	public function testListsEveryComponentTheChainCanRender(): void
	{
		$this->view('components/box', 'box');
		$this->view('components/cards/post', 'post card');
		$this->view('components/callout', 'site callout');

		$views    = $this->boot();
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('card', Card::class);
		$registry->register('orphan', Orphan::class);

		$components = [];

		foreach ($views->components() as $component) {
			$components[$component->key] = $component;
		}

		$this->assertSame(['box', 'callout', 'card', 'cards/post', 'embed', 'figure', 'gallery', 'orphan'], array_keys($components));
		$this->assertSame([true, true, true, true], [$components['callout']->isCore, $components['embed']->isCore, $components['figure']->isCore, $components['gallery']->isCore]);
		$this->assertFalse($components['box']->isCore);
		$this->assertSame(Embed::class, $components['embed']->class);
		$this->assertNull($components['callout']->class);
		$this->assertCount(2, $components['callout']->files);
		$this->assertStringEndsWith('resources/views/components/callout.php', (string) $components['callout']->file());
		$this->assertStringEndsWith('themes/default/views/components/gallery.php', (string) $components['gallery']->file());
		$this->assertNull($components['card']->file());

		// A class that picks its own view isn't missing a template; one
		// that relies on components/{key} is.
		$this->assertFalse($components['card']->isMissingTemplate());
		$this->assertTrue($components['orphan']->isMissingTemplate());
		$this->assertFalse($components['box']->isMissingTemplate());
	}

	public function testOnlyCoreComponentsWithClassesAreRegistered(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(ComponentRegistry::class);

		$this->assertSame(['embed' => Embed::class], $registry->all());
		$this->assertNull(ComponentType::Callout->className());
	}

	public function testMissingAndInvalidComponentsThrow(): void
	{
		$views = $this->boot();

		try {
			$views->component('nope', [], '', new Slots(), new ViewContext());
			$this->fail('A missing component should throw.');
		} catch (ViewNotFound $error) {
			$this->assertSame('No view found for: components/nope.', $error->getMessage());
		}

		$this->expectExceptionMessage('"../x" is not a valid component key.');
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
			MD);

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
