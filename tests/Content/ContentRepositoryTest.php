<?php

/**
 * Content repository tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use DateTimeImmutable;
use ReflectionClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Body;
use Blush\Content\Entry\BodySource;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Index\ArraySelector;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\RecordMatcher;
use Blush\Content\IndexedRepository;
use Blush\Content\Parser\BodyFormat;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\Order;
use Blush\Content\Query\Paginator;
use Blush\Content\Query\Query;
use Blush\Content\Query\Selection;
use Blush\Content\Status;
use Blush\Content\Visibility;

#[CoversClass(IndexedRepository::class)]
#[CoversClass(ArraySelector::class)]
#[CoversClass(RecordMatcher::class)]
#[CoversClass(EntryHydrator::class)]
#[CoversClass(Entry::class)]
#[CoversClass(Body::class)]
#[CoversClass(EntryCollection::class)]
#[CoversClass(Paginator::class)]
#[CoversClass(Selection::class)]
final class ContentRepositoryTest extends TestCase
{
	use BuildsContentSite;

	private ContentRepository $content;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->content = $this->repository();
	}

	/**
	 * Returns the slugs of a collection's entries.
	 *
	 * @param iterable<Entry> $entries
	 * @return list<string>
	 */
	private static function slugs(iterable $entries): array
	{
		$slugs = [];

		foreach ($entries as $entry) {
			$slugs[] = $entry->slug;
		}

		return $slugs;
	}

	public function testBuildsTheIndexOnFirstUse(): void
	{
		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($this->content->query()->type('post')->get()));
		$this->assertFileExists($this->temporaryDirectory() . '/storage/index/content.php');
	}

	public function testDefaultQueriesFindPublishedPublicEntriesInFileOrder(): void
	{
		$posts = $this->content->query()->type('post')->get();

		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($posts));
		$this->assertCount(3, $posts);
		$this->assertSame(3, $posts->total());
		$this->assertSame('welcome', $posts->first()?->slug);
		$this->assertSame('hello', $posts->last()?->slug);
		$this->assertTrue($posts->has('spring'));
		$this->assertFalse($posts->has('rainy'));
	}

	public function testQueriesFilterByStatusVisibilityAndLanding(): void
	{
		$post = $this->content->query()->type('post');

		$this->assertSame(['future'], self::slugs($post->status(Status::Scheduled)->get()));
		$this->assertSame(['unfinished'], self::slugs($post->status(Status::Draft)->get()));
		$this->assertSame(['rainy'], self::slugs($post->visibility(Visibility::Unlisted)->get()));
		$this->assertSame(['welcome', 'spring', 'hello', 'index'], self::slugs($post->withLanding()->get()));
		$this->assertSame(['welcome', 'spring', 'rainy', 'unfinished', 'future', 'hello', 'index'], self::slugs($post->any()->get()));

		$this->clock->set('2027-01-01');

		$this->assertSame(['welcome', 'spring', 'future', 'hello'], self::slugs($this->content->query()->type('post')->get()));
	}

	public function testNamingEntriesFindsHiddenOnesAndLandingPages(): void
	{
		$this->assertSame(['_private'], self::slugs($this->content->query()->names('_private')->get()));
		$this->assertSame(['rainy'], self::slugs($this->content->query()->names('rainy')->get()));
		$this->assertSame(['index'], self::slugs($this->content->query()->type('post')->names('index')->get()));
		$this->assertSame(['welcome', 'hello'], self::slugs($this->content->query()->type('post')->exceptNames('spring')->get()));
	}

	public function testQueriesFilterByFolderTermsAuthorsFieldsAndDates(): void
	{
		$query = $this->content->query();

		$this->assertSame(['biography'], self::slugs($query->in('about')->get()));
		$this->assertSame(['about', 'notes'], self::slugs($query->in('')->get()));
		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($query->in('_posts')->get()));
		$this->assertSame(['spring'], self::slugs($query->whereTerm('category', 'art')->get()));
		$this->assertSame(['spring'], self::slugs($query->whereTerm('category', 'Book Reviews')->get()));
		$this->assertSame(['welcome', 'spring'], self::slugs($query->whereTerm('category', 'art', 'old-posts')->get()));
		$this->assertSame(['welcome', 'spring'], self::slugs($query->whereAuthor('justintadlock')->get()));
		$this->assertSame(['spring'], self::slugs($query->whereAuthor('justintadlock', 'guest')->get()));
		$this->assertSame(['spring'], self::slugs($query->where('tag')->get()));
		$this->assertSame(['spring'], self::slugs($query->where('tag', 'Flowers')->get()));
		$this->assertSame([], self::slugs($query->where('tag', 'weeds')->get()));
		$this->assertSame(['spring'], self::slugs($query->date(year: 2008, month: 4)->get()));
		$this->assertSame(['welcome'], self::slugs($query->date(2003, 4, 15, 17)->get()));
		$this->assertSame([], self::slugs($query->date(year: 1999)->get()));
		$this->assertSame(['hello'], self::slugs($query->type('post')->locale('en_US')->date(2010)->get()));
		$this->assertSame([], self::slugs($query->locale('fr_FR')->get()));
	}

	public function testSearchesTitlesAndPaths(): void
	{
		$posts = $this->content->query()->type('post')->any();

		$this->assertSame(['welcome'], self::slugs($posts->search('WELCOME')->get()), 'Titles, in any case.');
		$this->assertSame(['hello'], self::slugs($posts->search('hello/index')->get()), 'Paths.');
		$this->assertSame(['spring', 'rainy'], self::slugs($posts->search('2008-')->get()));
		$this->assertCount(7, $posts->search('  ')->get(), 'Blank text matches everything.');
		$this->assertSame([], self::slugs($posts->search('nothing')->get()));
	}

	public function testEitherMatchesAnyAlternative(): void
	{
		$posts = $this->content->query()->type('post')->any();
		$draft = static fn (Query $query): Query => $query->status(Status::Draft);
		$art   = static fn (Query $query): Query => $query->whereTerm('category', 'art');

		$this->assertSame(['spring', 'rainy', 'unfinished'], self::slugs($posts->either($draft, $art)->get()));
		$this->assertSame(['spring'], self::slugs($posts->either($draft, $art)->search('spring')->get()), 'The query\'s own conditions still hold.');
		$this->assertSame(['spring'], self::slugs($posts->either($draft, $art)->either(static fn (Query $query): Query => $query->whereAuthor('guest'))->get()), 'Each group must match.');
		$this->assertSame([], self::slugs($posts->either()->get()), 'No alternatives match nothing.');
		$this->assertSame([], self::slugs($this->content->query()->type('post')->either(static fn (Query $query): Query => $query->status(Status::Scheduled))->get()), 'Alternatives narrow the query; they never widen it (it finds only published entries).');
		$this->assertSame(1, $posts->either($draft)->count());
	}

	public function testQueriesSortLimitAndOffset(): void
	{
		$posts = $this->content->query()->type('post');

		$this->assertSame(['hello', 'spring', 'welcome'], self::slugs($posts->orderBy('filename', Order::Desc)->get()));
		$this->assertSame(['hello', 'spring', 'welcome'], self::slugs($posts->orderBy('published', Order::Desc)->get()));
		$this->assertSame(['hello', 'spring', 'welcome'], self::slugs($posts->orderBy('title')->get()));
		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($posts->orderBy('title', Order::Desc)->get()));
		$this->assertSame(['hello', 'welcome', 'spring'], self::slugs($posts->orderBy('author')->get()));
		$this->assertSame(['welcome', 'hello', 'spring'], self::slugs($posts->orderBy('tag')->get()));

		$page = $posts->limit(1)->offset(1)->get();

		$this->assertSame(['spring'], self::slugs($page));
		$this->assertSame(3, $page->total());
		$this->assertSame(3, $posts->limit(1)->count());
		$this->assertSame('welcome', $posts->first()?->slug);
	}

	public function testRunsOneXQueryArguments(): void
	{
		$query = Query::fromArray(['type' => 'post', 'order' => 'desc', 'orderby' => 'date', 'number' => 2], $this->content);

		$this->assertSame(['hello', 'spring'], self::slugs($query->get()));
		$this->assertSame(['welcome'], self::slugs($this->content->get(Query::fromArray(['type' => 'post', 'year' => 2003, 'noindex' => true]))));
		$this->assertSame(['index'], self::slugs($this->content->get(Query::fromArray(['type' => 'post', 'slug' => 'index']))));
		$this->assertSame(['spring'], self::slugs($this->content->get(Query::fromArray(['meta_key' => 'category', 'meta_value' => 'book-reviews']))));
		$this->assertSame(['spring'], self::slugs($this->content->get(Query::fromArray(['author' => ['justintadlock', 'guest']]))));
	}

	public function testPaginates(): void
	{
		$first = $this->content->query()->type('post')->orderBy('published', Order::Desc)->paginate(perPage: 2);

		$this->assertSame(['hello', 'spring'], self::slugs($first));
		$this->assertCount(2, $first);
		$this->assertSame(3, $first->total());
		$this->assertSame(2, $first->pages());
		$this->assertSame(2, $first->next());
		$this->assertNull($first->previous());
		$this->assertFalse($first->isOutOfRange());

		$second = $this->content->query()->type('post')->orderBy('published', Order::Desc)->paginate(2, 2);

		$this->assertSame(['welcome'], self::slugs($second->all()));
		$this->assertNull($second->next());
		$this->assertSame(1, $second->previous());

		$beyond = $this->content->query()->type('post')->paginate(2, 5);

		$this->assertTrue($beyond->isOutOfRange());
		$this->assertSame([], $beyond->all());
		$this->assertSame(2, $beyond->previous());
	}

	public function testFindsEntriesByIdAndKey(): void
	{
		$this->assertSame('Biography', $this->content->findPath('about/biography.md')?->title);
		$this->assertNull($this->content->findPath('missing.md'));

		$id = self::idFor('about/biography.md');

		$this->assertSame($id, $this->content->findPath('about/biography.md')?->id);
		$this->assertSame('about/biography.md', $this->content->find($id)?->path, 'By its id (D-477).');
		$this->assertSame('about/biography.md', $this->content->find(strtoupper($id))?->path, 'In either case.');
		$this->assertNull($this->content->find('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74'));
		$this->assertNull($this->content->find('about/biography.md'), 'A path isn\'t an id.');
		$this->assertSame('about/index.md', $this->content->named('page', 'about')?->path);
		$this->assertSame('Home', $this->content->named('page', '')?->title);
		$this->assertSame('Blog', $this->content->named('post', '')?->title);
		$this->assertSame(Status::Draft, $this->content->named('post', 'unfinished')?->status);
		$this->assertNull($this->content->named('post', 'unfinished', 'fr_FR'));
	}

	public function testHydratesEntries(): void
	{
		$entry = $this->content->named('post', 'spring');

		$this->assertInstanceOf(Entry::class, $entry);
		$this->assertSame('_posts/2008-04-05.spring.md', $entry->path);
		$this->assertSame('post', $entry->type->name);
		$this->assertSame('spring', (string) $entry);
		$this->assertEquals(new DateTimeImmutable('2008-04-05 09:00:00 America/Chicago'), $entry->published);
		$this->assertSame('America/Chicago', $entry->updated->getTimezone()->getName());
		$this->assertSame(['justintadlock', 'guest'], $entry->field('authors'));
		$this->assertSame('flowers', $entry->field('tag'));
		$this->assertSame('fallback', $entry->field('missing', 'fallback'));
		$this->assertTrue($entry->has('tag'));
		$this->assertSame(['art', 'book-reviews'], $entry->terms('category'));
		$this->assertTrue($entry->hasTerm('category', 'art'));
		$this->assertFalse($entry->hasTerm('category', 'life'));
		$this->assertTrue($entry->isPublished());
		$this->assertTrue($entry->isRoutable());
		$this->assertTrue($entry->isListed());
		$this->assertFalse($entry->isVirtual());
		$this->assertSame('_posts/2008-04-05.spring.md', $entry->source?->path);
		$this->assertFalse($this->content->named('post', '')?->isListed());
		$this->assertFalse($this->content->findPath('_private.md')?->isRoutable());
	}

	public function testBodiesAreReadOnlyWhenUsed(): void
	{
		$entry = $this->content->named('post', 'welcome');
		$this->assertNotNull($entry);

		$body = new ReflectionClass(Entry::class)->getProperty('body')->getValue($entry);
		$this->assertInstanceOf(Body::class, $body);

		$source = new ReflectionClass(Body::class)->getProperty('source')->getValue($body);
		$this->assertInstanceOf(BodySource::class, $source);
		$this->assertTrue(new ReflectionClass(BodySource::class)->isUninitializedLazyObject($source));

		$this->assertSame("<p>Hello and welcome to my site.</p>\n", $entry->body());
		$this->assertSame('Hello and welcome to my site.', $entry->raw());
		$this->assertSame(BodyFormat::Markdown, $source->format);
		$this->assertFalse(new ReflectionClass(BodySource::class)->isUninitializedLazyObject($source));
		$this->assertSame("<p>Some <em>notes</em>.</p>\n", $this->content->named('page', 'notes')?->body());
	}

	public function testExcerptsAndPresentationFields(): void
	{
		$this->entry('_posts/long.md', "title: Long\nexcerpt: A *short* summary.\nview: [custom.php, other]", 'Unused.');
		$this->entry('_posts/figure.md', 'title: Figure', "<figure><img src=\"a.jpg\"><figcaption>Caption words</figcaption></figure>\n\nOne two three four five.");
		$this->content = $this->repository($this->site('development'));

		$long = $this->content->named('post', 'long');
		$this->assertNotNull($long);

		$this->assertSame("<p>A <em>short</em> summary.</p>\n", $long->excerpt());
		$this->assertSame('A *short* summary.', $long->summary());
		$this->assertSame(['custom', 'other'], $long->templates());
		$this->assertSame('', $long->subtitle());
		$figure = $this->content->named('post', 'figure');
		$this->assertNotNull($figure);

		$this->assertSame('<p>One two three…</p>', $figure->excerpt(3));
		$this->assertSame('<p>One two three <a href="/more">More&nbsp;&rarr;</a></p>', $figure->excerpt(3, ' <a href="/more">More&nbsp;&rarr;</a>'));
		$this->assertSame('<p>One two three four five.</p>', $figure->excerpt(10, ' <a href="/more">More</a>'));
		$this->assertSame(5, $figure->wordCount());
		$this->assertSame(1, $figure->readingTime());
		$this->assertSame(3, $figure->readingTime(2));
		$this->assertSame('<p>One two three four five.</p>', $figure->excerpt());
		$this->assertSame('', $this->content->named('page', '')?->excerpt());
	}

	public function testTermsAreRealOrVirtual(): void
	{
		$art = $this->content->term('category', 'art');

		$this->assertSame('topics/art.md', $art?->path);

		$reviews = $this->content->term('category', 'book-reviews');
		$this->assertNotNull($reviews);

		$this->assertSame('virtual:category/book-reviews', $reviews->path);
		$this->assertSame('Book Reviews', $reviews->title);
		$this->assertTrue($reviews->isVirtual());
		$this->assertSame('', $reviews->body());
		$this->assertSame('Justin Tadlock', $this->content->term('profile', 'justintadlock')?->title, 'Profiles are terms too (D-351).');
		$this->assertNull($this->content->term('category', 'unused'));
		$this->assertNull($this->content->term('post', 'welcome'));
		$this->assertSame(['old-posts' => 1, 'art' => 1, 'book-reviews' => 1], $this->content->termCounts('category'));
		$this->assertSame([], $this->content->termCounts('missing'));
	}

	public function testPagesNestByFolderAndHierarchicalTermsByParent(): void
	{
		$this->contentConfig(['types' => ['topic' => ['kind' => 'taxonomy', 'folder' => 'topics', 'hierarchical' => true]]]);
		$this->entry('topics/web.md', 'title: Web');
		$this->entry('topics/css.md', "title: CSS\nparent: web");
		$this->entry('topics/grid.md', "title: Grid\nparent: CSS");
		$this->entry('topics/html.md', "title: HTML\nparent: web");
		$this->entry('topics/orphan.md', "title: Orphan\nparent: missing");

		$content = $this->repository();
		$grid    = $content->named('topic', 'grid');
		$web     = $content->named('topic', 'web');
		$about   = $content->named('page', 'about');

		$this->assertNotNull($grid);
		$this->assertNotNull($web);
		$this->assertNotNull($about);
		$this->assertSame('css', $content->parent($grid)?->key, 'Parents are slugged like other references.');
		$this->assertNull($content->parent($web));
		$this->assertSame(['css', 'html'], array_map(static fn (Entry $entry): string => $entry->key, $content->children($web)));
		$this->assertNull($content->parent($content->named('topic', 'orphan') ?? $web));
		$this->assertSame('about', $content->parent($content->named('page', 'about/biography') ?? $about)?->key);
		$this->assertSame(['about/biography'], array_map(static fn (Entry $entry): string => $entry->key, $content->children($about)));
		$this->assertNull($content->parent($about));
		$this->assertSame([], $content->children($content->named('page', '') ?? $about), 'The homepage isn\'t every page\'s parent.');
	}

	public function testDevelopmentRefreshesTheIndexOnFirstUse(): void
	{
		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($this->content->query()->type('post')->get()));

		$this->entry('_posts/later.md', 'title: Later');

		$this->assertSame(['welcome', 'spring', 'hello'], self::slugs($this->repository()->query()->type('post')->get()));
		$this->assertSame(['welcome', 'spring', 'hello', 'later'], self::slugs($this->repository($this->site('development'))->query()->type('post')->get()));
	}

	public function testAutoIndexCanBeTurnedOff(): void
	{
		$this->contentConfig(['autoIndex' => false]);
		$this->repository($this->site('development'))->query()->get();

		$this->entry('later.md', 'title: Later');

		$app = $this->site('development');

		$this->assertNull($this->repository($app)->named('page', 'later'));
		$this->assertTrue($app->container()->make(ContentIndex::class)->exists());
	}

	public function testAStaleIndexIsRebuilt(): void
	{
		$this->repository()->query()->get();
		$this->contentConfig([]);

		$content = $this->repository($this->site());

		$this->assertSame('page', $content->findPath('_posts/2003-04-15.welcome.md')?->type->name);
	}
}
