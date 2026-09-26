<?php

/**
 * Rendered body and token cache tests.
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
use Blush\Cache\ContentCache;
use Blush\Cache\ContentVersion;
use Blush\Cache\RenderedBodies;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Body;
use Blush\Content\Entry\EntryHydrator;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\ViewFactory;

#[CoversClass(RenderedBodies::class)]
#[CoversClass(ContentCache::class)]
#[CoversClass(Body::class)]
#[CoversClass(EntryHydrator::class)]
#[CoversClass(ViewFactory::class)]
final class RenderedBodiesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Returns the number of entries in a store namespace.
	 */
	private function stored(string $namespace): int
	{
		return count(glob($this->temporaryDirectory() . "/storage/cache/store/{$namespace}/*/*.cache") ?: []);
	}

	public function testCachedBodiesDontReadTheirFiles(): void
	{
		$this->standardContent();

		$entry = $this->repository()->named('post', 'spring');

		$this->assertSame("<p>Spring is here.</p>\n", $entry?->body());
		$this->assertSame(1, $this->stored('bodies'));

		unlink($this->temporaryDirectory() . '/user/content/_posts/2008-04-05.spring.md');

		$this->assertSame("<p>Spring is here.</p>\n", $this->repository()->named('post', 'spring')?->body());
	}

	public function testSummariesAreCachedToo(): void
	{
		$this->entry('page.md', "title: Page\nsummary: A *short* one.", 'Body.');

		$this->assertSame("<p>A <em>short</em> one.</p>\n", $this->repository()->named('page', 'page')?->excerpt());
		$this->assertSame(1, $this->stored('bodies'));
	}

	public function testBodiesAreKeptPerThemeAndVersion(): void
	{
		$this->standardContent();

		$app = $this->site();
		$this->assertSame("<p>Spring is here.</p>\n", $app->container()->make(ContentRepository::class)->named('post', 'spring')?->body());

		$this->writeTemporaryFile('user/themes/child/theme.json', '{"name": "Child"}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'child');\n");

		$this->repository()->named('post', 'spring')?->body();
		$this->assertSame(2, $this->stored('bodies'));

		$app->container()->make(ContentVersion::class)->bump();

		$this->repository()->named('post', 'spring')?->body();
		$this->assertSame(3, $this->stored('bodies'));
	}

	public function testNothingIsCachedInDevelopment(): void
	{
		$this->standardContent();

		$this->repository($this->site('development'))->named('post', 'spring')?->body();

		$this->assertSame(0, $this->stored('bodies'));
	}

	public function testTokenCssIsKeptPerVersion(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(pages: false);\n");
		$this->writeTemporaryFile('user/data/theme.json', '{"tokens": {"color": {"accent": "#111111"}}}');

		$get = fn (): string => (string) $this->site()->container()->make(Kernel::class)->handle(Request::create('/about'))->getBody();

		$this->assertStringContainsString('--color-accent: #111111;', $get());
		$this->assertSame(1, $this->stored('tokens'));

		$this->writeTemporaryFile('user/data/theme.json', '{"tokens": {"color": {"accent": "#222222"}}}');

		$this->assertStringContainsString('--color-accent: #111111;', $get());

		$this->site()->container()->make(ContentVersion::class)->bump();

		$this->assertStringContainsString('--color-accent: #222222;', $get());
	}
}
