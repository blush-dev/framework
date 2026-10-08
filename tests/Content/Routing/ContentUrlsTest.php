<?php

/**
 * Content URL tests.
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
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Routing\RouteConfig;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ContentUrls::class)]
#[CoversClass(ContentType::class)]
final class ContentUrlsTest extends TestCase
{
	use BuildsContentSite;

	public function testBuildsUrlsFromTypeRouting(): void
	{
		$this->standardContent();
		$this->entry('_posts/2019/nested.md', "title: Nested\npublished: 2019-01-01");

		$app     = $this->site();
		$content = $app->container()->make(ContentRepository::class);
		$urls    = $app->container()->make(ContentUrls::class);
		$types   = $app->container()->make(ContentTypes::class);
		$entry   = static fn (string $path): Entry => $content->findPath($path) ?? self::fail("No entry {$path}.");

		$this->assertSame('/archives/welcome', $urls->entry($entry('_posts/2003-04-15.welcome.md')));
		$this->assertSame('/archives/hello', $urls->entry($entry('_posts/hello/index.md')));
		$this->assertSame('/', $urls->entry($entry('_posts/index.md')));
		$this->assertSame('/', $urls->entry($entry('index.md')));
		$this->assertSame('/about', $urls->entry($entry('about/index.md')));
		$this->assertSame('/about/biography', $urls->entry($entry('about/biography.md')));
		$this->assertSame('/topics/art', $urls->entry($entry('topics/art.md')));
		$this->assertSame('/topics', $urls->entry($entry('topics/index.md')));
		$this->assertNull($urls->entry($entry('_private.md')));
		$this->assertSame('/archives/nested', $urls->entry($entry('_posts/2019/nested.md')), 'A folder below a collection is only where its file is kept (D-629).');

		$post     = $types->get('post');
		$category = $types->get('category');

		$this->assertSame('/archives/{name}', $post->routePattern('single'));
		$this->assertNull($post->routePattern('unknown'));
		$this->assertNull($types->get('page')->routePattern('single'));
		$this->assertSame('/page/3', $urls->collection($post, 3));
		$this->assertSame('/topics/page/2', $urls->collection($category, 2));
		$this->assertSame('/topics/art/page/2', $urls->term($category, 'art', 2));
		$this->assertSame('/archives/2008/04', $urls->date($post, ['year' => 2008, 'month' => 4]));
		$this->assertSame('/archives/2008/04/05/page/2', $urls->date($post, ['year' => 2008, 'month' => 4, 'day' => 5], 2));
		$this->assertNull($urls->date($post, []));
		$this->assertNull($urls->date($post, ['year' => 2008, 'month' => 4, 'day' => 5, 'hour' => 1]));
		$this->assertNull($urls->date($category, ['year' => 2008]));
		$this->assertSame('http://localhost/archives/welcome', $urls->absolute('/archives/welcome'));
	}

	public function testFollowsTheTrailingSlashSetting(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$urls = new ContentUrls($app->container()->make(ContentTypes::class), new RouteConfig(trailingSlash: true), new AppConfig(url: 'https://example.com:8443/sub'), static fn (): ContentRepository => $app->container()->make(ContentRepository::class));

		$this->assertSame('/topics/art/', $urls->term($app->container()->make(ContentTypes::class)->get('category'), 'art'));
		$this->assertSame('https://example.com:8443/topics', $urls->absolute('topics'));
	}
}
