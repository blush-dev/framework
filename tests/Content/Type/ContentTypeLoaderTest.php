<?php

/**
 * Content type loader tests.
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
use Blush\Content\ContentServiceProvider;
use Blush\Content\EntryFields;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationOrigin;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Tree;
use Blush\Content\Type\TypeOrigin;
use Blush\Core\Application;
use Blush\Extension\DefinitionClash;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistry;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesContentConfig;
use Blush\Tests\Fixtures\Content\ColorField;
use Blush\Tests\Fixtures\Content\JtcomTypes;
use Blush\Tests\Fixtures\Content\MoreRecipeProvider;
use Blush\Tests\Fixtures\Content\MoreRecipeRelations;
use Blush\Tests\Fixtures\Content\MoreRecipeTypes;
use Blush\Tests\Fixtures\Content\RecipeProvider;
use Blush\Tests\Fixtures\Content\RecipeRelations;
use Blush\Tests\Fixtures\Content\RecipeTypes;

#[CoversClass(ContentTypeLoader::class)]
#[CoversClass(ContentTypes::class)]
#[CoversClass(TypeOrigin::class)]
#[CoversClass(EntryFields::class)]
#[CoversClass(ContentServiceProvider::class)]
final class ContentTypeLoaderTest extends TestCase
{
	use BootsScratchSite;
	use WritesContentConfig;

	private function types(?Application $application = null): ContentTypes
	{
		return ($application ?? $this->scratchApplication())->container()->make(ContentTypes::class);
	}

	public function testLoadsTheBuiltInTypesByDefault(): void
	{
		$types = $this->types();

		$this->assertSame(['page', 'profile'], array_keys($types->all()));
		$this->assertSame(TypeOrigin::BuiltIn, $types->origin('profile'));
		$this->assertSame([], $types->relations());
		$this->assertSame([], array_keys($types->classifications()));
		$this->assertSame('profile', $types->profiles()?->name);
		$this->assertSame(['profile'], array_keys($types->termTypes()));
		$this->assertNull($types->homeType());
		$this->assertCount(2, $types);
	}

	public function testDataTypesUseFieldTypesExtensionsRegister(): void
	{
		// Types are built when they load, after extensions register field
		// types (D-337).
		$this->contentConfig(['types' => ['swatch' => ['fields' => ['accent' => ['type' => 'color']]]]]);

		$application = $this->scratchApplication();
		$application->container()->make(FieldRegistry::class)->register('color', ColorField::class);

		$this->assertInstanceOf(ColorField::class, $this->types($application)->get('swatch')->schema->field('accent'));
	}

	public function testLoadsJtcomsTypesAndResolvesFiles(): void
	{
		$this->contentConfig(['types' => JtcomTypes::definitions(), 'relations' => JtcomTypes::relations(), 'home' => 'post']);

		$types = $this->types();

		$this->assertSame(TypeOrigin::Data, $types->origin('post'));
		$this->assertSame('post', $types->homeType()?->name);

		$cases = [
			'index.md'                                  => 'page',
			'about/biography.md'                        => 'page',
			'__drafts/2005-05-04.familial-obligations.md' => 'page',
			'_posts/2003-04-15.welcome-to-my-site.md'   => 'post',
			'_posts/hello/index.md'                     => 'post',
			'topics/art.md'                             => 'category',
			'eras/01.college.md'                        => 'era',
			'writing/2005-10-30.house-of-hypocrites.md' => 'literature',
			'writing/forms/essay.md'                    => 'literary_form',
			'/writing/techniques/prose.md'              => 'literary_technique',
			'profiles/justin.md'                        => 'profile'
		];

		foreach ($cases as $file => $type) {
			$this->assertSame($type, $types->forFile($file)->name, $file);
		}

		$this->assertSame('category', $types->byFolder('/topics/')?->name);
		$this->assertNull($types->byFolder('nowhere'));
	}

	public function testSchemasIncludeTheBuiltInAndTermFields(): void
	{
		$this->contentConfig(['types' => JtcomTypes::definitions(), 'relations' => JtcomTypes::relations()]);

		$types  = $this->types();
		$schema = $types->schema('post');

		foreach (['title', 'published', 'summary', 'category', 'era', 'authors'] as $field) {
			$this->assertTrue($schema->has($field), $field);
		}

		$this->assertFalse($schema->has('literary_form'), 'A classify relation adds its field to the types it\'s from (D-593).');
		$this->assertTrue($types->schema('literature')->has('literary_form'));

		$this->assertSame('authors', $schema->field('author')?->name);
		$this->assertSame('published', $schema->field('date')?->name);
		$this->assertSame($schema, $types->schema('post'));
		$this->assertFalse($types->schema('page')->has('authors'), 'Only the types with people fields credit people (D-351).');
		$this->assertFalse($types->schema('category')->has('authors'));
		$this->assertFalse($types->schema('profile')->has('authors'));
		$this->assertTrue($types->schema('profile')->has('avatar'));
	}

	public function testExtensionsAddTypesAndRelations(): void
	{
		$application = $this->scratchApplication();
		$application->register(RecipeProvider::class);

		$types = $this->types($application);

		$this->assertSame(TypeOrigin::Extension, $types->origin('recipe'));
		$this->assertSame(TypeOrigin::Extension, $types->origin('ingredient'));
		$this->assertSame('_ingredient', $types->get('ingredient')->folder);
		$this->assertSame(RelationOrigin::Extension, $types->relationOrigin('ingredient'));
		$this->assertSame(['recipe'], $types->classification('ingredient')?->from);
	}

	public function testTwoExtensionsDefiningOneNameKeepTheFirst(): void
	{
		$application = $this->scratchApplication();
		$application->register(RecipeProvider::class);
		$application->register(MoreRecipeProvider::class);

		$types = $this->types($application);

		$this->assertSame('recipes', $types->get('recipe')->folder, 'The first extension\'s type is kept, and the site loads (D-597).');
		$this->assertSame(RelationKind::Classify, $types->relations()['ingredient']->kind);
		$this->assertEquals([
			new DefinitionClash('type', 'recipe', RecipeTypes::class, MoreRecipeTypes::class),
			new DefinitionClash('relation', 'ingredient', RecipeRelations::class, MoreRecipeRelations::class)
		], $types->clashes);
		$this->assertEquals($types->clashes, ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class))->clashes, 'Kept in the compiled types.');
	}

	public function testDisablesBuiltInTypes(): void
	{
		$this->contentConfig(['disabled' => ['profile']]);

		$this->assertFalse($this->types()->has('profile'));
	}

	public function testLoadsDataTypes(): void
	{
		$this->writeTemporaryFile('user/data/types/movie.json', '{"path": "movies", "fields": [{"name": "rating", "type": "number"}]}');
		$this->writeTemporaryFile('user/data/types/profile.json', '{"path": "people", "kind": "profiles"}');

		$types = $this->types();

		$this->assertSame(TypeOrigin::Data, $types->origin('movie'));
		$this->assertTrue(TypeOrigin::Data->isEditable());
		$this->assertSame('number', $types->get('movie')->schema->fields['rating']->type());
		$this->assertSame('people', $types->get('profile')->folder);
		$this->assertSame(TypeOrigin::Data, $types->origin('profile'));
	}

	public function testDataFilesChangeCodeCollections(): void
	{
		$this->codeConfig(['types' => ['movie' => ['path' => 'movies', 'routing' => ['prefix' => 'films', 'single' => '{year}/{name}'], 'feed' => ['listing' => ['perPage' => 5]], 'description' => 'Films.'], 'genre' => ['order' => 'position']]]);
		$this->writeTemporaryFile('user/data/types/movie.json', '{"description": "Movies we watched.", "routing": {"prefix": "watched"}}');
		$this->writeTemporaryFile('user/data/types/genre.json', '{"hierarchical": true}');

		$types = $this->types();
		$movie = $types->get('movie');

		$this->assertSame(TypeOrigin::Extension, $types->origin('movie'), 'It\'s still the plugin\'s type.');
		$this->assertTrue($types->isOverridden('movie'));
		$this->assertTrue($types->isEditable('movie'));
		$this->assertFalse($types->isOverridden('page'));
		$this->assertSame(['movies', 'Movies we watched.', 'watched'], [$movie->folder, $movie->description, $movie->prefix()]);
		$this->assertSame('{name}', $movie->urls === false ? null : $movie->urls->path('single'), 'An option it sets replaces the code\'s whole option (D-349).');
		$this->assertSame(5, $movie->feed === false ? null : $movie->feed->listing?->perPage, 'Options it doesn\'t set stay the code\'s.');
		$this->assertTrue($types->nestsByParent('genre'));
	}

	public function testDataFilesCantChangeACodeTypesKindOrFolder(): void
	{
		$cases = [
			'{"kind": "taxonomy"}' => 'Content type "movie" is a taxonomy, which Blush no longer has',
			'{"taxonomy": true}'   => 'Content type "movie" is a taxonomy, which Blush no longer has',
			'{"kind": "tree"}'     => 'user/data/types/movie can\'t change the type\'s kind or folder',
			'{"path": "films"}'    => 'user/data/types/movie can\'t change the type\'s kind or folder'
		];

		$this->codeConfig(['types' => ['movie' => ['path' => 'movies']]]);

		foreach ($cases as $json => $message) {
			$this->writeTemporaryFile('user/data/types/movie.json', $json);

			try {
				$this->types();
				$this->fail("Expected: {$message}");
			} catch (InvalidContentType $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}

		$this->writeTemporaryFile('user/data/types/movie.json', '{"folder": "movies/", "kind": "collection"}');
		$this->assertTrue($this->types()->isOverridden('movie'), 'Its own kind and folder are fine.');
	}

	public function testDataFilesDefineTrees(): void
	{
		$this->writeTemporaryFile('user/data/types/doc.json', '{"kind": "tree", "icon": "book"}');
		$doc = $this->types()->get('doc');

		$this->assertInstanceOf(Tree::class, $doc);
		$this->assertSame('_doc', $doc->folder, 'Only the page type sits at the content root (D-386).');
		$this->assertFalse($doc->atRoot());
		$this->assertTrue($this->types()->get('page') instanceof Tree && $this->types()->get('page')->atRoot());
	}

	public function testDataFilesCantChangeTheCodesPagesType(): void
	{
		$this->codeConfig(['types' => ['page' => ['kind' => 'tree', 'description' => 'Pages.']]]);
		$this->writeTemporaryFile('user/data/types/page.json', '{"description": "Mine."}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('The "page" content type is the site\'s pages, which user/data/types can\'t change; define it in one place.');

		$this->types();
	}

	public function testDataTypesCanBeTurnedOffOrRestricted(): void
	{
		$this->writeTemporaryFile('user/data/types/movie.json', '{"routing": {"prefix": "films"}}');
		$this->contentConfig(['dataTypes' => false]);

		$this->assertFalse($this->types()->has('movie'));

		$this->contentConfig(['dataTypeUrls' => false]);

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('The "movie" data type sets "routing", which ContentConfig "dataTypeUrls" doesn\'t allow.');

		$this->types();
	}

	public function testDataTypesAreNamedAfterTheirFiles(): void
	{
		$this->writeTemporaryFile('user/data/types/movie.json', '{"name": "film"}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/types/movie names the type "film"');

		$this->types();
	}

	public function testBrokenDataFilesAreContentTypeErrors(): void
	{
		$this->writeTemporaryFile('user/data/types/movie.json', '{');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('Invalid JSON');

		$this->types();
	}

	public function testChecksThatTypesFitTogether(): void
	{
		$cases = [
			[['types' => ['post' => ['path' => 'profiles']]], 'The "profile" and "post" content types share the folder "profiles".'],
			[['types' => ['post' => ['collect' => 'nope']]], 'Content type "post" listing type names "nope", which doesn\'t exist.'],
			[['types' => ['tag' => []], 'relations' => ['tag' => ['kind' => 'classify', 'from' => ['nope'], 'to' => ['tag']]]], 'Relation "tag" names a "nope" content type, which doesn\'t exist.'],
			[['relations' => ['tag' => ['kind' => 'classify', 'to' => ['tag']]]], 'Relation "tag" names a "tag" content type, which doesn\'t exist.'],
			[['types' => ['tag' => []], 'relations' => ['tag' => ['kind' => 'classify', 'to' => ['tag'], 'inverse' => ['listing' => ['type' => 'nope']]]]], 'Relation "tag" inverse listing type names "nope"'],
			[['types' => ['post' => ['feed' => ['taxonomy' => 'nope']]]], 'Content type "post" feed categories names "nope"'],
			[['types' => ['post' => ['feed' => ['taxonomy' => 'page']]]], 'Content type "post" feed categories "page" isn\'t a type a classify relation files entries under.'],
			[['types' => ['page' => ['path' => 'pages']]], 'No content type claims the content root'],
			[['home' => 'post'], 'ContentConfig "home" names "post", which isn\'t a content type.'],
			[['types' => ['title' => []], 'relations' => ['title' => ['kind' => 'classify', 'to' => ['title']]]], 'Content type "page" has clashing fields: Schema key "title"'],
			[['types' => ['post' => ['fields' => ['refs' => ['type' => 'text']]]]], 'Content type "post" has a field (or alias) named "refs", which is reserved'],
			[['types' => ['person' => ['kind' => 'profiles']]], 'A site has one profiles type, but "profile", "person" are all profiles types.'],
			[['types' => ['post' => ['fields' => ['id' => ['type' => 'text']]]]], 'Content type "post" has a field (or alias) named "id", which is reserved for the entry\'s id; rename it.'],
			[['types' => ['post' => ['fields' => ['code' => ['type' => 'text', 'aliases' => ['id']]]]]], 'Content type "post" has a field (or alias) named "id"']
		];

		foreach ($cases as [$config, $message]) {
			$this->clearContentConfig();
			$this->contentConfig($config);

			try {
				$this->types();
				$this->fail($message);
			} catch (InvalidContentType $e) {
				$this->assertStringStartsWith($message, $e->getMessage());
			}
		}
	}

	public function testUnknownTypesAndFileRootsAreErrors(): void
	{
		$types = new ContentTypes([]);

		$this->assertNull($types->find('post'));

		try {
			$types->forFile('a.md');
			$this->fail('No type claims the root.');
		} catch (InvalidContentType $e) {
			$this->assertSame('No content type claims the content root.', $e->getMessage());
		}

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('There is no "post" content type.');

		$types->get('post');
	}

	public function testRoundTripsThroughArrays(): void
	{
		$this->contentConfig(['types' => JtcomTypes::definitions(), 'relations' => JtcomTypes::relations(), 'home' => 'post']);

		$application = $this->scratchApplication();
		$types       = $this->types($application);
		$rebuilt     = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class));

		$this->assertEquals($types->all(), $rebuilt->all());
		$this->assertSame(TypeOrigin::BuiltIn, $rebuilt->origin('page'));
		$this->assertSame('post', $rebuilt->home);
		$this->assertFalse($rebuilt->isOverridden('post'));
	}

	public function testRoundTripsOverrides(): void
	{
		$this->codeConfig(['types' => ['movie' => []]]);
		$this->writeTemporaryFile('user/data/types/movie.json', '{"description": "Films."}');

		$application = $this->scratchApplication();
		$rebuilt     = ContentTypes::fromArray($this->types($application)->toArray(), $application->container()->make(FieldFactory::class));

		$this->assertTrue($rebuilt->isOverridden('movie'));
		$this->assertSame('Films.', $rebuilt->get('movie')->description);
	}
}
