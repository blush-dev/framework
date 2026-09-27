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
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\FieldRegistrar;
use Blush\Content\Schema\FieldRegistry;
use Blush\Content\Schema\Fields\TextField;
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
use Blush\Content\Type\TypeUrls;
use Blush\Tests\Fixtures\Content\JtcomTypes;

#[CoversClass(ContentType::class)]
#[CoversClass(Collection::class)]
#[CoversClass(Taxonomy::class)]
#[CoversClass(Pages::class)]
#[CoversClass(TypeKind::class)]
#[CoversClass(ContentConfig::class)]
#[CoversClass(TypeUrls::class)]
#[CoversClass(TypeFeed::class)]
#[CoversClass(Listing::class)]
#[CoversClass(DateArchives::class)]
#[CoversClass(BuiltInType::class)]
#[CoversClass(InvalidContentType::class)]
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

		$this->assertSame('project', $type->folder);
		$this->assertSame('project', $type->prefix());
		$this->assertSame('project', $type->listedType());
		$this->assertSame(['type' => 'project'], $type->listingArguments());
		$this->assertTrue($type->hasUrls());
		$this->assertFalse($type->hasFeed());
		$this->assertNull($type->termField());
		$this->assertSame('{name}', $type->urls === false ? null : $type->urls->path('single'));
		$this->assertSame(['name' => 'project', 'kind' => 'collection'], $type->toArray());
		$this->assertSame('project', new Taxonomy('project')->field);
	}

	public function testAcceptsJtcoms1xConfigUnchanged(): void
	{
		$config = ContentConfig::fromArray(['types' => JtcomTypes::definitions(), 'home' => 'post']);
		$types  = array_column(array_map(static fn (ContentType $type): array => ['name' => $type->name, 'type' => $type], $config->types), 'type', 'name');

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
	}

	public function testReadsKinds(): void
	{
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'note'], $this->fields));
		$this->assertInstanceOf(Taxonomy::class, ContentType::fromArray(['name' => 'tag', 'kind' => 'taxonomy'], $this->fields));
		$this->assertInstanceOf(Taxonomy::class, ContentType::fromArray(['name' => 'tag', 'taxonomy' => true], $this->fields));
		$this->assertInstanceOf(Collection::class, ContentType::fromArray(['name' => 'note', 'taxonomy' => false], $this->fields));

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
			[['name' => 'post', 'kind' => 'blog'], 'Content type "post" "kind" must be one of collection, taxonomy, pages.'],
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
			static fn (): ContentConfig => ContentConfig::fromArray(['types' => ['post' => ['routing' => 'x']]])
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

		$this->assertInstanceOf(Taxonomy::class, $author);
		$this->assertSame('authors', $author->folder);
		$field = $author->termField();

		$this->assertSame('authors', $field->name);
		$this->assertSame(['author'], $field->aliases);
		$this->assertTrue(BuiltInType::Author->canDisable());
	}
}
