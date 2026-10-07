<?php

/**
 * View engine tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use ReflectionClass;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Directive\PendingDirective;
use Blush\Support\RegistrationException;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\View\BladeEngine;
use Blush\Tests\Fixtures\View\TokenEngine;
use Blush\Theme\ThemeResolver;
use Blush\View\Engine\PhpEngine;
use Blush\View\Engine\ViewEngineRegistrar;
use Blush\View\Engine\ViewEngineRegistry;
use Blush\View\Engine\ViewEngines;
use Blush\View\Engine\ViewEngine;
use Blush\View\Engine\ViewEngineType;
use Blush\View\Head;
use Blush\View\ReturnsHtml;
use Blush\View\SafeHtml;
use Blush\View\Site;
use Blush\View\Template;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewFinder;
use Blush\View\Views;

#[CoversClass(ViewEngines::class)]
#[CoversClass(ViewEngineRegistry::class)]
#[CoversClass(ViewEngineRegistrar::class)]
#[CoversClass(ViewEngineType::class)]
#[CoversClass(PhpEngine::class)]
#[CoversClass(ViewFinder::class)]
#[CoversClass(Views::class)]
#[CoversClass(Template::class)]
final class ViewEnginesTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Returns the active chain's views, with the `.tpl` engine (and any
	 * others) registered after the built-ins.
	 *
	 * @param array<string, class-string<ViewEngine>> $engines
	 */
	private function views(array $engines = ['tpl' => TokenEngine::class]): Views
	{
		$app       = $this->scratchApplication(['APP_NAME' => 'Test Site']);
		$container = $app->container();

		foreach ($engines as $extension => $class) {
			$container->make(ViewEngineRegistry::class)->register($extension, $class);
		}

		$app->boot();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	private function context(): ViewContext
	{
		return new ViewContext(new Head('Test Site'), ['site' => new Site('Test Site', 'http://localhost', 'en_US', 'en-US')]);
	}

	public function testTheRegistrySeedsPhpFirst(): void
	{
		$registry = new ViewEngineRegistry();
		$registry->register('twig', TokenEngine::class);
		new ViewEngineRegistrar($registry)->register();

		$this->assertSame(['twig' => TokenEngine::class, 'php' => PhpEngine::class], $registry->all());
	}

	public function testExtensionsMustBeBare(): void
	{
		foreach (['.twig', 'Twig', 'blade..php', ''] as $extension) {
			try {
				new ViewEngineRegistry()->register($extension, TokenEngine::class);
				$this->fail("\"{$extension}\" should be refused.");
			} catch (RegistrationException $error) {
				$this->assertStringContainsString('without the leading dot', $error->getMessage());
			}
		}
	}

	public function testEnginesMixAcrossLayoutsPartialsAndComponents(): void
	{
		$this->writeTemporaryFile('resources/views/layouts/plain.php', '<main><?= $template->section(\'content\') ?>|<?= $template->section(\'aside\') ?></main>');
		$this->writeTemporaryFile('resources/views/partials/note.php', '<i>note</i>');
		$this->writeTemporaryFile('resources/views/components/app-badge.tpl', '<span>badge</span>');
		$this->writeTemporaryFile('resources/views/page.tpl', '{layout:plain}{set:aside}Hi, {name}. {include:partials/note} {component:app/badge}');

		$this->assertSame(
			'<main>Hi, Ada &lt;3. <i>note</i> <span>badge</span>|<b>set</b></main>',
			$this->views()->render('page', ['name' => 'Ada <3'], $this->context())
		);
	}

	public function testTheFirstEngineWinsInAFolderAndFoldersStillDecide(): void
	{
		$this->writeTemporaryFile('resources/views/both.php', 'php');
		$this->writeTemporaryFile('resources/views/both.tpl', 'tpl');
		$this->writeTemporaryFile('resources/views/themes/blush/default/single.tpl', 'site single');

		$views = $this->views();

		$this->assertSame('php', $views->render('both', [], $this->context()));
		$this->assertCount(2, $views->finder->all('both'));
		$this->assertStringEndsWith('single.tpl', $views->finder->find('single') ?? '');
		$this->assertSame(['php', 'tpl'], $views->finder->extensions());
	}

	public function testTheLongestExtensionPicksTheEngine(): void
	{
		$this->writeTemporaryFile('resources/views/card.blade.php', 'card');
		$this->writeTemporaryFile('resources/views/components/app-chip.blade.php', 'chip');

		$views = $this->views(['blade.php' => BladeEngine::class]);

		$this->assertSame('blade:card', $views->render('card', [], $this->context()));
		$this->assertTrue($views->hasComponent('app/chip'));
		$this->assertNotContains('app/card.blade', array_map(strval(...), array_column($views->components(), 'name')));
		$this->assertSame([], array_filter($views->strayComponentFiles(), static fn (string $file): bool => str_contains($file, 'app-chip')));
	}

	public function testAFileNoEngineRendersFails(): void
	{
		$engines = new ViewEngines(new ViewEngineRegistry(), $this->scratchApplication()->container());

		$this->assertNull($engines->extensionOf('page.php'));
		$this->expectException(ViewException::class);
		$engines->forFile('page.php');
	}

	public function testHtmlIsMarkedForEnginesThatEscape(): void
	{
		$marked = array_map(
			static fn (ReflectionMethod $method): string => $method->getName(),
			array_filter(new ReflectionClass(Template::class)->getMethods(), static fn (ReflectionMethod $method): bool => $method->getAttributes(ReturnsHtml::class) !== [])
		);

		$this->assertSame(['section', 'include', 'includeIf', 'includeWhen', 'includeUnless', 'each', 'component', 'directive', 'icon', 'region', 'cache', 'widont', 'avatar'], array_values($marked));
		$this->assertContains(SafeHtml::class, class_implements(PendingDirective::class));
		$this->assertContains(SafeHtml::class, class_implements(Head::class));
	}
}
