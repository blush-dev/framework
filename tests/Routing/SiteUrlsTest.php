<?php

/**
 * Site URLs tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Routing\SiteUrl;
use Blush\Routing\SiteUrls;
use Blush\Routing\UrlSource;

#[CoversClass(SiteUrls::class)]
#[CoversClass(SiteUrl::class)]
final class SiteUrlsTest extends TestCase
{
	public function testListsEachPathOnceInSourceOrder(): void
	{
		$first = self::source(new SiteUrl('/'), new SiteUrl('/topics', static fn (int $page): string => "/topics/page/{$page}"));
		$other = self::source(new SiteUrl('/topics'), new SiteUrl('/about'));
		$urls  = [...new SiteUrls([$first, $other])->all()];

		$this->assertSame(['/', '/topics', '/about'], array_map(static fn (SiteUrl $url): string => $url->path, $urls));
		$this->assertSame('/topics/page/2', $urls[1]->page(2), 'The first to list a path decides its paging.');
		$this->assertNull($urls[2]->page(2));
	}

	public function testListsNothingWithoutSources(): void
	{
		$this->assertSame([], [...new SiteUrls()->all()]);
	}

	/**
	 * Returns a source listing these URLs.
	 */
	private static function source(SiteUrl ...$urls): UrlSource
	{
		return new readonly class (array_values($urls)) implements UrlSource {
			/**
			 * @param list<SiteUrl> $urls
			 */
			public function __construct(private array $urls)
			{}

			/**
			 * @inheritDoc
			 */
			public function urls(): iterable
			{
				return $this->urls;
			}
		};
	}
}
