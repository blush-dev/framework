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
use Blush\Admin\FieldSetEditController;
use Blush\Admin\FieldSetsController;
use Blush\Content\Type\DataFieldSetWriter;
use Blush\Data\DataKeys;

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
	 * `kitchen` data set on it, and an `seo` set from `config/fields.php`.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $config = ''): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"fields": [{"name": "servings", "type": "number"}]}');
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"label": "In the Kitchen", "targets": ["type:recipe", "type:gone"], "fields": [{"name": "oven", "type": "enum", "options": ["gas", "electric"]}]}');

		$config = $config === '' ? "new Blush\\Field\\FieldConfig(sets: [new Blush\\Field\\FieldSet('seo', [], ['type:page'])])" : $config;

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

	public function testListsTheSets(): void
	{
		$this->site();

		$list = self::json($this->send('GET', '/fields/sets'));
		$sets = array_column(is_array($list['sets'] ?? null) ? $list['sets'] : [], null, 'name');

		$this->assertSame(['kitchen', 'seo'], array_keys($sets));
		$this->assertTrue($list['create'] ?? null);

		$this->assertSame([
			'name'        => 'kitchen',
			'label'       => 'In the Kitchen',
			'description' => '',
			'kind'        => 'type',
			'slot'        => 'details',
			'origin'      => 'data',
			'editable'    => true,
			'file'        => 'user/data/fields/kitchen.json',
			'targets'     => [['key' => 'type:recipe', 'label' => 'Recipes', 'found' => true], ['key' => 'type:gone', 'label' => 'type:gone', 'found' => false]],
			'fields'      => 1
		], $sets['kitchen']);
		$seo = is_array($sets['seo'] ?? null) ? $sets['seo'] : [];

		$this->assertSame('config', $seo['origin'] ?? null);
		$this->assertFalse($seo['editable'] ?? null);
	}

	public function testDescribesOneSet(): void
	{
		$this->site();

		$set = self::json($this->send('GET', '/fields/sets/kitchen'));

		$this->assertSame([['name' => 'oven', 'type' => 'enum', 'options' => ['gas', 'electric']]], $set['fields'] ?? null);
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
			'fields'  => [['name' => 'shelf', 'type' => 'text', 'control' => 'mono']]
		]]);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('pantry', self::json($response)['name'] ?? null);
		$this->assertSame('details', self::json($response)['slot'] ?? null, 'The kind\'s default.');
		$this->assertSame(['targets' => ['type:recipe'], 'fields' => [['name' => 'shelf', 'type' => 'text', 'control' => 'mono']]], json_decode($this->file('pantry.json'), true), 'A new set is JSON (D-490), and a label the name gives is left out.');
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
		], json_decode($this->file('kitchen.json'), true), 'Its other keys stay.');
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
		$this->assertSame(422, $this->write('PATCH', '/fields/sets/seo', ['set' => ['label' => 'Search']])->getStatusCode(), 'A config set is shown, not edited.');
		$this->assertSame(422, $this->write('POST', '/fields/sets', ['name' => 'kitchen', 'set' => []])->getStatusCode());
		$this->assertSame(400, $this->write('PATCH', '/fields/sets/kitchen', ['set' => ['a', 'b']])->getStatusCode());
	}

	public function testDeletesASet(): void
	{
		$this->site();

		$response = $this->write('DELETE', '/fields/sets/kitchen');

		$this->assertSame(['deleted' => 'kitchen'], self::json($response));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/fields/kitchen.json');
		$this->assertSame(422, $this->write('DELETE', '/fields/sets/seo')->getStatusCode());
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
