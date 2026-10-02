<?php

/**
 * Static export tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Export;

use DOMDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Console\Commands\Build;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Routing\ContentExportUrls;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Export\Crawler;
use Blush\Export\Events\ExportFinished;
use Blush\Export\Events\ExportStarted;
use Blush\Export\ExportAssets;
use Blush\Export\ExportConfig;
use Blush\Export\Exporter;
use Blush\Export\ExportException;
use Blush\Export\ExportLayout;
use Blush\Export\ExportManifest;
use Blush\Export\ExportReport;
use Blush\Export\ExportServiceProvider;
use Blush\Export\ExportSite;
use Blush\Export\ExportUrl;
use Blush\Export\ExportWriter;
use Blush\Export\ExportFingerprint;
use Blush\Export\ExportRedirect;
use Blush\Export\Host\ApacheFiles;
use Blush\Export\Host\HostContext;
use Blush\Export\Host\HostFiles;
use Blush\Export\Host\HostFilesFactory;
use Blush\Export\Host\HostFilesRegistrar;
use Blush\Export\Host\HostFilesRegistry;
use Blush\Export\Host\HostOutput;
use Blush\Export\Host\NetlifyFiles;
use Blush\Export\RenderedUrl;
use Blush\Feed\FeedExportUrls;
use Blush\Routing\RedirectExportUrls;
use Blush\Sitemap\SitemapExportUrls;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(Exporter::class)]
#[CoversClass(ExportSite::class)]
#[CoversClass(Crawler::class)]
#[CoversClass(ExportAssets::class)]
#[CoversClass(ExportWriter::class)]
#[CoversClass(ExportLayout::class)]
#[CoversClass(ExportManifest::class)]
#[CoversClass(ExportReport::class)]
#[CoversClass(ExportConfig::class)]
#[CoversClass(ExportUrl::class)]
#[CoversClass(RenderedUrl::class)]
#[CoversClass(ExportServiceProvider::class)]
#[CoversClass(ExportStarted::class)]
#[CoversClass(ExportFinished::class)]
#[CoversClass(ContentExportUrls::class)]
#[CoversClass(FeedExportUrls::class)]
#[CoversClass(SitemapExportUrls::class)]
#[CoversClass(ExportFingerprint::class)]
#[CoversClass(ExportRedirect::class)]
#[CoversClass(RedirectExportUrls::class)]
#[CoversClass(HostFiles::class)]
#[CoversClass(ApacheFiles::class)]
#[CoversClass(NetlifyFiles::class)]
#[CoversClass(HostContext::class)]
#[CoversClass(HostOutput::class)]
#[CoversClass(HostFilesFactory::class)]
#[CoversClass(HostFilesRegistry::class)]
#[CoversClass(HostFilesRegistrar::class)]
#[CoversClass(Build::class)]
#[CoversClass(Bootstrap::class)]
final class ExportTest extends TestCase
{
	use BuildsContentSite;

	private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	/**
	 * Writes the standard content with feeds, paged listings, media, and
	 * an image beside an entry, which isn't media (D-294).
	 */
	private function exportableContent(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'number' => 1],
					'date_archives' => true,
					'feed'          => true,
					'routing'       => ['prefix' => 'archives']
				],
				'category' => [
					'path'            => 'topics',
					'taxonomy'        => true,
					'term_collect'    => 'post',
					'term_collection' => ['number' => 1],
					'feed'            => true
				]
			],
			'home' => 'post'
		]);
		$this->writeTemporaryFile('user/media/pixel.png', (string) base64_decode(self::PIXEL, true));
		$this->writeTemporaryFile('user/content/_posts/hello/pixel.png', (string) base64_decode(self::PIXEL, true));
	}

	private function export(Application $app, ?string $url = null, ?bool $crawl = null, bool $incremental = false): ExportReport
	{
		return $app->container()->make(Exporter::class)->export($url, $crawl, $incremental);
	}

	private function exported(string $file): string
	{
		return $this->temporaryDirectory() . '/storage/export/' . $file;
	}

	public function testExportsEveryPageAsProductionServesIt(): void
	{
		$this->exportableContent();

		$report = $this->export($this->site('development'));

		$this->assertTrue($report->isSuccessful(), print_r($report->failures, true));
		$this->assertSame([], $report->broken);

		$files = [
			'index.html',
			'page/2/index.html',
			'page/3/index.html',
			'about/index.html',
			'about/biography/index.html',
			'notes/index.html',
			'archives/spring/index.html',
			'archives/rainy/index.html',
			'archives/hello/index.html',
			'archives/2008/index.html',
			'archives/2008/04/index.html',
			'archives/2008/04/05/index.html',
			'topics/index.html',
			'topics/art/index.html',
			'topics/old-posts/index.html',
			'feed/index.rss',
			'feed/atom/index.atom',
			'feed/json/index.json',
			'topics/art/feed/index.rss',
			'sitemap/index.xml',
			'sitemap/post/index.xml',
			'sitemap.xml',
			'robots.txt',
			'404.html',
			'media/pixel.png',
			'themes/blush/default/style.css'
		];

		foreach ($files as $file) {
			$this->assertFileExists($this->exported($file));
		}

		// Drafts, scheduled entries, hidden files, and pages past the last.
		$this->assertFileDoesNotExist($this->exported('archives/unfinished/index.html'));
		$this->assertFileDoesNotExist($this->exported('archives/future/index.html'));
		$this->assertFileDoesNotExist($this->exported('page/4/index.html'));
		$this->assertFileDoesNotExist($this->exported('themes/blush/default/theme.json'));
		$this->assertFileDoesNotExist($this->exported('media/_content/_posts/hello/pixel.png'));
		$this->assertFileDoesNotExist($this->exported('_posts/hello/pixel.png'));
		$this->assertNotContains('/page/4', $report->skipped);

		// Production's robots.txt, though the site runs in development.
		$this->assertStringNotContainsString("Disallow: /\n", (string) file_get_contents($this->exported('robots.txt')));
		$this->assertStringContainsString('Spring is here.', (string) file_get_contents($this->exported('archives/spring/index.html')));

		$feed = new DOMDocument();
		$this->assertTrue($feed->loadXML((string) file_get_contents($this->exported('feed/index.rss'))));
	}

	public function testAbsoluteUrlsUseTheExportOrigin(): void
	{
		$this->exportableContent();

		$report = $this->export($this->site(), 'https://static.example.com/');
		$home   = (string) file_get_contents($this->exported('index.html'));

		$this->assertSame('https://static.example.com', $report->url);
		$this->assertStringContainsString('https://static.example.com/', $home);
		$this->assertStringNotContainsString('http://localhost', $home);
		$this->assertStringContainsString('<loc>https://static.example.com/archives/spring</loc>', (string) file_get_contents($this->exported('sitemap/post/index.xml')));

		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(url: 'https://mirror.example.com');\n");

		$this->assertSame('https://mirror.example.com', $this->export($this->site())->url);
		$this->expectException(ExportException::class);
		$this->export($this->site(), 'https://example.com/blog');
	}

	public function testUnchangedFilesStayAndStaleOnesGo(): void
	{
		$this->exportableContent();

		$first = $this->export($this->site());

		$this->assertSame(0, $first->unchanged);
		$this->writeTemporaryFile('storage/export/CNAME', 'example.com');
		unlink($this->temporaryDirectory() . '/user/content/about/biography.md');

		$second = $this->export($this->site());

		$this->assertGreaterThan(0, $second->unchanged);
		$this->assertContains('about/biography/index.html', $second->removed);
		$this->assertFileDoesNotExist($this->exported('about/biography'));
		$this->assertFileExists($this->exported('about/index.html'));
		$this->assertFileExists($this->exported('CNAME'));
	}

	public function testPublicFilesAreCopiedAndWin(): void
	{
		$this->exportableContent();
		$this->writeTemporaryFile('public/index.php', '<?php');
		$this->writeTemporaryFile('public/.htaccess', 'Deny from all');
		$this->writeTemporaryFile('public/robots.txt', "User-agent: *\nAllow: /\n");
		$this->writeTemporaryFile('public/favicon.ico', 'icon');
		$this->writeTemporaryFile('public/themes/blush/default/old.css', 'stale');

		$this->export($this->site());

		$this->assertSame("User-agent: *\nAllow: /\n", file_get_contents($this->exported('robots.txt')));
		$this->assertSame('icon', file_get_contents($this->exported('favicon.ico')));
		$this->assertFileDoesNotExist($this->exported('index.php'));
		$this->assertStringNotContainsString('Deny from all', (string) file_get_contents($this->exported('.htaccess')));
		$this->assertFileDoesNotExist($this->exported('themes/blush/default/old.css'));
	}

	public function testCrawlingFindsLinkedPagesAndBrokenLinks(): void
	{
		$this->exportableContent();
		$this->entry('about/links.md', 'title: Links', "[Hello](/hello), [gone](/nowhere), [off-site](https://example.org/), and [mail](mailto:me@example.com).");
		$this->writeTemporaryFile('config/routes.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			use Blush\Routing\Route;
			use Blush\Routing\RouteConfig;
			use Blush\Tests\Fixtures\Routing\Page;

			return new RouteConfig(routes: [Route::get('/hello', Page::class)]);
			PHP);

		$report = $this->export($this->site());

		$this->assertSame('page:page', file_get_contents($this->exported('hello/index.txt')));
		$this->assertSame(['/nowhere' => '/about/links'], $report->broken);

		$quiet = $this->export($this->site(), crawl: false);

		$this->assertSame([], $quiet->broken);
		$this->assertContains('hello/index.txt', $quiet->removed);
	}

	public function testConfiguredPathsExclusionsAndFailures(): void
	{
		$this->exportableContent();
		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(paths: ['/explode'], exclude: ['/topics*']);\n");
		$this->writeTemporaryFile('config/routes.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			use Blush\Routing\Route;
			use Blush\Routing\RouteConfig;
			use Blush\Tests\Fixtures\View\Explodes;

			return new RouteConfig(routes: [Route::get('/explode', Explodes::class)]);
			PHP);

		$report = $this->export($this->site());

		$this->assertFalse($report->isSuccessful());
		$this->assertSame(['/explode' => 'The site answered HTTP 500.'], $report->failures);
		$this->assertFileDoesNotExist($this->exported('topics/index.html'));
		$this->assertFileDoesNotExist($this->exported('topics/art/feed/index.rss'));
		$this->assertFileExists($this->exported('archives/spring/index.html'));
	}

	public function testEventsAreDispatched(): void
	{
		$this->exportableContent();

		$app    = $this->site();
		$events = [];

		$app->container()->make(ListenerRegistry::class)->listen(ExportStarted::class, static function (ExportStarted $event) use (&$events): void {
			$events[] = "started {$event->url}";
		});
		$app->container()->make(ListenerRegistry::class)->listen(ExportFinished::class, static function (ExportFinished $event) use (&$events): void {
			$events[] = "finished {$event->report->pages}";
		});

		$report = $this->export($app);

		$this->assertSame(['started http://localhost', "finished {$report->pages}"], $events);
	}

	public function testRefusesTheSiteOwnFolders(): void
	{
		$this->exportableContent();

		foreach (['user', 'public', '.', 'storage', 'storage/cache/out', 'user/content/out'] as $folder) {
			$app = new Bootstrap(Paths::fromRoot($this->temporaryDirectory(), ['export' => $folder]), ['APP_ENV' => 'production'])->createApplication();
			$app->boot();

			try {
				$app->container()->make(Exporter::class)->export();
				$this->fail("Exporting to {$folder} should fail.");
			} catch (ExportException $error) {
				$this->assertStringContainsString('overlaps', $error->getMessage());
			}
		}

		$app = new Bootstrap(Paths::fromRoot($this->temporaryDirectory(), ['export' => 'dist']), ['APP_ENV' => 'production'])->createApplication();
		$app->boot();

		$this->assertTrue($app->container()->make(Exporter::class)->export()->isSuccessful());
		$this->assertFileExists($this->temporaryDirectory() . '/dist/index.html');
	}

	public function testOneExportRunsAtATime(): void
	{
		$this->exportableContent();

		$app  = $this->site();
		$lock = fopen($this->writeTemporaryFile('storage/cache/export.lock', ''), 'c');

		$this->assertNotFalse($lock);
		flock($lock, LOCK_EX);

		try {
			$this->expectExceptionMessage('Another export is running.');
			$this->export($app);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function testTheBuildCommand(): void
	{
		$this->exportableContent();

		$tester = new CommandTester($this->site()->container()->make(Console::class));
		$result = $tester->run('build -v --base-url=https://example.com');

		$this->assertTrue($result->isSuccessful(), $result->errors);
		$this->assertMatchesRegularExpression('/Exported \d+ pages and \d+ files to storage\/export for https:\/\/example\.com in \d+ ms/', $result->output);

		$this->assertSame(ExitCode::Invalid, $tester->run('build --base-url=example.com')->exitCode);

		$this->entry('about/links.md', 'title: Links', '[gone](/nowhere)');
		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(paths: ['/explode']);\n");
		$this->writeTemporaryFile('config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Routing\\RouteConfig(routes: [Blush\\Routing\\Route::get('/explode', Blush\\Tests\\Fixtures\\View\\Explodes::class)]);\n");

		$failed = new CommandTester($this->site()->container()->make(Console::class))->run('build');

		$this->assertSame(ExitCode::Failure, $failed->exitCode);
		$this->assertStringContainsString('/explode: The site answered HTTP 500.', $failed->errors);
		$this->assertStringContainsString('/nowhere (linked from /about/links) is not a page.', $failed->errors . $failed->output);
	}

	public function testIncrementalBuildsKeepPagesWhenNothingChanged(): void
	{
		$this->exportableContent();

		$first = $this->export($this->site(), incremental: true);

		$this->assertTrue($first->rendered);

		$again = $this->export($this->site(), incremental: true);

		$this->assertFalse($again->rendered);
		$this->assertSame(0, $again->written);
		$this->assertSame([], $again->removed);
		$this->assertSame($first->unchanged + $first->written, $again->unchanged);
		$this->assertFileExists($this->exported('archives/spring/index.html'));

		// New content moves the content version on.
		$this->entry('_posts/2009-01-01.new.md', "title: New\npublished: 2009-01-01 12:00:00");
		$this->assertTrue($this->export($this->site(), incremental: true)->rendered);
		$this->assertFileExists($this->exported('archives/new/index.html'));
		$this->assertFalse($this->export($this->site(), incremental: true)->rendered);

		// So does anything the fingerprint covers, such as config.
		touch($this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(redirectPages: false);\n"), time() + 10);
		$this->assertTrue($this->export($this->site(), incremental: true)->rendered);

		// A rendered file gone from the output means rendering again.
		unlink($this->exported('about/index.html'));
		$this->assertTrue($this->export($this->site(), incremental: true)->rendered);
		$this->assertFileExists($this->exported('about/index.html'));

		// Another origin, or a build without --incremental, renders.
		$this->assertTrue($this->export($this->site(), 'https://example.com', incremental: true)->rendered);
		$this->assertTrue($this->export($this->site(), 'https://example.com')->rendered);
	}

	public function testRedirectsReachTheHost(): void
	{
		$this->exportableContent();
		$this->entry('about/team.md', "title: Team\nredirect_from: /people");
		$this->writeTemporaryFile('user/data/redirects.json', json_encode([
			'/old'             => '/about',
			'/legacy/{slug}'   => '/archives/{slug}',
			'/files/{path:.+}' => '/media/{path}',
			'/v-{id}'          => '/about',
			'/about/biography' => '/nowhere'
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

		$report   = $this->export($this->site());
		$htaccess = (string) file_get_contents($this->exported('.htaccess'));
		$netlify  = (string) file_get_contents($this->exported('_redirects'));
		$headers  = (string) file_get_contents($this->exported('_headers'));

		// Static redirects the site confirmed, with a page that redirects.
		$this->assertSame('/about', $report->redirects['/old']);
		$this->assertSame('/about/team', $report->redirects['/people']);
		$this->assertStringContainsString('<meta http-equiv="refresh" content="0; url=/about">', (string) file_get_contents($this->exported('old/index.html')));
		$this->assertStringContainsString('<link rel="canonical" href="http://localhost/about/team">', (string) file_get_contents($this->exported('people/index.html')));
		$this->assertStringContainsString("RewriteRule ^old$ /about [R=301,L,NE]\n", $htaccess);
		$this->assertStringContainsString("/old /about 301\n", $netlify);

		// A redirect whose path is a page isn't one.
		$this->assertArrayNotHasKey('/about/biography', $report->redirects);
		$this->assertStringContainsString('Biography', (string) file_get_contents($this->exported('about/biography/index.html')));
		$this->assertStringNotContainsString('biography', $htaccess);

		// Patterns apply only where no file exists.
		$this->assertStringContainsString("RewriteCond %{REQUEST_FILENAME} !-d\n\tRewriteRule ^legacy/([^/]+)$ /archives/$1 [R=301,L,NE]\n", $htaccess);
		$this->assertStringContainsString("/legacy/:slug /archives/:slug 301\n", $netlify);
		$this->assertStringContainsString("/files/* /media/:splat 301\n", $netlify);
		$this->assertStringContainsString('RewriteRule ^v\-([^/]+)$ /about [R=301,L,NE]', $htaccess);
		$this->assertSame(['The redirect from "/v-{id}" can\'t be written to _redirects.'], $report->notices);

		// Index files that aren't HTML.
		$this->assertStringContainsString("/feed /feed/index.rss 200\n", $netlify);
		$this->assertStringContainsString("/feed\n  Content-Type: application/rss+xml\n", $headers);
		$this->assertStringContainsString("ErrorDocument 404 /404.html\n", $htaccess);
		$this->assertStringContainsString("DirectorySlash Off\n", $htaccess);
	}

	public function testHostFormatsAndRedirectPagesAreConfigurable(): void
	{
		$this->exportableContent();
		$this->writeTemporaryFile('user/data/redirects.json', '{"/old": "/about"}');
		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(hosts: ['apache'], redirectPages: false);\n");
		$this->writeTemporaryFile('public/_redirects', '/custom /about 301');

		$this->export($this->site());

		$this->assertFileExists($this->exported('.htaccess'));
		$this->assertSame('/custom /about 301', file_get_contents($this->exported('_redirects')));
		$this->assertFileDoesNotExist($this->exported('_headers'));
		$this->assertFileDoesNotExist($this->exported('old/index.html'));

		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(hosts: ['netlify']);\n");

		$report = $this->export($this->site());

		$this->assertSame(['public/_redirects replaces the generated _redirects.'], $report->notices);
		$this->assertFileDoesNotExist($this->exported('.htaccess'));

		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(hosts: ['caddy']);\n");
		$this->expectExceptionMessage('Unknown host format "caddy"');
		$this->export($this->site());
	}

	public function testExtensionsAddHostFormats(): void
	{
		$this->exportableContent();
		$this->writeTemporaryFile('config/export.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Export\\ExportConfig(hosts: ['plain']);\n");

		// The export application boots from the site's files, so the
		// format is registered as an extension's provider would.
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Core\\AppConfig(timezone: 'America/Chicago', providers: [Blush\\Tests\\Fixtures\\Export\\PlainHostProvider::class]);\n");

		$this->export($this->site());

		$this->assertSame("redirects: 0\n", file_get_contents($this->exported('hosting.txt')));
	}

	public function testHostFilesForTrailingSlashes(): void
	{
		$context = new HostContext(trailingSlash: true);
		$apache  = new ApacheFiles()->files($context)->files['.htaccess'];

		$this->assertStringNotContainsString('DirectorySlash Off', $apache);
		$this->assertStringNotContainsString('ErrorDocument', $apache);
		$this->assertStringNotContainsString('# Redirects', $apache);
		$this->assertSame(['_redirects', '_headers'], array_keys(new NetlifyFiles()->files($context)->files));
	}

	public function testTheLayout(): void
	{
		$cases = [
			['/', 'text/html; charset=utf-8', 'index.html'],
			['/about', 'text/html', 'about/index.html'],
			['/about/', 'text/html', 'about/index.html'],
			['/feed', 'application/rss+xml', 'feed/index.rss'],
			['/feed/atom', 'application/atom+xml', 'feed/atom/index.atom'],
			['/feed/json', 'application/feed+json', 'feed/json/index.json'],
			['/sitemap', 'application/xml', 'sitemap/index.xml'],
			['/sitemap.xml', 'application/xml', 'sitemap.xml'],
			['/robots.txt', 'text/plain', 'robots.txt'],
			['/notes/v1.2', 'text/html', 'notes/v1.2/index.html'],
			['/caf%C3%A9', 'text/html', 'café/index.html'],
			['/images/logo.png', 'image/png', 'images/logo.png']
		];

		foreach ($cases as [$path, $type, $file]) {
			$this->assertSame($file, ExportLayout::file($path, $type), $path);
		}

		foreach ([['/a/%2E%2E/b', 'text/html'], ['/data', 'application/octet-stream']] as [$path, $type]) {
			try {
				ExportLayout::file($path, $type);
				$this->fail("{$path} should not be exportable.");
			} catch (ExportException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTheConfig(): void
	{
		$config = ExportConfig::fromArray(['url' => 'https://example.com/', 'crawl' => false, 'paths' => ['/extra'], 'exclude' => ['/drafts/*']]);

		$this->assertSame(
			['url' => 'https://example.com/', 'crawl' => false, 'paths' => ['/extra'], 'exclude' => ['/drafts/*'], 'hosts' => ['apache', 'netlify'], 'redirectPages' => true],
			$config->toArray()
		);
		$this->assertSame(['apache'], ExportConfig::fromArray(['hosts' => ['apache'], 'redirectPages' => false])->hosts);
		$this->assertTrue($config->excludes('/drafts/one'));
		$this->assertFalse($config->excludes('/drafts'));
		$this->assertTrue(ExportConfig::isOrigin('http://localhost:8080'));
		$this->assertFalse(ExportConfig::isOrigin('https://example.com/blog'));
		$this->assertFalse(ExportConfig::isOrigin('ftp://example.com'));

		foreach ([['paths' => ['relative']], ['hosts' => ['Not A Host']]] as $data) {
			try {
				ExportConfig::fromArray($data);
				$this->fail('The config should be invalid.');
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTheManifest(): void
	{
		$path = $this->writeTemporaryFile('manifest.json', '{"root": "/out", "url": "https://example.com", "files": {"index.html": "xxh128:1", "bad": 2}}');

		$manifest = ExportManifest::read($path);

		$this->assertNotNull($manifest);
		$this->assertSame(['index.html' => 'xxh128:1'], $manifest->files);

		file_put_contents($path, '{broken');

		$this->assertNull(ExportManifest::read($path));
		$this->assertNull(ExportManifest::read($path . '.missing'));
	}

	public function testTheStaticServerRouter(): void
	{
		$this->exportableContent();
		$this->export($this->site());

		$root = $this->temporaryDirectory() . '/storage/export';

		foreach (['/about' => 'about/index.html', '/feed' => 'feed/index.rss', '/robots.txt' => 'robots.txt', '/nowhere' => '404.html', '/../config/app.php' => '404.html', '/_redirects' => '404.html', '/.htaccess' => '404.html'] as $uri => $file) {
			$this->assertSame(file_get_contents("{$root}/{$file}"), $this->route($root, $uri), $uri);
		}

		file_put_contents("{$root}/_redirects", "# Rules\n/old /about 301\n/legacy/:slug /archives/:slug 301\n/files/* /media/:splat 302\n/robots /robots.txt 200\n");

		$this->assertSame('Redirecting to /about', $this->route($root, '/old'));
		$this->assertSame('Redirecting to /archives/spring', $this->route($root, '/legacy/spring'));
		$this->assertSame('Redirecting to /media/a/b.png', $this->route($root, '/files/a/b.png'));
		$this->assertSame(file_get_contents("{$root}/robots.txt"), $this->route($root, '/robots'));
		$this->assertSame(file_get_contents("{$root}/about/index.html"), $this->route($root, '/about'));
	}

	/**
	 * Runs the static server's router for a URI and returns its output.
	 */
	private function route(string $root, string $uri): string
	{
		$process = proc_open(
			[PHP_BINARY, dirname(__DIR__, 2) . '/resources/static-server.php'],
			[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes,
			$root,
			['DOCUMENT_ROOT' => $root, 'REQUEST_URI' => $uri]
		);

		$this->assertIsResource($process);

		$output = (string) stream_get_contents($pipes[1]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		proc_close($process);

		return $output;
	}
}
