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
use Blush\Content\Query\Order;
use Blush\Content\Type\Authors;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Listing;
use Blush\Content\Type\Pages;
use Blush\Content\Type\Taxonomy;
use Blush\Content\Type\TypeFeed;
use Blush\Content\Type\TypeKind;
use Blush\Content\Type\TypeLabels;
use Blush\Content\Type\TypeUrls;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Tests\Fixtures\Content\JtcomTypes;

#[CoversClass(ContentType::class)]
#[CoversClass(Collection::class)]
#[CoversClass(Taxonomy::class)]
#[CoversClass(Pages::class)]
#[CoversClass(Authors::class)]
#[CoversClass(TypeKind::class)]
#[CoversClass(ContentConfig::class)]
#[CoversClass(TypeUrls::class)]
#[CoversClass(TypeFeed::class)]
#[CoversClass(Listing::class)]
#[CoversClass(DateArchives::class)]
#[CoversClass(BuiltInType::class)]
#[CoversClass(InvalidContentType::class)]
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
		$this->assertNull($type->termField());
		$this->assertSame('{name}', $type->urls === false ? null : $type->urls->path('single'));
		$this->assertSame(['name' => 'project', 'kind' => 'collection'], $type->toArray());
		$this->assertSame('project', new Taxonomy('project')->field);
		$this->assertSame('', $type->description);
		$this->assertNull($type->icon);
		$this->assertFalse(new Taxonomy('topic')->hierarchical);
	}

	public function testPrefixesDropTheUnderscoresOfFolderNames(): void
	{
		$this->assertSame('writing/forms', new Taxonomy('form', folder: '_writing/_forms')->prefix());
		$this->assertSame('archives', new Collection('post', folder: '_posts', urls: new TypeUrls(prefix: 'archives'))->prefix());
	}

	public function testDescriptionIconAndHierarchyRoundTrip(): void
	{
		$type = ContentType::fromArray(['name' => 'topic', 'kind' => 'taxonomy', 'hierarchical' => true, 'description' => ' What posts are about. ', 'icon' => 'folder'], $this->fields);

		$this->assertInstanceOf(Taxonomy::class, $type);
		$this->assertTrue($type->hierarchical);
		$this->assertSame('What posts are about.', $type->description);
		$this->assertSame('folder', $type->icon);
		$this->assertSame(['name' => 'topic', 'kind' => 'taxonomy', 'description' => 'What posts are about.', 'icon' => 'folder', 'hierarchical' => true], $type->toArray());
		$this->assertSame('parent', $type->parentField()?->name);
		$this->assertFalse($type->parentField()->multiple);
		$this->assertNull(new Taxonomy('tag')->parentField());
	}

	public function testAcceptsJtcoms1xConfigUnchanged(): void
	{
		$config = ContentConfig::fromArray(['types' => JtcomTypes::definitions(), 'home' => 'post']);
		$types  = [];

		foreach ($config->definitions as $definition) {
			$type               = ContentType::fromArray($definition, $this->fields);
			$types[$type->name] = $type;
		}

		$post = $types['post'];

		$this->assertInstanceOf(Collection::class, $post);
		$this->assertSame('_posts', $post->folder);
		$this->assertSame('archives', $post->prefix());
		$this->assertSame('{year}/{month}/{day}/{name}', $post->urls === false ? null : $post->urls->path('single'));
		$this->assertSame('page/{page}', $post->urls === false ? null : $post->urls->path('collection.paged'));
		$this->assertEquals(new Listing(order: Order::Desc), $post->listing);
		$this->assertSame(['order' => 'desc', 'type' => 'post'], $post->listingArguments());
		$this->assertSame(DateArchives::Day, $post->dateArchives);
		$this->assertEquals(new TypeFeed(categories: 'category'), $post->feed);

		$form = $types['literary_form'];

		$this->assertInstanceOf(Taxonomy::class, $form);
		$this->assertSame('writing/forms', $form->folder);
		$this->assertSame(['literature'], $form->types);
		$this->assertEquals(new Listing(order: Order::Desc, perPage: 9999), $form->termListing);
		$this->assertSame(['type' => ['literature'], 'order' => 'desc', 'number' => 9999], $form->termArguments());
		$this->assertSame('literary_form', $form->termField()->name);
		$this->assertSame('post', $config->home);
	}

	public function testRoundTripsThroughArrays(): void
	{
		$type = new Collection(
			'post',
			folder: '_posts',
			urls: new TypeUrls('/archives/', single: '/{year}/{name}/'),
			listing: new Listing(type: 'post', order: Order::Desc, query: ['terms' => ['category' => 'news']]),
			feed: new TypeFeed('category', new Listing(perPage: 20)),
			dateArchives: DateArchives::Month,
			public: false,
			sitemap: false,
			fields: [new TextField('subtitle')],
			closed: true
		);

		$this->assertSame([
			'name'         => 'post',
			'kind'         => 'collection',
			'folder'       => '_posts',
			'urls'         => ['prefix' => 'archives', 'paths' => ['single' => '{year}/{name}']],
			'listing'      => ['type' => 'post', 'order' => 'desc', 'query' => ['terms' => ['category' => 'news']]],
			'feed'         => ['categories' => 'category', 'listing' => ['perPage' => 20]],
			'public'       => false,
			'sitemap'      => false,
			'dateArchives' => 'month',
			'fields'       => [['name' => 'subtitle', 'type' => 'text']],
			'closed'       => true
		], $type->toArray());
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));

		$taxonomy = new Taxonomy(
			'author',
			folder: 'authors',
			types: ['post', 'note'],
			field: 'authors',
			aliases: ['author'],
			urls: false,
			termListing: new Listing(perPage: Listing::ALL),
			feed: new TypeFeed()
		);

		$this->assertEquals($taxonomy, ContentType::fromArray($taxonomy->toArray(), $this->fields));
		$this->assertSame(true, $taxonomy->toArray()['feed']);
		$this->assertSame(['author'], $taxonomy->termField()->aliases);

		$pages = new Pages(fields: [new TextField('subtitle')]);

		$this->assertSame(['name' => 'page', 'kind' => 'pages', 'folder' => '', 'fields' => [['name' => 'subtitle', 'type' => 'text']]], $pages->toArray());
		$this->assertEquals($pages, ContentType::fromArray($pages->toArray(), $this->fields));

		$authors = new Authors(folder: 'people', field: 'credits', aliases: ['by'], public: false);

		$this->assertSame(['name' => 'author', 'kind' => 'authors', 'folder' => 'people', 'public' => false, 'field' => 'credits', 'aliases' => ['by']], $authors->toArray());
		$this->assertEquals($authors, ContentType::fromArray($authors->toArray(), $this->fields));
	}

	public function testUrlsNameTheWordAuthorArchivesSitUnder(): void
	{
		$this->assertSame('authors/{author}', new TypeUrls()->path('authors.single'));
		$this->assertSame([], new TypeUrls()->toArray(), 'The default word is left out.');

		$writers = new TypeUrls(authors: '/writers/', paths: ['authors.single.paged' => 'writers/{author}/p/{page}']);

		$this->assertSame('writers', $writers->path('authors.collection'));
		$this->assertSame('writers/{author}/feed/json', $writers->path('authors.single.feed.json'));
		$this->assertSame(['paths' => ['authors.single.paged' => 'writers/{author}/p/{page}'], 'authors' => 'writers'], $writers->toArray());
		$this->assertEquals($writers, TypeUrls::fromArray($writers->toArray(), 'urls'));

		$none = TypeUrls::fromArray(['authors' => false], 'urls');

		$this->assertFalse($none->authors);
		$this->assertNull($none->path('authors.single'));
		$this->assertSame(['authors' => false], $none->toArray());
		$this->assertFalse(new Collection('post', urls: $none)->hasAuthorArchives());
		$this->assertTrue(new Collection('post')->hasAuthorArchives());
		$this->assertFalse(new Collection('post', authors: false)->hasAuthorArchives());
		$this->assertFalse(new Collection('post', public: false)->hasAuthorArchives());

		foreach ([['authors' => ''], ['authors' => 5]] as $data) {
			try {
				TypeUrls::fromArray($data, 'urls');
				$this->fail('An empty or non-string word.');
			} catch (InvalidSchema $e) {
				$this->assertStringContainsString('"authors" must be a word', $e->getMessage());
			}
		}
	}

	public function testCollectionsCreditAuthorsByDefault(): void
	{
		$this->assertTrue(new Collection('post')->authors);
		$this->assertFalse(new Taxonomy('tag')->authors);
		$this->assertFalse(new Pages()->authors);

		$cases = [
			[new Collection('post', authors: false), ['authors' => false]],
			[new Taxonomy('tag', authors: true), ['authors' => true]],
			[new Pages(authors: true), ['authors' => true]]
		];

		foreach ($cases as [$type, $expected]) {
			$this->assertSame($expected, array_intersect_key($type->toArray(), ['authors' => true]), $type->name);
			$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));
		}

		$this->assertArrayNotHasKey('authors', new Collection('post')->toArray(), 'The default is left out.');
		$this->assertFalse(ContentType::fromArray(['name' => 'tag', 'kind' => 'taxonomy'], $this->fields)->authors);
	}

	public function testNamesTypesForPeople(): void
	{
		$names = static fn (ContentType $type): array => [$type->labels->plural, $type->labels->singular];

		$this->assertSame(['Posts', 'Post'], $names(new Collection('post')));
		$this->assertSame(['Categories', 'Category'], $names(new Taxonomy('category')));
		$this->assertSame(['Literary forms', 'Literary form'], $names(new Taxonomy('literary_form')));
		$this->assertSame(['Classes', 'Class'], $names(new Collection('class')));
		$this->assertSame(['Essays', 'Essay'], $names(new Collection('essay')), 'A vowel before the y keeps it.');
		$this->assertSame(['Pages', 'Page'], $names(new Pages()));

		$type = ContentType::fromArray(['name' => 'person', 'labels' => ['plural' => 'People']], $this->fields);

		$this->assertSame(['People', 'Person'], $names($type));
		$this->assertSame(['plural' => 'People'], $type->toArray()['labels'] ?? null, 'Defaults are left out.');
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));
		$this->assertArrayNotHasKey('labels', new Collection('post')->toArray());

		$era = new Taxonomy('era', labels: new TypeLabels('Era of life', plural: 'Eras of life'));

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
			'newItem'     => 'New literary form',
			'editItem'    => 'Edit literary form',
			'searchItems' => 'Search literary forms'
		], new Taxonomy('literary_form')->labels->all());

		$person = new TypeLabels('Person', plural: 'People', newItem: 'Add someone');

		$this->assertSame(['people', 'Add someone', 'Edit person', 'Search people'], [$person->items, $person->newItem, $person->editItem, $person->searchItems]);
		$this->assertSame(['plural' => 'People', 'newItem' => 'Add someone'], $person->toArray('person'), 'Labels made from the ones before are left out.');
		$this->assertSame(['singular' => 'Person', 'plural' => 'People', 'newItem' => 'Add someone'], $person->toArray('human'), 'A singular the name doesn\'t make is kept.');
		$this->assertSame('Edit person', new TypeLabels('Person', editItem: '  ')->editItem, 'A blank label is its default.');

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
		$this->assertInstanceOf(Taxonomy::class, ContentType::fromArray(['name' => 'tag', 'kind' => 'taxonomy'], $this->fields));
		$this->assertInstanceOf(Taxonomy::class, ContentType::fromArray(['name' => 'tag', 'taxonomy' => true], $this->fields));
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'note', 'taxonomy' => false], $this->fields));

		$this->assertInstanceOf(Authors::class, ContentType::fromArray(['name' => 'person', 'kind' => 'authors'], $this->fields));

		$pages = ContentType::fromArray(['name' => 'page', 'kind' => 'pages'], $this->fields);

		$this->assertInstanceOf(Pages::class, $pages);
		$this->assertSame('', $pages->folder);
		$this->assertFalse($pages->hasUrls());
	}

	public function testReadsThe2xNames(): void
	{
		$type = ContentType::fromArray([
			'name'         => 'event',
			'folder'       => 'events',
			'urls'         => ['prefix' => 'on', 'single' => '{year}/{name}', 'collection' => 'all'],
			'listing'      => ['orderBy' => 'published', 'order' => 'desc', 'perPage' => 5, 'query' => ['offset' => 1]],
			'dateArchives' => 'hour',
			'fields'       => [['name' => 'venue', 'type' => 'text', 'required' => true]],
			'feed'         => ['categories' => 'topic', 'listing' => ['perPage' => 50]]
		], $this->fields);

		$this->assertSame('events', $type->folder);
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
		$this->assertSame('page', ContentType::fromArray(['name' => 'page', 'path' => '', 'collect' => false], $this->fields)->listedType());

		$taxonomy = ContentType::fromArray(['name' => 'tag', 'taxonomy' => true, 'field_aliases' => ['tags'], 'term_collect' => 'post'], $this->fields);

		$this->assertInstanceOf(Taxonomy::class, $taxonomy);
		$this->assertSame(['tags'], $taxonomy->aliases);
		$this->assertSame(['post'], $taxonomy->types);
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
			[['name' => 'post', 'folder' => 'a/../b'], 'Content type "post" has an invalid folder "a/../b".'],
			[['name' => 'post', 'routes' => []], 'Content type "post" (collection) has unknown options: routes.'],
			[['name' => 'post', 'types' => ['x']], 'Content type "post" (collection) has unknown options: types.'],
			[['name' => 'tag', 'kind' => 'taxonomy', 'dateArchives' => 'day'], 'Content type "tag" (taxonomy) has unknown options: dateArchives.'],
			[['name' => 'page', 'kind' => 'pages', 'urls' => []], 'Content type "page" (pages) has unknown options: urls.'],
			[['name' => 'post', 'kind' => 'blog'], 'Content type "post" "kind" must be one of collection, taxonomy, pages, authors.'],
			[['name' => 'post', 'kind' => 'collection', 'taxonomy' => true], 'Content type "post" sets "kind: collection" and "taxonomy: true".'],
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
			[['name' => 'post', 'fields' => [['name' => 'x', 'type' => 'nope']]], 'Field "x" has an unknown type "nope".']
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

	public function testListingRejectsTypedArgumentsInItsQuery(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('Listing "query" can\'t set number; use the Listing options instead.');

		(void) new Listing(query: ['number' => 5]);
	}

	public function testConfigRejectsDuplicatesAndDisablingPages(): void
	{
		$cases = [
			static fn (): ContentConfig => new ContentConfig(types: [new Collection('post'), new Collection('post')]),
			static fn (): ContentConfig => new ContentConfig(disabled: ['page']),
			static fn (): ContentConfig => new ContentConfig(disabled: ['nope']),
			static fn (): ContentConfig => ContentConfig::fromArray(['types' => 'post']),
			static fn (): ContentConfig => new ContentConfig(types: [new Collection('post')], definitions: [['name' => 'post']]),
			static fn (): ContentConfig => new ContentConfig(definitions: [['kind' => 'collection']])
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
		$config = ContentConfig::fromArray(['types' => JtcomTypes::definitions(), 'home' => 'post', 'dataTypeUrls' => false, 'disabled' => ['author']]);

		$this->assertEquals($config, ContentConfig::fromArray($config->toArray()));
	}

	public function testBuiltInTypes(): void
	{
		$page   = BuiltInType::Page->type();
		$author = BuiltInType::Author->type();

		$this->assertInstanceOf(Pages::class, $page);
		$this->assertSame('', $page->folder);
		$this->assertFalse($page->hasUrls());
		$this->assertFalse(BuiltInType::Page->canDisable());

		$this->assertInstanceOf(Authors::class, $author);
		$this->assertSame('authors', $author->folder);
		$this->assertFalse($author->hasUrls(), 'Author pages are archives under the types that credit them (D-329).');
		$this->assertFalse($author->hasFeed());
		$this->assertFalse($author->sitemap);
		$this->assertFalse($author->authors);
		$field = $author->termField();

		$this->assertSame('authors', $field->name);
		$this->assertSame(['author'], $field->aliases);
		$this->assertTrue(BuiltInType::Author->canDisable());
	}
}
