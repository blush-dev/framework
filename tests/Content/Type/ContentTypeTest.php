<?php

/**
 * Content type tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Storage\Record\Order;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\Collection;
use Blush\Content\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Listing;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Tree;
use Blush\Content\Type\TypeOrder;
use Blush\Content\Type\LegacyFolder;
use Blush\Content\Type\LegacyTaxonomy;
use Blush\Content\Type\TypeFeed;
use Blush\Content\Type\TypeKind;
use Blush\Content\Type\TypeLabels;
use Blush\Content\Type\TypeUrls;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\TextField;
use Blush\Tests\Fixtures\Content\JtcomTypes;

#[CoversClass(ContentType::class)]
#[CoversClass(Collection::class)]
#[CoversClass(TypeOrder::class)]
#[CoversClass(LegacyTaxonomy::class)]
#[CoversClass(Tree::class)]
#[CoversClass(Profiles::class)]
#[CoversClass(TypeKind::class)]
#[CoversClass(ContentConfig::class)]
#[CoversClass(TypeUrls::class)]
#[CoversClass(TypeFeed::class)]
#[CoversClass(Listing::class)]
#[CoversClass(DateArchives::class)]
#[CoversClass(BuiltInType::class)]
#[CoversClass(InvalidContentType::class)]
#[CoversClass(LegacyFolder::class)]
#[CoversClass(TypeLabels::class)]
final class ContentTypeTest extends TestCase
{
	private FieldFactory $fields;

	protected function setUp(): void
	{
		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$this->fields = new FieldFactory($registry);
	}

	public function testDefaultsFollowThe1xRules(): void
	{
		$type = new Collection('project');

		$this->assertSame('_project', $type->folder, 'Type folders stand apart from page folders.');
		$this->assertSame('project', $type->prefix());
		$this->assertSame('project', $type->listedType());
		$this->assertSame(['type' => 'project'], $type->listingArguments());
		$this->assertTrue($type->hasUrls());
		$this->assertFalse($type->hasFeed());
		$this->assertSame('{name}', $type->urls === false ? null : $type->urls->path('single'));
		$this->assertSame(['name' => 'project', 'kind' => 'collection'], $type->toArray());
		$this->assertSame('', $type->description);
		$this->assertNull($type->icon);
		$this->assertFalse($type->hierarchical);
		$this->assertSame(TypeOrder::Published, $type->order);
	}

	public function testEveryTypeIsKeptInItsNameAndPrefixedByIt(): void
	{
		$this->assertSame('_form', new Collection('form')->folder, 'D-683.');
		$this->assertSame('form', new Collection('form')->prefix());
		$this->assertSame('archives', new Collection('post', urls: new TypeUrls(prefix: 'archives'))->prefix());
		$this->assertSame('_doc', new Tree('doc')->folder);
		$this->assertSame('doc', new Tree('doc')->pagePath());
		$this->assertSame('docs/guide', new Tree('doc', prefix: '/docs/guide/')->pagePath());
		$this->assertSame('docs', ContentType::fromArray(['name' => 'doc', 'kind' => 'tree', 'prefix' => 'docs'], $this->fields)->toArray()['prefix'] ?? null);
		$this->assertSame('', new Tree()->folder);
		$this->assertSame('', new Tree()->pagePath());
	}

	public function testATypeNeverNamesItsFolder(): void
	{
		foreach (['folder' => 'recipes', 'path' => 'recipes'] as $key => $folder) {
			try {
				ContentType::fromArray(['name' => 'recipe', $key => $folder], $this->fields);
				$this->fail("\"{$key}\" is refused.");
			} catch (InvalidContentType $e) {
				$this->assertStringContainsString('every type is kept in _recipe now', $e->getMessage());
			}
		}
	}

	public function testLegacyFoldersAreReadAwayKeepingAddresses(): void
	{
		$this->assertTrue(LegacyFolder::is(['path' => '']));
		$this->assertFalse(LegacyFolder::is(['folders' => '{year}']));
		$this->assertSame('_posts', LegacyFolder::folderOf(['folder' => '_posts/{year}']));

		$this->assertSame(['folders' => '{year}', 'urls' => ['prefix' => 'posts']], LegacyFolder::convert('post', ['folder' => '_posts/{year}'], TypeKind::Collection));
		$this->assertSame(['routing' => ['prefix' => 'archives']], LegacyFolder::convert('post', ['path' => '_posts', 'routing' => ['prefix' => 'archives']], TypeKind::Collection), 'A prefix it has stays.');
		$this->assertSame([], LegacyFolder::convert('recipe', ['folder' => '_recipe'], TypeKind::Collection), 'Its own folder needs nothing.');
		$this->assertSame(['urls' => false], LegacyFolder::convert('note', ['folder' => 'notes', 'urls' => false], TypeKind::Collection));
		$this->assertSame(['prefix' => 'docs'], LegacyFolder::convert('doc', ['folder' => '_docs'], TypeKind::Tree));
		$this->assertSame(['kind' => 'profiles'], LegacyFolder::convert('profile', ['kind' => 'profiles', 'folder' => 'people'], TypeKind::Profiles), 'Profiles are at /profiles whatever their folder.');
		$this->assertSame(['folders' => '{year}'], LegacyFolder::convert('movie', ['folder' => 'movies/{year}'], TypeKind::Collection, false), 'None over a code type\'s URLs.');
	}

	public function testReservedNamesAreRefused(): void
	{
		foreach (ContentType::RESERVED as $name) {
			try {
				new Collection($name);
				$this->fail("\"{$name}\" is refused.");
			} catch (InvalidContentType $e) {
				$this->assertStringContainsString("_{$name} is a folder the site's pages keep", $e->getMessage());
			}
		}
	}

	public function testOnlyATreeInAFolderTakesAPrefix(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('served from the root');

		(void) new Tree(prefix: 'pages');
	}

	public function testDescriptionIconAndHierarchyRoundTrip(): void
	{
		$type = ContentType::fromArray(['name' => 'topic', 'hierarchical' => true, 'order' => 'position', 'description' => ' What posts are about. ', 'icon' => 'folder'], $this->fields);

		$this->assertInstanceOf(Collection::class, $type);
		$this->assertTrue($type->hierarchical);
		$this->assertTrue($type->isPositioned());
		$this->assertSame('What posts are about.', $type->description);
		$this->assertSame('folder', $type->icon);
		$this->assertSame(['name' => 'topic', 'kind' => 'collection', 'description' => 'What posts are about.', 'icon' => 'folder', 'hierarchical' => true, 'order' => 'position'], $type->toArray());
		$this->assertSame('parent', $type->parentField()?->name);
		$this->assertFalse($type->parentField()->multiple);
		$this->assertSame('web', $type->parentKey('css', ['parent' => 'web']), 'A hierarchical collection nests by `parent` (D-593).');
		$this->assertNull($type->parentKey('css', ['parent' => 'css']), 'Never its own.');
		$this->assertNull(new Collection('tag')->parentField());
		$this->assertNull(new Collection('tag')->parentKey('css', ['parent' => 'web']));
	}

	public function testAcceptsJtcoms1xDefinitionsWithoutTheirFolders(): void
	{
		$types = [];

		// A 1.x folder is read away (D-683).
		foreach (JtcomTypes::definitions() as $name => $definition) {
			$types[$name] = ContentType::fromArray(['name' => $name, ...LegacyFolder::convert($name, $definition, TypeKind::Collection)], $this->fields);
		}

		$post = $types['post'];

		$this->assertInstanceOf(Collection::class, $post);
		$this->assertSame('_post', $post->folder);
		$this->assertSame('writing/forms', $types['literary_form']->prefix(), 'Its addresses stay.');
		$this->assertSame('archives', $post->prefix());
		$this->assertSame('{year}/{month}/{day}/{name}', $post->urls === false ? null : $post->urls->path('single'));
		$this->assertSame('page/{page}', $post->urls === false ? null : $post->urls->path('collection.paged'));
		$this->assertEquals(new Listing(order: Order::Desc), $post->listing);
		$this->assertSame(['order' => 'desc', 'type' => 'post'], $post->listingArguments());
		$this->assertSame(DateArchives::Day, $post->dateArchives);
		$this->assertEquals(new TypeFeed(categories: 'category'), $post->feed);

		$form = $types['literary_form'];

		$this->assertInstanceOf(Collection::class, $form);
		$this->assertSame(['_literary_form', 'writing/forms'], [$form->folder, $form->prefix()]);
		$this->assertSame(['position', Order::Asc], $form->order());

		$relation = Relation::fromArray(['name' => 'literary_form', ...JtcomTypes::relations()['literary_form']]);

		$this->assertSame(['literature'], $relation->from);
		$this->assertEquals(new Listing(order: Order::Desc, perPage: 9999), $relation->inverse === false ? null : $relation->inverse->listing);
	}

	public function testRoundTripsThroughArrays(): void
	{
		$type = new Collection(
			'post',
			folders: '{year}',
			urls: new TypeUrls('/archives/', single: '/{year}/{name}/'),
			listing: new Listing(type: 'post', order: Order::Desc, query: ['terms' => ['category' => 'news']]),
			feed: new TypeFeed('category', new Listing(perPage: 20)),
			dateArchives: DateArchives::Month,
			public: false,
			sitemap: false,
			fields: [new TextField('subtitle')],
			closed: true,
			llms: false
		);

		$this->assertSame([
			'name'         => 'post',
			'kind'         => 'collection',
			'folders'      => '{year}',
			'urls'         => ['prefix' => 'archives', 'paths' => ['single' => '{year}/{name}']],
			'listing'      => ['type' => 'post', 'order' => 'desc', 'query' => ['terms' => ['category' => 'news']]],
			'feed'         => ['categories' => 'category', 'listing' => ['perPage' => 20]],
			'public'       => false,
			'sitemap'      => false,
			'llms'         => false,
			'dateArchives' => 'month',
			'fields'       => [['name' => 'subtitle', 'type' => 'text']],
			'closed'       => true
		], $type->toArray());
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));

		// Each kind writes `llms` only when it isn't the kind's default (D-401).
		$this->assertTrue(ContentType::fromArray(['name' => 'tag'], $this->fields)->llms);
		$this->assertSame(false, ContentType::fromArray(['name' => 'tag', 'llms' => false], $this->fields)->toArray()['llms'] ?? null);
		$this->assertArrayNotHasKey('llms', ContentType::fromArray(['name' => 'tag', 'llms' => true], $this->fields)->toArray());
		$this->assertFalse(ContentType::fromArray(['name' => 'profile', 'kind' => 'profiles'], $this->fields)->llms);
		$this->assertTrue(ContentType::fromArray(['name' => 'doc', 'kind' => 'tree'], $this->fields)->llms);

		$terms = new Collection('topic', urls: false, feed: new TypeFeed(), hierarchical: true, order: TypeOrder::Position);

		$this->assertEquals($terms, ContentType::fromArray($terms->toArray(), $this->fields));
		$this->assertSame(true, $terms->toArray()['feed']);
		$this->assertSame(['hierarchical' => true, 'order' => 'position'], array_intersect_key($terms->toArray(), ['hierarchical' => true, 'order' => true]));

		$pages = new Tree(fields: [new TextField('subtitle')]);

		$this->assertSame(['name' => 'page', 'kind' => 'tree', 'fields' => [['name' => 'subtitle', 'type' => 'text']]], $pages->toArray());
		$this->assertEquals($pages, ContentType::fromArray($pages->toArray(), $this->fields));

		$profiles = new Profiles(folders: '{initial}', urls: new TypeUrls('team'), feed: new TypeFeed(), public: false);

		$this->assertSame(['name' => 'profile', 'kind' => 'profiles', 'folders' => '{initial}', 'urls' => ['prefix' => 'team'], 'feed' => true, 'public' => false], $profiles->toArray());
		$this->assertEquals($profiles, ContentType::fromArray($profiles->toArray(), $this->fields));
	}

	public function testATypeNamesItsBylineAndRefusesPeopleFields(): void
	{
		$type = new Collection('recipe', byline: 'cooks');

		$this->assertSame('cooks', $type->byline);
		$this->assertSame('cooks', $type->toArray()['byline'] ?? null);
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));
		$this->assertNull(new Collection('post')->byline, 'None named: its only credit relation (D-602).');
		$this->assertArrayNotHasKey('byline', new Collection('post')->toArray());

		foreach (['people' => ['cooks' => true], 'authors' => true] as $key => $value) {
			try {
				ContentType::fromArray(['name' => 'post', $key => $value], $this->fields);
				$this->fail($key);
			} catch (InvalidContentType $e) {
				$this->assertStringContainsString('credits people through a credit relation', $e->getMessage(), 'Credits are relations (D-602).');
			}
		}
	}

	public function testNamesTypesForPeople(): void
	{
		$names = static fn (ContentType $type): array => [$type->labels->plural, $type->labels->singular];

		$this->assertSame(['Posts', 'Post'], $names(new Collection('post')));
		$this->assertSame(['Categories', 'Category'], $names(new Collection('category')));
		$this->assertSame(['Literary forms', 'Literary form'], $names(new Collection('literary_form')));
		$this->assertSame(['Classes', 'Class'], $names(new Collection('class')));
		$this->assertSame(['Essays', 'Essay'], $names(new Collection('essay')), 'A vowel before the y keeps it.');
		$this->assertSame(['Pages', 'Page'], $names(new Tree()));

		$type = ContentType::fromArray(['name' => 'person', 'labels' => ['plural' => 'People']], $this->fields);

		$this->assertSame(['People', 'Person'], $names($type));
		$this->assertSame(['plural' => 'People'], $type->toArray()['labels'] ?? null, 'Defaults are left out.');
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));
		$this->assertArrayNotHasKey('labels', new Collection('post')->toArray());

		$era = new Collection('era', labels: new TypeLabels('Era of life', plural: 'Eras of life'));

		$this->assertEquals($era, ContentType::fromArray($era->toArray(), $this->fields));
	}

	public function testLabelsDefaultFromTheOnesBefore(): void
	{
		$this->assertSame([
			'singular'    => 'Literary form',
			'plural'      => 'Literary forms',
			'menu'        => 'Literary forms',
			'item'        => 'literary form',
			'items'       => 'literary forms',
			'newItem'     => 'New Literary Form',
			'editItem'    => 'Edit literary form',
			'searchItems' => 'Search literary forms'
		], new Collection('literary_form')->labels->all());

		$person = new TypeLabels('Person', plural: 'People', newItem: 'Add someone');

		$this->assertSame(['people', 'Add someone', 'Edit person', 'Search people'], [$person->items, $person->newItem, $person->editItem, $person->searchItems]);
		$this->assertSame(['plural' => 'People', 'newItem' => 'Add someone'], $person->toArray('person'), 'Labels made from the ones before are left out.');
		$this->assertSame(['singular' => 'Person', 'plural' => 'People', 'newItem' => 'Add someone'], $person->toArray('human'), 'A singular the name doesn\'t make is kept.');
		$this->assertSame('Edit person', new TypeLabels('Person', editItem: '  ')->editItem, 'A blank label is its default.');
		$this->assertSame('New Book of the Month', new TypeLabels('Book of the month')->newItem, 'The new label is in Title Case.');

		$forms = TypeLabels::fromArray(['menu' => 'Forms'], 'literary_form');

		$this->assertSame(['Forms', 'Literary forms', 'literary forms'], [$forms->menu, $forms->plural, $forms->items], 'The menu label changes only the menu.');
		$this->assertSame(['menu' => 'Forms'], $forms->toArray('literary_form'));
		$this->assertSame('People', new TypeLabels('Person', plural: 'People')->menu, 'The menu follows the plural.');
	}

	public function testLabelsKeepAcronymsMidSentence(): void
	{
		$faq = new TypeLabels('FAQ');

		$this->assertSame(['FAQs', 'FAQ', 'New FAQ', 'Search FAQs'], [$faq->plural, $faq->item, $faq->newItem, $faq->searchItems]);
		$this->assertSame('HTML snippet', new TypeLabels('HTML snippet')->item);
		$this->assertSame('McGuffin', new TypeLabels('McGuffin')->item);
		$this->assertSame('état', new TypeLabels('État')->item, 'Lowercasing is multibyte safe.');
		$this->assertSame("Jane's notes", new TypeLabels("Jane's note", items: "Jane's notes")->items, 'Proper nouns set it.');
	}

	public function testRejectsUnknownLabels(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('Content type "post" labels has unknown options: label.');

		ContentType::fromArray(['name' => 'post', 'labels' => ['label' => 'Posts']], $this->fields);
	}

	public function testRejectsTheOldLabelOptions(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('unknown options: label, singular.');

		ContentType::fromArray(['name' => 'post', 'label' => 'Posts', 'singular' => 'Post'], $this->fields);
	}

	public function testReadsKinds(): void
	{
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'note'], $this->fields));
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'note', 'taxonomy' => false], $this->fields));

		$this->assertInstanceOf(Profiles::class, ContentType::fromArray(['name' => 'person', 'kind' => 'profiles'], $this->fields));

		$pages = ContentType::fromArray(['name' => 'page', 'kind' => 'tree'], $this->fields);

		$this->assertInstanceOf(Tree::class, $pages);
		$this->assertSame('', $pages->folder);
		$this->assertFalse($pages->hasUrls());
	}

	public function testReadsThe2xNames(): void
	{
		$type = ContentType::fromArray([
			'name'         => 'event',
			'folders'      => '{year}',
			'urls'         => ['prefix' => 'on', 'single' => '{year}/{name}', 'collection' => 'all'],
			'listing'      => ['orderBy' => 'published', 'order' => 'desc', 'perPage' => 5, 'query' => ['offset' => 1]],
			'dateArchives' => 'hour',
			'fields'       => [['name' => 'venue', 'type' => 'text', 'required' => true]],
			'feed'         => ['categories' => 'topic', 'listing' => ['perPage' => 50]]
		], $this->fields);

		$this->assertSame(['_event', '{year}'], [$type->folder, $type->folders?->pattern]);
		$this->assertSame('/on/{year}/{name}', $type->routePattern('single'));
		$this->assertSame('/on/all', $type->routePattern('collection'));
		$this->assertSame(['offset' => 1, 'orderby' => 'published', 'order' => 'desc', 'number' => 5, 'type' => 'event'], $type->listingArguments());
		$this->assertSame(DateArchives::Hour, $type->dateArchives);
		$this->assertTrue($type->schema->fields['venue']->required);
		$this->assertEquals(new TypeFeed('topic', new Listing(perPage: 50)), $type->feed);
	}

	public function testReadsThe1xNames(): void
	{
		$type = ContentType::fromArray([
			'name'       => 'log',
			'collect'    => 'post',
			'collection' => ['orderby' => 'date', 'number' => 0, 'meta_key' => 'mood'],
			'feed'       => ['taxonomy' => 'topic', 'collection' => ['number' => 50]]
		], $this->fields);

		$this->assertEquals(new Listing(type: 'post', orderBy: 'published', perPage: Listing::ALL, query: ['meta_key' => 'mood']), $type->listing);
		$this->assertEquals(new TypeFeed('topic', new Listing(perPage: 50)), $type->feed);
		$this->assertSame(DateArchives::Second, ContentType::fromArray(['name' => 'log', 'time_archives' => true], $this->fields)->dateArchives);
		$this->assertSame(DateArchives::Day, ContentType::fromArray(['name' => 'log', 'date_archives' => true], $this->fields)->dateArchives);
		$this->assertSame('page', ContentType::fromArray(['name' => 'page', 'collect' => false], $this->fields)->listedType());
	}

	public function testTaxonomiesAreRefusedSayingWhatReplacedThem(): void
	{
		foreach ([['name' => 'tag', 'kind' => 'taxonomy'], ['name' => 'tag', 'taxonomy' => true]] as $definition) {
			try {
				ContentType::fromArray($definition, $this->fields);
				$this->fail('A taxonomy is refused (D-591).');
			} catch (InvalidContentType $e) {
				$this->assertStringStartsWith('Content type "tag" is a taxonomy, which Blush no longer has: make it a collection', $e->getMessage());
				$this->assertStringContainsString('content:taxonomies --write', $e->getMessage());
			}
		}
	}

	public function testLegacyTaxonomiesConvertToACollectionAndARelation(): void
	{
		$this->assertTrue(LegacyTaxonomy::is(['taxonomy' => true]));
		$this->assertTrue(LegacyTaxonomy::is(['kind' => 'taxonomy']));
		$this->assertFalse(LegacyTaxonomy::is(['kind' => 'collection', 'taxonomy' => false]));

		[$type, $relation] = LegacyTaxonomy::convert('tag', [
			'taxonomy'        => true,
			'path'            => 'tags',
			'field_aliases'   => ['tags'],
			'term_collect'    => 'post',
			'term_collection' => ['order' => 'desc'],
			'hierarchical'    => true
		]);

		$this->assertSame(['path' => 'tags', 'hierarchical' => true, 'order' => 'position', 'llms' => false], $type);
		$this->assertSame(['kind' => 'classify', 'from' => ['post'], 'to' => ['tag'], 'aliases' => ['tags'], 'create' => true, 'inverse' => ['listing' => ['order' => 'desc']]], $relation);
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'tag', ...LegacyFolder::convert('tag', $type, TypeKind::Collection)], $this->fields));

		[$type, $relation] = LegacyTaxonomy::convert('category', ['kind' => 'taxonomy', 'field' => 'categories', 'urls' => false, 'llms' => true, 'people' => true]);

		$this->assertSame(['urls' => false, 'llms' => true, 'order' => 'position'], $type, 'What the taxonomy said is kept, but who it credits: terms credit nobody (D-602).');
		$this->assertSame(['kind' => 'classify', 'to' => ['category'], 'field' => 'categories', 'create' => true], $relation, 'Without URLs, terms have no pages.');
	}

	public function testDateArchiveLevels(): void
	{
		$this->assertSame([], DateArchives::None->levels());
		$this->assertSame([DateArchives::Year], DateArchives::Year->levels());
		$this->assertSame([DateArchives::Year, DateArchives::Month, DateArchives::Day], DateArchives::Day->levels());
		$this->assertCount(6, DateArchives::Second->levels());
	}

	public function testRejectsBadDefinitions(): void
	{
		$cases = [
			[['path' => 'x'], 'A content type definition needs a "name".'],
			[['name' => 'Post'], 'Content type name "Post" must start with a lowercase letter'],
			[['name' => 'post', 'folder' => 'posts'], 'Content type "post" names its folder ("posts"), but every type is kept in _post now'],
			[['name' => 'post', 'folders' => 'a/../b'], 'Content type "post": The folder pattern "a/../b" must be tokens'],
			[['name' => 'doc', 'kind' => 'tree', 'prefix' => '_docs'], 'Content type "doc" has an invalid prefix "_docs".'],
			[['name' => 'post', 'routes' => []], 'Content type "post" (collection) has unknown options: routes.'],
			[['name' => 'post', 'types' => ['x']], 'Content type "post" (collection) has unknown options: types.'],
			[['name' => 'post', 'order' => 'sideways'], 'Content type "post" "order" must be one of published, position.'],
			[['name' => 'page', 'kind' => 'tree', 'urls' => []], 'Content type "page" (tree) has unknown options: urls.'],
			[['name' => 'post', 'kind' => 'blog'], 'Content type "post" "kind" must be one of collection, tree, profiles.'],
			[['name' => 'post', 'routing' => 'yes'], 'Content type "post" "urls" must be false or a map.'],
			[['name' => 'post', 'urls' => ['prefix' => 'a', 'single' => 'b', 'nope' => 'c']], 'Content type "post" urls has unknown options: nope.'],
			[['name' => 'post', 'feed' => 'yes'], 'Content type "post" "feed" must be true, false, or a map.'],
			[['name' => 'post', 'feed' => ['nope' => 1]], 'Content type "post" feed has unknown options: nope.'],
			[['name' => 'post', 'dateArchives' => 'weekly'], 'Content type "post" "dateArchives" must be one of none, year'],
			[['name' => 'post', 'collect' => 5], 'Content type "post" "collect" must be a type name or false.'],
			[['name' => 'post', 'collection' => ['a']], 'Content type "post" "listing" must be a map.'],
			[['name' => 'post', 'listing' => ['order' => 'sideways']], 'Content type "post" listing "order" must be "asc" or "desc".'],
			[['name' => 'post', 'listing' => ['perPage' => 2.5]], 'Content type "post" listing "perPage" must be a whole number.'],
			[['name' => 'post', 'listing' => ['query' => ['nope' => 1]]], 'Listing is invalid: Unknown query arguments: nope.'],
			[['name' => 'post', 'fields' => [['name' => 'x', 'type' => 'nope']]], 'Field "x" has an unknown type "nope".'],
			[['name' => 'post', 'filename' => '{slug}.{date}'], 'Content type "post": The file name pattern "{slug}.{date}" must end in {slug}.'],
			[['name' => 'page', 'kind' => 'tree', 'filename' => '{date}/x.{slug}'], 'Content type "page": The file name pattern "{date}/x.{slug}" may use only']
		];

		foreach ($cases as [$definition, $message]) {
			try {
				ContentType::fromArray($definition, $this->fields);
				$this->fail($message);
			} catch (InvalidContentType $e) {
				$this->assertStringStartsWith($message, $e->getMessage());
			}
		}
	}

	public function testEachKindHasAnOrderNeverByFile(): void
	{
		$this->assertSame(['published', Order::Desc], ContentType::fromArray(['name' => 'post'], $this->fields)->order(), 'D-516');
		$this->assertSame(['position', Order::Asc], ContentType::fromArray(['name' => 'doc', 'kind' => 'tree'], $this->fields)->order());
		$this->assertSame(['position', Order::Asc], ContentType::fromArray(['name' => 'tag', 'order' => 'position'], $this->fields)->order());
		$this->assertSame(['title', Order::Asc], ContentType::fromArray(['name' => 'person', 'kind' => 'profiles'], $this->fields)->order());

		$tag = ContentType::fromArray(['name' => 'tag', 'order' => 'position', 'listing' => ['order' => 'desc']], $this->fields);

		$this->assertSame(['position', 'desc'], [$tag->listingArguments()['orderby'] ?? null, $tag->listingArguments()['order'] ?? null], 'Terms list in their order, the listing\'s way.');
		$this->assertSame('published', ContentType::fromArray(['name' => 'post', 'listing' => ['orderBy' => 'filename']], $this->fields)->listing->orderBy, '1.x\'s file order is published.');
	}

	public function testCollectionsNameFilesByAPattern(): void
	{
		$dated = ContentType::fromArray(['name' => 'post', 'dateArchives' => 'day'], $this->fields);
		$plain = ContentType::fromArray(['name' => 'note'], $this->fields);
		$own   = ContentType::fromArray(['name' => 'log', 'filename' => '{year}.{slug}'], $this->fields);

		$this->assertSame(['{slug}', '{slug}', '{year}.{slug}'], [$dated->naming()->pattern, $plain->naming()->pattern, $own->naming()->pattern], 'The slug alone by default, date archives or not (D-515).');
		$this->assertArrayNotHasKey('filename', $dated->toArray(), 'The default isn\'t written.');
		$this->assertSame('{year}.{slug}', $own->toArray()['filename'] ?? null);
		$this->assertEquals($own, ContentType::fromArray($own->toArray(), $this->fields));
	}

	public function testListingRejectsTypedArgumentsInItsQuery(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('Listing "query" can\'t set number; use the Listing options instead.');

		(void) new Listing(query: ['number' => 5]);
	}

	public function testConfigRejectsTypesAndDisablingPages(): void
	{
		$cases = [
			static fn (): ContentConfig => new ContentConfig(disabled: ['page']),
			static fn (): ContentConfig => new ContentConfig(disabled: ['nope']),
			static fn (): ContentConfig => ContentConfig::fromArray(['types' => ['post' => []]]),
			static fn (): ContentConfig => ContentConfig::fromArray(['relations' => []])
		];

		foreach ($cases as $build) {
			try {
				$build();
				$this->fail('The config should be invalid.');
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testConfigRoundTrips(): void
	{
		$config = ContentConfig::fromArray(['home' => 'post', 'dataTypeUrls' => false, 'disabled' => ['profile']]);

		$this->assertEquals($config, ContentConfig::fromArray($config->toArray()));
	}

	public function testBuiltInTypes(): void
	{
		$page   = BuiltInType::Page->type();
		$profile = BuiltInType::Profile->type();

		$this->assertInstanceOf(Tree::class, $page);
		$this->assertSame('', $page->folder);
		$this->assertFalse($page->hasUrls());
		$this->assertFalse(BuiltInType::Page->canDisable());

		$this->assertInstanceOf(Profiles::class, $profile);
		$this->assertSame('profile', $profile->name);
		$this->assertSame('_profile', $profile->folder);
		$this->assertSame('/profiles/{name}', $profile->routePattern('single'), 'Each profile has a page of its own (D-351).');
		$this->assertSame('/profiles/{name}', new Profiles('person')->routePattern('single'), 'Whatever its name (D-357).');
		$this->assertSame('/team/{name}', new Profiles(urls: new TypeUrls('team'))->routePattern('single'));
		$this->assertFalse($profile->servedAsPages());
		$this->assertFalse($profile->hasFeed());
		$this->assertTrue(BuiltInType::Profile->canDisable());
	}
}
