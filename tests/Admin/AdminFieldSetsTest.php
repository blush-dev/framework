<?php

/**
 * Admin field sets API tests.
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
use Blush\Admin\Http\FieldSetEditController;
use Blush\Admin\Http\FieldSetsController;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Data\DataKeys;
use Blush\Setup\SiteChecks;

#[CoversClass(FieldSetsController::class)]
#[CoversClass(FieldSetEditController::class)]
#[CoversClass(DataFieldSetWriter::class)]
#[CoversClass(DataKeys::class)]
final class AdminFieldSetsTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Boots a site with a `recipe` collection (with a `servings` field), a
	 * `kitchen` data set on it, and `config/fields.php` as given.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $config = ''): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"fields": [{"name": "servings", "type": "number"}]}');
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"label": "In the Kitchen", "targets": ["type:recipe", "type:gone"], "fields": [{"name": "oven", "type": "enum", "options": ["gas", "electric"]}]}');

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

	private function file(string $name): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/user/data/fields/{$name}");
	}

	/**
	 * Returns a saved set's file as data, without the id a record gains
	 * when it's saved (D-678).
	 *
	 * @return array<array-key, mixed>
	 */
	private function saved(string $name): array
	{
		$data = json_decode($this->file($name), true);

		$this->assertIsArray($data);
		$this->assertIsString($data['id'] ?? null, 'A saved set has its id.');

		unset($data['id']);

		return $data;
	}

	public function testListsTheSets(): void
	{
		$this->site();

		$list = self::json($this->send('GET', '/fields/sets'));
		$sets = array_column(is_array($list['sets'] ?? null) ? $list['sets'] : [], null, 'name');

		$this->assertSame(['kitchen'], array_keys($sets), 'Only the site\'s sets (D-790).');
		$this->assertTrue($list['create'] ?? null);

		$this->assertSame([
			'name'        => 'kitchen',
			'label'       => 'In the Kitchen',
			'description' => '',
			'kind'        => 'type',
			'slot'        => 'details',
			'position'    => 10,
			'editable'    => true,
			'file'        => 'user/data/fields/kitchen.json',
			'targets'     => [['key' => 'type:recipe', 'label' => 'Recipes', 'found' => true], ['key' => 'type:gone', 'label' => 'type:gone', 'found' => false]],
			'fields'      => 1,
			'problems'    => []
		], $sets['kitchen']);
	}

	public function testListsASetOnEveryType(): void
	{
		$this->writeTemporaryFile('user/data/fields/notes.json', '{"targets": ["type:*"], "fields": [{"name": "note"}]}');
		$this->site();

		$notes = self::json($this->send('GET', '/fields/sets/notes'));

		$this->assertSame([['key' => 'type:*', 'label' => 'All content types', 'found' => true, 'every' => true]], $notes['targets'] ?? null, 'Every type (D-786).');
	}

	public function testSaysWhereASetIsLeftOff(): void
	{
		$this->writeTemporaryFile('user/data/fields/stove.json', '{"targets": ["type:recipe"], "fields": [{"name": "oven"}]}');
		$this->site();

		$list = self::json($this->send('GET', '/fields/sets'));
		$sets = array_column(is_array($list['sets'] ?? null) ? $list['sets'] : [], null, 'name');

		$this->assertSame(['type:recipe can\'t take field set "stove": field set "kitchen" declares "oven" there already, and a key is declared once.'], is_array($sets['stove'] ?? null) ? $sets['stove']['problems'] ?? null : null);

		$checks = $this->app->container()->make(SiteChecks::class)->extensions();

		$this->assertSame('Field sets left off', (string) ($checks['field-sets'] ?? null)?->label, 'The site keeps running, and says so (D-786).');
	}

	public function testDescribesOneSet(): void
	{
		$this->site();

		$set = self::json($this->send('GET', '/fields/sets/kitchen'));

		$this->assertSame([['name' => 'oven', 'type' => 'enum', 'options' => ['gas', 'electric'], 'control' => 'select', 'choices' => ['gas' => 'gas', 'electric' => 'electric']]], $set['fields'] ?? null, 'As forms take them, with the control (D-769).');
		$this->assertSame(['oven' => 0], $set['counts'] ?? null);
		$this->assertSame(404, $this->send('GET', '/fields/sets/nope')->getStatusCode());

		$type = self::json($this->send('GET', '/types/recipe'));

		$this->assertSame([['name' => 'kitchen', 'label' => 'In the Kitchen', 'fields' => 1]], $type['sets'] ?? null);
	}

	public function testCreatesASet(): void
	{
		$this->site();

		$response = $this->write('POST', '/fields/sets', ['name' => 'pantry', 'set' => [
			'label'   => 'Pantry',
			'targets' => ['type:recipe'],
			'fields'  => [['name' => 'shelf', 'type' => 'text', 'control' => 'code']]
		]]);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('pantry', self::json($response)['name'] ?? null);
		$this->assertSame('details', self::json($response)['slot'] ?? null, 'The kind\'s default.');
		$this->assertSame(['targets' => ['type:recipe'], 'fields' => [['name' => 'shelf', 'type' => 'text', 'control' => ['name' => 'code']]]], $this->saved('pantry.json'), 'A new set is JSON (D-490), and a label the name gives is left out; the control the form sent is an object beside the field (D-795).');
	}

	public function testChangesOnlyWhatChanged(): void
	{
		$this->site();

		$response = $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['description' => 'Where it\'s cooked.']]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame([
			'label'       => 'In the Kitchen',
			'targets'     => ['type:recipe', 'type:gone'],
			'fields'      => [['name' => 'oven', 'type' => 'enum', 'options' => ['gas', 'electric']]],
			'description' => 'Where it\'s cooked.'
		], $this->saved('kitchen.json'), 'Its other keys stay.');
	}

	public function testSavesAPositionAndLabeledOptions(): void
	{
		$this->site();

		$response = $this->write('PATCH', '/fields/sets/kitchen', ['set' => [
			'position' => 3,
			'fields'   => [['name' => 'oven', 'type' => 'enum', 'control' => 'radios', 'options' => [['value' => 'gas', 'label' => 'Gas'], 'electric']]]
		]]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$saved = $this->saved('kitchen.json');

		$this->assertSame(3, $saved['position'] ?? null);
		$this->assertSame([['name' => 'oven', 'type' => 'enum', 'options' => [['value' => 'gas', 'label' => 'Gas'], ['value' => 'electric', 'label' => 'electric']], 'control' => ['name' => 'radios']]], $saved['fields'] ?? null, 'Options with labels, each written with its own.');

		$set = self::json($response);

		$this->assertSame(3, $set['position'] ?? null);
		$fields = is_array($set['fields'] ?? null) ? $set['fields'] : [];
		$oven   = is_array($fields[0] ?? null) ? $fields[0] : [];

		$this->assertSame(['gas' => 'Gas', 'electric' => 'electric'], $oven['choices'] ?? null);
	}

	public function testMovesValuesWithAFieldMovedIntoOrOutOfAGroup(): void
	{
		$this->writeTemporaryFile('user/content/_recipe/beans.md', "---\ntitle: Beans\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f01\noven: gas\n---\n");
		$this->writeTemporaryFile('user/content/_recipe/stew.md', "---\ntitle: Stew\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f02\noven: electric\ndetails:\n  oven: gas\n---\n");
		$this->writeTemporaryFile('user/content/_recipe/toast.md', "---\ntitle: Toast\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f03\n---\n");
		$this->site();

		$oven  = ['name' => 'oven', 'type' => 'enum', 'options' => ['gas', 'electric']];
		$front = fn (string $name): string => (string) file_get_contents($this->temporaryDirectory() . "/user/content/_recipe/{$name}.md");

		$into = self::json($this->write('PATCH', '/fields/sets/kitchen', ['set' => ['fields' => [['name' => 'details', 'type' => 'object', 'fields' => [$oven]]]]]));

		$this->assertSame(['entries' => 1, 'kept' => 1, 'failed' => []], $into['moved'] ?? null, 'Stew already had a value in the group, so its own stays (D-780).');
		$this->assertStringContainsString('details:', $front('beans'));
		$this->assertStringNotContainsString("\noven: gas", $front('beans'));
		$this->assertStringContainsString("\noven: electric", $front('stew'));
		$this->assertSame(['details' => 2, 'details.oven' => 2], $into['counts'] ?? null, 'A group\'s fields are counted under both keys.');

		$out = self::json($this->write('PATCH', '/fields/sets/kitchen', ['set' => ['fields' => [$oven]]]));

		$this->assertSame(['entries' => 1, 'kept' => 1, 'failed' => []], $out['moved'] ?? null);
		$this->assertStringContainsString("\noven: gas", $front('beans'));
		$this->assertStringNotContainsString('details', $front('beans'), 'A group left empty goes.');
		$this->assertStringContainsString('details:', $front('stew'), 'Stew kept its own value, so the group\'s stays.');

		$activity = self::json($this->send('GET', '/activity'));

		$this->assertContains('0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1f01', array_column(is_array($activity['changes'] ?? null) ? $activity['changes'] : [], 'subject'), 'Each move is in the activity log, for Undo.');
	}

	public function testRefusesAFieldATargetAlreadyHas(): void
	{
		$this->site();
		$before = $this->file('kitchen.json');

		$response = $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['fields' => [['name' => 'servings']]]]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertStringContainsString('can\'t take field set "kitchen"', is_string($error = self::json($response)['error'] ?? null) ? $error : '');
		$this->assertSame($before, $this->file('kitchen.json'), 'The file is put back.');
	}

	public function testRefusesAFieldAMediaKindAlreadyHas(): void
	{
		$this->site();
		$before = $this->file('kitchen.json');

		$response = $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['targets' => ['media:image'], 'fields' => [['name' => 'alt']]]]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertStringContainsString('media:image can\'t take field set "kitchen"', is_string($error = self::json($response)['error'] ?? null) ? $error : '');
		$this->assertSame($before, $this->file('kitchen.json'));
	}

	public function testOffersEveryPlaceBySource(): void
	{
		$this->site();

		$targets = self::json($this->send('GET', '/fields/sets'))['targets'] ?? null;

		$this->assertContains(['key' => 'type:recipe', 'label' => 'Recipes', 'group' => 'Content types', 'kind' => 'type'], is_array($targets) ? $targets : []);
		$this->assertContains(['key' => 'media:audio', 'label' => 'Audio', 'group' => 'Media files', 'kind' => 'media'], is_array($targets) ? $targets : []);
		$this->assertContains(['key' => 'settings:general', 'label' => 'General', 'group' => 'Settings screens', 'kind' => 'settings'], is_array($targets) ? $targets : []);

		$kinds = self::json($this->send('GET', '/fields/sets'))['kinds'] ?? null;
		$slots = array_column(is_array($kinds) ? $kinds : [], 'slots', 'kind');

		$this->assertSame(['details'], array_column(is_array($slots['type'] ?? null) ? $slots['type'] : [], 'name'), 'Fields stay out of the writing area (D-348).');
		$this->assertSame(['details'], array_column(is_array($slots['media'] ?? null) ? $slots['media'] : [], 'name'));
		$this->assertSame(422, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['targets' => ['type:recipe', 'media:image']]])->getStatusCode(), 'One kind of place a set.');
	}

	public function testRefusesWhatIsntAChange(): void
	{
		$this->site();

		$this->assertSame(422, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['name' => 'stove']])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['targets' => ['recipe']]])->getStatusCode());
		$this->assertSame(422, $this->write('POST', '/fields/sets', ['name' => 'kitchen', 'set' => []])->getStatusCode());
		$this->assertSame(400, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['a', 'b']])->getStatusCode());
	}

	public function testDeletesASet(): void
	{
		$this->site();

		$response = $this->write('DELETE', '/fields/sets/kitchen');

		$this->assertSame(['deleted' => 'kitchen'], self::json($response));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/fields/kitchen.json');
		$this->assertSame(422, $this->write('DELETE', '/fields/sets/nope')->getStatusCode());
	}

	public function testDataSetsCanBeOff(): void
	{
		$this->site(config: 'new Blush\Field\FieldConfig(dataSets: false)');

		$list = self::json($this->send('GET', '/fields/sets'));

		$this->assertFalse($list['create'] ?? null);
		$this->assertSame(422, $this->write('POST', '/fields/sets', ['name' => 'pantry', 'set' => []])->getStatusCode());
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(roles: ['editor']);

		$this->assertSame(403, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['label' => 'Stove']])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/fields/sets/kitchen')->getStatusCode());
	}
}
