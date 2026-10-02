<?php

/**
 * Page cache and HTTP caching tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Cache\BumpContentVersion;
use Blush\Cache\CacheServiceProvider;
use Blush\Cache\ContentCache;
use Blush\Cache\ContentVersion;
use Blush\Cache\PageCache;
use Blush\Content\Index\Indexer;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Middleware\ConditionalGet;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(PageCache::class)]
#[CoversClass(ConditionalGet::class)]
#[CoversClass(ContentVersion::class)]
#[CoversClass(ContentCache::class)]
#[CoversClass(BumpContentVersion::class)]
#[CoversClass(CacheServiceProvider::class)]
#[CoversClass(Kernel::class)]
final class PageCacheTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * @param array<string, string> $headers
	 */
	private function get(Application $app, string $uri, array $headers = [], string $method = 'GET'): ResponseInterface
	{
		return $app->container()->make(Kernel::class)->handle(Request::create($uri, $method, $headers));
	}

	private function cacheConfig(string $arguments): void
	{
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig({$arguments});\n");
	}

	public function testPagesAreServedFromTheCacheUntilTheVersionChanges(): void
	{
		$this->standardContent();

		$app   = $this->site();
		$first = $this->get($app, '/archives/spring');

		$this->assertSame('miss', $first->getHeaderLine(PageCache::HEADER));
		$this->assertSame('public, max-age=0, must-revalidate', $first->getHeaderLine('Cache-Control'));

		// A new process (another request) is served the stored page, even
		// though the template changed underneath it.
		$this->writeTemporaryFile('resources/views/single-post.php', 'changed');

		$again = $this->site();
		$hit   = $this->get($again, '/archives/spring');

		$this->assertSame('hit', $hit->getHeaderLine(PageCache::HEADER));
		$this->assertSame((string) $first->getBody(), (string) $hit->getBody());
		$this->assertSame($first->getHeaderLine('Content-Type'), $hit->getHeaderLine('Content-Type'));

		$again->container()->make(ContentVersion::class)->bump();

		$this->assertSame('changed', (string) $this->get($this->site(), '/archives/spring')->getBody());
	}

	public function testOnlyPlainPageViewsAreCached(): void
	{
		$this->standardContent();

		$app = $this->site();

		$this->assertSame('', $this->get($app, '/archives/spring?ref=feed')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('', $this->get($app, '/archives/spring', ['Authorization' => 'Basic eDp5'])->getHeaderLine(PageCache::HEADER));
		$this->assertSame('', $this->get($app, '/archives/spring', method: 'POST')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('', $this->get($app, '/nowhere')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('', $this->get($app, '/archives/spring/')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('', $this->get($app, '/themes/blush/default/style.css')->getHeaderLine(PageCache::HEADER));

		$this->assertSame('miss', $this->get($app, '/archives/spring', method: 'HEAD')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('hit', $this->get($app, '/archives/spring')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('miss', $this->get($app, '/sitemap')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('miss', $this->get($app, '/robots.txt')->getHeaderLine(PageCache::HEADER));
	}

	public function testThePageCacheIsOffInDevelopmentAndCanBeTurnedOff(): void
	{
		$this->standardContent();

		$this->assertSame('', $this->get($this->site('development'), '/archives/spring')->getHeaderLine(PageCache::HEADER));

		$this->cacheConfig('pages: false');
		$this->assertSame('', $this->get($this->site(), '/archives/spring')->getHeaderLine(PageCache::HEADER));

		$this->cacheConfig('enabled: true, maxAge: 300');
		$response = $this->get($this->site('development'), '/archives/spring');

		$this->assertSame('miss', $response->getHeaderLine(PageCache::HEADER));
		$this->assertSame('public, max-age=300', $response->getHeaderLine('Cache-Control'));
	}

	public function testRepeatRequestsGetNotModified(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$page = $this->get($app, '/archives/spring');
		$etag = $page->getHeaderLine('ETag');

		$this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $etag);

		$repeat = $this->get($app, '/archives/spring', ['If-None-Match' => "\"other\", W/{$etag}"]);

		$this->assertSame(304, $repeat->getStatusCode());
		$this->assertSame('', (string) $repeat->getBody());
		$this->assertSame($etag, $repeat->getHeaderLine('ETag'));
		$this->assertSame('hit', $repeat->getHeaderLine(PageCache::HEADER));
		$this->assertSame('public, max-age=0, must-revalidate', $repeat->getHeaderLine('Cache-Control'));
		$this->assertFalse($repeat->hasHeader('Content-Type'));

		$this->assertSame(200, $this->get($app, '/archives/spring', ['If-None-Match' => '"other"'])->getStatusCode());
		$this->assertSame(304, $this->get($app, '/archives/spring', ['If-None-Match' => '*'])->getStatusCode());
		$this->assertSame(404, $this->get($app, '/nowhere', ['If-None-Match' => '*'])->getStatusCode());
	}

	public function testFilesAnswerIfModifiedSince(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$file = $this->get($app, '/themes/blush/default/style.css');

		$this->assertFalse($file->hasHeader('ETag'));

		$modified = $file->getHeaderLine('Last-Modified');

		$this->assertSame(304, $this->get($app, '/themes/blush/default/style.css', ['If-Modified-Since' => $modified])->getStatusCode());
		$this->assertSame(200, $this->get($app, '/themes/blush/default/style.css', ['If-Modified-Since' => 'Mon, 01 Jan 2001 00:00:00 GMT'])->getStatusCode());
	}

	public function testReindexingMovesTheVersionOn(): void
	{
		$this->standardContent();

		$app     = $this->site();
		$version = $app->container()->make(ContentVersion::class);
		$before  = $version->current();

		$this->assertSame($before, $version->current());
		$this->assertFileExists($version->path());

		$this->entry('_posts/2008-05-01.new.md', 'title: New');
		$app->container()->make(Indexer::class)->index();

		$this->assertNotSame($before, $version->current());
	}

	public function testTheVersionMovesOnWhenAScheduledEntryGoesLive(): void
	{
		$this->standardContent();

		$app     = $this->site();
		$version = $app->container()->make(ContentVersion::class);

		$this->assertSame('miss', $this->get($app, '/')->getHeaderLine(PageCache::HEADER));
		$this->assertSame('hit', $this->get($app, '/')->getHeaderLine(PageCache::HEADER));
		$this->assertSame(strtotime('2026-12-25 08:00:00 America/Chicago'), $version->scheduled());

		$before = $version->current();

		$this->clock->set('2026-12-25 08:00:00 America/Chicago');

		$after = $version->current();

		$this->assertNotSame($before, $after);
		$this->assertNull($version->scheduled());
		$this->assertSame($after, $this->site()->container()->make(ContentVersion::class)->current());
		$this->assertSame('miss', $this->get($app, '/')->getHeaderLine(PageCache::HEADER));
		$this->assertStringContainsString('Future', (string) $this->get($app, '/')->getBody());
	}

	public function testDamagedVersionFilesAreReplaced(): void
	{
		$this->standardContent();

		$app     = $this->site();
		$version = $app->container()->make(ContentVersion::class);

		$this->writeTemporaryFile('storage/cache/content-version.json', '{"version": ""}');

		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $version->current());
	}
}
