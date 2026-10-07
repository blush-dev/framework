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
use Blush\Content\Index\Indexer;
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

	/**
	 * Returns an answer's error message.
	 */
	private static function message(ResponseInterface $response): string
	{
		$error = self::json($response)['error'] ?? null;

		return is_string($error) ? $error : '';
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

	public function testCreatesTargetsAsTheyreTyped(): void
	{
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"], "create": true}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e10\n---\n");
		$this->site();

		$created = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Curry', 'status' => 'published', 'set' => ['cuisine' => ['thai', 'Street Food']]]);

		$this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
		$this->assertStringContainsString("title: \"Street Food\"\n", $this->file('user/content/cuisines/street-food.md'), 'Written, titled as typed (D-596).');
		$this->assertStringNotContainsString('status:', $this->file('user/content/cuisines/street-food.md'), 'Published.');

		$recipe = $this->file('user/content/recipes/curry.md');

		$this->assertStringContainsString("cuisine: [thai, street-food]\n", $recipe, 'Written as slugs.');
		$this->assertStringContainsString("  cuisine:\n    thai: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e10\n    street-food: ", $recipe, 'And linked by id.');
	}

	public function testRefusesToCreateTargetsTheAccountCant(): void
	{
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"], "create": true}');
		$this->site(['contributor']);

		$refused = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Curry', 'set' => ['cuisine' => ['Street Food']]]);

		$this->assertSame(403, $refused->getStatusCode(), (string) $refused->getBody());
		$this->assertSame('cuisine', self::json($refused)['field'] ?? null);
		$this->assertStringContainsString('Street Food', self::message($refused));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/recipes/curry.md', 'Nothing is written.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/cuisines/street-food.md');
	}

	public function testPublishingKeepsRelationsInTheirLimits(): void
	{
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"], "min": 2, "max": 3}');
		$this->writeTemporaryFile('user/data/relations/variant_of.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"], "multiple": false, "inverse": {"max": 1}}');
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e20\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\ncuisine: [a, b]\nvariant_of: soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e21\n---\n");
		$this->site();

		$few = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Curry', 'status' => 'published', 'set' => ['cuisine' => ['a']]]);

		$this->assertSame(422, $few->getStatusCode(), (string) $few->getBody());
		$this->assertSame('cuisine', self::json($few)['field'] ?? null);
		$this->assertStringContainsString('needs at least 2 to publish; it has 1', self::message($few));

		$this->assertSame(201, $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Curry', 'set' => ['cuisine' => ['a']]])->getStatusCode(), 'A draft may have fewer (D-585).');

		$many = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Broth', 'status' => 'published', 'set' => ['cuisine' => ['a', 'b', 'c', 'd']]]);

		$this->assertStringContainsString('takes at most 3; it has 4', self::message($many));

		$taken = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Chowder', 'status' => 'published', 'set' => ['cuisine' => ['a', 'b'], 'variant_of' => 'soup']]);

		$this->assertSame(422, $taken->getStatusCode(), (string) $taken->getBody());
		$this->assertSame('variant_of', self::json($taken)['field'] ?? null);
		$this->assertStringContainsString('"Soup" already has the most entries naming it in variant_of (1).', self::message($taken), 'The inverse\'s max counts the others (D-587).');
	}

	public function testTellsWhatLinksToAnEntryAndTakesItOutOnDelete(): void
	{
		$thai = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e30';
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: {$thai}\n---\n");
		$this->writeTemporaryFile('user/content/cuisines/indian.md', "---\ntitle: Indian\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e31\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\ncuisine: [thai, indian]\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e32\n---\n");
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nstatus: draft\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e33\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e34\n---\n");
		$this->site();

		$referrers = self::json($this->send('GET', "/entries/{$thai}/referrers"));

		$this->assertSame([3, 2, 3], [$referrers['count'] ?? null, $referrers['live'] ?? null, $referrers['editable'] ?? null], 'Every status, and how many are live (D-598).');
		$this->assertSame(['Curry', 'Soup', 'Stew'], array_column(is_array($referrers['entries'] ?? null) ? $referrers['entries'] : [], 'title'));

		$revision = self::json($this->send('GET', "/entries/{$thai}"))['revision'] ?? '';
		$this->assertSame(200, $this->write('DELETE', "/entries/{$thai}?revision=" . (is_string($revision) ? $revision : ''))->getStatusCode());
		$this->assertSame(3, self::json($this->send('GET', "/entries/{$thai}/referrers"))['count'] ?? null, 'The trash keeps its links, so restoring it loses nothing.');

		$deleted = $this->write('DELETE', "/entries/{$thai}?permanently=1&unlink=1");

		$this->assertSame(['deleted' => $thai, 'unlinked' => 3], self::json($deleted));
		$this->assertStringContainsString("cuisine: [indian]\n", $this->file('user/content/recipes/curry.md'), 'Taken out of what links to it.');
		$this->assertStringNotContainsString('thai', $this->file('user/content/recipes/curry.md'), 'In both forms.');
		$this->assertStringNotContainsString('cuisine', $this->file('user/content/recipes/soup.md'), 'A relation left with none goes.');
	}

	public function testDeletingCanLeaveWhatLinksToIt(): void
	{
		$thai = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e40';
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nstatus: trash\nid: {$thai}\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e41\n---\n");
		$this->site();

		$this->assertSame(['deleted' => $thai, 'unlinked' => 0], self::json($this->write('DELETE', "/entries/{$thai}?permanently=1")));
		$this->assertStringContainsString('cuisine: thai', $this->file('user/content/recipes/curry.md'));
	}

	public function testTheTrashDoesntCountTowardAnInverseLimit(): void
	{
		$this->writeTemporaryFile('user/data/relations/variant_of.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"], "multiple": false, "inverse": {"max": 1}}');
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e50\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\nstatus: trash\nvariant_of: soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e51\n---\n");
		$this->writeTemporaryFile('user/content/recipes/broth.md', "---\ntitle: Broth\nstatus: draft\nvariant_of: soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e52\n---\n");
		$this->site();

		$taken = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Chowder', 'status' => 'published', 'set' => ['variant_of' => 'soup']]);

		$this->assertSame(422, $taken->getStatusCode(), 'A draft counts: publishing it would break the limit (D-598).');

		unlink($this->temporaryDirectory() . '/user/content/recipes/broth.md');
		$this->app->container()->make(Indexer::class)->index();

		$second = $this->write('POST', '/entries', ['type' => 'recipe', 'title' => 'Chowder', 'status' => 'published', 'set' => ['variant_of' => 'soup']]);
		$this->assertSame(201, $second->getStatusCode(), 'The trash doesn\'t. ' . $second->getBody());
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
