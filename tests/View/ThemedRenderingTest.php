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
use Blush\Auth\Accounts;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Middleware\HandleErrors;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\WritesThemeViews;
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
	use WritesThemeViews;

	private function get(string $uri, ?Application $app = null): ResponseInterface
	{
		return ($app ?? $this->site())->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	private function body(string $uri, ?Application $app = null): string
	{
		return (string) $this->get($uri, $app)->getBody();
	}

	private function activeTheme(string $name): void
	{
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: '{$name}');\n");
	}

	private function childTheme(): void
	{
		$this->writeTemporaryFile('extensions/acme/child/theme.json', '{"name": "acme/child", "label": "Child", "namespace": "child", "parent": "blush/default", "styles": ["style.css", "extra.css"], "scripts": ["app.js"], "preload": ["fonts/body.woff2"]}');
		$this->writeTemporaryFile('extensions/acme/child/extra.css', '');
		$this->writeTemporaryFile('extensions/acme/child/fonts/body.woff2', 'font');
		$this->writeTemporaryFile('extensions/acme/child/app.js', '');
		$this->writeTemporaryFile('extensions/acme/child/lang/en.json', '{"skip_to_content": "Skip ahead"}');
		$this->writeTemporaryFile('extensions/acme/child/views/single-post.php', '<?php $template->layout(\'base\') ?><h1 class="post">Post: <?= e($title) ?></h1>');
	}

	public function testRendersEveryKindOfPage(): void
	{
		$this->standardContent();

		$app  = $this->site();
		$home = $this->body('/', $app);

		$this->assertStringContainsString('<html lang="en-US" dir="ltr">', $home);
		$this->assertStringContainsString('<title>Blush</title>', $home);
		$this->assertStringContainsString('<body class="is-home type-post">', $home);
		$this->assertStringContainsString('<a class="skip-link" href="#main">Skip to content</a>', $home);
		$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="http://localhost/themes/blush/default/style.css\?v=[0-9a-f]{8}">#', $home);
		$this->assertStringContainsString('<link rel="canonical" href="http://localhost/">', $home);
		$this->assertStringContainsString('<a href="/archives/spring">spring</a>', $home);
		$this->assertStringContainsString('<time datetime="2008-04-05T09:00:00-05:00">April 5, 2008</time>', $home);
		$this->assertStringContainsString('<a class="entry-meta__term" href="/topics/art">Art</a>', $home);
		$this->assertStringContainsString('<a class="entry-meta__term" href="/topics/book-reviews">Book Reviews</a>', $home);
		$this->assertStringContainsString('<p>Spring is here.</p>', $home);
		$this->assertMatchesRegularExpression('#<p>Powered by [^<]+\.</p>\s*</footer>#', $home, '1.x\'s lines (D-450).');

		$single = $this->body('/archives/spring', $app);

		$this->assertStringContainsString('<title>spring | Blush</title>', $single);
		$this->assertStringContainsString('<meta property="og:type" content="article">', $single);
		$this->assertStringContainsString('<h1 class="entry__title">spring</h1>', $single);
		$this->assertStringContainsString('<meta name="description" content="Spring is here.">', $single);
		$this->assertStringContainsString('<meta property="og:description" content="Spring is here.">', $single);
		$this->assertStringNotContainsString('og:image', $single);

		$this->assertStringContainsString('<h1 class="archive-header__title">Art</h1>', $this->body('/topics/art', $app));
		$this->assertStringContainsString('<body class="is-date type-post">', $this->body('/archives/2008/04', $app));
		$this->assertStringContainsString('<h1 class="entry__title">Biography</h1>', $this->body('/about/biography', $app));
		$this->assertStringContainsString('<h1 class="archive-header__title">Blog</h1>', $home);
	}

	public function testAThemeWithoutViewsRendersInTheFrameworksOwn(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('extensions/acme/bare/theme.json', '{"name": "acme/bare", "label": "Bare", "namespace": "bare"}');
		$this->activeTheme('acme/bare');

		$app    = $this->site();
		$single = $this->body('/archives/spring', $app);

		$this->assertStringContainsString('<html lang="en-US" dir="ltr">', $single);
		$this->assertStringContainsString('<a href="#main">Skip to content</a>', $single, 'The skeleton\'s skip link, in the framework\'s words (D-632).');
		$this->assertStringContainsString('<main id="main" tabindex="-1">', $single);
		$this->assertStringContainsString('<h1>spring</h1>', $single);
		$this->assertStringNotContainsString('blush/default', $single, 'The default theme isn\'t in the chain.');
		$this->assertStringNotContainsString('site-header', $single);
		$this->assertStringContainsString('<h1>Art</h1>', $this->body('/topics/art', $app));
		$this->assertStringContainsString('Page not found', $this->body('/nope', $app));
	}

	public function testLaterPagesOfAListingAreNumberedAndTitledByPage(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'collection'    => ['order' => 'desc', 'number' => 1],
					'date_archives' => true,
					'routing'       => ['prefix' => 'archives']
				]
			],
			'home' => 'post'
		]);
		$this->entry('_post/2008-04-10.showers.md', "title: Showers\npublished: 2008-04-10 09:00:00");

		$app  = $this->site();
		$home = $this->body('/page/2', $app);

		$this->assertStringContainsString('<title>Page 2 | Blush</title>', $home);
		$this->assertStringContainsString('<li class="pagination__item pagination__item--prev">', $home);
		$this->assertStringContainsString('<a class="pagination__link" href="/">Previous page</a>', $home);
		$this->assertStringContainsString('<span class="pagination__link" aria-current="page">2</span>', $home);
		$this->assertStringContainsString('<a class="pagination__link" href="/page/4">4</a>', $home);
		$this->assertStringContainsString('<a class="pagination__link" href="/page/3">Next page</a>', $home);

		$this->assertStringContainsString('<title>Blush</title>', $this->body('/', $app));
		$this->assertMatchesRegularExpression('#<title>April 2008: Page 2 \| Blush</title>#', $this->body('/archives/2008/04/page/2', $app));
		$this->assertStringNotContainsString('pagination', $this->body('/archives/spring', $app));
	}

	public function testAThemeCanRewordThePagedTitle(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => ['post' => ['collection' => ['number' => 1], 'routing' => ['prefix' => 'archives']]],
			'home'  => 'post'
		]);
		$this->childTheme();
		$this->writeTemporaryFile('extensions/acme/child/lang/en.json', '{"document_title": {"page": "Page {page} of the archives"}}');
		$this->activeTheme('acme/child');

		$this->assertStringContainsString('<title>Page 2 of the archives | Blush</title>', $this->body('/page/2'));
	}

	public function testAChildThemeOverridesOnlyWhatItProvides(): void
	{
		$this->standardContent();
		$this->childTheme();
		$this->activeTheme('acme/child');

		$app    = $this->site();
		$single = $this->body('/archives/spring', $app);

		$this->assertStringContainsString('<h1 class="post">Post: spring</h1>', $single);
		$this->assertStringContainsString('<a class="skip-link" href="#main">Skip ahead</a>', $single);
		$this->assertMatchesRegularExpression('#href="http://localhost/themes/blush/default/style.css\?v=[0-9a-f]{8}">\n\t<link rel="stylesheet" href="http://localhost/themes/acme/child/extra.css\?v=[0-9a-f]{8}">#', $single);
		$this->assertMatchesRegularExpression('#<script src="http://localhost/themes/acme/child/app.js\?v=[0-9a-f]{8}" defer></script>#', $single);
		$this->assertMatchesRegularExpression('#<link rel="preload" href="http://localhost/themes/acme/child/fonts/body.woff2" as="font" type="font/woff2" crossorigin>#', $single, 'A theme\'s preload list (D-558), a font without a version, as its stylesheet asks for it (D-698).');
		$this->assertStringContainsString('<h1 class="entry__title">Biography</h1>', $this->body('/about/biography', $app));
	}

	public function testSiteTranslationsOverrideThemes(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/lang/en/extensions/blush/default.json', '{"@@locale": "en", "@@domain": "blush/default", "skip_to_content": "Jump to the post", "powered_by": {"tea": "Powered by tea."}}');

		$single = $this->body('/archives/spring');

		$this->assertStringContainsString('<a class="skip-link" href="#main">Jump to the post</a>', $single);
		$this->assertStringContainsString('<p>Powered by tea.</p>', $single, 'An override\'s group replaces the theme\'s.');
	}

	public function testAnEntrysImageBecomesTheSharingImage(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/content/about/photo.md', "---\nid: 9b92581d-f1d3-06c1-8620-92b9d7e0ec1b\ntitle: Photo\nimage: /user/media/me.jpg\nsummary: A *photo* of me.\n---\nBody");
		$this->writeTemporaryFile('user/content/about/remote.md', "---\nid: bb40824a-2574-820b-0d26-5041e81bbe39\ntitle: Remote\nimage: https://cdn.example.com/me.jpg\n---\nBody");

		$app   = $this->site();
		$photo = $this->body('/about/photo', $app);

		$this->assertStringContainsString('<meta name="description" content="A photo of me.">', $photo);
		$this->assertStringContainsString('<meta property="og:image" content="http://localhost/user/media/me.jpg">', $photo);
		$this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $photo);
		$this->assertStringContainsString('<meta property="og:image" content="https://cdn.example.com/me.jpg">', $this->body('/about/remote', $app));
	}

	public function testPartialsSeeThePageAndTaxonomyListingsShareATemplate(): void
	{
		$this->standardContent();
		$this->themeView('collection-terms.php', '<?php $template->layout(\'base\') ?><?= $template->include(\'partials/terms-title\') ?>');
		$this->themeView('partials/terms-title.php', '<p class="terms"><?= e($title) ?>: <?= e($type->name) ?>, <?= count($page->entries ?? []) ?></p>');
		$this->themeView('partials/footer.php', '<footer><?= e($entry?->title ?? "none") ?></footer>');

		$app = $this->site();

		$this->assertStringContainsString('<p class="terms">Topics: category, 3</p>', $this->body('/topics', $app));
		$this->assertStringContainsString('<footer>Biography</footer>', $this->body('/about/biography', $app));
		$this->assertStringNotContainsString('class="terms"', $this->body('/archives', $app));
	}

	public function testFrontMatterControlsPresentation(): void
	{
		$this->standardContent();
		$this->entry('about/index.md', "title: About\ntemplate: [about-page]\nlayout: plain\nclass: [wide, 'dark mode']");
		$this->themeView('about-page.php', '<?php $template->layout(\'base\') ?>custom about');
		$this->themeView('layouts/plain.php', '<body class="<?= attr($template->bodyClass()) ?>"><?= $template->section(\'content\') ?></body>');

		$this->assertSame('<body class="wide dark mode is-page type-page">custom about</body>', $this->body('/about'));
	}

	public function testTheWelcomePageShowsOnAnEmptySite(): void
	{
		$this->contentConfig([]);

		$html = $this->body('/');

		$this->assertStringContainsString('<h1 class="entry__title">Welcome to Blush</h1>', $html);
		$this->assertStringContainsString('<body class="is-welcome">', $html);
		$this->assertStringContainsString('<code>user/content/index.md</code>', $html);
		$this->assertStringContainsString('turn on the admin in <code>config/admin.php</code>', $html);
		$this->assertStringNotContainsString('Setup notes', $html);
	}

	public function testTheWelcomePageShowsTheAdminAndSetupNotesWhileDeveloping(): void
	{
		$this->contentConfig([]);
		$this->writeTemporaryFile('config/admin.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Admin\\AdminConfig(enabled: true);\n");

		$app  = $this->site('development');
		$html = $this->body('/', $app);

		$this->assertStringContainsString('Create an account with <code>bin/blush account:add</code>, then sign in to the admin at <a href="/admin">/admin</a>.', $html);
		$this->assertStringContainsString('Setup notes', $html);
		$this->assertStringContainsString('<code>.env</code>: Not found', $html);
		$this->assertStringContainsString('<code>bin/blush doctor</code>', $html);

		$app->container()->make(Accounts::class)->create('jane', 'a long enough password', ['administrator'], email: 'jane@example.com');

		$this->assertStringContainsString('Sign in to the admin at <a href="/admin">/admin</a>.', $this->body('/', $app));
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
		$this->entry('_error/index.md', 'title: Error');
		$this->entry('_errors/index.md', 'title: Errors');
		$this->entry('_error/404.md', 'title: "404"', 'Sorry, nothing was found here (1.x).');

		// Development reindexes on each request, so new error entries show.
		$app = $this->site('development');

		$this->assertStringContainsString('<h1 class="entry__title">404</h1>', $this->body('/nowhere', $app));

		$this->entry('_errors/404.md', "title: Lost?\ntemplate: lost", 'Try the *archives*.');
		$lost = $this->themeView('lost.php', '<?php $template->layout(\'base\') ?>lost view');

		$html = $this->body('/nowhere', $this->site('development'));

		$this->assertStringContainsString('<title>Lost? | Blush</title>', $html);
		$this->assertStringContainsString('lost view', $html);

		unlink($lost);

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
		$this->themeView('error.php', '<?php throw new RuntimeException("Broken error view");');

		$response = $this->get('/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringContainsString('<h1>404 Not Found</h1>', (string) $response->getBody());
		$this->assertStringContainsString('Broken error view', (string) file_get_contents($this->temporaryDirectory() . '/storage/logs/blush.log'));
	}

	public function testThemesSwitchByQueryInDevelopment(): void
	{
		$this->standardContent();
		$this->childTheme();

		$this->assertStringContainsString('Post: spring', $this->body('/archives/spring?theme=acme/child', $this->site('development')));
		$this->assertStringNotContainsString('Post: spring', $this->body('/archives/spring?theme=acme/child', $this->site('production')));
	}
}
