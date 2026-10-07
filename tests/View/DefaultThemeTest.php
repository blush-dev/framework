<?php

/**
 * Default theme route coverage (the M5 exit criterion).
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use DOMDocument;
use Dom\HTMLDocument;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\CompiledRoute;
use Blush\Routing\RouteTable;
use Blush\Tests\Content\BuildsContentSite;

/**
 * The default theme renders every route type: each named route of a
 * jtcom-shaped site answers a sample URL with a 200 and a well-formed
 * document of its kind.
 */
#[CoversNothing]
final class DefaultThemeTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A sample URL for each route name.
	 */
	private const array SAMPLES = [
		'home'                              => '/',
		'home.paged'                        => '/page/2',
		'home.feed'                         => '/feed',
		'home.feed.atom'                    => '/feed/atom',
		'home.feed.json'                    => '/feed/json',
		'page.single'                       => '/about/biography',
		'post.single'                       => '/archives/spring',
		'post.collection.year'              => '/archives/2008',
		'post.collection.year.paged'        => '/archives/2008/page/2',
		'post.collection.month'             => '/archives/2008/04',
		'post.collection.month.paged'       => '/archives/2008/04/page/2',
		'post.collection.day'               => '/archives/2008/04/05',
		'post.collection.day.paged'         => '/archives/2008/04/05/page/2',
		'post.collection.hour'              => '/archives/2008/04/05/09',
		'post.collection.hour.paged'        => '/archives/2008/04/05/09/page/2',
		'post.collection.minute'            => '/archives/2008/04/05/09/00',
		'post.collection.minute.paged'      => '/archives/2008/04/05/09/00/page/2',
		'post.collection.second'            => '/archives/2008/04/05/09/00/00',
		'post.collection.second.paged'      => '/archives/2008/04/05/09/00/00/page/2',
		'category.collection'               => '/topics',
		'category.collection.paged'         => '/topics/page/2',
		'category.collection.feed'          => '/topics/feed',
		'category.collection.feed.atom'     => '/topics/feed/atom',
		'category.collection.feed.json'     => '/topics/feed/json',
		'category.single'                   => '/topics/art',
		'category.single.paged'             => '/topics/art/page/2',
		'category.single.feed'              => '/topics/art/feed',
		'category.single.feed.atom'         => '/topics/art/feed/atom',
		'category.single.feed.json'         => '/topics/art/feed/json',
		'author.collection'                 => '/authors',
		'author.collection.paged'           => '/authors/page/2',
		'author.single'                     => '/authors/justintadlock',
		'author.single.paged'               => '/authors/justintadlock/page/2',
		'post.editors.collection'           => '/archives/editors',
		'post.editors.single'               => '/archives/editors/sam',
		'post.editors.single.paged'         => '/archives/editors/sam/page/2',
		'post.editors.single.feed'          => '/archives/editors/sam/feed',
		'post.editors.single.feed.atom'     => '/archives/editors/sam/feed/atom',
		'post.editors.single.feed.json'     => '/archives/editors/sam/feed/json',
		'profile.single'                    => '/profiles/sam',
		'profile.single.paged'              => '/profiles/sam/page/2',
		'media'                             => '/media/pixel.png',
		'theme.asset'                       => '/themes/blush/default/style.css',
		'core.asset'                        => '/blush/js/player.js',
		'plugin.asset'                      => '/extensions/acme/stats/js/stats.js',
		'sitemap'                           => '/sitemap',
		'sitemap.xml'                       => '/sitemap.xml',
		'sitemap.type'                      => '/sitemap/post',
		'robots'                            => '/robots.txt',
		'llms'                              => '/llms.txt',
		'llms.markdown'                     => '/archives/spring.md'
	];

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'orderby' => 'published', 'number' => 1],
					'date_archives' => true,
					'time_archives' => true,
					'feed'          => ['taxonomy' => 'category'],
					'routing'       => ['prefix' => 'archives'],
					'people'        => ['editors' => ['aliases' => ['editor']]]
				],
				'profile' => [
					'kind'       => 'profiles',
					'path'       => 'profiles',
					'collection' => ['number' => 1]
				],
				'category' => [
					'path'            => 'topics',
					'taxonomy'        => true,
					'term_collect'    => 'post',
					'collection'      => ['number' => 1],
					'term_collection' => ['number' => 1],
					'feed'            => true
				],
				'author' => [
					'path'            => 'authors',
					'taxonomy'        => true,
					'field'           => 'authors',
					'field_aliases'   => ['author'],
					'collection'      => ['number' => 1],
					'term_collection' => ['number' => 1]
				]
			],
			'home' => 'post'
		]);
		$this->entry('_posts/2008-04-05-2.twin.md', "title: Twin\npublished: 2008-04-05 09:00:00\ncategory: art\nauthor: justintadlock", 'Same second as spring.');
		$this->entry('topics/news.md', 'title: News');
		$this->entry('_posts/2009-03-03.edited.md', "title: Edited\npublished: 2009-03-03\neditor: sam");
		$this->entry('_posts/2009-03-04.edited-again.md', "title: Edited Again\npublished: 2009-03-04\neditor: sam");
		$this->entry('profiles/sam.md', 'title: Sam', 'Edits things.');
		$this->entry('authors/justintadlock.md', 'title: Justin Tadlock');
		$this->entry('authors/guest.md', 'title: Guest');
		$this->writeTemporaryFile('extensions/acme/stats/plugin.json', '{"name": "acme/stats", "label": "Stats", "namespace": "stats"}');
		$this->writeTemporaryFile('extensions/acme/stats/js/stats.js', 'console.log("stats");');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/stats']);\n");
		$this->writeTemporaryFile('user/media/pixel.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));

		$this->app = $this->site();
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testRendersEveryRouteType(): void
	{
		$names = array_map(
			static fn (CompiledRoute $route): ?string => $route->name,
			$this->app->container()->make(RouteTable::class)->routes()
		) |> array_filter(...) |> array_values(...);

		sort($names);

		$this->assertSame([], array_values(array_diff($names, array_keys(self::SAMPLES))), 'Every route needs a sample URL.');
		$this->assertSame([], array_values(array_diff(array_keys(self::SAMPLES), $names)), 'Every sample needs a route.');

		foreach (self::SAMPLES as $name => $uri) {
			$response = $this->get($uri);
			$body     = (string) $response->getBody();
			$type     = strtok($response->getHeaderLine('Content-Type'), ';');

			$this->assertSame(200, $response->getStatusCode(), "{$name}: {$uri}");

			match ($type) {
				'text/html'                                                     => $this->assertPage($name, $body),
				'application/rss+xml', 'application/atom+xml', 'application/xml' => $this->assertXml($name, $body),
				'application/feed+json'                                         => $this->assertIsArray(json_decode($body, true, 512, JSON_THROW_ON_ERROR), $name),
				default                                                         => $this->assertNotSame('', $body, $name)
			};
		}
	}

	public function testRendersErrorsAndTheWelcomePage(): void
	{
		$response = $this->get('/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertPage('404', (string) $response->getBody());

		unlink($this->temporaryDirectory() . '/user/content/index.md');
		$this->contentConfig([]);
		$this->app = $this->site('development');

		$this->assertPage('welcome', (string) $this->get('/')->getBody());
	}

	/**
	 * Asserts an HTML page has the base layout's landmarks and one `<h1>`.
	 */
	private function assertPage(string $name, string $html): void
	{
		$document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

		$this->assertNotSame('', (string) $document->documentElement?->getAttribute('lang'), $name);
		$this->assertCount(1, $document->querySelectorAll('main'), $name);
		$this->assertCount(1, $document->querySelectorAll('h1'), $name);
		$this->assertNotNull($document->querySelector('a.skip-link[href="#main"]'), $name);
	}

	/**
	 * Asserts a document is well-formed XML.
	 */
	private function assertXml(string $name, string $xml): void
	{
		$document = new DOMDocument();

		$this->assertTrue(@$document->loadXML($xml), "{$name} is not well-formed XML:\n{$xml}");
	}
}
