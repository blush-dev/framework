<?php

/**
 * Entry store conformance.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Conformance;

use Psr\Clock\ClockInterface;
use PHPUnit\Framework\TestCase;
use Blush\Cache\ContentVersion;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\EntryHydrator;
use Blush\Content\Query\Query;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\QueryCompiler;
use Blush\Content\Record\RecordLocations;
use Blush\Content\Relation\EntryTargets;
use Blush\Content\Relation\Relations;
use Blush\Content\Status;
use Blush\Content\StoredEntries;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\RecordContentWriter;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Field\FieldContext;
use Blush\Markdown\MarkdownParser;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Refs;
use Blush\Storage\StorageArea;
use Blush\Tests\Content\BuildsContentSite;

/**
 * What every store that keeps content must answer, and answer alike
 * (the data layer's steps 3e and 3f): the standard content set
 * (`BuildsContentSite`) as `entries` and `refs` records, read through
 * `Entries` over the store under test, and through the store itself. A
 * driver's test extends this and hands over the stores holding those
 * records and the `Entries` built over them; a driver that fails a case
 * isn't done.
 *
 * Answers are slugs (`index` for a landing page), in the order found.
 */
abstract class EntryStoreConformance extends TestCase
{
	use BuildsContentSite;

	protected Application $app;

	protected Entries $content;

	private RecordStore $store;

	/**
	 * Returns the record stores under test, holding the site's entries
	 * and refs in the content area.
	 */
	abstract protected function stores(Application $app): RecordStores;

	/**
	 * Returns `Entries` over the stores.
	 */
	abstract protected function entries(Application $app, RecordStores $stores): Entries;

	/**
	 * Returns whether the driver writes entries through `Entries`.
	 */
	protected function writes(): bool
	{
		return true;
	}

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('about/biography.md', "title: Biography\nredirect_from: [/bio, /about-me]");
		$this->entry('_posts/_authors/justintadlock.md', 'title: Justin, Blogger');
		$this->writeTemporaryFile('user/content/_posts/2009-01-01.no-id.md', "---\ntitle: No Id\n---\nA file without an id.");

		$this->app     = $this->site();
		$stores        = $this->stores($this->app);
		$this->store   = $stores->store(EntryTable::table());
		$this->content = $this->entries($this->app, $stores);
	}

	/**
	 * Returns `Entries` over records alone, as a database driver gives
	 * it: locations from records, and writes as records
	 * (`RecordContentWriter`, D-662).
	 */
	protected static function recordEntries(Application $app, RecordStores $stores): Entries
	{
		$container = $app->container();
		$types     = $container->make(ContentTypes::class);
		$config    = $container->make(AppConfig::class);
		$clock     = $container->make(ClockInterface::class);
		$locations = new RecordLocations($stores, $types);
		$entries   = null;

		$entries = new StoredEntries(
			new EntryHydrator($types, $container->make(FieldContext::class), $stores, $container->make(MarkdownParser::class), $clock, $config),
			$types,
			$config,
			$clock,
			$container->make(QueryCompiler::class),
			$stores,
			$locations,
			static function () use (&$entries, $stores, $locations, $types, $container, $clock, $config): RecordContentWriter {
				assert($entries instanceof Entries);

				return new RecordContentWriter(
					$stores,
					$locations,
					$types,
					$container->make(Relations::class),
					new EntryTargets($entries, new EntryRecords($stores), $config),
					$container->make(FieldContext::class),
					$container->make(ContentVersion::class),
					$clock,
					$config
				);
			}
		);

		return $entries;
	}

	/**
	 * Skips a test of writes for a store under test without a writer.
	 */
	private function needsWrites(): void
	{
		if (! $this->writes()) {
			$this->markTestSkipped('This store has no writer.');
		}
	}

	/**
	 * Returns the slugs of entries.
	 *
	 * @param  iterable<Entry> $entries
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

	/**
	 * Returns the slugs a query finds.
	 *
	 * @return list<string>
	 */
	private function found(Query $query): array
	{
		return self::slugs($this->content->get($query));
	}

	/**
	 * Returns a new query.
	 */
	private function query(): Query
	{
		return $this->content->query();
	}

	/**
	 * Returns an entry, by type and key.
	 */
	private function named(string $type, string $key): Entry
	{
		$entry = $this->content->named($type, $key);

		$this->assertNotNull($entry, "No {$type} \"{$key}\".");

		return $entry;
	}

	/**
	 * Returns an entry's record, by type and slug.
	 */
	private function stored(string $type, string $slug): Record
	{
		$found = $this->store->select(EntryTable::table(), new RecordQuery()->where('type', '=', $type)->where('slug', '=', $slug))->first();

		$this->assertNotNull($found, "No {$type} \"{$slug}\".");

		return $found;
	}

	public function testDefaultQueriesFindPublishedPublicEntriesNewestFirst(): void
	{
		$posts = $this->query()->type('post')->get();

		$this->assertSame(['hello', 'spring', 'welcome'], self::slugs($posts));
		$this->assertSame(3, $posts->total());
		$this->assertSame(3, $this->query()->type('post')->count());
		$this->assertSame('hello', $posts->first()?->slug);
	}

	public function testStatusVisibilityAndLanding(): void
	{
		$post = $this->query()->type('post');

		$this->assertSame(['future'], $this->found($post->status(Status::Scheduled)));
		$this->assertSame(['unfinished'], $this->found($post->status(Status::Draft)));
		$this->assertSame(['rainy'], $this->found($post->visibility(Visibility::Unlisted)));
		$this->assertSame(['hello', 'spring', 'welcome', 'index'], $this->found($post->withLanding()));
		$this->assertSame(['future', 'unfinished', 'hello', 'rainy', 'spring', 'welcome', 'justintadlock', 'index'], $this->found($post->any()));

		$this->clock->set('2027-01-01');

		$this->assertSame(['future', 'hello', 'spring', 'welcome'], $this->found($post), 'Scheduled is a time, compared with now.');
		$this->assertSame(Status::Published, $this->named('post', 'future')->status);
	}

	public function testNames(): void
	{
		$this->assertSame(['_private'], $this->found($this->query()->names('_private')));
		$this->assertSame(['rainy'], $this->found($this->query()->names('rainy')));
		$this->assertSame(['index'], $this->found($this->query()->type('post')->names('index')));
		$this->assertSame(['hello', 'welcome'], $this->found($this->query()->type('post')->exceptNames('spring')));
	}

	public function testFoldersAndParents(): void
	{
		$this->assertSame(['biography'], $this->found($this->query()->in('about')));
		$this->assertSame(['about', 'notes'], $this->found($this->query()->in('')));
		$this->assertSame(['hello', 'spring', 'welcome'], $this->found($this->query()->in('_posts')));
		$this->assertSame(['justintadlock', 'guest'], $this->found($this->query()->type('profile')->in('profiles')->orderBy('title', Order::Desc)), 'A collection\'s folder, whatever folders its files are kept in (D-629).');
		$this->assertSame(['about', 'notes'], $this->found($this->query()->type('page')->exceptIn('about')));
		$this->assertSame(['biography'], $this->found($this->query()->whereParent('about')));
		$this->assertSame(['about', 'notes'], $this->found($this->query()->type('page')->whereParent(null)));
		$this->assertSame(3, $this->query()->type('post')->whereParent(null)->count(), 'Types that don\'t nest are all top level (D-562).');
	}

	public function testTermsAuthorsFieldsDatesAndLocales(): void
	{
		$query = $this->query();

		$this->assertSame(['spring'], $this->found($query->whereTerm('category', 'art')));
		$this->assertSame(['spring'], $this->found($query->whereTerm('category', 'Book Reviews')));
		$this->assertSame(['spring', 'welcome'], $this->found($query->whereTerm('category', 'art', 'old-posts')));
		$this->assertSame(['spring', 'welcome'], $this->found($query->whereAuthor('justintadlock')));
		$this->assertSame(['spring'], $this->found($query->whereAuthor('justintadlock', 'guest')));
		$this->assertSame(['spring'], $this->found($query->where('tag')));
		$this->assertSame(['spring'], $this->found($query->where('tag', 'Flowers')));
		$this->assertSame([], $this->found($query->where('tag', 'weeds')));
		$this->assertSame(['spring'], $this->found($query->date(year: 2008, month: 4)));
		$this->assertSame(['welcome'], $this->found($query->date(2003, 4, 15, 17)));
		$this->assertSame([], $this->found($query->date(year: 1999)));
		$this->assertSame(['hello'], $this->found($query->type('post')->locale('en_US')->date(2010)));
		$this->assertSame([], $this->found($query->locale('fr_FR')));
	}

	public function testSearch(): void
	{
		$posts = $this->query()->type('post')->any();

		$this->assertSame(['welcome'], $this->found($posts->search('WELCOME')), 'Titles, in any case.');
		$this->assertSame(['hello'], $this->found($posts->search('hell')), 'Slugs.');
		$this->assertSame([], $this->found($posts->search('hello/index')), 'Never paths.');
		$this->assertSame([], $this->found($posts->search('100%')), 'Text, not patterns.');
		$this->assertCount(8, $this->found($posts->search('  ')), 'Blank text matches everything.');
	}

	public function testAlternatives(): void
	{
		$posts = $this->query()->type('post')->any();
		$draft = static fn (Query $query): Query => $query->status(Status::Draft);
		$art   = static fn (Query $query): Query => $query->whereTerm('category', 'art');

		$this->assertSame(['unfinished', 'rainy', 'spring'], $this->found($posts->either($draft, $art)));
		$this->assertSame(['spring'], $this->found($posts->either($draft, $art)->either(static fn (Query $query): Query => $query->whereAuthor('guest'))));
		$this->assertSame([], $this->found($posts->either()));
	}

	public function testOrderLimitOffsetAndPages(): void
	{
		$posts = $this->query()->type('post');

		$this->assertSame(['hello', 'spring', 'welcome'], $this->found($posts->orderBy('title')));
		$this->assertSame(['welcome', 'spring', 'hello'], $this->found($posts->orderBy('title', Order::Desc)));
		$this->assertSame(['spring', 'welcome', 'hello'], $this->found($posts->orderBy('author')), 'By the slugs written for the relation (D-648).');
		$this->assertSame(['spring', 'welcome', 'hello'], $this->found($posts->orderBy('tag')), 'Entries without one last (D-648).');
		$this->assertSame(['art', 'book-reviews', 'old-posts'], $this->found($this->query()->type('category')->orderBy('position')), 'Positions, then titles.');

		$page = $posts->limit(1)->offset(1)->get();

		$this->assertSame(['spring'], self::slugs($page));
		$this->assertSame(3, $page->total());

		$second = $posts->paginate(2, 2);

		$this->assertSame(['welcome'], self::slugs($second->all()));
		$this->assertSame(2, $second->pages());
	}

	public function testOneXArguments(): void
	{
		$this->assertSame(['hello', 'spring'], $this->found(Query::fromArray(['type' => 'post', 'order' => 'desc', 'orderby' => 'date', 'number' => 2])));
		$this->assertSame(['welcome'], $this->found(Query::fromArray(['type' => 'post', 'year' => 2003, 'noindex' => true])));
		$this->assertSame(['index'], $this->found(Query::fromArray(['type' => 'post', 'slug' => 'index'])));
		$this->assertSame(['spring'], $this->found(Query::fromArray(['meta_key' => 'category', 'meta_value' => 'book-reviews'])));
		$this->assertSame(['spring'], $this->found(Query::fromArray(['author' => ['justintadlock', 'guest']])));
	}

	public function testEntriesAreBuiltFromTheirRecords(): void
	{
		$spring = $this->named('post', 'spring');

		$this->assertSame('spring', $spring->title);
		$this->assertSame('spring', $spring->key);
		$this->assertSame('en', $spring->language);
		$this->assertSame('en_US', $spring->locale);
		$this->assertSame(Status::Published, $spring->status);
		$this->assertSame(Visibility::Public, $spring->visibility);
		$this->assertSame('2008-04-05 09:00', $spring->published?->format('Y-m-d H:i'), 'In the site\'s timezone.');
		$this->assertSame(['art', 'book-reviews'], $spring->terms('category'));
		$this->assertSame(['justintadlock', 'guest'], $spring->terms('profile'));
		$this->assertSame('flowers', $spring->field('tag'), 'What the type doesn\'t declare is kept as written.');
		$this->assertSame('<p>Spring is here.</p>', trim($spring->content()), 'The content, read when it\'s first used.');
		$this->assertNotNull($spring->version);
		$this->assertSame($spring->id, $this->content->find((string) $spring->id)?->id);
		$this->assertSame($spring->id, $this->content->find(strtoupper((string) $spring->id))?->id, 'Ids in any case.');

		$landing = $this->named('post', '');

		$this->assertTrue($landing->landing);
		$this->assertSame('index', $landing->slug);
	}

	public function testKeysWalkParents(): void
	{
		$about     = $this->named('page', 'about');
		$biography = $this->named('page', 'about/biography');

		$this->assertSame($about->id, $biography->parentId);
		$this->assertSame($about->id, $this->content->parent($biography)?->id);
		$this->assertSame(['biography'], self::slugs($this->content->children($about)));
		$this->assertSame('about', $this->content->parentKey('page', 'about/biography'));
		$this->assertNull($this->content->parentKey('page', 'about'));
		$this->assertSame('idea', $this->named('page', 'idea')->key, 'A page whose folder isn\'t an entry is at the top of its tree until its parent is written (D-656).');
		$this->assertNull($this->content->named('page', '__drafts/idea'));
	}

	public function testARelationArchivesPageKeepsItsPlace(): void
	{
		$page = $this->named('post', '_authors/justintadlock');

		$this->assertSame('Justin, Blogger', $page->title);
		$this->assertSame('justintadlock', $page->slug);
		$this->assertSame('_authors/justintadlock', $page->key, 'Its place is its key, in a type that doesn\'t nest (D-657).');
		$this->assertNull($this->content->named('post', 'justintadlock'));
		$this->assertSame(['justintadlock'], $this->found($this->query()->in('_posts/_authors')->visibility(Visibility::Hidden)), 'Hidden by its `_` folder, and listed in it.');
	}

	public function testAFileWithoutAnIdIsntAnEntry(): void
	{
		$this->assertSame([], $this->found($this->query()->type('post')->any()->names('no-id')), 'D-656.');
		$this->assertNull($this->content->named('post', 'no-id'));
	}

	public function testTranslationsNeighborsTermCountsAndRedirects(): void
	{
		$spring = $this->named('post', 'spring');

		$this->assertSame(['en' => $spring], $this->content->translations($spring), 'An entry without translations is its own.');
		$this->assertSame($spring, $this->content->translation($spring, 'en'));
		$this->assertNull($this->content->translation($spring, 'fr'));

		$neighbors = $this->content->neighbors($spring);

		$this->assertSame(['hello', 'welcome'], [$neighbors['before']?->slug, $neighbors['after']?->slug], 'Newest first, so before is newer.');
		$this->assertSame(['art' => 1, 'book-reviews' => 1, 'old-posts' => 1], $this->content->termCounts('category'));
		$this->assertSame(['guest' => 1, 'justintadlock' => 2], $this->content->termCounts('profile'));
		$this->assertSame(['art' => 2, 'book-reviews' => 1, 'old-posts' => 1], $this->content->termCounts('category', $this->query()->visibility(Visibility::Public, Visibility::Unlisted)));
		$this->assertSame(['/bio', '/about-me'], array_keys($this->content->redirects()));
		$this->assertSame('biography', $this->content->redirects()['/bio']->slug);
		$this->assertSame('justintadlock', $this->content->term('profile', 'justintadlock')?->slug);
	}

	public function testRecordsCarryTheirValuesAndContent(): void
	{
		$spring = $this->stored('post', 'spring');

		$this->assertSame('Spring is here.', trim((string) $spring->content));
		$this->assertSame('spring', $spring->fields['title'] ?? null);
		$this->assertSame('2008-04-05T14:00:00Z', $spring->fields['published'] ?? null, 'Times as ISO 8601 text in UTC.');
		$this->assertSame([$spring->id], array_keys($this->store->findMany(EntryTable::table(), [$spring->id])));
	}

	public function testRefsAndAggregates(): void
	{
		$spring = $this->stored('post', 'spring');
		$refs   = Refs::group($this->store, EntryTable::table(), [$spring->id], ['category']);
		$terms  = $this->store->findMany(EntryTable::table(), $refs[$spring->id]['category'] ?? []);

		$this->assertEqualsCanonicalizing(['art', 'book-reviews'], array_map(static fn (Record $record): string => EntryRecords::text($record, 'slug'), array_values($terms)));
		$this->assertSame(2, $this->store->count(Ref::table(StorageArea::Content), new RecordQuery()->where('source_id', '=', $spring->id)->where('relation', '=', 'category')));
		$this->assertSame([['value' => 'category', 'count' => 4], ['value' => 'post', 'count' => 8]], $this->store->countBy(EntryTable::table(), new RecordQuery()->where('type', 'in', ['post', 'category']), 'type'));
		$this->assertSame('2026-12-25T14:00:00Z', $this->store->aggregate(EntryTable::table(), new RecordQuery()->where('type', '=', 'post'), Aggregate::Max, 'published'));
	}

	public function testRecordWritesAreQueriedAndCheckedByVersion(): void
	{
		$spring = $this->stored('post', 'spring');
		$saved  = $this->store->save(EntryTable::table(), $spring->with('title', 'Springtime'), $spring->version);

		$this->assertSame('Springtime', $saved->fields['title'] ?? null);
		$this->assertNotSame($spring->version, $saved->version);
		$this->assertSame(['spring'], $this->found($this->query()->type('post')->search('springtime')));

		$this->expectException(RecordConflict::class);

		$this->store->save(EntryTable::table(), $spring->with('title', 'Stale'), $spring->version);
	}

	public function testWritingAPageAtAKeyWritesItsParents(): void
	{
		$this->needsWrites();

		$type = $this->named('page', 'about')->type;
		$jane = $this->content->createAt($type, '_cooks/jane', new EntryChanges(set: ['title' => 'Jane']));

		$this->assertSame('_cooks/jane', $jane->key);
		$parent = $this->content->parent($jane);

		$this->assertNotNull($parent);
		$this->assertSame('Cooks', $parent->title, 'Its missing parent, written first (D-656)…');
		$this->assertSame(Status::Draft, $parent->status, '…as a draft.');

		$changed = $this->content->change($jane, new EntryChanges(set: ['title' => 'Jane Doe']), $jane->version);

		$this->assertSame('Jane Doe', $changed->title);
		$this->assertSame('Jane Doe', $this->content->named('page', '_cooks/jane')?->title);
	}

	public function testCreatesAnEntryWithAnIdAPublishedDateAndItsRelations(): void
	{
		$this->needsWrites();

		$type  = $this->named('post', 'spring')->type;
		$fresh = $this->content->create($type, 'fresh', new EntryChanges(set: ['title' => 'Fresh', 'category' => ['art'], 'authors' => ['guest']], body: "Fresh text.\n"));

		$this->assertNotNull($fresh->id);
		$this->assertSame('fresh', $fresh->key);
		$this->assertSame(Status::Published, $fresh->status);
		$this->assertNotNull($fresh->published, 'Dated now (D-514).');
		$this->assertSame(['art'], $fresh->terms('category'));
		$this->assertSame(['guest'], $fresh->terms('profile'));
		$this->assertSame('Fresh text.', trim($this->content->editable($fresh)->body));
		$this->assertSame(['art'], $this->content->editable($fresh)->frontMatter['category'] ?? null, 'The editor gets the relation\'s slugs.');
		$this->assertContains('fresh', $this->found($this->query()->type('post')->whereTerm('category', 'art')));

		$this->expectException(WriteException::class);

		$this->content->create($type, 'fresh', new EntryChanges(set: ['title' => 'Again']));
	}

	public function testChangesAreCheckedByVersion(): void
	{
		$this->needsWrites();

		$spring  = $this->named('post', 'spring');
		$changed = $this->content->change($spring, new EntryChanges(set: ['title' => 'Springtime'], body: "Changed.\n"), $spring->version);

		$this->assertSame('Springtime', $changed->title);
		$this->assertSame($spring->id, $changed->id);
		$this->assertSame('Changed.', trim($this->content->editable($changed)->body));
		$this->assertSame(['art', 'book-reviews'], $changed->terms('category'), 'Its relations are kept.');

		$this->expectException(WriteConflict::class);

		$this->content->change($spring, new EntryChanges(set: ['title' => 'Stale']), $spring->version);
	}

	public function testRenamingKeepsLinksToIt(): void
	{
		$this->needsWrites();

		$art     = $this->named('category', 'art');
		$renamed = $this->content->rename($art, 'arts', $art->version);

		$this->assertSame('arts', $renamed->key);
		$this->assertNull($this->content->named('category', 'art'));
		$this->assertSame(['arts', 'book-reviews'], $this->named('post', 'spring')->terms('category'), 'Its referrers follow it.');

		$this->expectException(WriteException::class);

		$this->content->rename($this->named('post', ''), 'blog');
	}

	public function testMovingATreesPageTakesThePagesUnderIt(): void
	{
		$this->needsWrites();

		$type  = $this->named('page', 'about')->type;
		$team  = $this->content->create($type, 'team', new EntryChanges(set: ['title' => 'Team']), $this->named('page', 'about'));

		$this->assertSame('about/team', $team->key);

		$about = $this->named('page', 'about');
		$moved = $this->content->move($about, $this->named('page', 'notes'), $about->version);

		$this->assertSame('notes/about', $moved->key);
		$this->assertSame('notes/about/biography', $this->content->find((string) $this->named('page', 'notes/about/biography')->id)?->key);
		$this->assertNotNull($this->content->named('page', 'notes/about/team'));

		$this->expectException(WriteException::class);

		$this->content->move($this->named('page', 'notes'), $this->named('page', 'notes/about/team'));
	}

	public function testTrashRestoreAndDelete(): void
	{
		$this->needsWrites();

		$spring  = $this->named('post', 'spring');
		$trashed = $this->content->trash($spring, $spring->version);

		$this->assertSame(Status::Trash, $trashed->status);
		$this->assertSame(Status::Draft, $this->content->restore($trashed, $trashed->version)->status);

		$this->content->delete((string) $spring->id);

		$this->assertNull($this->content->find((string) $spring->id));
		$this->assertSame(0, $this->store->count(Ref::table(StorageArea::Content), new RecordQuery()->where('source_id', '=', $spring->id)), 'Its refs go with it.');
	}

	public function testDuplicatingCopiesAnEntry(): void
	{
		$this->needsWrites();

		$copy = $this->content->duplicate($this->named('post', 'spring'), 'spring-again', new EntryChanges(set: ['title' => 'Spring Again']));

		$this->assertSame('spring-again', $copy->slug);
		$this->assertSame('Spring Again', $copy->title);
		$this->assertSame('Spring is here.', trim($this->content->editable($copy)->body));
		$this->assertNotSame($this->named('post', 'spring')->id, $copy->id);
		$this->assertSame(['art', 'book-reviews'], $copy->terms('category'));
	}
}
