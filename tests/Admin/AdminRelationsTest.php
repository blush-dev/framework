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
		$this->assertSame(['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'create' => true], json_decode($this->file('user/data/relations/cuisine.json'), true), 'Written without its name, the file\'s (D-593).');

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
		$this->assertSame(['id' => '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e20', 'title' => 'Soup', 'type' => 'recipe', 'max' => 1, 'room' => ['title' => 'Stew', 'slug' => 'stew', 'left' => 1]], self::json($taken)['full'] ?? null, 'Names the full target, and a published one with room (D-608).');
	}

	public function testCountsWhatNamesEachTargetAgainstTheInverseLimit(): void
	{
		$this->writeTemporaryFile('user/data/relations/variant_of.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"], "multiple": false, "inverse": {"max": 2}}');
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e90\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\nvariant_of: soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e91\n---\n");
		$this->writeTemporaryFile('user/content/recipes/broth.md', "---\ntitle: Broth\nstatus: trash\nvariant_of: soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e92\n---\n");
		$this->site();

		$answer = self::json($this->send('GET', '/references/recipe?upto=50&from=recipe.variant_of'));
		$items  = is_array($answer['items'] ?? null) ? $answer['items'] : [];

		$this->assertSame(2, $answer['inverseMax'] ?? null);
		$this->assertEquals(['soup' => 1, 'stew' => 0], array_column($items, 'taken', 'slug'), 'Drafts count, the trash doesn\'t (D-608).');
		$this->assertNull(self::json($this->send('GET', '/references/recipe?upto=50'))['inverseMax'] ?? null);
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

		$this->assertSame([3, 2, 1, 3], [$referrers['count'] ?? null, $referrers['live'] ?? null, $referrers['drafts'] ?? null, $referrers['editable'] ?? null], 'Every status, and how many are live (D-598).');
		$this->assertSame(['Curry', 'Soup', 'Stew'], array_column(is_array($referrers['entries'] ?? null) ? $referrers['entries'] : [], 'title'));
		$this->assertSame(['id' => '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e32', 'title' => 'Curry', 'type' => 'Recipe', 'status' => 'published', 'relations' => ['Cuisine']], is_array($referrers['entries'] ?? null) ? $referrers['entries'][0] : null, 'With what it links through (D-608).');
		$this->assertSame(['Curry', 'Stew'], array_column(is_array($live = self::json($this->send('GET', "/entries/{$thai}/referrers?live=1"))['entries'] ?? null) ? $live : [], 'title'), 'Only the live ones.');

		$revision = self::json($this->send('GET', "/entries/{$thai}"))['revision'] ?? '';
		$this->assertSame(200, $this->write('DELETE', "/entries/{$thai}?revision=" . (is_string($revision) ? $revision : ''))->getStatusCode());
		$this->assertSame(3, self::json($this->send('GET', "/entries/{$thai}/referrers"))['count'] ?? null, 'The trash keeps its links, so restoring it loses nothing.');

		$deleted = $this->write('DELETE', "/entries/{$thai}?permanently=1&unlink=1");

		$this->assertSame(['deleted' => $thai, 'unlinked' => 3], self::json($deleted));
		$this->assertStringContainsString("cuisine: [indian]\n", $this->file('user/content/recipes/curry.md'), 'Taken out of what links to it.');
		$this->assertStringNotContainsString('thai', $this->file('user/content/recipes/curry.md'), 'In both forms.');
		$this->assertStringNotContainsString('cuisine', $this->file('user/content/recipes/soup.md'), 'A relation left with none goes.');
	}

	public function testListsTheEntriesLinkingToOne(): void
	{
		$thai = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea0';
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: {$thai}\n---\n");
		$this->writeTemporaryFile('user/content/cuisines/indian.md', "---\ntitle: Indian\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea1\n---\n");
		$this->writeTemporaryFile('user/content/cuisines/greek.md', "---\ntitle: Greek\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea2\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\ncuisine: [thai, indian]\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea3\n---\n");
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nstatus: draft\ncuisine: [thai, greek]\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea4\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1ea5\n---\n");
		$this->site();

		$titles = fn (string $query): array => array_column(is_array($entries = self::json($this->send('GET', "/entries?{$query}"))['entries'] ?? null) ? $entries : [], 'title');

		$this->assertSame(['Curry', 'Soup'], $titles("type=recipe&sort=title&linking={$thai}&via=recipe.cuisine"), 'Linked From\'s View All (D-608).');
		$this->assertSame([], $titles("type=recipe&linking={$thai}&via=recipe.other"));
		$this->assertSame(['Indian', 'Thai'], $titles('type=cuisine&sort=title&linked=1'), 'Only what live entries link to; a draft\'s links don\'t count.');
		$this->assertSame(400, $this->send('GET', '/entries?linked=1')->getStatusCode(), 'It needs a type.');
		$this->assertSame(400, $this->send('GET', '/entries?linking=0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eff')->getStatusCode());

		$thaiPage = self::json($this->send('GET', "/entries/{$thai}"));

		$this->assertNull($thaiPage['introduces'] ?? null, 'An entry introduces no archive.');
	}

	public function testDescribesTheArchiveAnIndexPageIntroduces(): void
	{
		$this->writeTemporaryFile('user/content/recipes/index.md', "---\ntitle: All Recipes\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eb0\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eb1\n---\n");
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nstatus: draft\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eb2\n---\n");
		$this->site();

		$index = self::json($this->send('GET', '/entries/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1eb0'));

		$this->assertSame(['url' => '/recipes', 'listed' => 1, 'item' => 'recipe', 'items' => 'recipes', 'drafts' => 1], $index['introduces'] ?? null, 'For its Archive Page row (D-608).');
	}

	public function testSaysWhichOfSeveralEntriesAreLinkedTo(): void
	{
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e80\n---\n");
		$this->writeTemporaryFile('user/content/cuisines/plain.md', "---\ntitle: Plain\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e81\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e82\n---\n");
		$this->site();

		$answer = self::json($this->write('POST', '/entries/referrers', ['ids' => ['0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e80', '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e81']]));

		$this->assertSame([['id' => '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e80', 'title' => 'Thai', 'live' => 1]], $answer['linked'] ?? null, 'For a bulk change\'s warning (D-598).');
		$this->assertSame(400, $this->write('POST', '/entries/referrers', ['ids' => []])->getStatusCode());
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

	public function testTheEditorFollowsTheRelation(): void
	{
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Core\\AppConfig::fromArray(['environment' => 'development', 'languages' => ['fr' => 'fr_FR']]);\n");
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe"], "to": ["cuisine"], "create": true, "ordered": true, "min": 2, "max": 3, "translations": "add"}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e60\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.md', "---\ntitle: Curry\ncuisine: [thai]\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e61\n---\n");
		$this->writeTemporaryFile('user/content/recipes/curry.fr.md', "---\ntitle: Curry\ntranslation_of: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e61\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e62\n---\n");
		$this->site();

		$entry = self::json($this->send('GET', '/entries/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e61'));
		$type  = $entry['type'] ?? null;
		$field = array_find(is_array($type) && is_array($type['fields'] ?? null) ? $type['fields'] : [], static fn (mixed $field): bool => is_array($field) && ($field['name'] ?? null) === 'cuisine');

		$this->assertIsArray($field);
		$this->assertSame(['name' => 'cuisine', 'key' => 'recipe.cuisine', 'label' => '', 'ordered' => true, 'min' => 2, 'max' => 3, 'create' => true, 'control' => 'tokens'], $field['relation'] ?? null, 'What the picker follows (D-599).');

		$thai = self::json($this->send('GET', '/entries/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e60'));

		$this->assertSame([[
			'key'      => 'recipe.cuisine',
			'label'    => '',
			'type'     => 'Recipes',
			'typeName' => 'recipe',
			'relation' => 'Cuisine',
			'count'    => 1,
			'drafts'   => 0,
			'entries'  => [['id' => '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e61', 'title' => 'Curry', 'status' => 'published', 'type' => 'Recipe', 'date' => null, 'image' => null]]
		]], $thai['linkedFrom'] ?? null, 'What links to it, by relation; a translation\'s own link to its original isn\'t one.');
		$this->assertEquals((object) [], (object) ($entry['inherited'] ?? null), 'An original inherits nothing.');

		$translation = self::json($this->send('GET', '/entries/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e62'));

		$this->assertSame(['cuisine' => ['rule' => 'add', 'values' => ['thai'], 'title' => 'Curry', 'language' => 'English (United States)']], $translation['inherited'] ?? null, 'A translation shows what it uses from its original.');
	}

	public function testTakesEveryOptionTheFormSends(): void
	{
		$this->site();

		$sent = [
			'name' => 'pairs_with', 'kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'label' => 'Pairs with',
			'multiple' => true, 'ordered' => true, 'min' => 1, 'max' => 3, 'create' => false, 'symmetric' => true,
			'translations' => 'own', 'control' => 'tokens',
			'inverse' => ['archive' => 'pairings', 'label' => 'Paired with', 'max' => 5]
		];

		$this->assertSame(201, $this->write('POST', '/relations', $sent)->getStatusCode());
		$this->reload();

		$listed = self::json($this->send('GET', '/relations'))['relations'] ?? [];
		$found  = array_find(is_array($listed) ? $listed : [], static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === 'pairs_with');

		$this->assertIsArray($found);
		$this->assertSame(
			['Pairs with', true, 1, 3, true, 'own', 'tokens', ['label' => 'Paired with', 'page' => false, 'archive' => 'pairings', 'types' => [], 'max' => 5]],
			[$found['label'] ?? null, $found['ordered'] ?? null, $found['min'] ?? null, $found['max'] ?? null, $found['symmetric'] ?? null, $found['translations'] ?? null, $found['control'] ?? null, $found['inverse'] ?? null],
			'Everything the form sets (D-599).'
		);

		$this->assertSame(422, $this->write('PATCH', '/relations/pairs_with', [...$sent, 'control' => 'wheel'])->getStatusCode());
	}

	/**
	 * A site whose recipes pair with others, and are filed under cuisines.
	 */
	private function pairedSite(): void
	{
		$this->writeTemporaryFile('user/data/types/drink.yaml', "folder: drinks\n");
		$this->writeTemporaryFile('user/data/relations/pairs.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"]}');
		$this->writeTemporaryFile('user/data/relations/cuisine.json', '{"kind": "classify", "from": ["recipe", "drink"], "to": ["cuisine"]}');
		$this->writeTemporaryFile('user/content/cuisines/thai.md', "---\ntitle: Thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e70\n---\n");
		$this->writeTemporaryFile('user/content/recipes/soup.md', "---\ntitle: Soup\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e71\n---\n");
		$this->writeTemporaryFile('user/content/recipes/bread.md', "---\ntitle: Bread\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e72\n---\n");
		$this->writeTemporaryFile('user/content/recipes/stew.md', "---\ntitle: Stew\npairs: [soup, bread]\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e73\n---\n");
		$this->writeTemporaryFile('user/content/drinks/tea.md', "---\ntitle: Tea\ncuisine: thai\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74\n---\n");
		$this->site();
	}

	public function testRefusesChangesThatWouldBreakWhatEntriesHave(): void
	{
		$this->pairedSite();

		$retarget = self::json($this->write('POST', '/relations/pairs/check', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['drink']]));

		$this->assertStringContainsString('1 entry has a value in "pairs", so it can\'t point at another type', is_string($retarget['refusal'] ?? null) ? $retarget['refusal'] : '', 'D-600.');
		$this->assertSame(422, $this->write('PATCH', '/relations/pairs', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['drink']])->getStatusCode(), 'Saving refuses it too.');

		$one = self::json($this->write('POST', '/relations/pairs/check', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'multiple' => false]));

		$this->assertStringContainsString('more than one value in "pairs"', is_string($one['refusal'] ?? null) ? $one['refusal'] : '');

		$limits = self::json($this->write('POST', '/relations/pairs/check', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'max' => 1]));

		$this->assertArrayHasKey('refusal', $limits);
		$this->assertNull($limits['refusal'], 'Tightening limits only warns.');
		$this->assertSame(['1 entry has more than 1; lint reports it, and it won\'t publish again until it has fewer.'], $limits['warnings'] ?? null);
	}

	public function testANewKeyKeepsTheOldOneOrRewritesTheFiles(): void
	{
		$this->pairedSite();

		$this->assertSame(1, self::json($this->write('POST', '/relations/pairs/check', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'field' => 'goes_with']))['moved'] ?? null);

		$kept = self::json($this->write('PATCH', '/relations/pairs', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'field' => 'goes_with']));

		$this->assertSame(['goes_with', ['pairs']], [$kept['field'] ?? null, $kept['aliases'] ?? null], 'The old key is still read (D-600).');
		$this->assertStringContainsString('pairs: [soup, bread]', $this->file('user/content/recipes/stew.md'), 'No file changes.');

		$this->reload();
		$this->write('PATCH', '/relations/pairs', ['kind' => 'reference', 'from' => ['recipe'], 'to' => ['recipe'], 'field' => 'served_with', 'rewrite' => true]);

		$this->assertStringContainsString('served_with: [soup, bread]', $this->file('user/content/recipes/stew.md'), 'Moved to the new key.');
		$this->assertStringNotContainsString("\npairs:", $this->file('user/content/recipes/stew.md'));
	}

	public function testUnfilingOrRemovingCanStripWhatEntriesHave(): void
	{
		$this->pairedSite();

		$check = self::json($this->write('POST', '/relations/cuisine/check', ['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine']]));

		$this->assertSame([['drink'], 1], [$check['unfiled'] ?? null, $check['stripped'] ?? null]);

		$this->write('PATCH', '/relations/cuisine', ['kind' => 'classify', 'from' => ['recipe'], 'to' => ['cuisine'], 'strip' => true]);

		$this->assertStringNotContainsString('cuisine', $this->file('user/content/drinks/tea.md'), 'Taken out of the type it no longer files.');
		$this->assertStringContainsString('cuisine: thai', $this->file('user/content/recipes/stew.md'));
		$this->reload();

		$this->assertSame(['entries' => 1], self::json($this->send('GET', '/relations/pairs/uses')));
		$this->assertSame(['deleted' => 'pairs', 'stripped' => 1], self::json($this->write('DELETE', '/relations/pairs?strip=1')));
		$this->assertStringNotContainsString('pairs', $this->file('user/content/recipes/stew.md'));
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
		$this->assertSame("# Kinds of writing.\nfolder: genres\nhierarchical: true\norder: position\nllms: false\n", $this->file('user/data/types/genre.yaml'), 'Its other keys and comments stay.');
		$this->assertSame(['kind' => 'classify', 'from' => ['page'], 'to' => ['genre'], 'create' => true, 'inverse' => ['listing' => ['perPage' => 5]]], json_decode($this->file('user/data/relations/genre.json'), true));
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
