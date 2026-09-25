<?php

/**
 * Content routing tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Content\Http\BasicPageRenderer;
use Blush\Content\Http\CollectionController;
use Blush\Content\Http\ContentController;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\DateArchiveController;
use Blush\Content\Http\HomeController;
use Blush\Content\Http\PageController;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\SingleController;
use Blush\Content\Http\TermController;
use Blush\Content\Routing\ContentRedirects;
use Blush\Content\Routing\ContentRoutes;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Routing\PageRoutes;
use Blush\Content\Routing\RefreshRouteCache;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\RouteTable;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ContentRoutes::class)]
#[CoversClass(PageRoutes::class)]
#[CoversClass(ContentRedirects::class)]
#[CoversClass(DataRedirects::class)]
#[CoversClass(RefreshRouteCache::class)]
#[CoversClass(ContentController::class)]
#[CoversClass(HomeController::class)]
#[CoversClass(CollectionController::class)]
#[CoversClass(DateArchiveController::class)]
#[CoversClass(SingleController::class)]
#[CoversClass(TermController::class)]
#[CoversClass(PageController::class)]
#[CoversClass(ContentPage::class)]
#[CoversClass(PageKind::class)]
#[CoversClass(BasicPageRenderer::class)]
final class ContentRoutingTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'orderby' => 'published', 'number' => 2],
					'date_archives' => true,
					'routing'       => ['prefix' => 'archives', 'paths' => ['single' => '{year}/{month}/{day}/{name}']]
				],
				'category' => [
					'path'         => 'topics',
					'taxonomy'     => true,
					'term_collect' => 'post'
				]
			],
			'home' => 'post'
		]);

		$this->app = $this->site();
	}

	private function get(string $uri, ?Application $app = null): ResponseInterface
	{
		return ($app ?? $this->app)->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	/**
	 * Asserts a response's status and, for a page, its `<h1>` and listed
	 * entries; for a redirect, its target.
	 *
	 * @param list<string> $listed
	 */
	private function assertPage(string $uri, int $status, string $title = '', array $listed = []): void
	{
		$response = $this->get($uri);
		$body     = (string) $response->getBody();

		$this->assertSame($status, $response->getStatusCode(), $uri);

		if ($status >= 300 && $status < 400) {
			$this->assertSame($title, $response->getHeaderLine('Location'), $uri);

			return;
		}

		if ($status === 200) {
			$this->assertStringContainsString("<h1>{$title}</h1>", $body, $uri);
			preg_match_all('/<li>(?:<a href="([^"]*)">)?/', $body, $links);
			$this->assertSame($listed, $links[1], $uri);
		}
	}

	public function testRegistersOneXRouteNames(): void
	{
		$table = $this->app->container()->make(RouteTable::class);
		$names = [
			'home', 'home.paged', 'page.single', 'media',
			'post.single', 'post.collection.year', 'post.collection.year.paged', 'post.collection.month',
			'post.collection.month.paged', 'post.collection.day', 'post.collection.day.paged',
			'category.collection', 'category.collection.paged', 'category.single', 'category.single.paged',
			'author.collection', 'author.single', 'author.single.paged'
		];

		foreach ($names as $name) {
			$this->assertNotNull($table->named($name), $name);
		}

		$this->assertNull($table->named('post.collection'));
		$this->assertNull($table->named('post.collection.hour'));
		$this->assertNull($table->named('page.collection'));
		$this->assertSame('/archives/{year}/{month}/{day}/{name}', $table->named('post.single')?->path());
		$this->assertSame([], $table->shadowed());
	}

	public function testServesTheHomeTypesCollection(): void
	{
		$this->assertPage('/', 200, 'Blog', ['/archives/2010/01/01/hello', '/archives/2008/04/05/spring']);
		$this->assertStringContainsString('<a rel="next" href="/page/2">', (string) $this->get('/')->getBody());
		$this->assertPage('/page/2', 200, 'Blog', ['/archives/2003/04/15/welcome']);
		$this->assertStringContainsString('<a rel="prev" href="/">', (string) $this->get('/page/2')->getBody());
		$this->assertPage('/page/1', 301, '/');
		$this->assertPage('/page/3', 404);
	}

	public function testServesSingleEntries(): void
	{
		$this->assertPage('/archives/2003/04/15/welcome', 200, 'Welcome');
		$this->assertStringContainsString('<p>Hello and welcome to my site.</p>', (string) $this->get('/archives/2003/04/15/welcome')->getBody());
		$this->assertPage('/archives/2008/04/20/rainy', 200, 'Rainy');
		$this->assertPage('/archives/2010/01/01/hello', 200, 'Hello Bundle');
		$this->assertPage('/archives/1999/01/01/welcome', 301, '/archives/2003/04/15/welcome');
		$this->assertPage('/archives/2020/01/01/unfinished', 404);
		$this->assertPage('/archives/2026/12/25/future', 404);
		$this->assertPage('/archives/2003/04/15/nope', 404);
	}

	public function testServesDateArchives(): void
	{
		$this->assertPage('/archives/2008', 200, '2008', ['/archives/2008/04/05/spring']);
		$this->assertPage('/archives/2008/04', 200, 'April 2008', ['/archives/2008/04/05/spring']);
		$this->assertPage('/archives/2003/04/15', 200, 'April 15, 2003', ['/archives/2003/04/15/welcome']);
		$this->assertPage('/archives/2008/04/page/1', 301, '/archives/2008/04');
		$this->assertPage('/archives/2008/04/page/2', 404);
		$this->assertPage('/archives/2008/13', 404);
		$this->assertPage('/archives/2008/02/30', 404);
		$this->assertPage('/archives/1999', 404);
	}

	public function testServesTermsAndTheirCollections(): void
	{
		$this->assertPage('/topics', 200, 'Topics', ['/topics/art']);
		$this->assertPage('/topics/art', 200, 'Art', ['/archives/2008/04/05/spring']);
		$this->assertPage('/topics/book-reviews', 200, 'Book Reviews', ['/archives/2008/04/05/spring']);
		$this->assertPage('/topics/art/page/1', 301, '/topics/art');
		$this->assertPage('/topics/art/page/2', 404);
		$this->assertPage('/topics/unused', 404);
		$this->assertPage('/authors/justintadlock', 200, 'justintadlock', ['/archives/2003/04/15/welcome', '/archives/2008/04/05/spring']);
	}

	public function testServesPagesByFolderPath(): void
	{
		$this->assertPage('/about', 200, 'About');
		$this->assertPage('/about/', 301, '/about');
		$this->assertPage('/about/biography', 200, 'Biography');
		$this->assertPage('/notes', 200, 'Notes');
		$this->assertPage('/_private', 404);
		$this->assertPage('/__drafts/idea', 404);
		$this->assertPage('/missing', 404);
		$this->assertPage('/missing/', 404);
		$this->assertPage('/_posts/2003-04-15.welcome', 404);
	}

	public function testRedirectsFromFrontMatterAndData(): void
	{
		$this->entry('_posts/2008-04-05.spring.md', "title: spring\npublished: 2008-04-05 09:00:00\nredirect_from: [/spring, 'https://old.example.com/old/spring/', '/bad/{x}']");
		$this->writeTemporaryFile('user/data/redirects.yaml', "/old-about: /about\n/promo: { to: 'https://example.com/sale', status: 302 }\n/spring: /not-this-one\n");
		$this->app = $this->site('development');

		$this->assertPage('/spring', 301, '/not-this-one');
		$this->assertPage('/old/spring', 301, '/archives/2008/04/05/spring');
		$this->assertPage('/old-about', 301, '/about');
		$this->assertPage('/promo', 302, 'https://example.com/sale');
		$this->assertPage('/bad/x', 404);
	}

	public function testDataRedirectsMayBeAList(): void
	{
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/blog/{slug}", "to": "/archives/{slug}", "status": 307}]');
		$this->app = $this->site('development');

		$this->assertPage('/blog/hello', 307, '/archives/hello');
	}

	public function testTheSitesOwnHomeRouteWins(): void
	{
		$this->writeTemporaryFile('config/routes.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			use Blush\Routing\Route;
			use Blush\Routing\RouteConfig;
			use Blush\Tests\Fixtures\Routing\Page;

			return new RouteConfig(routes: [Route::get('/', Page::class)->defaults(['name' => 'mine'])]);
			PHP);

		$this->assertSame('page:mine', (string) $this->get('/', $this->site())->getBody());
	}

	public function testTheHomePageIsIndexOrTheWelcomePage(): void
	{
		$this->get('/');
		$this->contentConfig([]);
		$this->app = $this->site();

		$this->assertPage('/', 200, 'Home');
		$this->assertStringContainsString('<title>' . $this->app->container()->make(AppConfig::class)->name . '</title>', (string) $this->get('/')->getBody());

		unlink($this->temporaryDirectory() . '/user/content/index.md');
		$this->app = $this->site('development');

		$this->assertStringContainsString('Blush Framework', (string) $this->get('/')->getBody());
		$this->assertSame(404, $this->get('/page/2')->getStatusCode());
	}

	public function testRedirectsReachACompiledRouteTable(): void
	{
		new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), ['APP_ENV' => 'production', 'APP_TIMEZONE' => 'America/Chicago'])->compile();

		$this->entry('about/index.md', "title: About\nredirect_from: /who");
		touch($this->temporaryDirectory() . '/user/content/about/index.md', time() + 10);

		$app = $this->site();
		$this->assertSame(404, $this->get('/who', $app)->getStatusCode());

		$this->assertTrue($this->tester($app)->run('content:index')->isSuccessful());
		$this->assertSame('/about', $this->get('/who', $this->site())->getHeaderLine('Location'));
	}

	private function tester(Application $app): CommandTester
	{
		return new CommandTester($app->container()->make(Console::class));
	}
}
