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
use Blush\Content\Http\ProfileController;
use Blush\Content\Http\RelatedController;
use Blush\Content\Http\RelatedListController;
use Blush\Content\ProfileList;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentSiteUrls;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Application;
use Blush\Feed\FeedSiteUrls;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Routing\RouteTable;
use Blush\Routing\SiteUrl;
use Blush\Routing\SiteUrls;
use Blush\Routing\UrlSource;
use Blush\Sitemap\SitemapBuilder;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Theme\ThemeResolver;
use Blush\View\Hierarchy;
use Blush\View\Template;
use Blush\View\ViewContext;
use Blush\View\ViewFactory;

/**
 * People archives as credit relations' archives (D-602): what were people
 * fields are relations of kind `credit`, whose archives are relation
 * archives with an intro page and written pages per person.
 */
#[CoversClass(RelatedController::class)]
#[CoversClass(RelatedListController::class)]
#[CoversClass(ProfileController::class)]
#[CoversClass(RelationArchives::class)]
#[CoversClass(ProfileList::class)]
#[CoversClass(Template::class)]
final class PeopleArchivesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Writes the standard content with a blog that credits authors, a
	 * feed, and one post per page, then boots it.
	 *
	 * @param array<string, mixed>                $post      Options for the post type.
	 * @param array<string, mixed>                $profile   Options for the profiles type.
	 * @param ?array<string, mixed>               $authors   Options for the `authors` credit, or `null` for none.
	 * @param array<string, array<string, mixed>> $relations More relations, by name.
	 */
	private function boot(array $post = [], array $profile = [], ?array $authors = [], array $relations = []): Application
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
					'path'   => 'topics',
					'order'  => 'position'
				],
				...($profile === [] ? [] : ['profile' => ['kind' => 'profiles', 'path' => 'profiles', ...$profile]])
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true],
				...($authors === null ? [] : ['authors' => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'aliases' => ['author'], 'label' => 'Authors', ...$authors]]),
				...$relations
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
		$this->assertStringContainsString('<h1 class="archive-header__title">Authors</h1>', $body, 'The relation\'s label without a list page.');

		preg_match_all('#<h2 class="people__name"><a href="([^"]*)">([^<]*)</a>#', $body, $people);

		$this->assertSame(['A Guest', 'Justin Tadlock'], $people[2], 'By name; Sam has no posts, so no archive.');
		$this->assertSame(['/archives/authors/guest', '/archives/authors/justintadlock'], $people[1]);
		$this->assertStringContainsString('<p>Writes things.</p>', $body, 'Each with their bio.');
	}

	public function testAnArchivesListPageIntroducesTheList(): void
	{
		$this->entry('_posts/_authors.md', 'title: Our Writers', 'The people behind the blog.');

		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/authors')->getBody();

		$this->assertStringContainsString('<h1 class="archive-header__title">Our Writers</h1>', $body);
		$this->assertStringContainsString('The people behind the blog.', $body);
		$this->assertSame(404, $this->get($app, '/archives/_authors')->getStatusCode(), 'It has no address of its own.');
		$this->assertStringNotContainsString('Our Writers', (string) $this->get($app, '/feed/json')->getBody(), 'Nor is it in the feed.');
	}

	public function testServesAPersonsArchiveUnderACredit(): void
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
		$this->assertStringContainsString('is-related type-post', $body);

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

	public function testBylinesLinkToTheCreditsArchiveElseTheProfile(): void
	{
		$app  = $this->boot();
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<span class="entry-meta__people">By <a class="entry-meta__person" href="/archives/authors/justintadlock">Justin Tadlock</a>, <a class="entry-meta__person" href="/archives/authors/guest">A Guest</a></span>', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/justintadlock">', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/guest">', $body, 'One tag per person.');

		$app  = $this->boot(authors: ['inverse' => ['archive' => false]]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<a class="entry-meta__person" href="/profiles/justintadlock">Justin Tadlock</a>', $body, 'Without archives, the profile\'s page.');
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/profiles/justintadlock">', $body);
		$this->assertSame(404, $this->get($app, '/archives/authors')->getStatusCode());

		$app  = $this->boot(profile: ['routing' => false], authors: ['inverse' => ['archive' => false]]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<span class="entry-meta__person">Justin Tadlock</span>', $body, 'Nowhere to link.');
	}

	public function testTemplatesReachAnAuthorAProfileAndTheirAvatar(): void
	{
		$app       = $this->boot();
		$container = $app->container();
		$views     = $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
		$template  = new Template($views, new ViewContext());
		$spring    = $container->make(ContentRepository::class)->named('post', 'spring');

		$this->assertNotNull($spring);
		$this->assertSame('Justin Tadlock', $template->author($spring)?->title, 'The byline\'s first person.');
		$this->assertSame(['Justin Tadlock', 'A Guest'], array_map(static fn ($person): string => $person->title, $template->byline($spring)));
		$this->assertSame('/archives/authors/guest', $template->archiveUrl('post', null, 'guest'), 'The byline\'s archive.');
		$this->assertSame('/archives/authors', $template->archiveUrl('post', 'authors'));
		$this->assertSame('', $template->archiveUrl('post', 'nope'));
		$this->assertNull($template->profile('nobody'));

		$sam = $template->profile('sam');

		$this->assertNotNull($sam);
		$this->assertSame('Sam', $sam->title);
		$this->assertStringContainsString('>S</text></svg>', $template->avatar($sam, 32));
		$this->assertStringContainsString('width="32" height="32"', $template->avatar($sam, 32));
	}

	public function testATypeCreditsPeopleInItsOwnWords(): void
	{
		$this->entry('_posts/2009-02-02.shots.md', "title: Shots\npublished: 2009-02-02\nauthor: guest\nphotographer: justintadlock");

		$app  = $this->boot(['byline' => 'authors'], authors: ['inverse' => ['archive' => 'writers']], relations: ['photographers' => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'multiple' => false, 'aliases' => ['photographer'], 'label' => 'Photographers']]);
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

		$app = $this->boot(authors: null);

		$this->assertStringNotContainsString('entry-meta__people', (string) $this->get($app, '/archives/spring')->getBody(), 'Posts don\'t credit people at all.');
		$this->assertNull($app->container()->make(RouteTable::class)->named('post.authors.single'));
	}

	public function testPagesHaveTheirOwnTemplates(): void
	{
		$app     = $this->boot();
		$post     = $app->container()->make(ContentTypes::class)->get('post');
		$relation = $app->container()->make(ContentTypes::class)->relations()['authors'];
		$profile  = $app->container()->make(ContentRepository::class)->term('profile', 'justintadlock');

		$this->assertSame(['related-list-post-authors', 'related-list-authors', 'related-list', 'collection'], Hierarchy::forPage(new ContentPage(PageKind::RelatedList, 'Authors', type: $post, relation: $relation))->names);
		$this->assertSame(
			['related-post-authors', 'related-authors', 'related', 'profile', 'collection'],
			Hierarchy::forPage(new ContentPage(PageKind::Related, 'Justin Tadlock', entry: $profile, type: $post, relation: $relation, target: $profile))->names,
			'A person\'s archive falls back to the profile template (D-602).'
		);
		$this->assertSame(
			['profile-justintadlock', 'profile', 'collection'],
			Hierarchy::forPage(new ContentPage(PageKind::Profile, 'Justin Tadlock', entry: $profile, target: $profile))->names
		);
	}

	public function testTemplatesFallBackByTheTypesKind(): void
	{
		$app   = $this->boot();
		$types = $app->container()->make(ContentTypes::class);
		$post  = $types->get('post');
		$page  = $types->get('page');
		$entry = $app->container()->make(ContentRepository::class)->named('page', 'about');

		$this->assertNotNull($entry);
		$this->assertSame(['single-page-about', 'single-page', 'single-tree', 'single'], Hierarchy::forPage(new ContentPage(PageKind::Single, 'About', entry: $entry, type: $page))->names, 'D-561.');
		$this->assertSame(['collection-post', 'collection-collection', 'collection'], Hierarchy::forPage(new ContentPage(PageKind::Collection, 'Posts', type: $post))->names);
		$this->assertSame(['collection-profile', 'collection-profiles', 'collection'], Hierarchy::forPage(new ContentPage(PageKind::Collection, 'Profiles', type: $types->get('profile')))->names);
	}

	public function testListsAndMapsThePages(): void
	{
		$app   = $this->boot();
		$paths = [];

		foreach ([ContentSiteUrls::class, FeedSiteUrls::class] as $source) {
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

		$all = array_map(static fn (SiteUrl $url): string => $url->path, [...$app->container()->make(SiteUrls::class)->all()]);

		foreach (['/profiles/justintadlock', '/archives/authors/justintadlock/feed/json', '/robots.txt'] as $path) {
			$this->assertContains($path, $all, 'Every tagged source (D-476).');
		}

		$sitemap = $app->container()->make(SitemapBuilder::class);
		$types   = $app->container()->make(ContentTypes::class);
		$urls    = array_map(static fn ($url): string => $url->loc, $sitemap->urls($types->get('profile')));

		$this->assertSame(['http://localhost/profiles/guest', 'http://localhost/profiles/justintadlock', 'http://localhost/profiles/sam'], $urls);
	}
}
