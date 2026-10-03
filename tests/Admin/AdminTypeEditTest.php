<?php

/**
 * Admin content type editing tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\TypeEditController;
use Blush\Admin\TypesController;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DataTypeWriter;

#[CoversClass(TypeEditController::class)]
#[CoversClass(TypesController::class)]
#[CoversClass(DataTypeWriter::class)]
final class AdminTypeEditTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	private static function error(ResponseInterface $response): string
	{
		$error = self::json($response)['error'] ?? '';

		return is_string($error) ? $error : '';
	}

	private function file(string $relative): string
	{
		return (string) @file_get_contents($this->temporaryDirectory() . "/{$relative}");
	}

	public function testSetsWhetherATypeCreditsAuthorsAndWhere(): void
	{
		$this->site();

		$answer = $this->write('POST', '/types', ['name' => 'recipe', 'kind' => 'collection', 'authorsPage' => true, 'set' => ['labels' => ['singular' => 'Recipe', 'plural' => 'Recipes'], 'authors' => true, 'authorsWord' => 'cooks']]);

		$this->assertSame(201, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertSame([true, 'cooks', ['id' => '_recipe/_authors.md', 'title' => 'Authors']], [$type['authors'] ?? null, $type['authorsWord'] ?? null, $type['authorsPage'] ?? null]);
		$this->assertSame("people:\n  authors: { archive: cooks }\n", $this->file('user/data/types/recipe.yaml'), 'Only the word differs from a collection\'s default (D-351).');
		$this->assertSame("---\ntitle: \"Authors\"\n---\n", $this->file('user/content/_recipe/_authors.md'));

		$this->assertSame('authors', self::json($this->write('PATCH', '/types/recipe', ['set' => ['authorsWord' => null]]))['authorsWord'] ?? null);
		$this->assertStringNotContainsString('people', $this->file('user/data/types/recipe.yaml'), 'The default is left out.');

		$off = $this->write('PATCH', '/types/recipe', ['set' => ['authorsWord' => false]]);
		$this->assertSame(200, $off->getStatusCode(), (string) $off->getBody());
		$this->assertFalse(self::json($off)['authorsWord'] ?? null);
		$this->assertSame("people:\n  authors: { archive: false }\n", $this->file('user/data/types/recipe.yaml'));

		$this->assertFalse(self::json($this->write('PATCH', '/types/recipe', ['set' => ['authors' => false]]))['authors'] ?? null);
		$this->assertStringContainsString("people: false\n", $this->file('user/data/types/recipe.yaml'));

		$refused = $this->write('PATCH', '/types/recipe', ['set' => [], 'authorsPage' => true]);
		$this->assertSame(422, $refused->getStatusCode(), 'No author archives, no authors page.');
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['authorsWord' => 5]])->getStatusCode());
	}

	public function testEditsATypesPeopleFields(): void
	{
		$this->site();
		$this->assertSame(201, $this->write('POST', '/types', ['name' => 'recipe', 'kind' => 'collection', 'set' => ['labels' => ['singular' => 'Recipe', 'plural' => 'Recipes']]])->getStatusCode());

		$saved = $this->write('PATCH', '/types/recipe', ['set' => ['people' => [
			'cooks'         => ['plural' => 'Cooks', 'singular' => 'Cook', 'aliases' => [], 'archive' => 'cooks', 'multiple' => true, 'required' => true],
			'photographers' => ['plural' => 'Photographers', 'singular' => 'Photographer', 'aliases' => ['photographer'], 'archive' => false, 'multiple' => false, 'required' => false]
		]], 'listPages' => ['cooks']]);

		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$type = self::json($saved);

		$this->assertSame(['cooks', 'photographers'], array_column(is_array($type['people'] ?? null) ? $type['people'] : [], 'field'));
		$people = is_array($type['people'] ?? null) ? $type['people'] : [];

		$this->assertSame(['field' => 'cooks', 'plural' => 'Cooks', 'singular' => 'Cook', 'aliases' => [], 'archive' => 'cooks', 'multiple' => true, 'required' => true, 'listPage' => ['id' => '_recipe/_cooks.md', 'title' => 'Cooks']], $people[0] ?? null);
		$this->assertFalse(is_array($people[1] ?? null) ? $people[1]['archive'] ?? null : null);
		$this->assertSame("people:\n  cooks: { required: true }\n  photographers: { aliases: [photographer], archive: false, multiple: false }\n", $this->file('user/data/types/recipe.yaml'), 'Only what differs from each field\'s defaults (D-353).');
		$this->assertSame("---\ntitle: \"Cooks\"\n---\n", $this->file('user/content/_recipe/_cooks.md'));
		$keys = array_column(is_array($type['routes'] ?? null) ? $type['routes'] : [], 'key');

		$this->assertContains('cooks.single', $keys);
		$this->assertNotContains('photographers.single', $keys, 'No archives, no routes.');
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => [], 'listPages' => ['photographers']])->getStatusCode(), 'No archives, no list page.');
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['people' => ['single' => true]]])->getStatusCode());

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['people' => false]])->getStatusCode());
		$this->assertSame("people: false\n", $this->file('user/data/types/recipe.yaml'), 'A collection that credits no one.');
	}

	public function testCreatesATypeWithFieldsAndAnIndexPage(): void
	{
		$this->site();

		$answer = $this->write('POST', '/types', [
			'name'   => 'recipe',
			'kind'   => 'collection',
			'folder' => 'recipes',
			'index'  => true,
			'set'    => [
				'labels'      => ['singular' => 'Recipe', 'plural' => 'Recipes', 'newItem' => 'Add a recipe'],
				'description' => 'Dishes we cook at home.',
				'icon'        => 'book-open',
				'public'      => true,
				'fields'      => [
					['name' => 'servings', 'type' => 'number', 'integer' => true, 'required' => true],
					['name' => 'difficulty', 'type' => 'enum', 'options' => ['easy', 'hard'], 'default' => 'easy'],
					['name' => 'image', 'type' => 'media', 'label' => 'Featured image']
				]
			]
		]);

		$this->assertSame(201, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertTrue($type['editable'] ?? null);
		$this->assertSame('user/data/types/recipe.yaml', $type['file'] ?? null);
		$this->assertSame(['id' => 'recipes/index.md', 'title' => 'Recipes'], $type['index'] ?? null);
		$this->assertSame(
			"folder: recipes\nlabels:\n  newItem: 'Add a recipe'\ndescription: \"Dishes we cook at home.\"\nicon: book-open\nfields:\n"
			. "  - name: servings\n    type: number\n    required: true\n    integer: true\n"
			. "  - name: difficulty\n    type: enum\n    default: easy\n    options: [easy, hard]\n"
			. "  - name: image\n    type: media\n    label: 'Featured image'\n",
			$this->file('user/data/types/recipe.yaml'),
			'Defaults (the singular and plural a recipe type gets, public) are left out.'
		);
		$this->assertSame("---\ntitle: \"Recipes\"\n---\n", $this->file('user/content/recipes/index.md'));

		$this->assertSame(200, $this->write('POST', '/types/refresh')->getStatusCode());

		$next = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test']);
		$next->boot();
		$this->assertTrue($next->container()->make(ContentTypes::class)->has('recipe'), 'The next request has it.');
	}

	public function testEditsOnlyWhatChangedAndKeepsTheRest(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "# Recipes, by the book.\nfolder: recipes   # where they live\nrouting:\n  single: 'r/{name}'\nlisting: { orderBy: title }\n");
		$this->site();

		$answer = $this->write('PATCH', '/types/recipe', ['set' => ['description' => 'Food.', 'prefix' => 'cook', 'hierarchical' => null]]);

		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$this->assertSame('/cook', self::json($answer)['prefix'] ?? null);
		$this->assertSame(
			"# Recipes, by the book.\nfolder: recipes   # where they live\nlisting: { orderBy: title }\ndescription: Food.\nurls:\n  prefix: cook\n  paths: { single: 'r/{name}' }\n",
			$this->file('user/data/types/recipe.yaml'),
			'routing becomes urls; comments and other keys stay.'
		);

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['prefix' => 'recipes']])->getStatusCode());
		$this->assertStringNotContainsString('prefix', $this->file('user/data/types/recipe.yaml'), 'A prefix the folder gives is left out.');
	}

	public function testRewritesCompiledTypes(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\n");
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production']);
		$this->login();
		$cache = $this->app->container()->make(ContentTypeCache::class);
		$cache->write();

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['description' => 'Compiled.']])->getStatusCode());
		$this->assertStringContainsString('Compiled.', $this->file(substr($cache->path(), strlen($this->temporaryDirectory()) + 1)));
	}

	public function testEditsAJsonType(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"kind": "taxonomy", "folder": "topics", "termListing": {"perPage": 5}}');
		$this->site();

		$this->assertSame(200, $this->write('PATCH', '/types/topic', ['set' => ['hierarchical' => true, 'labels' => ['plural' => 'Subjects']]])->getStatusCode());
		$this->assertSame(
			['kind' => 'taxonomy', 'folder' => 'topics', 'termListing' => ['perPage' => 5], 'hierarchical' => true, 'labels' => ['plural' => 'Subjects']],
			json_decode($this->file('user/data/types/topic.json'), true)
		);
	}

	public function testRefusesWhatDoesNotFitAndWritesNothing(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\n");
		$this->site();

		$badField = $this->write('PATCH', '/types/recipe', ['set' => ['fields' => [['name' => 'x', 'type' => 'colour']]]]);
		$this->assertSame(422, $badField->getStatusCode());
		$this->assertStringContainsString('colour', self::error($badField));
		$this->assertSame("folder: recipes\n", $this->file('user/data/types/recipe.yaml'));

		$clash = $this->write('POST', '/types', ['name' => 'dish', 'folder' => 'recipes']);
		$this->assertSame(422, $clash->getStatusCode(), 'Two types can\'t share a folder.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/dish.yaml', 'The new file is taken back.');

		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'recipe'])->getStatusCode(), 'The name is taken.');
		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'Bad Name'])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])->getStatusCode(), 'Built-in types aren\'t data types.');
		$this->assertSame(400, $this->write('PATCH', '/types/recipe', ['set' => ['a', 'b']])->getStatusCode());
	}

	public function testDeletesATypeButNotOneOthersNeed(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\n");
		$this->writeTemporaryFile('user/data/types/cuisine.yaml', "kind: taxonomy\nfolder: cuisines\ntypes: [recipe]\n");
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\n---\n");
		$this->site();

		$needed = $this->write('DELETE', '/types/recipe');
		$this->assertSame(422, $needed->getStatusCode());
		$this->assertSame('Cuisines groups it; take it out of their groups first.', self::error($needed));
		$this->assertFileExists($this->temporaryDirectory() . '/user/data/types/recipe.yaml', 'It\'s put back.');

		$this->assertSame(200, $this->write('DELETE', '/types/cuisine')->getStatusCode());
		$this->assertSame(200, $this->write('DELETE', '/types/recipe')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/recipe.yaml');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/recipes/soup.md', 'Entries stay.');
	}

	public function testTheListSaysWhetherTypesCanBeCreated(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Content\\Type\\ContentConfig(dataTypes: false);\n");
		$this->site();

		$this->assertFalse(self::json($this->send('GET', '/types'))['create'] ?? null);
		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'recipe'])->getStatusCode());
	}

	/**
	 * A type's routes (D-350), keyed by route key.
	 *
	 * @return array<string, array<mixed>>
	 */
	private function routes(string $name): array
	{
		$routes = self::json($this->send('GET', "/types/{$name}"))['routes'] ?? null;
		$keyed  = [];

		foreach (is_array($routes) ? $routes : [] as $route) {
			if (is_array($route) && is_string($route['key'] ?? null)) {
				$keyed[$route['key']] = $route;
			}
		}

		return $keyed;
	}

	public function testCreatesATree(): void
	{
		$this->site();

		$answer = $this->write('POST', '/types', [
			'name'   => 'doc',
			'kind'   => 'tree',
			'folder' => '_docs',
			'index'  => true,
			'set'    => ['labels' => ['singular' => 'Doc', 'plural' => 'Docs'], 'icon' => 'book', 'public' => true, 'sitemap' => true, 'authors' => false]
		]);

		$this->assertSame(201, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertSame(['tree', true, ['id' => '_docs/index.md', 'title' => 'Docs']], [$type['kind'] ?? null, $type['editable'] ?? null, $type['index'] ?? null]);
		$this->assertSame("kind: tree\nfolder: _docs\nicon: book\n", $this->file('user/data/types/doc.yaml'));

		$feed = $this->write('PATCH', '/types/doc', ['set' => ['feed' => true]]);
		$this->assertSame(422, $feed->getStatusCode(), 'A tree has no feed.');
		$this->assertStringContainsString('unknown options: feed', self::error($feed));

		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'person', 'kind' => 'profiles', 'set' => []])->getStatusCode(), 'The site has one profiles type.');
	}

	private function contentConfig(string $source): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray({$source});\n");
	}

	public function testChangesACodeTypeInADataFile(): void
	{
		$this->contentConfig("['types' => ['movie' => ['folder' => 'movies', 'urls' => ['prefix' => 'films'], 'description' => 'Films.', 'feed' => true, 'fields' => [['name' => 'rating', 'type' => 'number']]]]]");
		$this->site();

		$before = self::json($this->send('GET', '/types/movie'));
		$this->assertSame([true, false, null, [], true], [$before['editable'] ?? null, $before['overridden'] ?? null, $before['file'] ?? null, $before['overrides'] ?? null, $before['fieldsEditable'] ?? null]);

		$answer = $this->write('PATCH', '/types/movie', ['set' => ['description' => 'Movies.', 'feed' => false]]);
		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertSame(['config', true, ['description', 'feed'], 'user/data/types/movie.yaml', false], [$type['origin'] ?? null, $type['overridden'] ?? null, $type['overrides'] ?? null, $type['file'] ?? null, $type['feed'] ?? null]);
		$this->assertSame("description: Movies.\nfeed: false\n", $this->file('user/data/types/movie.yaml'), 'Only what differs from the code, with a default the code doesn\'t have written out (D-349).');
		$this->assertSame('/films', $type['prefix'] ?? null);

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['fields' => [['name' => 'rating', 'type' => 'number'], ['name' => 'year', 'type' => 'number']]]])->getStatusCode());
		$this->assertStringContainsString("fields:\n  - name: rating\n", $this->file('user/data/types/movie.yaml'), 'Fields are the whole list.');

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['description' => 'Films.', 'feed' => true, 'fields' => [['name' => 'rating', 'type' => 'number']]]])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/movie.yaml', 'Back at the code\'s values, the file goes.');

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['labels' => ['plural' => 'Pictures']]])->getStatusCode());
		$reset = $this->write('POST', '/types/movie/reset');
		$this->assertSame(200, $reset->getStatusCode(), (string) $reset->getBody());
		$reverted = self::json($reset);
		$this->assertSame(['Movies', false], [is_array($reverted['labels'] ?? null) ? $reverted['labels']['plural'] ?? null : null, $reverted['overridden'] ?? null]);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/movie.yaml');

		$this->assertSame(422, $this->write('POST', '/types/movie/reset')->getStatusCode(), 'Nothing to reset.');
		$this->assertSame(422, $this->write('DELETE', '/types/movie')->getStatusCode(), 'A code type isn\'t deleted here.');
		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'movie'])->getStatusCode(), 'Nor created again.');
	}

	public function testChangesACodeTreeInADataFile(): void
	{
		$this->contentConfig("['types' => ['doc' => ['kind' => 'tree', 'folder' => '_docs']]]");
		$this->site();

		$this->assertTrue(self::json($this->send('GET', '/types/doc'))['editable'] ?? null, 'A tree in a folder can change (D-386).');

		$answer = $this->write('PATCH', '/types/doc', ['set' => ['description' => 'The manual.', 'sitemap' => false, 'llms' => false]]);
		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$this->assertSame(['config', true, 'The manual.', false], [self::json($answer)['origin'] ?? null, self::json($answer)['overridden'] ?? null, self::json($answer)['description'] ?? null, self::json($answer)['llms'] ?? null]);
		$this->assertSame("description: \"The manual.\"\nsitemap: false\nllms: false\n", $this->file('user/data/types/doc.yaml'));

		$this->assertSame(200, $this->write('POST', '/types/doc/reset')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/doc.yaml');
	}

	public function testLeavesCodeFieldClassesAndThePagesTypeAlone(): void
	{
		$this->contentConfig("['types' => ['swatch' => ['fields' => [['name' => 'tint', 'type' => 'color', 'class' => Blush\\Tests\\Fixtures\\Content\\ColorField::class]]], 'page' => ['kind' => 'tree']]]");
		$this->site();

		$this->assertFalse(self::json($this->send('GET', '/types/swatch'))['fieldsEditable'] ?? null);
		$fields = $this->write('PATCH', '/types/swatch', ['set' => ['fields' => []]]);
		$this->assertSame(422, $fields->getStatusCode());
		$this->assertStringContainsString('field classes from code', self::error($fields));
		$this->assertSame(200, $this->write('PATCH', '/types/swatch', ['set' => ['description' => 'Colors.']])->getStatusCode(), 'The rest can change.');
		$this->assertSame("description: Colors.\n", $this->file('user/data/types/swatch.yaml'), 'Its fields stay in code.');

		$this->assertFalse(self::json($this->send('GET', '/types/page'))['editable'] ?? null);
		$this->assertStringContainsString('the site\'s pages', self::error($this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])));
		$this->assertSame(422, $this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])->getStatusCode());
		$this->assertFalse(self::json($this->send('GET', '/types/profile'))['editable'] ?? null);
	}

	public function testChangesAndChecksRoutePaths(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\nfeed: true\nurls:\n  single: 'r/{name}'\n");
		$this->writeTemporaryFile('user/data/types/cuisine.yaml', "kind: taxonomy\nfolder: cuisines\n");
		$this->site();

		$routes = $this->routes('recipe');
		$this->assertSame(['collection', 'collection.paged', 'single', 'collection.feed', 'collection.feed.atom', 'collection.feed.json', 'authors.collection', 'authors.single', 'authors.single.paged', 'authors.single.feed', 'authors.single.feed.atom', 'authors.single.feed.json'], array_keys($routes));
		$this->assertSame(['key' => 'single', 'path' => 'r/{name}', 'default' => '{name}', 'requires' => ['name'], 'allows' => ['year', 'month', 'day', 'hour', 'minute', 'second', 'profile', 'cuisine'], 'root' => false], $routes['single']);

		$refusals = [
			'{year}'          => 'The "single" address needs {name}.',
			'{slug}/{name}'   => 'can\'t fill {slug}; it can hold {name}, {year}',
			'r s/{name}'      => 'can use letters, digits',
			'{name:[a-z]+}'   => 'can use letters, digits',
			'{name'           => 'isn\'t one'
		];

		foreach ($refusals as $path => $message) {
			$refused = $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['single' => $path]]]);
			$this->assertSame(422, $refused->getStatusCode(), $path);
			$this->assertStringContainsString($message, self::error($refused));
		}

		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['nope' => 'x']]])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['collection.paged' => 'p']]])->getStatusCode(), 'A paged address needs {page}.');
		$this->assertStringContainsString("single: 'r/{name}'", $this->file('user/data/types/recipe.yaml'), 'Nothing written.');

		$saved = $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['single' => '/{cuisine}/{year}/{name}/', 'collection.paged' => 'p/{page}']]]);
		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$this->assertSame("folder: recipes\nfeed: true\nurls:\n  paths: { single: '{cuisine}/{year}/{name}', collection.paged: 'p/{page}' }\n", $this->file('user/data/types/recipe.yaml'), 'The shortcut moves into paths (D-350).');

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['single' => null, 'collection.paged' => '']]])->getStatusCode());
		$this->assertSame("folder: recipes\nfeed: true\n", $this->file('user/data/types/recipe.yaml'), 'Defaults are left out.');
	}

	public function testListsTheHomeTypesFeedsAtTheRoot(): void
	{
		$this->contentConfig("['types' => ['post' => ['feed' => true, 'dateArchives' => 'month']], 'home' => 'post']");
		$this->site();

		$routes = $this->routes('post');
		$this->assertArrayNotHasKey('collection', $routes, 'The homepage is its listing.');
		$this->assertSame(['year', 'month', 'page'], ($routes['collection.month.paged'] ?? [])['requires'] ?? null);
		$this->assertTrue(($routes['collection.feed'] ?? [])['root'] ?? null);
	}

	public function testNeedsSiteSettings(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\n");
		$this->site(['editor']);

		$this->assertSame(403, $this->write('POST', '/types', ['name' => 'dish'])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/types/recipe', ['set' => []])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/types/recipe')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/types/refresh')->getStatusCode());
	}
}
