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

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Framework;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeResolver;
use Blush\View\Head;
use Blush\View\Site;
use Blush\View\Template;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewFinder;
use Blush\View\ViewNotFound;
use Blush\View\Views;
use Blush\View\ViewServiceProvider;

#[CoversClass(Views::class)]
#[CoversClass(Template::class)]
#[CoversClass(ViewContext::class)]
#[CoversClass(ViewFinder::class)]
#[CoversClass(ViewFactory::class)]
#[CoversClass(ViewException::class)]
#[CoversClass(ViewNotFound::class)]
#[CoversClass(Site::class)]
#[CoversClass(ViewServiceProvider::class)]
final class ViewsTest extends TestCase
{
	use BootsScratchSite;

	private function views(): Views
	{
		$app = $this->scratchApplication(['APP_NAME' => 'Test Site', 'APP_TIMEZONE' => 'America/Chicago', 'APP_LOCALE' => 'en_US']);
		$app->boot();

		$container = $app->container();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	/**
	 * Writes a site view under `resources/views`.
	 */
	private function view(string $name, string $code): void
	{
		$this->writeTemporaryFile("resources/views/{$name}.php", $code);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function render(string $name, array $data = [], ?ViewContext $context = null): string
	{
		return $this->views()->render($name, $data, $context ?? new ViewContext(new Head('Test Site'), ['site' => new Site('Test Site', 'http://localhost', 'en_US', 'en-US')]));
	}

	public function testRendersLayoutsSectionsAndData(): void
	{
		$this->view('layouts/shell', '<html><?= $this->section(\'content\') ?>|<?= $this->section(\'aside\', \'no aside\') ?>|<?= e($title) ?>|<?= e($site->name) ?></html>');
		$this->view('layouts/page', '<?php $this->layout(\'shell\', title: \'From page\') ?><main><?= $this->section(\'content\') ?></main>');
		$this->view('page', '<?php $this->layout(\'page\') ?><?php $this->start(\'aside\') ?>Aside for <?= e($name) ?><?php $this->stop() ?>Hello, <?= e($name) ?>.<?= $this->hasSection(\'aside\') ? \'!\' : \'?\' ?>');

		$this->assertSame(
			'<html><main>Hello, Ada &lt;3.!</main>|Aside for Ada &lt;3|From page|Test Site</html>',
			$this->render('page', ['name' => 'Ada <3', 'title' => 'Ignored'])
		);
	}

	public function testPartialsSeeOnlyTheirDataAndSharedData(): void
	{
		$this->view('card', '[<?= e($label ?? \'no label\') ?>|<?= isset($secret) ? \'leak\' : \'sealed\' ?>|<?= e($site->name) ?>]');
		$this->view('list', '<?php $secret = 1 ?><?= $this->insert(\'card\', label: \'One\') ?><?= $this->insert(\'card\') ?>');

		$this->assertSame('[One|sealed|Test Site][no label|sealed|Test Site]', $this->render('list'));
	}

	public function testTemplatesCanOnlyUseThePublicApi(): void
	{
		$this->view('snoop', '<?= get_class($this) ?>:<?= $this->views->app->name ?>');

		try {
			$this->render('snoop');
			$this->fail('A template should not reach private state.');
		} catch (ViewException $error) {
			$this->assertStringContainsString('Cannot access private property', $error->getMessage());
			$this->assertStringContainsString('snoop.php', $error->getMessage());
		}
	}

	public function testFailuresCloseTheirOutputBuffers(): void
	{
		$level = ob_get_level();

		$this->view('open-section', '<?php $this->start(\'a\') ?>never stopped');
		$this->view('throws', '<?php $this->start(\'a\') ?>partial output<?php throw new RuntimeException(\'Boom\') ?>');
		$this->view('positional', '<?= $this->insert(\'throws\', \'value\') ?>');

		$cases = [
			'open-section' => 'Section "a" was never stopped',
			'throws'       => 'Boom in view',
			'positional'   => 'Pass view data by name'
		];

		foreach ($cases as $name => $message) {
			try {
				$this->render($name);
				$this->fail("{$name} should fail.");
			} catch (ViewException $error) {
				$this->assertStringContainsString($message, $error->getMessage());
			}

			$this->assertSame($level, ob_get_level(), $name);
		}

		$this->view('stray-stop', '<?php $this->stop() ?>');
		$this->expectExceptionMessage('stop() was called without start().');
		$this->render('stray-stop');
	}

	public function testFindsViewsThroughTheChain(): void
	{
		$this->view('parts/footer', 'site footer');
		$this->writeTemporaryFile('resources/views/themes/default/parts/footer.php', 'theme-scoped footer');

		$views = $this->views();
		$files = $views->finder->all('parts/footer');

		$this->assertCount(3, $files);
		$this->assertStringEndsWith('resources/views/themes/default/parts/footer.php', $files[0]);
		$this->assertSame(Framework::path('resources/themes/default/views/parts/footer.php'), $files[2]);
		$this->assertTrue($views->exists('single'));
		$this->assertFalse($views->exists('nope'));
		$this->assertSame('theme-scoped footer', $views->partial('parts/footer', [], new ViewContext()));
		$this->assertNull($views->finder->first(['../secret', 'nope']));
		$this->assertSame('single', $views->finder->first(['bad name', 'single'])[0] ?? null);
		$this->assertFalse(ViewFinder::isValidName('a/../b'));
		$this->assertFalse(ViewFinder::isValidName('a.php'));
	}

	public function testMissingAndInvalidViewsThrow(): void
	{
		try {
			$this->render('nope');
			$this->fail('A missing view should throw.');
		} catch (ViewNotFound $error) {
			$this->assertSame('No view found for: nope.', $error->getMessage());
		}

		$this->expectException(ViewException::class);
		$this->views()->exists('../etc/passwd');
	}

	public function testTheContextLayoutReplacesThePagesLayout(): void
	{
		$this->view('layouts/wide', 'wide:<?= $this->section(\'content\') ?>');
		$this->view('layouts/narrow', 'narrow:<?= $this->section(\'content\') ?>');
		$this->view('page', '<?php $this->layout(\'narrow\') ?>body');

		$this->assertSame('narrow:body', $this->render('page'));
		$this->assertSame('wide:body', $this->render('page', [], new ViewContext(layout: 'wide')));
		$this->assertSame('narrow:body', $this->render('page', [], new ViewContext(layout: 'missing')));
		$this->assertSame('narrow:body', $this->render('page', [], new ViewContext(layout: '../x')));
	}

	public function testHelpers(): void
	{
		$this->writeTemporaryFile('resources/views/helpers.php', <<<'PHP'
			<?php $this->head()->title('Helpers'); $this->head()->meta('robots', 'noindex') ?>
			<?= $this->t('pagination.page', page: 2, pages: 9) ?>|<?= $this->t('no.such.key') ?>|<?= $this->date($when) ?>|<?= $this->date($when, 'MMMM y') ?>|<?= $this->asset('style.css') !== '' ? 'asset' : '' ?>|<?= $this->asset('nope.css') ?>|<?= $this->bodyClass() ?>
			PHP);

		$context = new ViewContext(new Head('Test Site'));
		$context->addClass('one two', 'two', '1bad', 'three');

		$html = $this->render('helpers', ['when' => new DateTimeImmutable('2026-01-05 23:30:00', new DateTimeZone('UTC'))], $context);

		$this->assertSame('Page 2 of 9|no.such.key|January 5, 2026|January 2026|asset||one two three', trim($html));
		$this->assertSame('Helpers | Test Site', $context->head->documentTitle());
		$this->assertTrue($context->head->has('meta:robots'));
	}
}
