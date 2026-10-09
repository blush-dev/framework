<?php

/**
 * Markdown page and llms.txt tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Llms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Content\Type\ContentType;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Llms\LlmsConfig;
use Blush\Llms\LlmsRoutes;
use Blush\Llms\LlmsServiceProvider;
use Blush\Llms\LlmsSiteUrls;
use Blush\Llms\LlmsTxt;
use Blush\Llms\LlmsTxtController;
use Blush\Llms\MarkdownController;
use Blush\Llms\MarkdownPages;
use Blush\Routing\SiteUrl;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\SavedSettings;

#[CoversClass(LlmsConfig::class)]
#[CoversClass(LlmsSiteUrls::class)]
#[CoversClass(LlmsRoutes::class)]
#[CoversClass(LlmsServiceProvider::class)]
#[CoversClass(LlmsTxt::class)]
#[CoversClass(LlmsTxtController::class)]
#[CoversClass(MarkdownController::class)]
#[CoversClass(MarkdownPages::class)]
final class LlmsTest extends TestCase
{
	use BuildsContentSite;
	use SavedSettings;

	private Application $app;

	private function boot(?string $llmsConfig = null, ?string $routeConfig = null): void
	{
		// These tests change config between boots.
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");

		if ($llmsConfig !== null) {
			$this->writeTemporaryFile('config/llms.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$llmsConfig};\n");
		}

		if ($routeConfig !== null) {
			$this->writeTemporaryFile('config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$routeConfig};\n");
		}

		$this->app = $this->site();
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testServesAnEntrysMarkdown(): void
	{
		$this->standardContent();
		$this->entry('_post/2009-01-01.quoted.md', "title: \"Say \\\"hi\\\"\"\npublished: 2009-01-01 10:00:00\nsummary: |\n  A *short*\n  summary.", "Some text.\n\n:::figure\n![A photo](/media/photo.jpg)\n:::");
		$this->boot();

		$response = $this->get('/archives/quoted.md');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('text/markdown; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertSame('<http://localhost/archives/quoted>; rel="canonical"', $response->getHeaderLine('Link'));
		$this->assertMatchesRegularExpression(
			'#^---\ntitle: "Say \\\\"hi\\\\""\nurl: "http://localhost/archives/quoted"\npublished: "2009-01-01T10:00:00-06:00"\nupdated: "[^"]+"\nsummary: "A \*short\*\\\\nsummary."\n---\n\nSome text.\n\n:::figure\n!\[A photo\]\(http://localhost/media/photo.jpg\)\n:::\n$#',
			(string) $response->getBody(),
			'Front matter, then the body as written, directives included, with full URLs (D-396).'
		);
	}

	public function testFindsEveryKindOfPage(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertStringContainsString("title: \"Blog\"\nurl: \"http://localhost/\"", (string) $this->get('/index.md')->getBody(), 'The home type\'s landing page is the homepage.');
		$this->assertStringContainsString('title: "About"', (string) $this->get('/about.md')->getBody(), 'A landing page is at its collection\'s URL.');
		$this->assertStringContainsString('title: "Biography"', (string) $this->get('/about/biography.md')->getBody());
		$this->assertStringContainsString('title: "Art"', (string) $this->get('/topics/art.md')->getBody());
		$this->assertStringContainsString('Some *notes*.', (string) $this->get('/notes.md')->getBody());
		$this->assertStringContainsString('title: "Rainy"', (string) $this->get('/archives/rainy.md')->getBody(), 'Unlisted entries have URLs, so they have Markdown pages.');
		$this->assertStringNotContainsString("---\n\n", (string) $this->get('/archives/rainy.md')->getBody(), 'An empty body leaves nothing after the front matter.');

		foreach (['/archives/future.md', '/archives/unfinished.md', '/private.md', '/drafts/idea.md', '/nowhere.md', '/archives/spring/extra.md', '/archives.md'] as $uri) {
			$this->assertSame(404, $this->get($uri)->getStatusCode(), $uri);
		}
	}

	public function testServesTheRootIndexWithoutAHomeType(): void
	{
		$this->standardContent();
		$this->contentConfig(['types' => ['post' => ['routing' => ['prefix' => 'archives']]], 'home' => null]);
		$this->boot();

		$this->assertStringContainsString("title: \"Home\"\nurl: \"http://localhost/\"", (string) $this->get('/index.md')->getBody());
		$this->assertStringContainsString('title: "Blog"', (string) $this->get('/archives.md')->getBody());
	}

	public function testDropsTheTrailingSlash(): void
	{
		$this->standardContent();
		$this->boot(routeConfig: 'new Blush\\Routing\\RouteConfig(trailingSlash: true)');

		$response = $this->get('/about.md');

		$this->assertSame(200, $response->getStatusCode());
		$this->assertSame('<http://localhost/about/>; rel="canonical"', $response->getHeaderLine('Link'));
		$this->assertSame(404, $this->get('/about/.md')->getStatusCode());
	}

	public function testAdvertisesTheMarkdownVersion(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/archives/spring.md" type="text/markdown">', (string) $this->get('/archives/spring')->getBody());
		$this->assertStringContainsString('<link rel="alternate" href="http://localhost/index.md" type="text/markdown">', (string) $this->get('/')->getBody());
		$this->assertStringNotContainsString('text/markdown', (string) $this->get('/page/2')->getBody(), 'Only on the entry\'s own URL.');
	}

	public function testListsTheSiteInLlmsTxt(): void
	{
		$this->standardContent();
		$this->entry('_post/2009-01-01.summed.md', "title: \"Summed [up]\"\npublished: 2009-01-01 10:00:00\nsummary: |\n  Two\n  [lines](/about).");
		$this->writeSettings('{"app": {"description": "Notes on the web."}}');
		$this->boot();

		$response = $this->get('/llms.txt');

		$this->assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertSame(
			"# Blush\n\n> Notes on the web.\n\n"
			. "## Pages\n\n- [About](http://localhost/about.md)\n- [Biography](http://localhost/about/biography.md)\n- [Notes](http://localhost/notes.md)\n\n"
			. "## Posts\n\n- [Hello Bundle](http://localhost/archives/hello.md)\n- [Summed \\[up\\]](http://localhost/archives/summed.md): Two [lines](http://localhost/about).\n- [spring](http://localhost/archives/spring.md)\n- [Welcome](http://localhost/archives/welcome.md)\n- [Blog](http://localhost/index.md)\n",
			(string) $response->getBody(),
			'Public, published entries by type, newest first when dated; no taxonomies or profiles.'
		);
		$this->assertSame(8, $this->app->container()->make(LlmsTxt::class)->count());
	}

	public function testTypesChooseWhetherTheyreListed(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post'     => ['routing' => ['prefix' => 'archives'], 'llms' => false],
				'category' => ['urls' => ['prefix' => 'topics'], 'order' => 'position', 'llms' => false]
			],
			'relations' => ['category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true]],
			'home' => 'post'
		]);
		$this->boot();

		$body = (string) $this->get('/llms.txt')->getBody();

		$this->assertStringContainsString('## Pages', $body);
		$this->assertStringNotContainsString('## Posts', $body, 'A type\'s `llms` option leaves it out (D-398).');
		$this->assertSame(200, $this->get('/archives/spring.md')->getStatusCode(), 'Its entries still have Markdown copies.');
		$this->assertSame(['page'], array_map(static fn (ContentType $type): string => $type->name, $this->app->container()->make(LlmsTxt::class)->types()));
	}

	public function testTaxonomiesAndProfilesCanBeListed(): void
	{
		$this->standardContent();
		$this->entry('_category/book-reviews.md', 'title: Book Reviews');
		$this->contentConfig([
			'types' => [
				'post'     => ['routing' => ['prefix' => 'archives']],
				'category' => ['urls' => ['prefix' => 'topics'], 'order' => 'position', 'llms' => true],
				'profile'  => ['kind' => 'profiles', 'llms' => true]
			],
			'relations' => ['category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true]]
		]);
		$this->boot();

		$body = (string) $this->get('/llms.txt')->getBody();

		$this->assertStringContainsString("## Categories\n\n- [Art](http://localhost/topics/art.md)\n- [Book Reviews](http://localhost/topics/book-reviews.md)\n- [Old Posts](http://localhost/topics/old-posts.md)\n- [Topics](http://localhost/topics.md)\n", $body, 'Terms, by title (D-401).');
		$this->assertStringContainsString("## Profiles\n\n- [A Guest](http://localhost/profiles/guest.md)\n- [Justin Tadlock](http://localhost/profiles/justintadlock.md)\n", $body);
	}

	public function testServesTheFullFile(): void
	{
		$this->standardContent();
		$this->boot();

		$this->assertSame(404, $this->get('/llms-full.txt')->getStatusCode(), 'Off by default (D-402).');
		$this->assertNotContains('/llms-full.txt', array_map(static fn (SiteUrl $url): string => $url->path, [...$this->app->container()->make(LlmsSiteUrls::class)->urls()]));

		$this->boot('new Blush\\Llms\\LlmsConfig(full: true)');
		$response = $this->get('/llms-full.txt');
		$body     = (string) $response->getBody();

		$this->assertSame('text/plain; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertStringStartsWith("# Blush\n\n---\ntitle: \"About\"\nurl: \"http://localhost/about\"\n", $body, 'The heading, then each listed page\'s copy in llms.txt\'s order.');
		$this->assertStringContainsString("---\ntitle: \"spring\"\nurl: \"http://localhost/archives/spring\"\npublished: ", $body);
		$this->assertStringContainsString("\n\nSpring is here.\n\n---\n", $body);
		$this->assertSame(7, substr_count($body, "\ntitle: "), 'Every page llms.txt lists, and no others.');
		$this->assertStringNotContainsString('Rainy', $body, 'Not unlisted entries.');
		$this->assertContains('/llms-full.txt', array_map(static fn (SiteUrl $url): string => $url->path, [...$this->app->container()->make(LlmsSiteUrls::class)->urls()]));

		$this->boot('new Blush\\Llms\\LlmsConfig(enabled: false, full: true)');
		$this->assertSame(404, $this->get('/llms-full.txt')->getStatusCode(), 'Off with the Markdown copies.');
	}

	public function testListsSiteUrls(): void
	{
		$this->standardContent();
		$this->boot();

		$paths = array_map(static fn (SiteUrl $url): string => $url->path, [...$this->app->container()->make(LlmsSiteUrls::class)->urls()]);

		$this->assertSame('/llms.txt', $paths[0]);
		$this->assertContains('/index.md', $paths);
		$this->assertContains('/archives/rainy.md', $paths);
		$this->assertNotContains('/archives/future.md', $paths);
	}

	public function testCanBeTurnedOff(): void
	{
		$this->standardContent();
		$this->boot('new Blush\\Llms\\LlmsConfig(enabled: false)');

		$this->assertSame(404, $this->get('/llms.txt')->getStatusCode());
		$this->assertSame(404, $this->get('/archives/spring.md')->getStatusCode());
		$this->assertStringNotContainsString('text/markdown', (string) $this->get('/archives/spring')->getBody());
		$this->assertSame([], [...$this->app->container()->make(LlmsSiteUrls::class)->urls()]);
	}

	public function testReadsConfigArrays(): void
	{
		$config = LlmsConfig::fromArray(['enabled' => false]);

		$this->assertFalse($config->enabled);
		$this->assertFalse($config->full, 'llms-full.txt is off by default (D-402).');
		$this->assertSame(['enabled' => false, 'full' => false], $config->toArray());
		$this->assertSame('/index.md', MarkdownPages::pathFor('/'));
		$this->assertSame('/about.md', MarkdownPages::pathFor('/about/'));
	}
}
