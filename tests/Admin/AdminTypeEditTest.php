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
use Blush\Support\Uuid;
use Blush\Tests\Fixtures\Content\ColorField;
use Blush\Tests\WritesContentConfig;

#[CoversClass(TypeEditController::class)]
#[CoversClass(TypesController::class)]
#[CoversClass(DataTypeWriter::class)]
final class AdminTypeEditTest extends TestCase
{
	use BootsAdmin;
	use WritesContentConfig;

	/**
	 * Returns a page as a type answers it, without its id, once that's
	 * checked: a page written for a type has one (D-477).
	 */
	private static function withoutId(mixed $page): mixed
	{
		if (is_array($page)) {
			self::assertTrue(Uuid::isValid($page['id'] ?? null), 'A page written for a type has an id.');
			unset($page['id']);
		}

		return $page;
	}

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

	/**
	 * A JSON data file's data, without the `id` a record's file ends with
	 * (D-672), which is checked.
	 *
	 * @return array<array-key, mixed>
	 */
	private function data(string $relative): array
	{
		$data = json_decode($this->file($relative), true);

		return is_array($data) ? self::withoutRecordId($data) : [];
	}

	/**
	 * A record's file's data without its `id`, checking that it's a UUID
	 * and comes last.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<array-key, mixed>
	 */
	private static function withoutRecordId(array $data): array
	{
		if (array_key_exists('id', $data)) {
			self::assertTrue(Uuid::isValid($data['id']), 'A record\'s id.');
			self::assertSame('id', array_key_last($data), 'Its id comes last.');
		}

		return array_diff_key($data, ['id' => true]);
	}

	public function testANewTypeCanCreditAuthors(): void
	{
		$this->site();

		$answer = $this->write('POST', '/types', ['name' => 'recipe', 'kind' => 'collection', 'authors' => true, 'listPages' => ['authors'], 'set' => ['labels' => ['singular' => 'Recipe', 'plural' => 'Recipes']]]);

		$this->assertSame(201, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);

		$this->assertSame(['authors'], $type['credits'] ?? null, 'Credited through the authors relation (D-602).');
		$this->assertSame(['kind' => 'credit', 'from' => ['recipe'], 'to' => ['profile'], 'aliases' => ['author'], 'label' => 'Authors'], $this->data('user/data/relations/authors.json'), 'Written when the site has none.');
		$this->assertArrayNotHasKey('people', $this->data('user/data/types/recipe.json'));
		$this->assertMatchesRegularExpression('/\A---\ntitle: Authors\npublished: \S+ \S+ \S+\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\z/', $this->file('user/content/_recipe/_authors.md'), 'Its list page, written as every new entry is (D-514), with an id, last (D-477).');
		$this->assertSame([['relation' => 'authors', 'label' => 'Authors', 'word' => 'authors', 'page' => ['type' => 'recipe', 'path' => '_recipe/_authors.md', 'title' => 'Authors']]], array_map(static fn (mixed $item): mixed => is_array($item) ? [...$item, 'page' => self::withoutId($item['page'] ?? null)] : $item, is_array($type['archivePages'] ?? null) ? $type['archivePages'] : []));

		$this->assertSame(201, $this->write('POST', '/types', ['name' => 'note', 'kind' => 'collection', 'authors' => true, 'set' => []])->getStatusCode());
		$this->assertSame(['recipe', 'note'], $this->data('user/data/relations/authors.json')['from'] ?? null, 'A second type joins it.');
	}

	public function testATypeNamesItsBylineAndRefusesPeopleFields(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->writeTemporaryFile('user/data/relations/cooks.json', '{"kind": "credit", "from": ["recipe"], "to": ["profile"]}');
		$this->writeTemporaryFile('user/data/relations/photographers.json', '{"kind": "credit", "from": ["recipe"], "to": ["profile"], "inverse": {"archive": false}}');
		$this->site();

		$saved = $this->write('PATCH', '/types/recipe', ['set' => ['byline' => 'cooks'], 'listPages' => ['cooks']]);

		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$this->assertSame(['cooks', ['cooks', 'photographers']], [self::json($saved)['byline'] ?? null, self::json($saved)['credits'] ?? null]);
		$this->assertSame(['folder' => 'recipes', 'byline' => 'cooks'], $this->data('user/data/types/recipe.json'));
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/recipes/_cooks.md');

		$keys = array_column(is_array(self::json($saved)['routes'] ?? null) ? self::json($saved)['routes'] : [], 'key');

		$this->assertContains('cooks.single', $keys);
		$this->assertNotContains('photographers.single', $keys, 'No archives, no routes.');
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => [], 'listPages' => ['photographers']])->getStatusCode(), 'No archives, no list page.');
		$this->assertStringContainsString('isn\'t a credit relation from it', self::error($this->write('PATCH', '/types/recipe', ['set' => ['byline' => 'nope']])));
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['people' => false]])->getStatusCode(), 'Credits are relations (D-602).');
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
		$this->assertSame('user/data/types/recipe.json', $type['file'] ?? null, 'A new type is JSON (D-490).');
		$this->assertSame(['type' => 'recipe', 'path' => 'recipes/index.md', 'title' => 'Recipes'], self::withoutId($type['index'] ?? null));
		$this->assertSame(
			[
				'folder'      => 'recipes',
				'labels'      => ['newItem' => 'Add a recipe'],
				'description' => 'Dishes we cook at home.',
				'icon'        => 'book-open',
				'fields'      => [
					['name' => 'servings', 'type' => 'number', 'required' => true, 'integer' => true],
					['name' => 'difficulty', 'type' => 'enum', 'default' => 'easy', 'options' => ['easy', 'hard']],
					['name' => 'image', 'type' => 'media', 'label' => 'Featured image']
				]
			],
			$this->data('user/data/types/recipe.json'),
			'Defaults (the singular and plural a recipe type gets, public) are left out.'
		);
		$this->assertMatchesRegularExpression('/\A---\ntitle: Recipes\npublished: \S+ \S+ \S+\nid: [0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\n---\n\z/', $this->file('user/content/recipes/index.md'), 'Written as every new entry is (D-514), with an id, last (D-477).');

		$this->assertSame(200, $this->write('POST', '/types/refresh')->getStatusCode());

		$next = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test']);
		$next->boot();
		$this->assertTrue($next->container()->make(ContentTypes::class)->has('recipe'), 'The next request has it.');
	}

	public function testEditsOnlyWhatChangedAndKeepsTheRest(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes", "routing": {"single": "r/{name}"}, "listing": {"orderBy": "title"}}');
		$this->site();

		$answer = $this->write('PATCH', '/types/recipe', ['set' => ['description' => 'Food.', 'prefix' => 'cook', 'hierarchical' => null]]);

		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$this->assertSame('/cook', self::json($answer)['prefix'] ?? null);
		$this->assertSame(
			['folder' => 'recipes', 'listing' => ['orderBy' => 'title'], 'description' => 'Food.', 'urls' => ['prefix' => 'cook', 'paths' => ['single' => 'r/{name}']]],
			$this->data('user/data/types/recipe.json'),
			'routing becomes urls; other keys stay.'
		);

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['prefix' => 'recipes']])->getStatusCode());
		$this->assertStringNotContainsString('prefix', $this->file('user/data/types/recipe.json'), 'A prefix the folder gives is left out.');
	}

	public function testRewritesCompiledTypes(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->boot(roles: ['administrator'], environment: ['APP_ENV' => 'production']);
		$this->login();
		$cache = $this->app->container()->make(ContentTypeCache::class);
		$cache->write();

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['description' => 'Compiled.']])->getStatusCode());
		$this->assertStringContainsString('Compiled.', $this->file(substr($cache->path(), strlen($this->temporaryDirectory()) + 1)));
	}

	public function testEditsAJsonType(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"$schema": "../type.schema.json", "folder": "topics", "order": "position"}');
		$this->site();

		$this->assertSame(200, $this->write('PATCH', '/types/topic', ['set' => ['hierarchical' => true, 'labels' => ['plural' => 'Subjects']]])->getStatusCode());
		$this->assertSame(
			['$schema' => '../type.schema.json', 'folder' => 'topics', 'order' => 'position', 'hierarchical' => true, 'labels' => ['plural' => 'Subjects']],
			$this->data('user/data/types/topic.json'),
			'An editor\'s schema key isn\'t an option, and stays (D-491).'
		);

		$this->writeTemporaryFile('user/data/types/genre.json', '{"kind": "taxonomy"}');

		$legacy = $this->write('PATCH', '/types/genre', ['set' => ['description' => 'x']]);
		$this->assertSame(422, $legacy->getStatusCode());
		$this->assertSame('"genre" is still written as a taxonomy; migrate it first, on Site Health or with content:taxonomies --write.', self::error($legacy));
	}

	public function testRefusesWhatDoesNotFitAndWritesNothing(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->site();

		$badField = $this->write('PATCH', '/types/recipe', ['set' => ['fields' => [['name' => 'x', 'type' => 'colour']]]]);
		$this->assertSame(422, $badField->getStatusCode());
		$this->assertStringContainsString('colour', self::error($badField));
		$this->assertSame(['folder' => 'recipes'], $this->data('user/data/types/recipe.json'));

		$clash = $this->write('POST', '/types', ['name' => 'dish', 'folder' => 'recipes']);
		$this->assertSame(422, $clash->getStatusCode(), 'Two types can\'t share a folder.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/dish.json', 'The new file is taken back.');

		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'recipe'])->getStatusCode(), 'The name is taken.');
		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'Bad Name'])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])->getStatusCode(), 'Built-in types aren\'t data types.');
		$this->assertSame(400, $this->write('PATCH', '/types/recipe', ['set' => ['a', 'b']])->getStatusCode());
	}

	public function testDeletesATypeButNotOneOthersNeed(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->writeTemporaryFile('user/data/types/cuisine.json', '{"folder": "cuisines", "order": "position"}');
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\n---\n");
		$this->site();

		$needed = $this->write('DELETE', '/types/recipe');
		$this->assertSame(422, $needed->getStatusCode());
		$this->assertSame('The "cuisine" relation names recipe; remove it first.', self::error($needed));
		$this->assertFileExists($this->temporaryDirectory() . '/user/data/types/recipe.json', 'It\'s put back.');

		$this->assertSame(200, $this->write('DELETE', '/relations/cuisine')->getStatusCode());
		$this->assertSame(200, $this->write('DELETE', '/types/cuisine')->getStatusCode());
		$this->assertSame(200, $this->write('DELETE', '/types/recipe')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/recipe.json');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/recipes/soup.md', 'Entries stay.');
	}

	public function testTheListSaysWhetherTypesCanBeCreated(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Content\\ContentConfig(dataTypes: false);\n");
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
			'set'    => ['labels' => ['singular' => 'Doc', 'plural' => 'Docs'], 'icon' => 'book', 'public' => true, 'sitemap' => true]
		]);

		$this->assertSame(201, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertSame(['tree', true, ['type' => 'doc', 'path' => '_docs/index.md', 'title' => 'Docs']], [$type['kind'] ?? null, $type['editable'] ?? null, self::withoutId($type['index'] ?? null)]);
		$this->assertSame(['kind' => 'tree', 'folder' => '_docs', 'icon' => 'book'], $this->data('user/data/types/doc.json'));

		$feed = $this->write('PATCH', '/types/doc', ['set' => ['feed' => true]]);
		$this->assertSame(422, $feed->getStatusCode(), 'A tree has no feed.');
		$this->assertStringContainsString('unknown options: feed', self::error($feed));

		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'person', 'kind' => 'profiles', 'set' => []])->getStatusCode(), 'The site has one profiles type.');
	}

	public function testChangesACodeTypeInADataFile(): void
	{
		$this->codeConfig(['types' => ['movie' => ['folder' => 'movies', 'urls' => ['prefix' => 'films'], 'description' => 'Films.', 'feed' => true, 'fields' => [['name' => 'rating', 'type' => 'number']]]]]);
		$this->site();

		$before = self::json($this->send('GET', '/types/movie'));
		$this->assertSame([true, false, null, [], true], [$before['editable'] ?? null, $before['overridden'] ?? null, $before['file'] ?? null, $before['overrides'] ?? null, $before['fieldsEditable'] ?? null]);

		$answer = $this->write('PATCH', '/types/movie', ['set' => ['description' => 'Movies.', 'feed' => false]]);
		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$type = self::json($answer);
		$this->assertSame(['extension', true, ['description', 'feed'], 'user/data/types/movie.json', false], [$type['origin'] ?? null, $type['overridden'] ?? null, $type['overrides'] ?? null, $type['file'] ?? null, $type['feed'] ?? null]);
		$this->assertSame(['description' => 'Movies.', 'feed' => false], $this->data('user/data/types/movie.json'), 'Only what differs from the code, with a default the code doesn\'t have written out (D-349).');
		$this->assertSame('/films', $type['prefix'] ?? null);

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['fields' => [['name' => 'rating', 'type' => 'number'], ['name' => 'year', 'type' => 'number']]]])->getStatusCode());
		$this->assertSame([['name' => 'rating', 'type' => 'number'], ['name' => 'year', 'type' => 'number']], $this->data('user/data/types/movie.json')['fields'] ?? null, 'Fields are the whole list.');

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['description' => 'Films.', 'feed' => true, 'fields' => [['name' => 'rating', 'type' => 'number']]]])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/movie.json', 'Back at the code\'s values, the file goes.');

		$this->assertSame(200, $this->write('PATCH', '/types/movie', ['set' => ['labels' => ['plural' => 'Pictures']]])->getStatusCode());
		$reset = $this->write('POST', '/types/movie/reset');
		$this->assertSame(200, $reset->getStatusCode(), (string) $reset->getBody());
		$reverted = self::json($reset);
		$this->assertSame(['Movies', false], [is_array($reverted['labels'] ?? null) ? $reverted['labels']['plural'] ?? null : null, $reverted['overridden'] ?? null]);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/movie.json');

		$this->assertSame(422, $this->write('POST', '/types/movie/reset')->getStatusCode(), 'Nothing to reset.');
		$this->assertSame(422, $this->write('DELETE', '/types/movie')->getStatusCode(), 'A code type isn\'t deleted here.');
		$this->assertSame(422, $this->write('POST', '/types', ['name' => 'movie'])->getStatusCode(), 'Nor created again.');
	}

	public function testChangesAFolderPatternButNeverTheFolder(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->codeConfig(['types' => ['movie' => ['folder' => 'movies/{year}']]]);
		$this->site();

		$answer = $this->write('PATCH', '/types/recipe', ['set' => ['folders' => '{initial}']]);
		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$this->assertSame('{initial}', self::json($answer)['folders'] ?? null);
		$this->assertSame(['folder' => 'recipes/{initial}'], $this->data('user/data/types/recipe.json'), 'Written after the folder (D-629).');

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['folders' => null]])->getStatusCode());
		$this->assertSame(['folder' => 'recipes'], $this->data('user/data/types/recipe.json'), 'The folder stays without one.');

		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['folder' => 'dishes']])->getStatusCode(), 'The folder itself never changes here.');
		$this->assertSame(422, $this->write('PATCH', '/types/recipe', ['set' => ['folders' => '{day}']])->getStatusCode());

		$movie = $this->write('PATCH', '/types/movie', ['set' => ['folders' => null]]);
		$this->assertSame(200, $movie->getStatusCode(), (string) $movie->getBody());
		$this->assertSame(['folders' => null], array_intersect_key(self::json($movie), ['folders' => true]));
		$this->assertSame(['folder' => 'movies'], $this->data('user/data/types/movie.json'), 'Over code, none is written out.');
	}

	public function testChangesACodeTreeInADataFile(): void
	{
		$this->codeConfig(['types' => ['doc' => ['kind' => 'tree', 'folder' => '_docs']]]);
		$this->site();

		$this->assertTrue(self::json($this->send('GET', '/types/doc'))['editable'] ?? null, 'A tree in a folder can change (D-386).');

		$answer = $this->write('PATCH', '/types/doc', ['set' => ['description' => 'The manual.', 'sitemap' => false, 'llms' => false]]);
		$this->assertSame(200, $answer->getStatusCode(), (string) $answer->getBody());
		$this->assertSame(['extension', true, 'The manual.', false], [self::json($answer)['origin'] ?? null, self::json($answer)['overridden'] ?? null, self::json($answer)['description'] ?? null, self::json($answer)['llms'] ?? null]);
		$this->assertSame(['description' => 'The manual.', 'sitemap' => false, 'llms' => false], $this->data('user/data/types/doc.json'));

		$this->assertSame(200, $this->write('POST', '/types/doc/reset')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/types/doc.json');
	}

	public function testLeavesCodeFieldClassesAndThePagesTypeAlone(): void
	{
		$this->codeConfig(['types' => ['swatch' => ['fields' => [['name' => 'tint', 'type' => 'color', 'class' => ColorField::class]]]]]);
		$this->site();

		$this->assertFalse(self::json($this->send('GET', '/types/swatch'))['fieldsEditable'] ?? null);
		$fields = $this->write('PATCH', '/types/swatch', ['set' => ['fields' => []]]);
		$this->assertSame(422, $fields->getStatusCode());
		$this->assertStringContainsString('field classes from code', self::error($fields));
		$this->assertSame(200, $this->write('PATCH', '/types/swatch', ['set' => ['description' => 'Colors.']])->getStatusCode(), 'The rest can change.');
		$this->assertSame(['description' => 'Colors.'], $this->data('user/data/types/swatch.json'), 'Its fields stay in code.');

		$this->assertFalse(self::json($this->send('GET', '/types/page'))['editable'] ?? null);
		$this->assertStringContainsString('isn\'t defined in user/data/types', self::error($this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])), 'A built-in type isn\'t changed here.');
		$this->assertSame(422, $this->write('PATCH', '/types/page', ['set' => ['description' => 'x']])->getStatusCode());
		$this->assertFalse(self::json($this->send('GET', '/types/profile'))['editable'] ?? null);
	}

	public function testMovesARelationsArchives(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->writeTemporaryFile('user/data/relations/pairs_with.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"], "inverse": {"archive": "pairs"}}');
		$this->site();

		$routes = $this->routes('recipe');

		$this->assertSame(['key' => 'pairs_with.single', 'path' => '', 'default' => 'pairs/{target}', 'requires' => ['target'], 'allows' => [], 'root' => false, 'relation' => 'Pairs with'], $routes['pairs_with.single'] ?? null, 'A relation\'s archives are among the type\'s addresses (D-596).');
		$this->assertSame('pairs', $routes['pairs_with.collection']['default'] ?? null);

		$refused = $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['pairs_with.single' => 'goes-with/{name}']]]);

		$this->assertSame(422, $refused->getStatusCode());
		$this->assertStringContainsString('needs {target}', self::error($refused));

		$saved = $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['pairs_with.single' => 'goes-with/{target}']]]);

		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$this->assertSame(['folder' => 'recipes', 'urls' => ['paths' => ['pairs_with.single' => 'goes-with/{target}']]], $this->data('user/data/types/recipe.json'));
	}

	public function testChangesAndChecksRoutePaths(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes", "feed": true, "urls": {"single": "r/{name}"}}');
		$this->writeTemporaryFile('user/data/types/cuisine.json', '{"kind": "taxonomy", "folder": "cuisines"}');
		$this->writeTemporaryFile('user/data/relations/authors.json', '{"kind": "credit", "from": ["recipe"], "to": ["profile"]}');
		$this->site();

		$routes = $this->routes('recipe');
		$this->assertSame(['collection', 'collection.paged', 'single', 'collection.feed', 'collection.feed.atom', 'collection.feed.json', 'authors.collection', 'authors.single', 'authors.single.paged', 'authors.single.feed', 'authors.single.feed.atom', 'authors.single.feed.json'], array_keys($routes));
		$this->assertSame(['key' => 'single', 'path' => 'r/{name}', 'default' => '{name}', 'requires' => ['name'], 'allows' => ['year', 'month', 'day', 'hour', 'minute', 'second', 'profile', 'cuisine'], 'root' => false, 'relation' => null], $routes['single']);

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
		$this->assertSame(['folder' => 'recipes', 'feed' => true, 'urls' => ['single' => 'r/{name}']], $this->data('user/data/types/recipe.json'), 'Nothing written.');

		$saved = $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['single' => '/{cuisine}/{year}/{name}/', 'collection.paged' => 'p/{page}']]]);
		$this->assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
		$this->assertSame(['folder' => 'recipes', 'feed' => true, 'urls' => ['paths' => ['single' => '{cuisine}/{year}/{name}', 'collection.paged' => 'p/{page}']]], $this->data('user/data/types/recipe.json'), 'The shortcut moves into paths (D-350).');

		$this->assertSame(200, $this->write('PATCH', '/types/recipe', ['set' => ['paths' => ['single' => null, 'collection.paged' => '']]])->getStatusCode());
		$this->assertSame(['folder' => 'recipes', 'feed' => true], $this->data('user/data/types/recipe.json'), 'Defaults are left out.');
	}

	public function testListsTheHomeTypesFeedsAtTheRoot(): void
	{
		$this->contentConfig(['types' => ['post' => ['feed' => true, 'dateArchives' => 'month']], 'home' => 'post']);
		$this->site();

		$routes = $this->routes('post');
		$this->assertArrayNotHasKey('collection', $routes, 'The homepage is its listing.');
		$this->assertSame(['year', 'month', 'page'], ($routes['collection.month.paged'] ?? [])['requires'] ?? null);
		$this->assertTrue(($routes['collection.feed'] ?? [])['root'] ?? null);
	}

	public function testNeedsSiteSettings(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"folder": "recipes"}');
		$this->site(['editor']);

		$this->assertSame(403, $this->write('POST', '/types', ['name' => 'dish'])->getStatusCode());
		$this->assertSame(403, $this->write('PATCH', '/types/recipe', ['set' => []])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/types/recipe')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/types/refresh')->getStatusCode());
	}
}
