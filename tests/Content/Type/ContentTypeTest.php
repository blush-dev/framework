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
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\FieldRegistrar;
use Blush\Content\Schema\FieldRegistry;
use Blush\Content\Schema\Fields\TextField;
use Blush\Content\Schema\Schema;
use Blush\Content\Type\ArchiveGranularity;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\TypeFeed;
use Blush\Content\Type\TypeRouting;
use Blush\Tests\Fixtures\Content\JtcomTypes;

#[CoversClass(ContentType::class)]
#[CoversClass(ContentConfig::class)]
#[CoversClass(TypeRouting::class)]
#[CoversClass(TypeFeed::class)]
#[CoversClass(ArchiveGranularity::class)]
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
		$type = new ContentType('project');

		$this->assertSame('project', $type->path);
		$this->assertSame('project', $type->prefix());
		$this->assertSame('project', $type->collect);
		$this->assertSame('project', $type->field);
		$this->assertTrue($type->hasRouting());
		$this->assertFalse($type->hasFeed());
		$this->assertNull($type->termField());
		$this->assertSame('{name}', $type->routing === false ? null : $type->routing->path('single'));
		$this->assertSame(['name' => 'project'], $type->toArray());
	}

	public function testAcceptsJtcoms1xConfigUnchanged(): void
	{
		$config = ContentConfig::fromArray(['types' => JtcomTypes::definitions(), 'home' => 'post']);
		$types  = array_column(array_map(static fn (ContentType $type): array => ['name' => $type->name, 'type' => $type], $config->types), 'type', 'name');

		$post = $types['post'];

		$this->assertSame('_posts', $post->path);
		$this->assertSame('archives', $post->prefix());
		$this->assertSame('{year}/{month}/{day}/{name}', $post->routing === false ? null : $post->routing->path('single'));
		$this->assertSame('page/{page}', $post->routing === false ? null : $post->routing->path('collection.paged'));
		$this->assertSame(['order' => 'desc'], $post->collection);
		$this->assertSame(ArchiveGranularity::Day, $post->archives);
		$this->assertEquals(new TypeFeed(taxonomy: 'category'), $post->feed);

		$form = $types['literary_form'];

		$this->assertTrue($form->taxonomy);
		$this->assertSame('writing/forms', $form->path);
		$this->assertSame('literature', $form->termCollect);
		$this->assertSame(['order' => 'desc', 'number' => 9999], $form->termCollection);
		$this->assertSame('literary_form', $form->termField()?->name);
		$this->assertSame('post', $config->home);
	}

	public function testRoundTripsThroughArrays(): void
	{
		$type = new ContentType(
			'post',
			path: '_posts',
			public: false,
			routing: new TypeRouting('/archives/', ['single' => '/{year}/{name}/']),
			collection: ['order' => 'desc'],
			collect: 'post',
			feed: new TypeFeed('category', ['number' => 20]),
			sitemap: false,
			archives: ArchiveGranularity::Month,
			schema: new Schema([new TextField('subtitle')], closed: true)
		);

		$this->assertSame([
			'name'       => 'post',
			'path'       => '_posts',
			'public'     => false,
			'routing'    => ['prefix' => 'archives', 'paths' => ['single' => '{year}/{name}']],
			'collection' => ['order' => 'desc'],
			'feed'       => ['taxonomy' => 'category', 'collection' => ['number' => 20]],
			'sitemap'    => false,
			'archives'   => 'month',
			'fields'     => [['name' => 'subtitle', 'type' => 'text']],
			'closed'     => true
		], $type->toArray());
		$this->assertEquals($type, ContentType::fromArray($type->toArray(), $this->fields));

		$taxonomy = new ContentType('author', path: 'authors', routing: false, taxonomy: true, field: 'authors', fieldAliases: ['author'], collect: false, feed: new TypeFeed());

		$this->assertEquals($taxonomy, ContentType::fromArray($taxonomy->toArray(), $this->fields));
		$this->assertSame(true, $taxonomy->toArray()['feed']);
		$this->assertSame(['author'], $taxonomy->termField()?->aliases);
	}

	public function testReadsFieldsAndArchiveSettings(): void
	{
		$type = ContentType::fromArray([
			'name'          => 'event',
			'archives'      => 'hour',
			'time_archives' => false,
			'fields'        => [['name' => 'venue', 'type' => 'text', 'required' => true]],
			'feed'          => true
		], $this->fields);

		$this->assertSame(ArchiveGranularity::Hour, $type->archives);
		$this->assertTrue($type->schema->fields['venue']->required);
		$this->assertEquals(new TypeFeed(), $type->feed);
		$this->assertSame(ArchiveGranularity::Second, ContentType::fromArray(['name' => 'log', 'time_archives' => true], $this->fields)->archives);
	}

	public function testArchiveLevels(): void
	{
		$this->assertSame([], ArchiveGranularity::None->levels());
		$this->assertSame([ArchiveGranularity::Year], ArchiveGranularity::Year->levels());
		$this->assertSame([ArchiveGranularity::Year, ArchiveGranularity::Month, ArchiveGranularity::Day], ArchiveGranularity::Day->levels());
		$this->assertCount(6, ArchiveGranularity::Second->levels());
	}

	public function testRejectsBadDefinitions(): void
	{
		$cases = [
			[['path' => 'x'], 'A content type definition needs a "name".'],
			[['name' => 'Post'], 'Content type name "Post" must start with a lowercase letter'],
			[['name' => 'post', 'path' => 'a/../b'], 'Content type "post" has an invalid path "a/../b".'],
			[['name' => 'post', 'routes' => []], 'Content type "post" has unknown options: routes.'],
			[['name' => 'post', 'routing' => 'yes'], 'Content type "post" "routing" must be false or a map.'],
			[['name' => 'post', 'feed' => 'yes'], 'Content type "post" "feed" must be true, false, or a map.'],
			[['name' => 'post', 'archives' => 'weekly'], 'Content type "post" "archives" must be one of none, year'],
			[['name' => 'post', 'collect' => 5], 'Content type "post" "collect" must be a type name or false.'],
			[['name' => 'post', 'collection' => ['a']], 'Content type "post" "collection" must be a map.'],
			[['name' => 'post', 'collection' => ['a', 'x' => 1]], 'Query arguments must be keyed by name.'],
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

	public function testConfigRejectsDuplicatesAndDisablingPages(): void
	{
		$cases = [
			static fn (): ContentConfig => new ContentConfig(types: [new ContentType('post'), new ContentType('post')]),
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
		$config = ContentConfig::fromArray(['types' => JtcomTypes::definitions(), 'home' => 'post', 'dataTypeRouting' => false, 'disabled' => ['author']]);

		$this->assertEquals($config, ContentConfig::fromArray($config->toArray()));
	}

	public function testBuiltInTypes(): void
	{
		$page   = BuiltInType::Page->type();
		$author = BuiltInType::Author->type();

		$this->assertSame('', $page->path);
		$this->assertFalse($page->hasRouting());
		$this->assertFalse($page->collect);
		$this->assertFalse(BuiltInType::Page->canDisable());

		$this->assertSame('authors', $author->path);
		$this->assertTrue($author->taxonomy);
		$field = $author->termField();

		$this->assertNotNull($field);
		$this->assertSame('authors', $field->name);
		$this->assertSame(['author'], $field->aliases);
		$this->assertTrue(BuiltInType::Author->canDisable());
	}
}
