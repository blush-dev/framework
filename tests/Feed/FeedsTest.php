<?php

/**
 * Feed tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Feed;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Config\InvalidConfig;
use Blush\Core\Application;
use Blush\Feed\Feed;
use Blush\Feed\FeedBuilder;
use Blush\Feed\FeedConfig;
use Blush\Feed\FeedController;
use Blush\Feed\FeedFormat;
use Blush\Feed\FeedItem;
use Blush\Feed\FeedLinks;
use Blush\Feed\FeedRoutes;
use Blush\Feed\FeedServiceProvider;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\RouteTable;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\DocumentRenderer;

#[CoversClass(Feed::class)]
#[CoversClass(FeedBuilder::class)]
#[CoversClass(FeedConfig::class)]
#[CoversClass(FeedController::class)]
#[CoversClass(FeedFormat::class)]
#[CoversClass(FeedItem::class)]
#[CoversClass(FeedLinks::class)]
#[CoversClass(FeedRoutes::class)]
#[CoversClass(FeedServiceProvider::class)]
#[CoversClass(DocumentRenderer::class)]
final class FeedsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	/**
	 * Writes the standard content with feeds on posts and categories.
	 *
	 * @param array<string, mixed> $postFeed
	 */
	private function feeds(array $postFeed = ['taxonomy' => 'category'], ?string $feedConfig = null): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post'     => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives'], 'feed' => $postFeed],
				'category' => ['path' => 'topics', 'taxonomy' => true, 'term_collect' => 'post', 'feed' => true]
			],
			'home' => 'post'
		]);

		if ($feedConfig !== null) {
			$this->writeTemporaryFile('config/feed.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$feedConfig};\n");
		}

		$this->app = $this->site();
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function json(string $uri): array
	{
		$data = json_decode((string) $this->get($uri)->getBody(), true, 512, JSON_THROW_ON_ERROR);

		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * Returns a JSON feed's items.
	 *
	 * @param  array<string, mixed>       $json
	 * @return list<array<string, mixed>>
	 */
	private static function items(array $json): array
	{
		$items = [];

		foreach (is_array($json['items'] ?? null) ? $json['items'] : [] as $item) {
			if (is_array($item)) {
				/** @var array<string, mixed> $item */
				$items[] = $item;
			}
		}

		return $items;
	}

	public function testServesTheHomeFeedInEveryFormat(): void
	{
		$this->feeds();

		$rss  = $this->get('/feed');
		$atom = $this->get('/feed/atom');
		$json = $this->json('/feed/json');

		$this->assertSame('application/rss+xml; charset=UTF-8', $rss->getHeaderLine('Content-Type'));
		$this->assertSame('application/atom+xml; charset=UTF-8', $atom->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('<atom:link href="http://localhost/feed" rel="self" type="application/rss+xml"/>', (string) $rss->getBody());
		$this->assertStringContainsString('<link rel="self" type="application/atom+xml" href="http://localhost/feed/atom"/>', (string) $atom->getBody());

		// Newest file first; drafts, scheduled, unlisted, and landing pages stay out.
		$this->assertSame(
			['http://localhost/archives/hello', 'http://localhost/archives/spring', 'http://localhost/archives/welcome'],
			array_column(self::items($json), 'url')
		);
		// The home feed is the site's, whatever the landing page is titled.
		$this->assertSame('Blush', $json['title'] ?? null);
		$this->assertSame('http://localhost/', $json['home_page_url'] ?? null);

		$spring = self::items($json)[1] ?? [];

		$this->assertSame(['Art', 'Book Reviews'], $spring['tags'] ?? null);
		$this->assertSame([['name' => 'Justin Tadlock'], ['name' => 'A Guest']], $spring['authors'] ?? null, 'Authors by their entries\' titles.');
		$this->assertSame('2008-04-05T09:00:00-05:00', $spring['date_published'] ?? null);
		$this->assertSame("<p>Spring is here.</p>\n", $spring['content_html'] ?? null);
		$this->assertSame('Spring is here.', $spring['summary'] ?? null);
	}

	public function testServesTermAndCollectionFeeds(): void
	{
		$this->feeds();

		$art = $this->json('/topics/art/feed/json');

		$this->assertSame('Art | Blush', $art['title'] ?? null);
		$this->assertSame('http://localhost/topics/art', $art['home_page_url'] ?? null);
		$this->assertSame(['http://localhost/archives/spring'], array_column(self::items($art), 'url'));
		$this->assertSame(200, $this->get('/topics/book-reviews/feed')->getStatusCode());
		$this->assertStringContainsString('<title>Topics | Blush</title>', (string) $this->get('/topics/feed')->getBody());
		$this->assertSame(404, $this->get('/topics/nope/feed')->getStatusCode());
		$this->assertSame(404, $this->get('/authors/feed')->getStatusCode());
		$this->assertNull($this->app->container()->make(RouteTable::class)->named('post.collection.feed'));
	}

	public function testFeedArgumentsAndConfigShapeFeeds(): void
	{
		$this->feeds(['collection' => ['number' => 1, 'order' => 'asc']], "new Blush\\Feed\\FeedConfig(formats: [Blush\\Feed\\FeedFormat::Json], content: false, limit: 5)");

		$json  = $this->json('/feed/json');
		$items = self::items($json);

		$this->assertCount(1, $items);
		$this->assertSame('http://localhost/archives/welcome', $items[0]['url'] ?? null);
		// Without full content, the item's HTML is its excerpt.
		$this->assertSame("<p>Hello and welcome to my site.</p>", $items[0]['content_html'] ?? null);
		$this->assertSame(404, $this->get('/feed')->getStatusCode());
		$this->assertSame(404, $this->get('/feed/atom')->getStatusCode());

		$html = (string) $this->get('/')->getBody();

		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/feed/json" type="application/feed+json" title="Blush (JSON Feed)">', $html);
		$this->assertStringNotContainsString('application/rss+xml', $html);
	}

	public function testPagesAdvertiseTheirFeeds(): void
	{
		$this->feeds();

		$term = (string) $this->get('/topics/art')->getBody();

		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/feed" type="application/rss+xml" title="Blush (RSS)">', $term);
		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/topics/art/feed/atom" type="application/atom+xml" title="Art (Atom)">', $term);
		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/feed" type="application/rss+xml" title="Blush (RSS)">', (string) $this->get('/archives/spring')->getBody());
		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/topics/feed" type="application/rss+xml" title="Topics (RSS)">', (string) $this->get('/topics')->getBody());
	}

	public function testThemesCanOverrideFeedTemplates(): void
	{
		$this->feeds();
		$this->writeTemporaryFile('resources/views/feed-rss-category.php', 'category feed: <?= e($feed->title) ?>');

		$this->assertSame('category feed: Art | Blush', (string) $this->get('/topics/art/feed')->getBody());
		$this->assertStringStartsWith('<?xml', (string) $this->get('/feed')->getBody());
	}

	public function testConfigValidates(): void
	{
		$this->assertSame(['formats' => ['rss'], 'content' => false, 'limit' => 3], FeedConfig::fromArray(['formats' => ['rss'], 'content' => false, 'limit' => 3])->toArray());
		$this->assertSame('RSS', FeedFormat::Rss->label());

		try {
			FeedConfig::fromArray(['formats' => ['rdf']]);
			$this->fail('An unknown format should be refused.');
		} catch (InvalidConfig $error) {
			$this->assertStringContainsString('"rdf"', $error->getMessage());
		}

		$this->expectException(InvalidConfig::class);
		new FeedConfig(limit: 0);
	}
}
