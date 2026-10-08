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
use Blush\Content\ContentRepository;
use Blush\Core\Framework;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesContentConfig;
use Blush\Tests\WritesThemeViews;
use Blush\Theme\ThemeResolver;
use Blush\View\PageMarkup;
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
	use WritesContentConfig;
	use WritesThemeViews;

	private function views(): Views
	{
		$app = $this->scratchApplication(['APP_NAME' => 'Test Site', 'APP_TIMEZONE' => 'America/Chicago', 'APP_LOCALE' => 'en_US']);
		$app->boot();

		$container = $app->container();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	/**
	 * Writes a view into the test theme.
	 */
	private function view(string $name, string $code): void
	{
		$this->themeView("{$name}.php", $code);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function render(string $name, array $data = [], ?ViewContext $context = null): string
	{
		return $this->views()->render($name, $data, $context ?? new ViewContext(new PageMarkup('Test Site'), ['site' => new Site('Test Site', 'http://localhost', 'en_US', 'en-US')]));
	}

	public function testDatesAndTimesUseTheSitesFormats(): void
	{
		$this->writeTemporaryFile('user/data/settings.json', '{"app": {"dateFormat": "d MMMM y", "timeFormat": "HH:mm"}}');
		$this->view('dates', '<?= $template->date($when) ?>|<?= $template->time($when) ?>|<?= $template->datetime($when) ?>|<?= $template->date($when, \'short\') ?>|<?= $template->date($when, Blush\\Clock\\DateStyle::Medium) ?>|<?= $template->time($when, \'h:mm a\') ?>|<?= $template->datetime($when, \'long\', \'short\') ?>');

		$html = $this->render('dates', ['when' => new DateTimeImmutable('2026-01-05 23:30:00', new DateTimeZone('UTC'))]);

		$this->assertSame('5 January 2026|17:30|5 January 2026, 17:30|1/5/26|Jan 5, 2026|5:30 PM|January 5, 2026 at 5:30 PM', str_replace("\u{202F}", ' ', trim($html)));
	}

	public function testRendersLayoutsSectionsAndData(): void
	{
		$this->view('layouts/shell', '<html><?= $template->section(\'content\') ?>|<?= $template->section(\'aside\', \'no aside\') ?>|<?= e($title) ?>|<?= e($site->name) ?></html>');
		$this->view('layouts/page', '<?php $template->layout(\'shell\', title: \'From page\') ?><main><?= $template->section(\'content\') ?></main>');
		$this->view('page', '<?php $template->layout(\'page\') ?><?php $template->start(\'aside\') ?>Aside for <?= e($name) ?><?php $template->stop() ?>Hello, <?= e($name) ?>.<?= $template->hasSection(\'aside\') ? \'!\' : \'?\' ?>');

		$this->assertSame(
			'<html><main>Hello, Ada &lt;3.!</main>|Aside for Ada &lt;3|From page|Test Site</html>',
			$this->render('page', ['name' => 'Ada <3', 'title' => 'Ignored'])
		);
	}

	public function testPartialsSeeOnlyTheirDataAndSharedData(): void
	{
		$this->view('card', '[<?= e($label ?? \'no label\') ?>|<?= isset($secret) ? \'leak\' : \'sealed\' ?>|<?= e($site->name) ?>]');
		$this->view('list', '<?php $secret = 1 ?><?= $template->include(\'card\', label: \'One\') ?><?= $template->include(\'card\') ?>');

		$this->assertSame('[One|sealed|Test Site][no label|sealed|Test Site]', $this->render('list'));
	}

	public function testTemplatesCanOnlyUseThePublicApi(): void
	{
		$this->view('snoop', '<?= get_class($template) ?>:<?= $template->views->app->name ?>');

		try {
			$this->render('snoop');
			$this->fail('A template should not reach private state.');
		} catch (ViewException $error) {
			$this->assertStringContainsString('Cannot access private property', $error->getMessage());
			$this->assertStringContainsString('snoop.php', $error->getMessage());
		}
	}

	public function testIncludeHelpers(): void
	{
		$this->view('card', '[<?= e($label ?? \'-\') ?>]');
		$this->view('row', '<?= $index ?>:<?= e($entry) ?>:<?= e($label) ?>;');
		$this->view('none', 'none');
		$this->view('page', implode('|', [
			'<?= $template->include([\'card-special\', \'bad name!\', \'card\'], label: \'first\') ?>',
			'<?= $template->includeIf(\'sidebar\') ?>',
			'<?= $template->includeIf([\'sidebar\', \'card\']) ?>',
			'<?= $template->includeWhen(1, \'card\', label: \'when\') ?><?= $template->includeWhen([], \'card\') ?>',
			'<?= $template->includeUnless(false, \'card\', label: \'unless\') ?><?= $template->includeUnless(true, \'card\') ?>',
			'<?= $template->each(\'row\', [\'a\', \'b\'], as: \'entry\', empty: \'none\', label: \'x\') ?>',
			'<?= $template->each(\'row\', [], as: \'entry\', empty: \'none\') ?>',
			'<?= $template->each(\'row\', []) ?>'
		]));

		$this->assertSame('[first]||[-]|[when]|[unless]|0:a:x;1:b:x;|none|', $this->render('page'));
	}

	public function testIncludeThrowsWhenNoViewExists(): void
	{
		$this->view('page', '<?= $template->include([\'missing\', \'gone\']) ?>');

		$this->expectException(ViewNotFound::class);

		$this->render('page');
	}

	public function testTemplatesUseTemplateNotThis(): void
	{
		$this->view('named', '<?= get_class($template) ?>|<?= e($title) ?>');

		$this->assertSame('Blush\View\Template|Hi', $this->render('named', ['title' => 'Hi', 'template' => 'ignored']));

		$this->view('old', '<?= $this->section(\'content\') ?>');

		try {
			$this->render('old');
			$this->fail('A template should not have $this.');
		} catch (ViewException $error) {
			$this->assertStringStartsWith('Views use $template, not $this, in view ', $error->getMessage());
			$this->assertStringContainsString('old.php', $error->getMessage());
		}
	}

	public function testFailuresCloseTheirOutputBuffers(): void
	{
		$level = ob_get_level();

		$this->view('open-section', '<?php $template->start(\'a\') ?>never stopped');
		$this->view('throws', '<?php $template->start(\'a\') ?>partial output<?php throw new RuntimeException(\'Boom\') ?>');
		$this->view('positional', '<?= $template->include(\'throws\', \'value\') ?>');

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

		$this->view('stray-stop', '<?php $template->stop() ?>');
		$this->expectExceptionMessage('stop() was called without start().');
		$this->render('stray-stop');
	}

	public function testFindsViewsThroughTheChain(): void
	{
		$this->view('partials/footer', 'child footer');

		$views = $this->views();
		$files = $views->finder->all('partials/footer');

		$this->assertCount(2, $files, 'Only themes have views; the site has none (D-617).');
		$this->assertStringEndsWith('extensions/test/site/views/partials/footer.php', $files[0]);
		$this->assertSame(Framework::path('resources/themes/default/views/partials/footer.php'), $files[1]);
		$this->assertTrue($views->exists('single'));
		$this->assertFalse($views->exists('nope'));
		$this->assertSame('child footer', $views->partial('partials/footer', [], new ViewContext()));
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
		$this->view('layouts/wide', 'wide:<?= $template->section(\'content\') ?>');
		$this->view('layouts/narrow', 'narrow:<?= $template->section(\'content\') ?>');
		$this->view('page', '<?php $template->layout(\'narrow\') ?>body');

		$this->assertSame('narrow:body', $this->render('page'));
		$this->assertSame('wide:body', $this->render('page', [], new ViewContext(layout: 'wide')));
		$this->assertSame('narrow:body', $this->render('page', [], new ViewContext(layout: 'missing')));
		$this->assertSame('narrow:body', $this->render('page', [], new ViewContext(layout: '../x')));
	}

	public function testHelpers(): void
	{
		$this->themeView('helpers.php', <<<'PHP'
			<?php $template->head()->title('Helpers'); $template->head()->meta('robots', 'noindex') ?>
			<?= $template->t('pagination.page', page: 2, pages: 9) ?>|<?= $template->t('no.such.key') ?>|<?= $template->date($when) ?>|<?= $template->date($when, 'MMMM y') ?>|<?= $template->asset('style.css') !== '' ? 'asset' : '' ?>|<?= $template->asset('nope.css') ?>|<?= $template->bodyClass() ?>
			PHP);

		$context = new ViewContext(new PageMarkup('Test Site'));
		$context->addClass('one two', 'two', '1bad', 'three');

		$html = $this->render('helpers', ['when' => new DateTimeImmutable('2026-01-05 23:30:00', new DateTimeZone('UTC'))], $context);

		$this->assertSame('Page 2 of 9|no.such.key|January 5, 2026|January 2026|asset||one two three', trim($html));
		$this->assertSame('Helpers | Test Site', $context->head->documentTitle());
		$this->assertTrue($context->head->has('meta:robots'));

		$context->head->remove('meta:robots')->remove('meta:nope');

		$this->assertFalse($context->head->has('meta:robots'));
		$this->assertStringNotContainsString('robots', $context->head->render());
	}

	public function testTranslationsKeepHtmlMarkedWithRaw(): void
	{
		$this->themeView('marked.php', <<<'PHP'
			<?= e($template->t('pagination.page', page: raw('<b>2</b>'), pages: '<9>')) ?>|<?= attr($template->t('pagination.page', page: raw('<b>2</b>'), pages: 9)) ?>|<?= e($template->t('pagination.page', page: '<b>2</b>', pages: 9)) ?>
			PHP);

		$this->assertSame(
			'Page <b>2</b> of &lt;9&gt;|Page &lt;b&gt;2&lt;/b&gt; of 9|Page &lt;b&gt;2&lt;/b&gt; of 9',
			trim($this->render('marked')),
			'A raw() parameter goes in as HTML and the rest is escaped; attr() escapes it all (D-559).'
		);
	}

	public function testWidontJoinsTheLastTwoWords(): void
	{
		$this->themeView('widont.php', <<<'PHP'
			<?= $template->widont('Tom & Jerry go  home') ?>|<?= $template->widont('Three short words') ?>|<?= $template->widont('') ?>
			PHP);

		$this->assertSame('Tom &amp; Jerry go&nbsp;home|Three short words|', trim($this->render('widont')));
	}

	public function testParentsAncestorsAndChildren(): void
	{
		$this->contentConfig([
			'types'     => ['topic' => ['folder' => 'topics', 'hierarchical' => true, 'order' => 'position']],
			'relations' => ['topic' => ['kind' => 'classify', 'to' => ['topic']]]
		]);
		$this->writeTemporaryFile('user/content/topics/web.md', "---\ntitle: Web\n---\n");
		$this->writeTemporaryFile('user/content/topics/css.md', "---\ntitle: CSS\nparent: web\n---\n");
		$this->writeTemporaryFile('user/content/topics/grid.md', "---\ntitle: Grid\nparent: css\n---\n");
		$this->writeTemporaryFile('user/content/topics/flex.md', "---\ntitle: Flex\nparent: css\nstatus: draft\n---\n");
		$this->themeView('tree.php', <<<'PHP'
			<?= e(implode('/', array_map(fn ($entry) => $entry->title, $template->ancestors($term)))) ?>|<?= e($template->parent($term)?->title ?? '') ?>|<?= e(implode(',', array_map(fn ($entry) => $entry->title, $template->children($template->parent($term))))) ?>
			PHP);

		$app = $this->scratchApplication(['APP_NAME' => 'Test Site']);
		$app->boot();

		$container = $app->container();
		$term      = $container->make(ContentRepository::class)->named('topic', 'grid');
		$views     = $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
		$context   = new ViewContext(new PageMarkup('Test Site'), ['site' => new Site('Test Site', 'http://localhost', 'en_US', 'en-US')]);

		$this->assertSame('Web/CSS|CSS|Grid', trim($views->render('tree', ['term' => $term], $context)), 'Drafts aren\'t children.');
	}

	public function testInlineReadsOnlyServableThemeAssets(): void
	{
		$this->themeView('inline.php', <<<'PHP'
			<?= strlen($template->inline('style.css')) > 0 ? 'css' : '' ?>|<?= $template->inline('views/single.php') ?>|<?= $template->inline('theme.json') ?>|<?= $template->inline('../../../composer.json') ?>|<?= $template->inline('nope.svg') ?>
			PHP);

		$this->assertSame('css||||', trim($this->render('inline')));
	}
}
