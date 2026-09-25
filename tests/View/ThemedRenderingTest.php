<?php

/**
 * Themed rendering tests.
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
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Middleware\HandleErrors;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\Hierarchy;
use Blush\View\ThemedErrorPages;
use Blush\View\ThemedPageRenderer;

#[CoversClass(ThemedPageRenderer::class)]
#[CoversClass(ThemedErrorPages::class)]
#[CoversClass(Hierarchy::class)]
#[CoversClass(HandleErrors::class)]
final class ThemedRenderingTest extends TestCase
{
	use BuildsContentSite;

	private function get(string $uri, ?Application $app = null): ResponseInterface
	{
		return ($app ?? $this->site())->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	private function body(string $uri, ?Application $app = null): string
	{
		return (string) $this->get($uri, $app)->getBody();
	}

	private function activeTheme(string $slug): void
	{
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: '{$slug}');\n");
	}

	private function childTheme(): void
	{
		$this->writeTemporaryFile('user/themes/child/theme.json', '{"name": "Child", "styles": ["style.css", "extra.css"], "scripts": ["app.js"]}');
		$this->writeTemporaryFile('user/themes/child/extra.css', '');
		$this->writeTemporaryFile('user/themes/child/app.js', '');
		$this->writeTemporaryFile('user/themes/child/lang/en.json', '{"powered_by": "Made with {generator}"}');
		$this->writeTemporaryFile('user/themes/child/views/single-post.php', '<?php $this->layout(\'base\') ?><h1 class="post">Post: <?= e($title) ?></h1>');
	}

	public function testRendersEveryKindOfPage(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$home = $this->body('/', $app);

		$this->assertStringContainsString('<html lang="en-US">', $home);
		$this->assertStringContainsString('<title>Blush</title>', $home);
		$this->assertStringContainsString('<body class="is-home type-post">', $home);
		$this->assertStringContainsString('<a class="skip-link" href="#main">Skip to content</a>', $home);
		$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/themes/default/style.css\?v=\d+">#', $home);
		$this->assertStringContainsString('<link rel="canonical" href="http://localhost/">', $home);
		$this->assertStringContainsString('<a href="/archives/spring">spring</a>', $home);
		$this->assertStringContainsString('<time datetime="2008-04-05T09:00:00-05:00">April 5, 2008</time>', $home);
		$this->assertStringContainsString('<a class="entry-meta__term" href="/topics/art">Art</a>', $home);
		$this->assertStringContainsString('<a class="entry-meta__term" href="/topics/book-reviews">Book Reviews</a>', $home);
		$this->assertStringContainsString('<p>Spring is here.</p>', $home);

		$single = $this->body('/archives/spring', $app);

		$this->assertStringContainsString('<title>spring | Blush</title>', $single);
		$this->assertStringContainsString('<meta property="og:type" content="article">', $single);
		$this->assertStringContainsString('<h1 class="entry__title">spring</h1>', $single);

		$this->assertStringContainsString('<h1 class="archive-header__title">Art</h1>', $this->body('/topics/art', $app));
		$this->assertStringContainsString('<body class="is-date type-post">', $this->body('/archives/2008/04', $app));
		$this->assertStringContainsString('<h1 class="entry__title">Biography</h1>', $this->body('/about/biography', $app));
		$this->assertStringContainsString('<h1 class="archive-header__title">Blog</h1>', $home);
	}

	public function testAChildThemeOverridesOnlyWhatItProvides(): void
	{
		$this->standardContent();
		$this->childTheme();
		$this->activeTheme('child');

		$app    = $this->site();
		$single = $this->body('/archives/spring', $app);

		$this->assertStringContainsString('<h1 class="post">Post: spring</h1>', $single);
		$this->assertStringContainsString('Made with Blush Framework', $single);
		$this->assertMatchesRegularExpression('#href="/themes/default/style.css\?v=\d+">\n<link rel="stylesheet" href="/themes/child/extra.css\?v=\d+">#', $single);
		$this->assertMatchesRegularExpression('#<script src="/themes/child/app.js\?v=\d+" defer></script>#', $single);
		$this->assertStringContainsString('<h1 class="entry__title">Biography</h1>', $this->body('/about/biography', $app));
	}

	public function testSiteViewsOverrideThemes(): void
	{
		$this->standardContent();
		$this->childTheme();
		$this->activeTheme('child');
		$this->writeTemporaryFile('resources/views/single-post.php', 'site override');
		$this->writeTemporaryFile('resources/views/themes/child/single-post.php', 'child-scoped override');
		$this->writeTemporaryFile('resources/views/themes/other/single.php', 'other-scoped override');

		$this->assertSame('child-scoped override', $this->body('/archives/spring'));

		unlink($this->temporaryDirectory() . '/resources/views/themes/child/single-post.php');

		$this->assertSame('site override', $this->body('/archives/spring'));
	}

	public function testFrontMatterControlsPresentation(): void
	{
		$this->standardContent();
		$this->entry('about/index.md', "title: About\ntemplate: [about-page]\nlayout: plain\nclass: [wide, 'dark mode']");
		$this->writeTemporaryFile('resources/views/about-page.php', '<?php $this->layout(\'base\') ?>custom about');
		$this->writeTemporaryFile('resources/views/layouts/plain.php', '<body class="<?= attr($this->bodyClass()) ?>"><?= $this->section(\'content\') ?></body>');

		$this->assertSame('<body class="wide dark mode is-page type-page">custom about</body>', $this->body('/about'));
	}

	public function testTheWelcomePageShowsOnAnEmptySite(): void
	{
		$this->contentConfig([]);

		$html = $this->body('/');

		$this->assertStringContainsString('<h1 class="entry__title">Welcome to Blush</h1>', $html);
		$this->assertStringContainsString('<body class="is-welcome">', $html);
		$this->assertStringContainsString('user/content/index.md', $html);
	}

	public function testErrorsRenderWithTheTheme(): void
	{
		$this->standardContent();

		$response = $this->get('/nowhere');
		$html     = (string) $response->getBody();

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('<title>Page not found | Blush</title>', $html);
		$this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
		$this->assertStringContainsString('<body class="is-error is-error-404">', $html);
		$this->assertStringContainsString('<p>Sorry, nothing was found here.</p>', $html);
		$this->assertStringNotContainsString('error-details', $html);
	}

	public function testErrorPagesComeFromContent(): void
	{
		$this->standardContent();
		$this->entry('_error/404.md', 'title: "404"', 'Sorry, nothing was found here (1.x).');

		// Development reindexes on each request, so new error entries show.
		$app = $this->site('development');

		$this->assertStringContainsString('<h1 class="entry__title">404</h1>', $this->body('/nowhere', $app));

		$this->entry('_errors/404.md', "title: Lost?\ntemplate: lost", 'Try the *archives*.');
		$this->writeTemporaryFile('resources/views/lost.php', '<?php $this->layout(\'base\') ?>lost view');

		$html = $this->body('/nowhere', $this->site('development'));

		$this->assertStringContainsString('<title>Lost? | Blush</title>', $html);
		$this->assertStringContainsString('lost view', $html);

		unlink($this->temporaryDirectory() . '/resources/views/lost.php');

		$this->assertStringContainsString('<p>Try the <em>archives</em>.</p>', $this->body('/nowhere', $this->site('development')));
		$this->assertSame(404, $this->get('/_errors/404')->getStatusCode());
	}

	public function testServerErrorsAreThemedUnlessDebugging(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('config/routes.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			use Blush\Routing\Route;
			use Blush\Routing\RouteConfig;
			use Blush\Tests\Fixtures\View\Explodes;

			return new RouteConfig(routes: [Route::get('/explode', Explodes::class)]);
			PHP);

		$response = $this->get('/explode');
		$html     = (string) $response->getBody();

		$this->assertSame(500, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="entry__title">Something went wrong</h1>', $html);
		$this->assertStringNotContainsString('Controller exploded', $html);

		$debug = $this->scratchApplication(['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'APP_TIMEZONE' => 'America/Chicago']);
		$debug->boot();

		$this->assertStringContainsString('RuntimeException', $this->body('/explode', $debug));
		$this->assertStringContainsString('<p class="error-details"><code>There is no page at &quot;nowhere&quot;.</code></p>', $this->body('/nowhere', $debug));
	}

	public function testABrokenErrorPageFallsBackToTheGenericOne(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('resources/views/error.php', '<?php throw new RuntimeException("Broken error view");');

		$response = $this->get('/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringContainsString('<h1>404 Not Found</h1>', (string) $response->getBody());
		$this->assertStringContainsString('Broken error view', (string) file_get_contents($this->temporaryDirectory() . '/storage/logs/blush.log'));
	}

	public function testThemesSwitchByQueryInDevelopment(): void
	{
		$this->standardContent();
		$this->childTheme();

		$this->assertStringContainsString('Post: spring', $this->body('/archives/spring?theme=child', $this->site('development')));
		$this->assertStringNotContainsString('Post: spring', $this->body('/archives/spring?theme=child', $this->site('production')));
	}
}
