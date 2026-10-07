<?php

/**
 * Site description tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Feed\FeedBuilder;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\Site;
use Blush\View\ThemedPageRenderer;

#[CoversClass(AppConfig::class)]
#[CoversClass(FeedBuilder::class)]
#[CoversClass(Site::class)]
#[CoversClass(ThemedPageRenderer::class)]
final class SiteDescriptionTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testDescribesTheFrontPageAndFeeds(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post'     => ['path' => '_posts', 'collection' => ['order' => 'desc'], 'routing' => ['prefix' => 'archives'], 'feed' => true],
				'category' => ['path' => 'topics', 'order' => 'position', 'people' => false]
			],
			'relations' => ['category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true]],
			'home' => 'post'
		]);
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");
		$this->writeTemporaryFile('user/data/settings.json', '{"app": {"description": "Notes on the web."}}');
		$this->app = $this->site();

		$this->assertSame('Notes on the web.', $this->app->container()->make(AppConfig::class)->description);
		$this->assertSame('Notes on the web.', Site::fromConfig($this->app->container()->make(AppConfig::class))->description);
		$this->assertStringContainsString('<meta name="description" content="Notes on the web.">', (string) $this->get('/')->getBody(), 'The front page, with nothing more specific (D-398).');
		$this->assertStringNotContainsString('Notes on the web.', (string) $this->get('/about')->getBody(), 'Only the front page.');
		$this->assertStringContainsString('<description>Notes on the web.</description>', (string) $this->get('/feed')->getBody());
	}

	public function testHasNoneByDefault(): void
	{
		$this->standardContent();
		$this->app = $this->site();

		$this->assertSame('', $this->app->container()->make(AppConfig::class)->description);
		$this->assertStringNotContainsString('name="description"', (string) $this->get('/')->getBody());
		$this->assertSame(['description' => 'Hi'], array_intersect_key(AppConfig::fromArray(['description' => 'Hi'])->toArray(), ['description' => true]));
	}
}
