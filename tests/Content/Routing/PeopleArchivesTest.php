<?php

/**
 * People archive tests.
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
use Psr\Http\Message\ResponseInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\PeopleController;
use Blush\Content\Http\PersonController;
use Blush\Content\Http\ProfileController;
use Blush\Content\PeopleArchives;
use Blush\Content\Routing\ContentExportUrls;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\PeopleField;
use Blush\Core\Application;
use Blush\Export\UrlSource;
use Blush\Feed\FeedExportUrls;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\RouteTable;
use Blush\Sitemap\SitemapBuilder;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\Hierarchy;

#[CoversClass(PeopleController::class)]
#[CoversClass(PersonController::class)]
#[CoversClass(ProfileController::class)]
#[CoversClass(PeopleArchives::class)]
final class PeopleArchivesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Writes the standard content with a blog that credits authors, a
	 * feed, and one post per page, then boots it.
	 *
	 * @param array<string, mixed> $post    Options for the post type.
	 * @param array<string, mixed> $profile Options for the profiles type.
	 */
	private function boot(array $post = [], array $profile = []): Application
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'orderby' => 'published', 'number' => 1],
					'date_archives' => true,
					'feed'          => true,
					'routing'       => ['prefix' => 'archives'],
					...$post
				],
				'category' => [
					'path'         => 'topics',
					'taxonomy'     => true,
					'term_collect' => 'post'
				],
				...($profile === [] ? [] : ['profile' => ['kind' => 'profiles', 'path' => 'profiles', ...$profile]])
			],
			'home' => 'post'
		]);
		$this->entry('profiles/sam.md', 'title: Sam', 'Credited by nothing yet.');

		return $this->site();
	}

	private function get(Application $app, string $uri): ResponseInterface
	{
		return $app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testListsTheCreditedPeopleByName(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors');
		$body     = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="archive-header__title">Authors</h1>', $body, 'The field\'s label without a list page.');

		preg_match_all('#<h2 class="people__name"><a href="([^"]*)">([^<]*)</a>#', $body, $people);

		$this->assertSame(['A Guest', 'Justin Tadlock'], $people[2], 'By name; Sam has no posts, so no archive.');
		$this->assertSame(['/archives/authors/guest', '/archives/authors/justintadlock'], $people[1]);
		$this->assertStringContainsString('<p>Writes things.</p>', $body, 'Each with their bio.');
	}

	public function testAFieldsListPageIntroducesTheList(): void
	{
		$this->entry('_posts/_authors.md', 'title: Our Writers', 'The people behind the blog.');

		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/authors')->getBody();

		$this->assertStringContainsString('<h1 class="archive-header__title">Our Writers</h1>', $body);
		$this->assertStringContainsString('The people behind the blog.', $body);
		$this->assertSame(404, $this->get($app, '/archives/_authors')->getStatusCode(), 'It has no address of its own.');
		$this->assertStringNotContainsString('Our Writers', (string) $this->get($app, '/feed/json')->getBody(), 'Nor is it in the feed.');
	}

	public function testServesAPersonsArchiveUnderAField(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors/justintadlock');
		$body     = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="archive-header__title">Justin Tadlock</h1>', $body);
		$this->assertStringContainsString('<p>Writes things.</p>', $body, 'The profile\'s bio introduces it.');
		$this->assertStringContainsString('<a href="/archives/spring">', $body, 'Newest first, one a page.');
		$this->assertStringContainsString('<link rel="next" href="http://localhost/archives/authors/justintadlock/page/2">', $body);
		$this->assertStringContainsString('<meta property="og:type" content="profile">', $body);
		$this->assertStringContainsString('href="http://localhost/archives/authors/justintadlock/feed/json"', $body, 'The person\'s feed is advertised.');
		$this->assertStringContainsString('is-person type-post', $body);

		$this->assertSame(200, $this->get($app, '/archives/authors/justintadlock/page/2')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/justintadlock/page/3')->getStatusCode());
		$this->assertSame('/archives/authors/justintadlock', $this->get($app, '/archives/authors/justintadlock/page/1')->getHeaderLine('Location'));
		$this->assertSame(404, $this->get($app, '/archives/authors/sam')->getStatusCode(), 'No posts credit Sam.');
		$this->assertSame(404, $this->get($app, '/archives/authors/nobody')->getStatusCode());
	}

	public function testAPageWrittenForAnArchiveIntroducesItInstead(): void
	{
		$this->entry('_posts/_authors/justintadlock.md', 'title: Justin, Blogger', 'Writes the blog.');
		$this->entry('_posts/_authors/guest.md', "title: Unfinished\nstatus: draft", 'Not yet.');

		$app     = $this->boot();
		$written = (string) $this->get($app, '/archives/authors/justintadlock')->getBody();
		$draft   = (string) $this->get($app, '/archives/authors/guest')->getBody();

		$this->assertStringContainsString('<h1 class="archive-header__title">Justin, Blogger</h1>', $written);
		$this->assertStringContainsString('<p>Writes the blog.</p>', $written);
		$this->assertStringNotContainsString('Writes things.', $written, 'The profile\'s bio steps aside.');
		$this->assertStringContainsString('href="http://localhost/archives/authors/justintadlock/feed/json"', $written, 'The feed is still the person\'s.');
		$this->assertStringContainsString('<h1 class="archive-header__title">A Guest</h1>', $draft, 'A draft falls back to the profile.');
		$this->assertSame(404, $this->get($app, '/archives/_authors/justintadlock')->getStatusCode());
		$this->assertStringNotContainsString('Justin, Blogger', (string) $this->get($app, '/archives/authors')->getBody(), 'It isn\'t a person.');
	}

	public function testServesAPersonsFeed(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/archives/authors/guest/feed/json');
		$feed     = json_decode((string) $response->getBody(), true);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertIsArray($feed);
		$this->assertSame('A Guest | Authors | Posts | Blush', $feed['title'] ?? null);
		$this->assertSame('http://localhost/archives/authors/guest', $feed['home_page_url'] ?? null);
		$this->assertSame(['http://localhost/archives/spring'], array_column(is_array($feed['items'] ?? null) ? $feed['items'] : [], 'url'));
		$this->assertSame(200, $this->get($app, '/archives/authors/guest/feed')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/nobody/feed')->getStatusCode());
	}

	public function testServesEachProfilesOwnPage(): void
	{
		$app      = $this->boot();
		$response = $this->get($app, '/profiles/justintadlock');
		$body     = (string) $response->getBody();

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('<h1 class="archive-header__title">Justin Tadlock</h1>', $body);
		$this->assertStringContainsString('<p>Writes things.</p>', $body);
		$this->assertStringContainsString('<a href="/archives/spring">', $body, 'Everything crediting them.');
		$this->assertStringContainsString('<a href="/archives/welcome">', $body);
		$this->assertStringContainsString('<meta property="og:type" content="profile">', $body);
		$this->assertStringContainsString('is-profile type-profile', $body);
		$this->assertStringNotContainsString('/profiles/feed', $body, 'The profiles type has no feed of its own.');

		$this->assertSame(200, $this->get($app, '/profiles/sam')->getStatusCode(), 'A profile has a page before anything credits them.');
		$this->assertSame(404, $this->get($app, '/profiles/nobody')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/profiles')->getStatusCode(), 'Nothing answers at the base.');
		$this->assertSame(404, $this->get($app, '/profiles/page/2')->getStatusCode());
		$this->assertSame('/profiles/justintadlock', $app->container()->make(ContentUrls::class)->entry($app->container()->make(ContentRepository::class)->term('profile', 'justintadlock') ?? $this->fail('No profile.')));
	}

	public function testAProfilesTypeSetsItsBaseAndFeed(): void
	{
		$app      = $this->boot(profile: ['routing' => ['prefix' => 'people'], 'feed' => true]);
		$response = $this->get($app, '/people/justintadlock/feed/json');
		$feed     = json_decode((string) $response->getBody(), true);

		$this->assertSame(200, $this->get($app, '/people/justintadlock')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/profiles/justintadlock')->getStatusCode());
		$this->assertSame(200, $response->getStatusCode());
		$this->assertIsArray($feed);
		$this->assertSame('Justin Tadlock | Blush', $feed['title'] ?? null);
		$this->assertCount(2, is_array($feed['items'] ?? null) ? $feed['items'] : []);
		$this->assertStringContainsString('href="http://localhost/people/justintadlock/feed/json"', (string) $this->get($app, '/people/justintadlock')->getBody());
		$this->assertNull($app->container()->make(RouteTable::class)->named('profile.collection'));
	}

	public function testBylinesLinkToTheFieldsArchiveElseTheProfile(): void
	{
		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<span class="entry-meta__people">By <a class="entry-meta__person" href="/archives/authors/justintadlock">Justin Tadlock</a>, <a class="entry-meta__person" href="/archives/authors/guest">A Guest</a></span>', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/justintadlock">', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/guest">', $body, 'One tag per person.');

		$app  = $this->boot(['people' => ['authors' => ['archive' => false]]]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<a class="entry-meta__person" href="/profiles/justintadlock">Justin Tadlock</a>', $body, 'Without archives, the profile\'s page.');
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/profiles/justintadlock">', $body);
		$this->assertSame(404, $this->get($app, '/archives/authors')->getStatusCode());

		$app  = $this->boot(['people' => ['authors' => ['archive' => false]]], ['routing' => false]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<span class="entry-meta__person">Justin Tadlock</span>', $body, 'Nowhere to link.');
	}

	public function testATypeCreditsPeopleInItsOwnWords(): void
	{
		$this->entry('_posts/2009-02-02.shots.md', "title: Shots\npublished: 2009-02-02\nauthor: guest\nphotographer: justintadlock");

		$app  = $this->boot(['people' => ['authors' => ['archive' => 'writers'], 'photographers' => ['multiple' => false, 'aliases' => ['photographer']]]]);
		$body = (string) $this->get($app, '/archives/shots')->getBody();

		$this->assertStringContainsString('By <a class="entry-meta__person" href="/archives/writers/guest">A Guest</a>', $body);
		$this->assertStringContainsString('Photographer: <a class="entry-meta__person" href="/archives/photographers/justintadlock">Justin Tadlock</a>', $body);
		$this->assertSame(200, $this->get($app, '/archives/writers/justintadlock')->getStatusCode());
		$this->assertSame(404, $this->get($app, '/archives/authors/justintadlock')->getStatusCode());
		$this->assertNotNull($app->container()->make(RouteTable::class)->named('post.authors.single'));

		$photos = (string) $this->get($app, '/archives/photographers/justintadlock')->getBody();

		$this->assertStringContainsString('<a href="/archives/shots">', $photos);
		$this->assertStringNotContainsString('<a href="/archives/spring">', $photos, 'Only what credits them as a photographer.');
		$this->assertSame(404, $this->get($app, '/archives/photographers/guest')->getStatusCode());

		$profile = (string) $this->get($app, '/profiles/justintadlock')->getBody();

		$this->assertStringContainsString('<a href="/archives/shots">', $profile, 'The profile\'s page lists every credit.');
		$this->assertStringContainsString('<a href="/archives/spring">', $profile);

		$app = $this->boot(['authors' => false]);

		$this->assertStringNotContainsString('entry-meta__people', (string) $this->get($app, '/archives/spring')->getBody(), 'Posts don\'t credit people at all.');
		$this->assertNull($app->container()->make(RouteTable::class)->named('post.authors.single'));
		$this->assertNull($app->container()->make(ContentUrls::class)->people($app->container()->make(ContentTypes::class)->get('post'), new PeopleField('authors')));
	}

	public function testPagesHaveTheirOwnTemplates(): void
	{
		$app     = $this->boot();
		$post    = $app->container()->make(ContentTypes::class)->get('post');
		$field   = $post->people['authors'];
		$profile = $app->container()->make(ContentRepository::class)->term('profile', 'justintadlock');

		$this->assertSame(['people-post-authors', 'people-authors', 'people', 'collection'], Hierarchy::forPage(new ContentPage(PageKind::People, 'Authors', type: $post, people: $field))->names);
		$this->assertSame(
			['person-post-authors', 'person-authors', 'person', 'profile', 'collection'],
			Hierarchy::forPage(new ContentPage(PageKind::Person, 'Justin Tadlock', entry: $profile, type: $post, people: $field, profile: $profile))->names
		);
		$this->assertSame(
			['profile-justintadlock', 'profile', 'collection'],
			Hierarchy::forPage(new ContentPage(PageKind::Profile, 'Justin Tadlock', entry: $profile, profile: $profile))->names
		);
	}

	public function testExportsAndMapsThePages(): void
	{
		$app   = $this->boot();
		$paths = [];

		foreach ([ContentExportUrls::class, FeedExportUrls::class] as $source) {
			$urls = $app->container()->make($source);
			$this->assertInstanceOf(UrlSource::class, $urls);

			foreach ($urls->urls() as $url) {
				$paths[] = $url->path;
			}
		}

		foreach (['/archives/authors', '/archives/authors/guest', '/archives/authors/justintadlock', '/archives/authors/justintadlock/feed/json', '/profiles/justintadlock', '/profiles/sam'] as $path) {
			$this->assertContains($path, $paths);
		}

		$this->assertNotContains('/archives/authors/sam', $paths);
		$this->assertNotContains('/profiles', $paths);
		$this->assertSame(1, count(array_keys($paths, '/profiles/justintadlock', true)), 'Once.');

		$sitemap = $app->container()->make(SitemapBuilder::class);
		$types   = $app->container()->make(ContentTypes::class);
		$urls    = array_map(static fn ($url): string => $url->loc, $sitemap->urls($types->get('profile')));

		$this->assertSame(['http://localhost/profiles/guest', 'http://localhost/profiles/justintadlock', 'http://localhost/profiles/sam'], $urls);
	}
}
