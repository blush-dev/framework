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
