<?php

/**
 * Admin relations tests.
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
use Blush\Admin\HealthController;
use Blush\Admin\RelationsController;
use Blush\Admin\TypeEditController;
use Blush\Content\Relation\DataRelationWriter;
use Blush\Content\Relation\RelationLoader;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\TaxonomyMigration;

#[CoversClass(RelationsController::class)]
#[CoversClass(TypeEditController::class)]
#[CoversClass(DataRelationWriter::class)]
#[CoversClass(RelationLoader::class)]
#[CoversClass(TaxonomyMigration::class)]
#[CoversClass(HealthController::class)]
final class AdminRelationsTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['owner']): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.yaml', "folder: recipes\n");
		$this->writeTemporaryFile('user/data/types/cuisine.yaml', "# Kinds of food.\nfolder: cuisines\norder: position\n");
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

	/**
	 * Loads the types again, as the next request would: these requests
	 * share one application.
	 */
	private function reload(): void
	{
		$this->app->container()->instance(ContentTypes::class, $this->app->container()->make(ContentTypeLoader::class)->load());
	}

	private function file(string $relative): string
	{
		return (string) @file_get_contents($this->temporaryDirectory() . "/{$relative}");
	}

	public function testCreatesChangesAndDeletesARelation(): void
	{
		$this->site();

		$created = $this->write('POST', '/relations', ['name' => 'cuisine', 'kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'create' => true]);

		$this->assertSame(201, $created->getStatusCode());
		$this->assertEquals(['name' => 'cuisine', 'kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'create' => true, 'editable' => true, 'origin' => 'data'], array_intersect_key(self::json($created), array_flip(['name', 'kind', 'from', 'to', 'create', 'editable', 'origin'])));
		$this->assertSame(['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'create' => true, 'inverse' => ['archive' => true]], json_decode($this->file('user/data/relations/cuisine.json'), true), 'Written without its name, the file\'s (D-593).');

		$this->reload();

		$listed = self::json($this->send('GET', '/relations'))['relations'] ?? [];

		$this->assertSame(['cuisine'], array_column(is_array($listed) ? $listed : [], 'name'));

		$type = self::json($this->send('GET', '/types/recipe'));

		$this->assertSame(['cuisine'], $type['taxonomies'] ?? null, 'Recipes are filed under cuisines.');
		$this->assertSame(['cuisine'], array_column(is_array($type['relations'] ?? null) ? $type['relations'] : [], 'name'));
		$this->assertTrue(self::json($this->send('GET', '/types/cuisine'))['terms'] ?? null);

		$changed = $this->write('PATCH', '/relations/cuisine', ['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'min' => 1]);

		$this->assertSame(200, $changed->getStatusCode());
		$this->assertSame(1, self::json($changed)['min'] ?? null);
		$this->assertFalse(self::json($changed)['create'] ?? null, 'A change sends the whole definition.');

		$this->assertSame(200, $this->write('DELETE', '/relations/cuisine')->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/relations/cuisine.json');
		$this->reload();
		$this->assertFalse(self::json($this->send('GET', '/types/cuisine'))['terms'] ?? null);
	}

	public function testRefusesWhatDoesNotFitAndWritesNothing(): void
	{
		$this->site();

		$cases = [
			['name' => 'cuisine', 'kind' => 'classify', 'from' => ['nope'], 'to' => ['cuisine']],
			['name' => 'dish', 'kind' => 'classify', 'to' => ['cuisine']],
			['name' => 'Bad', 'kind' => 'reference', 'to' => ['recipe']],
			['name' => 'cuisine', 'kind' => 'sideways', 'to' => ['cuisine']]
		];

		foreach ($cases as $definition) {
			$response = $this->write('POST', '/relations', $definition);

			$this->assertSame(422, $response->getStatusCode(), (string) json_encode($definition));
			$this->assertNotSame('', self::json($response)['error'] ?? '');
		}

		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/data/relations/cuisine.json');
		$this->assertSame(422, $this->write('PATCH', '/relations/cuisine', ['kind' => 'classify', 'to' => ['cuisine']])->getStatusCode(), 'Only data relations change.');
		$this->assertSame(422, $this->write('DELETE', '/relations/cuisine')->getStatusCode());
	}

	public function testWontReplaceARelationDefinedInCode(): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['relations' => ['cuisine' => ['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine']]]]);\n");
		$this->site();

		$response = $this->write('POST', '/relations', ['name' => 'cuisine', 'kind' => 'classify', 'to' => ['cuisine']]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertSame('The "cuisine" relation is already defined in code; change it there.', self::json($response)['error'] ?? null);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/relations/cuisine.json');
		$listed = self::json($this->send('GET', '/relations'))['relations'] ?? [];

		$this->assertSame([false], array_column(is_array($listed) ? $listed : [], 'editable'), 'It\'s shown, not edited.');
	}

	public function testNeedsTheSettingsCapability(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->write('POST', '/relations', ['name' => 'cuisine', 'kind' => 'classify', 'to' => ['cuisine']])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/relations/cuisine')->getStatusCode());
	}

	public function testMigratesDataTaxonomiesKeepingWhatTheyHad(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.yaml', "# Kinds of writing.\nkind: taxonomy\nfolder: genres\ntypes: [page]\nhierarchical: true\ntermListing:\n  perPage: 5\n");
		$this->site();

		$types = $this->app->container()->make(ContentTypes::class);

		$this->assertSame(['genre'], $types->legacy, 'Read as a collection and its relation until it\'s migrated (D-591).');
		$this->assertSame(['page'], $types->classification('genre')?->from);
		$this->assertTrue($types->nestsByParent('genre'));

		$this->assertSame(['genre'], self::json($this->write('POST', '/health'))['taxonomies'] ?? null);

		$checks = self::json($this->send('GET', '/health/site'))['checks'] ?? [];
		$check  = array_find(is_array($checks) ? $checks : [], static fn (mixed $check): bool => is_array($check) && ($check['key'] ?? null) === 'taxonomies');

		$this->assertSame(['warning', 'Taxonomies', '1 content type is still written as a taxonomy, which Blush reads as a collection and its relation until it\'s migrated.'], [$check['status'] ?? null, $check['label'] ?? null, $check['message'] ?? null]);

		$migrated = self::json($this->write('POST', '/health/taxonomies'));

		$this->assertSame(['genre' => ['user/data/types/genre.yaml', 'user/data/relations/genre.json']], $migrated['migrated'] ?? null);
		$this->assertSame("# Kinds of writing.\nfolder: genres\nhierarchical: true\norder: position\nllms: false\npeople: false\n", $this->file('user/data/types/genre.yaml'), 'Its other keys and comments stay.');
		$this->assertSame(['kind' => 'classify', 'from' => ['page'], 'to' => ['genre'], 'create' => true, 'inverse' => ['archive' => true, 'listing' => ['perPage' => 5]]], json_decode($this->file('user/data/relations/genre.json'), true));
		$this->assertSame([], $this->app->container()->make(TaxonomyMigration::class)->report());
	}

	public function testMigratingNeedsTheSettingsCapabilityToo(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.yaml', "kind: taxonomy\n");
		$this->site(['editor']);

		$this->assertSame(403, $this->write('POST', '/health/taxonomies')->getStatusCode());
		$this->assertStringContainsString('kind: taxonomy', $this->file('user/data/types/genre.yaml'));
	}
}
