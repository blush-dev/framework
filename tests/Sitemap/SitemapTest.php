<?php

/**
 * Sitemap and robots.txt tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Sitemap;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Config\InvalidConfig;
use Blush\Sitemap\AiCrawlerGroup;
use Blush\Sitemap\RobotsController;
use Blush\Sitemap\SitemapBuilder;
use Blush\Sitemap\SitemapConfig;
use Blush\Sitemap\SitemapController;
use Blush\Sitemap\SitemapRoutes;
use Blush\Sitemap\SitemapServiceProvider;
use Blush\Sitemap\SitemapUrl;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(AiCrawlerGroup::class)]
#[CoversClass(SitemapBuilder::class)]
#[CoversClass(SitemapConfig::class)]
#[CoversClass(SitemapController::class)]
#[CoversClass(SitemapRoutes::class)]
#[CoversClass(SitemapServiceProvider::class)]
#[CoversClass(SitemapUrl::class)]
#[CoversClass(RobotsController::class)]
final class SitemapTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private function boot(?string $sitemapConfig = null, string $environment = 'production'): void
	{
		// Config changes reach a cached site on deploy (`cache:clear`);
		// these tests change config between boots.
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");

		if ($sitemapConfig !== null) {
			$this->writeTemporaryFile('config/sitemap.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$sitemapConfig};\n");
		}

		$this->app = $this->site($environment);
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	/**
	 * Returns the `<loc>`s in a sitemap response.
	 *
	 * @return list<string>
	 */
	private function locations(string $uri): array
	{
		preg_match_all('#<loc>([^<]+)</loc>#', (string) $this->get($uri)->getBody(), $matches);

		return $matches[1];
	}

	public function testIndexesEachTypesSitemap(): void
	{
		$this->standardContent();
		$this->boot();

		$index = $this->get('/sitemap');

		$this->assertSame('application/xml; charset=UTF-8', $index->getHeaderLine('Content-Type'));
		$this->assertSame(
			['http://localhost/sitemap/page', 'http://localhost/sitemap/profile', 'http://localhost/sitemap/category', 'http://localhost/sitemap/post'],
			$this->locations('/sitemap')
		);
		$this->assertSame($this->locations('/sitemap'), $this->locations('/sitemap.xml'));
		// Each sitemap's latest change; landing pages without dates count by mtime.
		$this->assertMatchesRegularExpression('#<loc>http://localhost/sitemap/post</loc>\s*<lastmod>\d{4}-\d{2}-\d{2}T[\d:]+[+-]\d{2}:\d{2}</lastmod>#', (string) $index->getBody());
	}

	public function testListsEachTypesUrls(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame(
			[
				'http://localhost/', 'http://localhost/archives/hello', 'http://localhost/archives/spring', 'http://localhost/archives/welcome',
				'http://localhost/archives/authors', 'http://localhost/archives/authors/guest', 'http://localhost/archives/authors/justintadlock'
			],
			$this->locations('/sitemap/post'),
			'Each people field\'s archives follow the entries, by name (D-351).'
		);
		$this->assertSame(
			['http://localhost/topics', 'http://localhost/topics/art', 'http://localhost/topics/book-reviews', 'http://localhost/topics/old-posts'],
			$this->locations('/sitemap/category')
		);
		// Entries follow their type's order, never files' (D-516): pages by
		// position, then title.
		$this->assertSame(['http://localhost/about', 'http://localhost/about/biography', 'http://localhost/notes'], $this->locations('/sitemap/page'));
		$this->assertSame(['http://localhost/profiles/guest', 'http://localhost/profiles/justintadlock'], $this->locations('/sitemap/profile'), 'Each profile has a page of its own (D-351).');
		$this->assertSame(404, $this->get('/sitemap/nope')->getStatusCode());
	}

	public function testTypesCanLeaveTheSitemap(): void
	{
		$this->standardContent();
		$this->contentConfig(['types' => ['category' => ['path' => 'topics', 'order' => 'position', 'sitemap' => false]]]);
		$this->boot();

		$this->assertSame(404, $this->get('/sitemap/category')->getStatusCode());
		$this->assertNotContains('http://localhost/sitemap/category', $this->locations('/sitemap'));
		$this->assertContains('http://localhost/sitemap/post', $this->locations('/sitemap'), 'The other types stay.');
	}

	public function testServesRobotsTxt(): void
	{
		$this->standardContent();
		$this->boot("new Blush\\Sitemap\\SitemapConfig(disallow: ['/private', '/drafts'])");

		$robots = $this->get('/robots.txt');

		$this->assertSame('text/plain; charset=UTF-8', $robots->getHeaderLine('Content-Type'));
		$this->assertSame("User-agent: *\nDisallow: /private\nDisallow: /drafts\n\nSitemap: http://localhost/sitemap\n", (string) $robots->getBody());

		$this->boot(null, 'staging');

		$this->assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('/robots.txt')->getBody());
	}

	public function testSitemapsCanBeTurnedOff(): void
	{
		$this->standardContent();
		$this->boot("Blush\\Sitemap\\SitemapConfig::fromArray(['enabled' => false])");

		$this->assertSame(404, $this->get('/sitemap')->getStatusCode());
		$this->assertSame("User-agent: *\nDisallow:\n", (string) $this->get('/robots.txt')->getBody());

		$this->boot("new Blush\\Sitemap\\SitemapConfig(robots: \"User-agent: *\\nDisallow: /secret\\n\")");

		$this->assertSame("User-agent: *\nDisallow: /secret\n", (string) $this->get('/robots.txt')->getBody());
		$this->assertSame(['enabled' => true, 'disallow' => [], 'robots' => "User-agent: *\nDisallow: /secret\n", 'blockAi' => []], $this->app->container()->make(SitemapConfig::class)->toArray());
	}

	public function testAsksAiCrawlersToStayAway(): void
	{
		$this->standardContent();
		$this->boot("Blush\\Sitemap\\SitemapConfig::fromArray(['disallow' => ['/drafts/'], 'blockAi' => ['fetchers', 'training']])");

		$this->assertSame(
			"User-agent: *\nDisallow: /drafts/\n\n"
			. "# Training crawlers\nUser-agent: GPTBot\nUser-agent: ClaudeBot\nUser-agent: CCBot\nUser-agent: Google-Extended\nUser-agent: Applebot-Extended\nUser-agent: Bytespider\nUser-agent: meta-externalagent\nDisallow: /\n\n"
			. "# Fetchers acting for a person\nUser-agent: ChatGPT-User\nUser-agent: Claude-User\nUser-agent: Perplexity-User\nDisallow: /\n\n"
			. "Sitemap: http://localhost/sitemap\n",
			(string) $this->get('/robots.txt')->getBody(),
			'Each blocked group, in its usual order (D-398).'
		);
		$this->assertSame([AiCrawlerGroup::Fetchers, AiCrawlerGroup::Training], $this->app->container()->make(SitemapConfig::class)->blockAi);

		$this->boot("Blush\\Sitemap\\SitemapConfig::fromArray(['blockAi' => ['training']])", 'development');
		$this->assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('/robots.txt')->getBody(), 'Outside production, everything is blocked anyway.');

		$this->expectException(InvalidConfig::class);
		SitemapConfig::fromArray(['blockAi' => ['robots']]);
	}
}
