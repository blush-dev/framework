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
use Blush\Component\ComponentListing;
use Blush\Component\ComponentRegistry;
use Blush\Component\PendingComponent;
use Blush\Component\Slots;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Support\RegistrationException;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Component\Card;
use Blush\Tests\Fixtures\Component\Chip;
use Blush\Tests\Fixtures\Component\Orphan;
use Blush\Tests\Fixtures\Directive\Stamp;
use Blush\Tests\Fixtures\View\Greeting;
use Blush\Theme\ThemeResolver;
use Blush\View\ContextProviders;
use Blush\View\RenderableFactory;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewNotFound;
use Blush\View\Views;

#[CoversClass(ComponentListing::class)]
#[CoversClass(ComponentRegistry::class)]
#[CoversClass(PendingComponent::class)]
#[CoversClass(RenderableFactory::class)]
#[CoversClass(ContextProviders::class)]
#[CoversClass(Views::class)]
final class ComponentsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	private function boot(): Views
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'production']);
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
		$this->view('components/app-box', '<div <?= $component->attributes(["data-tone" => $component->prop("tone", "plain")]) ?> data-n="<?= attr($component->prop("data-n", "")) ?>"><?= $component->content() ?>|<?= $component->slots->footer ?>|<?= $component->slots->has("header") ? "has header" : "no header" ?></div>');
		$this->view('page', '<?= $template->component("app/box", tone: "info")->content("<p>Body</p>")->slot("footer", "<small>Foot</small>") ?>');

		$views = $this->boot();

		$this->assertSame('<div class="component-box" data-tone="info" data-n=""><p>Body</p>|<small>Foot</small>|no header</div>', $views->render('page'));
		$this->assertSame('<div class="component-box extra" id="b" data-tone="plain" data-n="3">|' . '|no header</div>', $views->component('app/box', ['data-n' => '3', 'class' => 'extra', 'id' => 'b'], '', new Slots(), new ViewContext()));
		$this->assertTrue($views->hasComponent('app/box'));
		$this->assertFalse($views->hasComponent('callout'), 'A directive isn\'t a component (D-532).');
		$this->assertFalse($views->hasComponent('box'));
		$this->assertFalse($views->hasComponent('app/nope'));
		$this->assertFalse($views->hasComponent('../x'));
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

	public function testRegistrationNeedsAFullNameAndAComponentClass(): void
	{
		$registry = new ComponentRegistry();
		$registry->register('acme/card', Card::class);
		$registry->register('app/chip', Chip::class);

		$this->assertSame(Card::class, $registry->get('acme/card'));
		$this->assertSame(['acme', 'app'], $registry->namespaces());
		$this->assertCount(2, $registry);

		$registry->unregister('app/chip');
		$this->assertFalse($registry->isRegistered('app/chip'));

		try {
			$registry->register('card', Card::class);
			$this->fail('A short name should be refused.');
		} catch (RegistrationException $error) {
			$this->assertSame('"card" is not a valid component name; a component is always "{namespace}/{name}", such as "acme/card".', $error->getMessage());
		}

		$this->expectException(RegistrationException::class);
		// @phpstan-ignore argument.type (verifies the runtime guard)
		$registry->register('app/stamp', Stamp::class);
	}

	public function testListsEveryComponentTheChainCanRender(): void
	{
		$this->view('components/app-box', 'box');
		$this->view('components/cards/post', 'a subfolder is not a component');
		$this->view('components/loose', 'not named for a component');

		$views    = $this->boot();
		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('app/card', Card::class);
		$registry->register('app/chip', Chip::class);
		$registry->register('app/orphan', Orphan::class);

		$components = [];

		foreach ($views->components() as $component) {
			$components[(string) $component->name] = $component;
		}

		$this->assertSame(['app/box', 'app/card', 'app/chip', 'app/orphan'], array_keys($components));
		$this->assertSame(Card::class, $components['app/card']->class);
		$this->assertNull($components['app/box']->class);
		$this->assertStringEndsWith('resources/views/components/app-box.php', (string) $components['app/box']->file());
		$this->assertNull($components['app/card']->file());
		$this->assertTrue($components['app/chip']->rendersItself());
		$this->assertFalse($components['app/orphan']->rendersItself(), 'Its render() can return null.');

		// A class that picks its own view isn't missing a template; one
		// that relies on its components/ template is.
		$this->assertFalse($components['app/card']->isMissingTemplate());
		$this->assertFalse($components['app/chip']->isMissingTemplate());
		$this->assertTrue($components['app/orphan']->isMissingTemplate());
		$this->assertFalse($components['app/box']->isMissingTemplate());

		$this->assertSame(['loose.php'], array_map(basename(...), $views->strayComponentFiles()));
	}

	public function testComponentsRenderThemselvesUnlessTheChainHasATemplate(): void
	{
		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('app/chip', Chip::class);

		$this->assertSame('<span class="component-chip">Paid &amp; done</span>', $views->component('app/chip', ['text' => 'Paid & done'], '', new Slots(), new ViewContext()));

		$this->view('components/app-chip', 'site chip: <?= e($component->text) ?>');

		$views = $this->boot();
		$this->app->container()->make(ComponentRegistry::class)->register('app/chip', Chip::class);

		$this->assertSame('site chip: Paid', $views->component('app/chip', ['text' => 'Paid'], '', new Slots(), new ViewContext()), 'A template in the chain wins.');
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
			$views->component('callout', [], '', new Slots(), new ViewContext());
			$this->fail('A short name should throw.');
		} catch (ViewException $error) {
			$this->assertSame('"callout" is not a valid component name; a component is always "{namespace}/{name}", such as "default/callout" (D-532).', $error->getMessage());
		}

		$this->expectExceptionMessage('"../x" is not a valid component name;');
		$views->component('../x', [], '', new Slots(), new ViewContext());
	}

	public function testContentCantNameAComponent(): void
	{
		$this->view('components/app-box', 'a box');
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n::app/box[Just text]\n");

		$this->boot();

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString('<p>Just text</p>', $html, 'What content says is a directive (D-532).');
		$this->assertStringNotContainsString('a box', $html);
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
}
