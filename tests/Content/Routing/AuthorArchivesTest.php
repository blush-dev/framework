<?php

/**
 * Author archive tests.
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
use Blush\Content\AuthorArchives;
use Blush\Content\ContentRepository;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\AuthorController;
use Blush\Content\Http\AuthorsController;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Application;
use Blush\Export\UrlSource;
use Blush\Content\Routing\ContentExportUrls;
use Blush\Feed\FeedExportUrls;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\RouteTable;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\Hierarchy;

#[CoversClass(AuthorsController::class)]
#[CoversClass(AuthorController::class)]
#[CoversClass(AuthorArchives::class)]
final class AuthorArchivesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Writes the standard content with a blog that credits authors, a
	 * feed, and two posts per page, then boots it.
	 *
	 * @param array<string, mixed> $post Options for the post type.
	 */
	private function boot(array $post = []): Application
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'orderby' => 'published', 'number' => 1],
					'date_archives' => true,
					'feed'          => true,
					'routing'       => ['prefix' => 'archives'],
					...$post
				],
				'category' => [
					'path'         => 'topics',
					'taxonomy'     => true,
					'term_collect' => 'post'
				]
			],
			'home' => 'post'
		]);
		$this->entry('authors/sam.md', 'title: Sam', 'Credited by nothing.');

		return $this->site();
	}

	private function get(Application $app, string $uri): ResponseInterface
	{
		return $app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testListsATypesAuthorsByName(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors');
		$body     = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="archive-header__title">Authors</h1>', $body, 'The authors type\'s label without an authors page.');

		preg_match_all('#<h2 class="authors__name"><a href="([^"]*)">([^<]*)</a>#', $body, $authors);

		$this->assertSame(['A Guest', 'Justin Tadlock'], $authors[2], 'By name; Sam has no posts, so no archive.');
		$this->assertSame(['/archives/authors/guest', '/archives/authors/justintadlock'], $authors[1]);
		$this->assertStringContainsString('<p>Writes things.</p>', $body, 'Each with their bio.');
	}

	public function testATypesAuthorsPageIntroducesTheList(): void
	{
		$this->entry('_posts/_authors.md', 'title: Our Writers', 'The people behind the blog.');

		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/authors')->getBody();

		$this->assertStringContainsString('<h1 class="archive-header__title">Our Writers</h1>', $body);
		$this->assertStringContainsString('The people behind the blog.', $body);
		$this->assertSame(404, $this->get($app, '/archives/_authors')->getStatusCode(), 'It has no address of its own.');
		$this->assertStringNotContainsString('Our Writers', (string) $this->get($app, '/feed/json')->getBody(), 'Nor is it in the feed.');
	}

	public function testServesAnAuthorsArchiveInAType(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors/justintadlock');
		$body     = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="archive-header__title">Justin Tadlock</h1>', $body);
		$this->assertStringContainsString('<p>Writes things.</p>', $body, 'The bio introduces it.');
		$this->assertStringContainsString('<a href="/archives/spring">', $body, 'Newest first, one a page.');
		$this->assertStringContainsString('<link rel="next" href="http://localhost/archives/authors/justintadlock/page/2">', $body);
		$this->assertStringContainsString('<meta property="og:type" content="profile">', $body);
		$this->assertStringContainsString('href="http://localhost/archives/authors/justintadlock/feed/json"', $body, 'The author\'s feed is advertised.');
		$this->assertStringContainsString('is-author type-post', $body);

		$this->assertSame(200, $this->get($app, '/archives/authors/justintadlock/page/2')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/justintadlock/page/3')->getStatusCode());
		$this->assertSame('/archives/authors/justintadlock', $this->get($app, '/archives/authors/justintadlock/page/1')->getHeaderLine('Location'));
		$this->assertSame(404, $this->get($app, '/archives/authors/sam')->getStatusCode(), 'No posts credit Sam.');
		$this->assertSame(404, $this->get($app, '/archives/authors/nobody')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/authors/justintadlock')->getStatusCode(), 'Authors have no pages outside a type.');
	}

	public function testServesAnAuthorsFeed(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors/guest/feed/json');
		$feed     = json_decode((string) $response->getBody(), true);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertIsArray($feed);
		$this->assertSame('A Guest | Posts | Blush', $feed['title'] ?? null);
		$this->assertSame('http://localhost/archives/authors/guest', $feed['home_page_url'] ?? null);
		$this->assertSame(['http://localhost/archives/spring'], array_column(is_array($feed['items'] ?? null) ? $feed['items'] : [], 'url'));
		$this->assertSame(200, $this->get($app, '/archives/authors/guest/feed')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/nobody/feed')->getStatusCode());
	}

	public function testBylinesLinkToTheTypesArchive(): void
	{
		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<a class="entry-meta__author" href="/archives/authors/justintadlock">Justin Tadlock</a>', $body);
		$this->assertStringContainsString('<a class="entry-meta__author" href="/archives/authors/guest">A Guest</a>', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/justintadlock">', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/guest">', $body, 'One tag per author.');
	}

	public function testATypeSetsItsWordOrHasNoArchives(): void
	{
		$app = $this->boot(['routing' => ['prefix' => 'archives', 'authors' => 'writers']]);

		$this->assertSame(200, $this->get($app, '/archives/writers')->getStatusCode());
		$this->assertSame(200, $this->get($app, '/archives/writers/justintadlock')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/justintadlock')->getStatusCode());
		$this->assertNotNull($app->container()->make(RouteTable::class)->named('post.authors.single'));

		$bylines = [
			'<span class="entry-meta__author">Justin Tadlock</span>' => ['routing' => ['prefix' => 'archives', 'authors' => false]],
			''                                                       => ['authors' => false]
		];

		foreach ($bylines as $byline => $options) {
			$app   = $this->boot($options);
			$urls  = $app->container()->make(ContentUrls::class);
			$types = $app->container()->make(ContentTypes::class);
			$body  = (string) $this->get($app, '/archives/spring')->getBody();

			$this->assertSame(404, $this->get($app, '/archives/authors')->getStatusCode());
			$this->assertNull($app->container()->make(RouteTable::class)->named('post.authors.single'));
			$this->assertNull($urls->author($types->get('post'), 'justintadlock'));

			if ($byline === '') {
				$this->assertStringNotContainsString('entry-meta__authors', $body, 'Posts don\'t credit authors at all.');
			} else {
				$this->assertStringContainsString($byline, $body, 'A byline without a link.');
			}
		}
	}

	public function testArchivesHaveTheirOwnTemplates(): void
	{
		$app    = $this->boot();
		$post   = $app->container()->make(ContentTypes::class)->get('post');
		$author = $app->container()->make(ContentRepository::class)->term('author', 'justintadlock');

		$this->assertSame(['authors-post', 'authors', 'collection'], Hierarchy::forPage(new ContentPage(PageKind::Authors, 'Authors', type: $post))->names);
		$this->assertSame(
			['author-post-justintadlock', 'author-post', 'author', 'collection'],
			Hierarchy::forPage(new ContentPage(PageKind::Author, 'Justin Tadlock', entry: $author, type: $post))->names
		);
	}

	public function testExportsAndMapsTheArchives(): void
	{
		$app   = $this->boot();
		$paths = [];

		foreach ([ContentExportUrls::class, FeedExportUrls::class] as $source) {
			$urls = $app->container()->make($source);
			$this->assertInstanceOf(UrlSource::class, $urls);

			foreach ($urls->urls() as $url) {
				$paths[] = $url->path;
			}
		}

		foreach (['/archives/authors', '/archives/authors/guest', '/archives/authors/justintadlock', '/archives/authors/justintadlock/feed/json'] as $path) {
			$this->assertContains($path, $paths);
		}

		$this->assertNotContains('/archives/authors/sam', $paths);
	}
}
