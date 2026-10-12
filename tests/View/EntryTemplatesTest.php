<?php

/**
 * Entry template tests.
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
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesThemeViews;
use Blush\Theme\ThemeResolver;
use Blush\View\EntryTemplate;
use Blush\View\EntryTemplateRegistry;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\Views;

#[CoversClass(EntryTemplate::class)]
#[CoversClass(EntryTemplateRegistry::class)]
#[CoversClass(Views::class)]
final class EntryTemplatesTest extends TestCase
{
	use BootsScratchSite;
	use WritesThemeViews;

	private Application $app;

	private function boot(): Views
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'production']);
		$this->app->boot();

		$container = $this->app->container();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	public function testATemplateIsForItsTypesOrEveryType(): void
	{
		$any  = new EntryTemplate('template-wide', 'Wide');
		$page = new EntryTemplate('templates/compact', 'Compact List', 'One line each.', ['page']);

		$this->assertTrue($any->isFor('post'));
		$this->assertTrue($page->isFor('page'));
		$this->assertFalse($page->isFor('post'));
		$this->assertSame(['templates/compact', 'Compact List', 'One line each.'], [$page->name, $page->label, $page->description]);
	}

	public function testATemplateNeedsAViewNameAndALabel(): void
	{
		foreach (['template-wide.php', '../x', ''] as $name) {
			try {
				new EntryTemplate($name, 'Wide');
				$this->fail(sprintf('"%s" isn\'t a view name.', $name));
			} catch (ViewException) {
				$this->addToAssertionCount(1);
			}
		}

		$this->expectException(ViewException::class);
		new EntryTemplate('template-wide', ' ');
	}

	public function testTheRegistryOffersATypesTemplates(): void
	{
		$registry = new EntryTemplateRegistry();
		$registry->register(new EntryTemplate('template-wide', 'Wide'));
		$registry->register(new EntryTemplate('template-compact', 'Compact List', types: ['page']));
		$registry->register(new EntryTemplate('template-card', 'Card', types: ['post']));

		$this->assertSame(['template-wide', 'template-compact'], array_map(static fn (EntryTemplate $template): string => $template->name, $registry->forType('page')));
		$this->assertSame(['template-wide', 'template-card'], array_map(static fn (EntryTemplate $template): string => $template->name, $registry->forType('post')));

		$registry->register(new EntryTemplate('template-wide', 'Full Width'));
		$registry->remove('template-card');

		$this->assertSame('Full Width', $registry->get('template-wide')?->label);
		$this->assertFalse($registry->has('template-card'));
		$this->assertSame(['template-wide', 'template-compact'], array_keys($registry->all()));
	}

	public function testViewsOfferOnlyTemplatesTheChainHas(): void
	{
		$this->themeView('template-wide.php', 'wide');

		$views    = $this->boot();
		$registry = $this->app->container()->make(EntryTemplateRegistry::class);
		$registry->register(new EntryTemplate('template-wide', 'Wide'));
		$registry->register(new EntryTemplate('template-gone', 'Gone'));

		$this->assertSame(['template-wide'], array_map(static fn (EntryTemplate $template): string => $template->name, $views->entryTemplates('post')));
		$this->assertSame(['template-gone'], array_map(static fn (EntryTemplate $template): string => $template->name, $views->missingEntryTemplates()));
	}
}
