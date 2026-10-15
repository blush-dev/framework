<?php

/**
 * Admin field library API tests.
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
use Blush\Admin\Http\FieldEditController;
use Blush\Admin\Http\FieldsController;
use Blush\Content\Type\DataFieldWriter;
use Blush\Setup\SiteChecks;

#[CoversClass(FieldsController::class)]
#[CoversClass(FieldEditController::class)]
#[CoversClass(DataFieldWriter::class)]
final class AdminFieldsTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Boots a site with a `recipe` collection placing the library's `oven`
	 * (and `notes`, in a section), and `config/fields.php` as given.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $config = '', string $layout = '["oven", {"section": "More", "fields": ["notes"]}]'): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', "{\"fields\": {$layout}}");
		$this->writeTemporaryFile('user/data/fields/oven.json', '{"type": "enum", "label": "Oven", "options": ["gas", "electric"]}');
		$this->writeTemporaryFile('user/data/fields/notes.json', '{"type": "text"}');

		$config = $config === '' ? 'new Blush\\Field\\FieldConfig()' : $config;

		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$config};\n");

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

	private function file(string $path): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/user/data/{$path}");
	}

	/**
	 * Returns a saved record as data, without the id a record gains when
	 * it's saved (D-678).
	 *
	 * @return array<array-key, mixed>
	 */
	private function saved(string $path): array
	{
		$data = json_decode($this->file($path), true);

		$this->assertIsArray($data);
		unset($data['id']);

		return $data;
	}

	public function testListsTheLibrary(): void
	{
		$this->site();

		$list   = self::json($this->send('GET', '/fields/library'));
		$fields = array_column(is_array($list['fields'] ?? null) ? $list['fields'] : [], null, 'name');

		$this->assertSame(['notes', 'oven'], array_keys($fields), 'Only the site\'s fields, by name (D-858).');
		$this->assertTrue($list['create'] ?? null);
		$this->assertSame([
			'name'        => 'oven',
			'label'       => 'Oven',
			'description' => '',
			'required'    => false,
			'type'        => 'enum',
			'control'     => 'select',
			'editable'    => true,
			'file'        => 'user/data/fields/oven.json',
			'types'       => [['name' => 'recipe', 'label' => 'Recipes']],
			'problems'    => []
		], $fields['oven']);
	}

	public function testSaysWhereAFieldIsLeftOff(): void
	{
		$this->writeTemporaryFile('user/data/fields/date.json', '{"type": "text"}');
		$this->site(layout: '["oven", "date"]');

		$list   = self::json($this->send('GET', '/fields/library'));
		$fields = array_column(is_array($list['fields'] ?? null) ? $list['fields'] : [], null, 'name');

		$this->assertSame(['type:recipe can\'t take the "date" field: "date" is Blush\'s own key, so nothing else can declare it.'], is_array($fields['date'] ?? null) ? $fields['date']['problems'] ?? null : null);

		$checks = $this->app->container()->make(SiteChecks::class)->extensions();

		$this->assertSame('Fields left off', (string) ($checks['fields'] ?? null)?->label, 'The site keeps running, and says so (D-786).');
	}

	public function testDescribesOneField(): void
	{
		$this->site();

		$field = self::json($this->send('GET', '/fields/library/oven'));

		$this->assertSame(['name' => 'oven', 'type' => 'enum', 'label' => 'Oven', 'options' => ['gas', 'electric'], 'control' => 'select', 'choices' => ['gas' => 'gas', 'electric' => 'electric']], $field['field'] ?? null, 'As forms take it, with the control (D-769).');
		$this->assertSame(['oven' => 0], $field['counts'] ?? null);
		$this->assertSame(404, $this->send('GET', '/fields/library/nope')->getStatusCode());

		$type = self::json($this->send('GET', '/types/recipe'));

		$this->assertSame(['oven', ['section' => 'More', 'fields' => ['notes']]], $type['fields'] ?? null);
		$this->assertContains(['name' => 'oven', 'label' => 'Oven', 'description' => '', 'required' => false, 'type' => 'enum', 'control' => 'select'], is_array($type['library'] ?? null) ? $type['library'] : []);
	}

	public function testMakesAField(): void
	{
		$this->site();

		$response = $this->write('POST', '/fields/library', ['field' => ['name' => 'shelf', 'type' => 'text', 'label' => 'Shelf', 'control' => 'code']]);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('shelf', self::json($response)['name'] ?? null);
		$this->assertSame([], self::json($response)['types'] ?? null, 'On no type until one places it (D-858).');
		$this->assertSame(['type' => 'text', 'label' => 'Shelf', 'control' => ['name' => 'code']], $this->saved('fields/shelf.json'), 'Named by its file; the control the form sent is an object beside the field (D-795).');

		$this->assertSame(422, $this->write('POST', '/fields/library', ['field' => ['name' => 'shelf', 'type' => 'text']])->getStatusCode(), 'The key is taken.');
		$this->assertSame(422, $this->write('POST', '/fields/library', ['field' => ['name' => 'Shelf', 'type' => 'text']])->getStatusCode());
		$this->assertSame(422, $this->write('POST', '/fields/library', ['field' => ['name' => 'bin', 'type' => 'nope']])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/fields/bin.json', 'Nothing is written.');
		$this->assertSame(400, $this->write('POST', '/fields/library', ['field' => ['type' => 'text']])->getStatusCode(), 'A field needs its name.');
	}

	public function testChangesAFieldWholeButNotItsKey(): void
	{
		$this->site();

		$response = $this->write('PATCH', '/fields/library/oven', ['field' => ['name' => 'oven', 'type' => 'enum', 'control' => 'radios', 'options' => [['value' => 'gas', 'label' => 'Gas'], 'electric']]]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['type' => 'enum', 'options' => [['value' => 'gas', 'label' => 'Gas'], ['value' => 'electric', 'label' => 'electric']], 'control' => ['name' => 'radios']], $this->saved('fields/oven.json'), 'Written whole, options with labels.');

		$field = self::json($response)['field'] ?? null;
		$this->assertSame(['gas' => 'Gas', 'electric' => 'electric'], is_array($field) ? $field['choices'] ?? null : null);

		$renamed = $this->write('PATCH', '/fields/library/oven', ['field' => ['name' => 'stove', 'type' => 'text']]);
		$this->assertSame(422, $renamed->getStatusCode());
		$this->assertStringContainsString('key is fixed', is_string($error = self::json($renamed)['error'] ?? null) ? $error : '', 'Every type that carries it reads it by its key (D-859).');
		$this->assertSame(400, $this->write('PATCH', '/fields/library/oven', ['field' => 'text'])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/fields/library/nope', ['field' => ['type' => 'text']])->getStatusCode());
	}

	public function testMovesValuesWithAFieldMovedInsideAnObject(): void
	{
		$this->writeTemporaryFile('user/data/fields/details.json', '{"type": "object", "fields": [{"name": "heat", "type": "text"}]}');
		$this->writeTemporaryFile('user/content/_recipe/beans.md', "---\ntitle: Beans\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f01\ndetails:\n  heat: low\n---\n");
		$this->writeTemporaryFile('user/content/_recipe/toast.md', "---\ntitle: Toast\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f03\n---\n");
		$this->site(layout: '["details"]');

		$heat  = ['name' => 'heat', 'type' => 'text'];
		$front = fn (string $name): string => (string) file_get_contents($this->temporaryDirectory() . "/user/content/_recipe/{$name}.md");

		$into = self::json($this->write('PATCH', '/fields/library/details', ['field' => ['name' => 'details', 'type' => 'object', 'fields' => [['name' => 'stove', 'type' => 'object', 'fields' => [$heat]]]]]));

		$this->assertSame(['entries' => 1, 'kept' => 0, 'failed' => []], $into['moved'] ?? null, 'In every entry of the types that carry it (D-780).');
		$this->assertStringContainsString('details: { stove: { heat: low } }', $front('beans'));
		$this->assertSame(['details' => 1, 'details.stove' => 1, 'details.stove.heat' => 1], $into['counts'] ?? null, 'An Object\'s fields are counted under each key.');

		$activity = self::json($this->send('GET', '/activity'));

		$this->assertContains('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f01', array_column(is_array($activity['changes'] ?? null) ? $activity['changes'] : [], 'subject'), 'Each move is in the activity log, for Undo.');
	}

	public function testRefusesAFieldATypeAlreadyHas(): void
	{
		$this->site();
		$before = $this->file('fields/oven.json');

		$response = $this->write('PATCH', '/fields/library/oven', ['field' => ['name' => 'oven', 'type' => 'text', 'aliases' => ['title']]]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertStringContainsString('can\'t take the "oven" field', is_string($error = self::json($response)['error'] ?? null) ? $error : '');
		$this->assertSame($before, $this->file('fields/oven.json'), 'The file is put back.');
	}

	public function testDeletesAFieldAndTakesItOffLayouts(): void
	{
		$this->site();

		$this->assertSame(['deleted' => 'oven'], self::json($this->write('DELETE', '/fields/library/oven')));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/fields/oven.json');
		$this->assertSame(['fields' => [['section' => 'More', 'fields' => ['notes']]]], $this->saved('types/recipe.json'), 'Off the types that carried it (D-859).');

		$this->assertSame(200, $this->write('DELETE', '/fields/library/notes')->getStatusCode());
		$this->assertSame(['fields' => [['section' => 'More', 'fields' => []]]], $this->saved('types/recipe.json'), 'Its section stays.');
		$this->assertSame(422, $this->write('DELETE', '/fields/library/nope')->getStatusCode());
	}

	public function testTheLibraryCanBeOff(): void
	{
		$this->site(config: 'new Blush\Field\FieldConfig(library: false)');

		$list = self::json($this->send('GET', '/fields/library'));

		$this->assertFalse($list['create'] ?? null);
		$this->assertSame([], $list['fields'] ?? null);
		$this->assertSame(422, $this->write('POST', '/fields/library', ['field' => ['name' => 'shelf', 'type' => 'text']])->getStatusCode());
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(roles: ['editor']);

		$this->assertSame(403, $this->write('PATCH', '/fields/library/oven', ['field' => ['name' => 'oven', 'type' => 'text']])->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/fields/library', ['field' => ['name' => 'shelf']])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/fields/library/oven')->getStatusCode());
	}
}
