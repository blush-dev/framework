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
use Blush\Auth\Accounts;
use Blush\Auth\Avatars;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\ProfileController;
use Blush\Content\Http\RelatedController;
use Blush\Content\Http\RelatedListController;
use Blush\Content\ProfileList;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\RelationArchives;
use Blush\Content\Relation\RelatedEntries;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Routing\ContentSiteUrls;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\Application;
use Blush\Feed\FeedSiteUrls;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Publish\Publisher;
use Blush\Routing\RouteTable;
use Blush\Routing\SiteUrl;
use Blush\Routing\SiteUrls;
use Blush\Routing\UrlSource;
use Blush\Sitemap\SitemapBuilder;
use Blush\Support\RequestMemo;
use Blush\Tests\Content\BuildsContentSite;
use Blush\View\Hierarchy;

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
#[CoversClass(RelatedEntries::class)]
#[CoversClass(RequestMemo::class)]
#[CoversClass(Avatars::class)]
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

		// Only what this boot defines: types and relations are files.
		foreach ([...glob($this->temporaryDirectory() . '/user/data/types/*.json') ?: [], ...glob($this->temporaryDirectory() . '/user/data/relations/*.json') ?: []] as $file) {
			unlink($file);
		}

		$this->contentConfig([
			'types' => [
				'post' => [
					'collection'    => ['order' => 'desc', 'orderby' => 'published', 'number' => 1],
					'date_archives' => true,
					'feed'          => true,
					'routing'       => ['prefix' => 'archives'],
					...$post
				],
				'category' => [
					'urls' => ['prefix' => 'topics'],
					'order'  => 'position'
				],
				...($profile === [] ? [] : ['profile' => ['kind' => 'profiles', ...$profile]])
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true],
				...($authors === null ? [] : ['authors' => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'aliases' => ['author'], 'label' => 'Authors', ...$authors]]),
				...$relations
			],
			'home' => 'post'
		]);
		$this->entry('_profile/sam.md', 'title: Sam', 'Credited by nothing yet.');

		// A test may boot again with other config; production notices
		// changes when they're published.
		$app = $this->site();
		$app->container()->make(Publisher::class)->publish();

		return $app;
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
		$this->entry('_post/_authors.md', 'title: Our Writers', 'The people behind the blog.');

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
		$this->entry('_post/_authors/justintadlock.md', 'title: Justin, Blogger', 'Writes the blog.');
		$this->entry('_post/_authors/guest.md', "title: Unfinished\nstatus: draft", 'Not yet.');

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
		$this->assertSame('/profiles/justintadlock', $app->container()->make(ContentUrls::class)->entry($app->container()->make(Entries::class)->term('profile', 'justintadlock') ?? $this->fail('No profile.')));
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

		$this->assertMatchesRegularExpression('#<span class="entry-meta__byline">\s*By\s*<a href="/profiles/justintadlock" class="entry-meta__profile">Justin Tadlock</a>\s*<a href="/profiles/guest" class="entry-meta__profile">A Guest</a>\s*</span>#', $body, 'The default theme links the byline\'s profiles (D-804).');
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/justintadlock">', $body);
		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/archives/authors/guest">', $body, 'One tag per person.');

		$app  = $this->boot(authors: ['inverse' => ['archive' => false]]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertStringContainsString('<meta property="article:author" content="http://localhost/profiles/justintadlock">', $body);
		$this->assertSame(404, $this->get($app, '/archives/authors')->getStatusCode());

		$app  = $this->boot(profile: ['routing' => false], authors: ['inverse' => ['archive' => false]]);
		$body = (string) $this->get($app, '/archives/spring')->getBody();

		$this->assertMatchesRegularExpression('#By\s*Justin Tadlock\s*A Guest\s*</span>#', $body, 'Nowhere to link: the names alone.');
	}

	public function testTemplatesReachAnAuthorAProfileAndTheirAvatar(): void
	{
		$app       = $this->boot();
		$container = $app->container();
		$spring    = $container->make(Entries::class)->named('post', 'spring');

		$this->assertNotNull($spring);
		$this->assertSame('/archives/spring', $spring->url(), 'Its own URL (D-802).');
		$authors = $spring->related('authors');

		$this->assertSame(['authors'], array_keys($spring->relations(RelationKind::Credit)), 'Its credit relations, the byline first (D-803).');
		$this->assertTrue($authors->byline, 'The type\'s byline (D-803).');
		$this->assertSame('authors', $authors->relation?->name);
		$this->assertSame('Justin Tadlock', $authors->first()?->title, 'The byline\'s first profile.');
		$this->assertSame(['Justin Tadlock', 'A Guest'], array_map(static fn (Entry $profile): string => $profile->title, $authors->all()));
		$this->assertCount(2, $authors);
		$this->assertSame(['Justin Tadlock', 'A Guest'], array_map(static fn (Entry $profile): string => $profile->title, $spring->creators()->all()), 'The byline, shorter (D-804).');
		$this->assertTrue($spring->creators()->byline);
		$this->assertSame(['Art', 'Book Reviews'], array_map(static fn (Entry $linked): string => $linked->title, $spring->classifications()->all()), 'What it\'s filed under, every classify relation (D-808).');
		$this->assertSame($spring->creators()->all(), $spring->credits()->all(), 'Every credit relation, the byline first.');
		$this->assertTrue($spring->references()->isEmpty());
		$this->assertSame(['en' => $spring], $spring->translations());
		$this->assertSame($spring->classifications(), $spring->classifications(), 'Kept for the request (D-807).');

		$about = $container->make(Entries::class)->named('page', 'about');

		$this->assertNotNull($about);
		$this->assertTrue($about->creators()->isEmpty(), 'A type without a byline has none.');
		$this->assertFalse($spring->related('category')->byline);
		$this->assertTrue($spring->related('nothing')->isEmpty(), 'A relation the type hasn\'t leads nowhere.');
		$this->assertNull($spring->related('nothing')->relation);
		$this->assertSame('<a href="/archives/spring" class="entry-link" data-entry-id="7" aria-current="page" hidden>spring</a>', (string) $spring->link(class: 'entry-link', dataEntryId: 7, ariaCurrent: 'page', hidden: true, title: null, download: false), 'Attributes by name, dashed (D-803).');
		$guest = $container->make(Entries::class)->named('profile', 'guest');

		$this->assertNotNull($guest);
		$this->assertSame('/archives/authors/guest', $guest->archiveUrl('post'), 'The byline\'s archive (D-815).');
		$this->assertSame('/archives/authors/guest', $guest->archiveUrl($spring->type, 'authors'), 'By the relation\'s name.');
		$this->assertSame($guest->archiveUrl('post'), $guest->archiveUrl('post'));
		$this->assertSame('', $guest->archiveUrl('post', 'nope'));
		$this->assertSame('', $guest->archiveUrl('nothing'));

		$sam = $container->make(Entries::class)->named('profile', 'sam');

		$this->assertNotNull($sam);
		$this->assertSame('Sam', $sam->title);
		$this->assertStringContainsString('>S</text></svg>', (string) $sam->avatar(32), 'Its initials, without a picture (D-809).');
		$this->assertStringContainsString('width="32" height="32"', (string) $sam->avatar(32));
		$this->assertSame('', (string) $spring->avatar(), 'Only a profile has one.');
	}

	public function testAProfilesAvatarIsItsPicture(): void
	{
		$this->entry('_profile/ann.md', "title: Ann\navatar: https://cdn.example.com/ann.jpg", 'Has a portrait.');

		$container = $this->boot()->container();
		$content   = $container->make(Entries::class);
		$ann       = $content->named('profile', 'ann');

		$this->assertNotNull($ann);
		$this->assertStringStartsWith('<img class="avatar" src="https://cdn.example.com/ann.jpg" width="40" height="40" alt=""', (string) $ann->avatar(40), 'Its `avatar` field (D-809).');
		$this->assertStringContainsString('alt="Ann"', (string) $ann->avatar(label: 'Ann'));

		$this->assertNotNull($ann->id);

		$avatars = $container->make(Avatars::class);
		$linked  = $container->make(Accounts::class)->create('ann', 'a long enough password', ['author'], $ann->id, null, 'ann@example.com');
		$agent   = $container->make(Accounts::class)->createTokenOnly('helper', ['author'], 'Helper Bot');

		$this->assertSame('https://cdn.example.com/ann.jpg', $avatars->account($linked, 40)->image, 'An account shows its profile\'s portrait.');
		$this->assertSame('Ann', $avatars->account($linked)->name, 'Named as the admin names it.');
		$this->assertSame('', $avatars->account($agent)->image, 'An account without a profile has initials.');
		$this->assertSame('HB', $avatars->account($agent)->initials());
		$this->assertSame($avatars->account($linked, 40), $avatars->account($linked, 40), 'Kept for the request (D-807).');
		$this->assertSame($avatars->profile($ann, 40), $avatars->profile($ann, 40));
	}

	public function testWhatAnEntryLeadsToIsKeptUntilAWrite(): void
	{
		$container = $this->boot()->container();
		$content   = $container->make(Entries::class);
		$spring    = $content->named('post', 'spring');
		$guest     = $content->named('profile', 'guest');

		$this->assertNotNull($spring);
		$this->assertNotNull($guest);
		$this->assertSame($guest, $content->find((string) $guest->id), 'One object per entry, however it\'s reached (D-806).');
		$this->assertSame($guest, $content->term('profile', 'guest'));
		$this->assertSame($guest, $spring->creators()->all()[1] ?? null);
		$this->assertSame($spring, $content->query()->type('post')->names('spring')->get()->first());
		$this->assertSame($content->query()->type('post')->get(), $content->query()->type('post')->get(), 'A query asked twice is one lookup.');
		$this->assertSame($spring->creators(), $spring->creators(), 'Asked again, it\'s kept (D-805).');
		$this->assertSame($spring->related('authors'), $spring->creators());
		$this->assertSame($spring->creators()->published(), $spring->creators()->published());
		$this->assertSame($spring->url(), $spring->url());
		$this->assertSame($spring->excerpt(), $spring->excerpt());

		$content->change($guest, new EntryChanges(set: ['title' => 'A Visitor']));

		$this->assertSame(['Justin Tadlock', 'A Visitor'], array_map(static fn (Entry $profile): string => $profile->title, $spring->creators()->all()), 'A write drops what was kept.');
	}

	public function testATypeCreditsPeopleInItsOwnWords(): void
	{
		$this->entry('_post/2009-02-02.shots.md', "title: Shots\ndate: 2009-02-02\nauthor: guest\nphotographer: justintadlock");

		$app  = $this->boot(['byline' => 'authors'], authors: ['inverse' => ['archive' => 'writers']], relations: ['photographers' => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'multiple' => false, 'aliases' => ['photographer'], 'label' => 'Photographers']]);
		$body = (string) $this->get($app, '/archives/shots')->getBody();

		$this->assertMatchesRegularExpression('#By\s*<a href="/profiles/guest" class="entry-meta__profile">A Guest</a>\s*</span>#', $body, 'The byline alone, in the default theme.');
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

		$this->assertStringNotContainsString('entry-meta__byline', (string) $this->get($app, '/archives/spring')->getBody(), 'Posts don\'t credit people at all.');
		$this->assertNull($app->container()->make(RouteTable::class)->named('post.authors.single'));
	}

	public function testPagesHaveTheirOwnTemplates(): void
	{
		$app     = $this->boot();
		$post     = $app->container()->make(ContentTypes::class)->get('post');
		$relation = $app->container()->make(ContentTypes::class)->relations()['authors'];
		$profile  = $app->container()->make(Entries::class)->term('profile', 'justintadlock');

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
		$entry = $app->container()->make(Entries::class)->named('page', 'about');

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
