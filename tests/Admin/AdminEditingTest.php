<?php

/**
 * Admin editing API tests.
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
use Blush\Admin\EntryController;
use Blush\Admin\EntryHandles;
use Blush\Admin\IndexPage;
use Blush\Admin\InvalidEdit;
use Blush\Content\Index\Indexer;
use Blush\Content\Lint\Linter;
use Blush\Tests\WritesContentConfig;

#[CoversClass(EntryController::class)]
#[CoversClass(EntryHandles::class)]
#[CoversClass(IndexPage::class)]
#[CoversClass(InvalidEdit::class)]
#[CoversClass(Linter::class)]
final class AdminEditingTest extends TestCase
{
	use BootsAdmin;
	use WritesContentConfig;

	private const string FLAME = '_posts/2022-03-29.flame.md';

	/**
	 * Its id.
	 */
	private const string FLAME_ID = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74';

	private string $token = '';

	/**
	 * Boots a site with dated posts: Jane's published post (with 1.x's
	 * `date`, and an undeclared key), her draft, and Sam's post.
	 *
	 * @param list<string>                        $roles
	 * @param array<string, array<string, mixed>> $types   More types, by name.
	 * @param list<string>                        $credits The types credited to authors.
	 */
	private function site(array $roles = ['editor'], array $types = [], array $credits = ['post']): void
	{
		$types = ['post' => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives']], ...$types];
		$this->contentConfig(['types' => $types, 'relations' => ['authors' => ['kind' => 'credit', 'from' => $credits, 'to' => ['profile'], 'aliases' => ['author']]]]);
		$this->writeTemporaryFile('user/content/' . self::FLAME, "---\ntitle     : \"Rekindling the Flame\"\nauthors   : jane\ndate      : 2022-03-29 23:00:00 -6\nmood      : hopeful\nid        : " . self::FLAME_ID . "\n---\n\nThe body.\n");
		$this->writeTemporaryFile('user/content/_posts/2023-01-01.idea.md', "---\ntitle: An Idea\nauthors: jane\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/_posts/2021-05-05.sams.md', "---\ntitle: Sam's Post\nauthors: sam\npublished: 2021-05-05 09:00:00 -05:00\n---\n");

		$this->boot(roles: $roles);
		$this->login();

		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);
		$this->token = $token;
	}

	/**
	 * Returns a value inside a JSON answer by its keys, or `null`.
	 */
	private static function at(mixed $value, string ...$keys): mixed
	{
		foreach ($keys as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function call(string $method, string $path, array $data = []): ResponseInterface
	{
		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => $this->token]);
	}

	/**
	 * @return array<mixed>
	 */
	private function load(string $id): array
	{
		$response = $this->call('GET', $this->entryPath($id));
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

		return self::json($response);
	}

	private function revision(string $id): string
	{
		$revision = $this->load($id)['revision'] ?? null;
		$this->assertIsString($revision);

		return $revision;
	}

	private function file(string $id): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/user/content/{$id}");
	}

	/**
	 * @return list<array<mixed>>
	 */
	private function trash(string $query = ''): array
	{
		$trash = self::json($this->call('GET', "/entries?status=trash{$query}"))['entries'] ?? null;
		$this->assertIsArray($trash);

		/** @var list<array<mixed>> $trash */
		return $trash;
	}

	public function testTrashRestoresAsADraftDeletesAndEmpties(): void
	{
		$this->site();
		$sams = '_posts/2021-05-05.sams.md';

		$this->assertSame(self::FLAME_ID, self::json($this->call('DELETE', $this->entryPath(self::FLAME) . '?revision=' . $this->revision(self::FLAME)))['trashed'] ?? null);
		$this->assertSame(200, $this->call('DELETE', $this->entryPath($sams) . '?revision=' . $this->revision($sams))->getStatusCode());

		$this->assertEqualsCanonicalizing(['Rekindling the Flame', "Sam's Post"], array_column($this->trash(), 'title'));
		$this->assertSame(['post', 'post'], array_column($this->trash('&type=post'), 'type'));
		$this->assertSame([], $this->trash('&type=page'));
		$this->assertIsString($this->trash()[0]['trashed'] ?? null, 'When it was trashed.');
		$listed = self::at(self::json($this->call('GET', '/entries')), 'entries');
		$this->assertIsArray($listed);
		$this->assertNotContains(self::FLAME_ID, array_column($listed, 'id'), 'Every status but the trash.');

		$shown = $this->load(self::FLAME);

		$this->assertSame(['trash', false, true], [$shown['status'] ?? null, self::at($shown, 'can', 'edit'), self::at($shown, 'can', 'delete')], 'Looked at, not edited.');
		$this->assertIsString($shown['trashed'] ?? null);
		$this->assertSame("The body.\n", $shown['body'] ?? null);
		$this->assertSame(422, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $shown['revision'] ?? '', 'set' => ['title' => 'No']])->getStatusCode(), 'Restored first.');
		$this->assertSame(422, $this->call('POST', $this->entryPath(self::FLAME) . '/duplicate')->getStatusCode());
		$this->assertSame(422, $this->call('DELETE', $this->entryPath(self::FLAME) . '?revision=' . $this->revision(self::FLAME))->getStatusCode(), 'Already in the trash.');
		$this->assertSame(422, $this->call('DELETE', $this->entryPath('_posts/2023-01-01.idea.md') . '?permanently=1')->getStatusCode(), 'Only the trash is deleted for good.');

		$restored = $this->call('POST', $this->entryPath(self::FLAME) . '/restore');

		$this->assertSame(['id' => self::FLAME_ID], self::json($restored), 'It keeps its id.');
		$this->assertSame('draft', $this->load(self::FLAME)['status'] ?? null, 'A restored entry is never live again by itself.');
		$this->assertStringContainsString("mood      : hopeful\nstatus: draft\n", $this->file(self::FLAME));
		$this->assertStringNotContainsString('trashed', $this->file(self::FLAME));
		$this->assertSame(404, $this->call('POST', $this->entryPath(self::FLAME) . '/restore')->getStatusCode(), 'It isn\'t in the trash now.');

		$samsId = $this->idOf($sams);

		$this->assertSame(['deleted' => $samsId, 'unlinked' => 0], self::json($this->call('DELETE', "/entries/{$samsId}?permanently=1")));
		$this->assertFileDoesNotExist($this->temporaryDirectory() . "/user/content/{$sams}");
		$this->assertSame([], $this->trash());

		$idea = '_posts/2023-01-01.idea.md';
		$this->call('DELETE', $this->entryPath($idea) . '?revision=' . $this->revision($idea));

		$this->assertSame(400, $this->call('POST', '/entries/empty-trash', ['type' => 'missing'])->getStatusCode());
		$this->assertSame(['deleted' => 1], self::json($this->call('POST', '/entries/empty-trash', ['type' => 'post'])));
		$this->assertSame([], $this->trash());
	}

	public function testUndoPutsATrashedEntryBackAsItWas(): void
	{
		$this->site();
		$before  = $this->file(self::FLAME);
		$trashed = self::json($this->call('DELETE', $this->entryPath(self::FLAME) . '?revision=' . $this->revision(self::FLAME)));
		$restore = $trashed['restore'] ?? null;

		$this->assertIsArray($restore);
		$this->assertArrayHasKey('status', $restore);
		$this->assertNull($restore['status'], 'Its file names no status.');
		$this->assertSame(422, $this->call('POST', $this->entryPath(self::FLAME) . '/restore', ['status' => 'trash'])->getStatusCode());
		$this->assertSame(428, $this->call('POST', $this->entryPath(self::FLAME) . '/restore', ['status' => 'published'])->getStatusCode(), 'Only as it was.');
		$this->assertSame(409, $this->call('POST', $this->entryPath(self::FLAME) . '/restore', ['status' => 'published', 'revision' => 'stale'])->getStatusCode());

		$this->assertSame(200, $this->call('POST', $this->entryPath(self::FLAME) . '/restore', ['status' => $restore['status'], 'revision' => $restore['revision'] ?? null])->getStatusCode());
		$this->assertSame('published', $this->load(self::FLAME)['status'] ?? null);
		$this->assertSame($before, $this->file(self::FLAME), 'The file is as it was.');

		$idea  = '_posts/2023-01-01.idea.md';
		$draft = self::json($this->call('DELETE', $this->entryPath($idea) . '?revision=' . $this->revision($idea)))['restore'] ?? null;

		$this->assertIsArray($draft);
		$this->assertSame('draft', $draft['status'] ?? null, 'A draft goes back a draft.');
	}

	public function testATrashedEntryKeepsItsAddress(): void
	{
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->site();
		$this->call('DELETE', $this->entryPath('about.md') . '?revision=' . $this->revision('about.md'));

		$created = $this->call('POST', '/entries', ['type' => 'page', 'title' => 'About']);

		$this->assertSame(422, $created->getStatusCode());
		$this->assertSame('“About” in the trash has this address; restore it, or delete it permanently, first.', self::json($created)['error'] ?? null);
	}

	public function testAuthorsHandleOnlyTheirOwnTrash(): void
	{
		$this->writeTemporaryFile('user/content/_posts/2020-01-01.old.md', "---\ntitle: Old\nauthors: sam\nstatus: trash\n---\n");
		$this->site(['author']);

		$this->call('DELETE', $this->entryPath('_posts/2023-01-01.idea.md') . '?revision=' . $this->revision('_posts/2023-01-01.idea.md'));

		$this->assertSame(['An Idea'], array_column($this->trash(), 'title'), 'Sam\'s trash is his.');
		$this->assertSame(403, $this->call('POST', $this->entryPath('_posts/2020-01-01.old.md') . '/restore')->getStatusCode());
		$this->assertSame(403, $this->call('GET', $this->entryPath('_posts/2020-01-01.old.md'))->getStatusCode(), 'Nor can he look at it.');
		$this->assertSame(['deleted' => 1], self::json($this->call('POST', '/entries/empty-trash', ['type' => 'post'])));
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/2020-01-01.old.md');
	}

	public function testOffersOnlyTheTaxonomiesThatGroupTheType(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.json', '{"taxonomy": true, "folder": "genres", "types": ["page"]}');
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/data/types/shelf.json', '{"taxonomy": true, "types": ["post"]}');
		$this->writeTemporaryFile('user/data/types/mood.json', '{"taxonomy": true}');
		$this->site();

		$names = static fn (array $entry): array => array_column(is_array($entry['type'] ?? null) && is_array($entry['type']['fields'] ?? null) ? $entry['type']['fields'] : [], 'name');
		$post  = $names($this->load('_posts/2023-01-01.idea.md'));

		$this->assertContains('shelf', $post, 'It groups posts.');
		$this->assertContains('mood', $post, 'It groups every type.');
		$this->assertNotContains('genre', $post, 'It groups pages only.');
		$this->assertContains('genre', $names($this->load('about.md')), 'Pages have it.');

		$this->writeTemporaryFile('user/content/_posts/2023-01-01.idea.md', "---\ntitle: An Idea\nauthors: jane\nstatus: draft\ngenre: essay\nid: {$this->idOf('_posts/2023-01-01.idea.md')}\n---\n");
		$this->app->container()->make(Indexer::class)->index();
		$this->assertNotContains('genre', $names($this->load('_posts/2023-01-01.idea.md')), 'Genres file only pages (D-593); a post\'s `genre` is other front matter.');

		$this->writeTemporaryFile('user/content/genres/essay.md', "---\ntitle: Essay\nid: 0199b6e2-0000-7000-8000-0000000000ab\n---\n");
		$this->app->container()->make(Indexer::class)->index();
		$this->assertNotContains('mood', $names($this->load('genres/essay.md')), 'A term isn\'t in "every type".');
	}

	public function testLoadsAnEntryForEditing(): void
	{
		$this->site();

		$entry = $this->load(self::FLAME);

		$this->assertIsArray($entry['values'] ?? null);
		$this->assertSame('Rekindling the Flame', $entry['values']['title'] ?? null);
		$this->assertSame('2022-03-29T23:00:00-06:00', $entry['values']['published'] ?? null, 'The 1.x `date` is the `published` field, as parsed.');
		$this->assertSame(['mood' => 'hopeful'], $entry['extra'] ?? null);
		$this->assertSame("The body.\n", $entry['body'] ?? null);
		$this->assertIsString($entry['modified'] ?? null, 'When the file was last written, for the editor\'s conflict notice.');
		$this->assertSame(filemtime($this->temporaryDirectory() . '/user/content/' . self::FLAME), strtotime($entry['modified']));
		$this->assertSame('/archives/flame', $entry['url'] ?? null);
		$this->assertTrue($entry['own'] ?? null);
		$this->assertSame(['edit' => true, 'publish' => true, 'rename' => true, 'move' => false, 'delete' => true, 'duplicate' => true, 'makeHomepage' => false], $entry['can'] ?? null);
		$this->assertIsArray($entry['type'] ?? null);
		$this->assertTrue($entry['type']['dated'] ?? null);
		$this->assertIsArray($entry['type']['fields'] ?? null);
		$this->assertContains('title', array_column($entry['type']['fields'], 'name'));
		$this->assertIsArray($entry['violations'] ?? null);

		$this->assertSame(404, $this->call('GET', '/entries/0199b6e2-7f3a-7c41-9d2e-000000000000')->getStatusCode());
	}

	public function testDescribesEachEntrysHandle(): void
	{
		$this->writeTemporaryFile('user/content/about.md', "---\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1a08\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/content/about/team.md', "---\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1a09\ntitle: The Team\n---\n");
		$this->writeTemporaryFile('user/content/index.md', "---\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1a0a\ntitle: Home\n---\n");
		$this->site();

		$this->assertSame('post/flame', $this->load(self::FLAME)['handle'] ?? null, 'A type and the key, without the date or extension.');

		$this->assertSame('page/about/team', $this->load('about/team.md')['handle'] ?? null, 'A page\'s key has its parents\' slugs (D-656).');
		$this->assertSame('page/index', $this->load('index.md')['handle'] ?? null, 'A landing page is `index`.');
		$this->assertSame(404, $this->call('GET', '/content/post/flame')->getStatusCode(), 'Entries are found by id, not handle (D-483).');

		$listed = self::json($this->call('GET', '/entries?type=post'))['entries'] ?? null;
		$this->assertIsArray($listed);
		$this->assertContains('post/flame', array_column($listed, 'handle'));


		$renamed = self::json($this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'slug' => 'the-flame']));
		$this->assertSame('post/the-flame', $renamed['handle'] ?? null, 'A rename moves the handle.');
	}

	public function testListsEachEntrysAddressAndWhetherItCanBeTrashed(): void
	{
		$this->site(['author']);

		$listed = self::json($this->call('GET', '/entries?type=post'))['entries'] ?? null;
		$this->assertIsArray($listed);

		$byId  = array_column($listed, null, 'path');
		$flame = $byId[self::FLAME] ?? null;
		$idea  = $byId['_posts/2023-01-01.idea.md'] ?? null;
		$this->assertIsArray($flame);
		$this->assertIsArray($idea);
		$this->assertSame('/archives/flame', $flame['url'] ?? null);
		$this->assertSame('/archives/idea', $idea['url'] ?? null, 'A draft has the address it will have.');
		$this->assertSame(['delete' => true, 'duplicate' => true, 'makeHomepage' => false], $flame['can'] ?? null, 'An author trashes their own.');
		$this->assertArrayNotHasKey('_posts/2021-05-05.sams.md', $byId, 'An author lists only their own.');
	}

	public function testPinsTheIndexPageApartFromTheEntries(): void
	{
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\n---\n");
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->site();

		$list = self::json($this->call('GET', '/entries?type=post'));
		$this->assertIsArray($list['entries'] ?? null);
		$this->assertNotContains('_posts/index.md', array_column($list['entries'], 'path'), 'The index page isn\'t one of the posts.');
		$this->assertSame(3, $list['total'] ?? null, 'Nor is it counted.');
		$index = $list['index'] ?? null;
		$this->assertIsArray($index);
		$this->assertSame(['_posts/index.md', true], [$index['path'] ?? null, $index['index'] ?? null]);
		$this->assertSame(['delete' => false, 'duplicate' => false, 'makeHomepage' => false], $index['can'] ?? null, 'It can\'t be trashed from the list.');

		$this->assertNull($this->pinned('/entries?type=post&per=1&page=2'), 'It\'s pinned on the first page only.');

		$drafts = self::json($this->call('GET', '/entries?type=post&status=draft'));
		$this->assertArrayHasKey('index', $drafts);
		$this->assertNull($drafts['index'], 'A tab it isn\'t in hides it.');

		$found = self::json($this->call('GET', '/entries?type=post&search=flame'));
		$this->assertArrayHasKey('index', $found);
		$this->assertNull($found['index'], 'A search it doesn\'t match hides it.');
		$this->assertSame('_posts/index.md', $this->pinned('/entries?type=post&search=writ'));

		$pages = self::json($this->call('GET', '/entries?type=page'));
		$this->assertIsArray($pages['entries'] ?? null);
		$this->assertNotContains('index.md', array_column($pages['entries'], 'path'), 'Pages pin their root page instead (D-420).');
		$this->assertSame('index.md', self::at($pages, 'index', 'path'));
	}

	public function testPinsTheRootPageAndMarksTheHomepage(): void
	{
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\nposition: 2\n---\n");
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\n---\n");
		$this->site(['administrator']);

		$marks = static fn (mixed $entry): array => is_array($entry) ? [$entry['path'] ?? null, $entry['index'] ?? null, $entry['homepage'] ?? null, $entry['rootPage'] ?? null, $entry['homeInstead'] ?? null] : [];

		$pages = self::json($this->call('GET', '/entries?type=page'));
		$this->assertIsArray($pages['entries'] ?? null);
		$this->assertSame(['about.md'], array_column($pages['entries'], 'path'), 'The root page isn\'t one of the pages.');
		$this->assertSame(1, $pages['total'] ?? null, 'Nor is it counted.');
		$this->assertSame(['index.md', false, true, true, null], $marks($pages['index'] ?? null));
		$this->assertSame(['delete' => true, 'duplicate' => false, 'makeHomepage' => false], self::at($pages, 'index', 'can'));
		$this->assertSame(['_posts/index.md', true, false, false, null], $marks(self::json($this->call('GET', '/entries?type=post'))['index'] ?? null));

		$root = self::json($this->call('GET', $this->entryPath('index.md')));
		$this->assertSame([null, false, false, false], [array_key_exists('parent', $root) ? $root['parent'] : 'missing', self::at($root, 'can', 'rename'), self::at($root, 'can', 'move'), self::at($root, 'can', 'duplicate')]);
		$fields = self::at($root, 'type', 'fields');
		$about  = self::at(self::json($this->call('GET', $this->entryPath('about.md'))), 'type', 'fields');
		$this->assertIsArray($fields);
		$this->assertIsArray($about);
		$this->assertNotContains('position', array_column($fields, 'name'), 'It has no siblings to be placed among.');
		$this->assertContains('position', array_column($about, 'name'));
		$this->assertSame(422, $this->call('POST', $this->entryPath('index.md') . '/duplicate')->getStatusCode(), 'There\'s only one.');
	}

	public function testACollectionsIndexPageIsTheHomepageWhenItsSet(): void
	{
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\n---\n");
		$this->writeTemporaryFile('user/data/settings.json', '{"content": {"home": "post"}}');
		$this->site(['administrator']);

		$marks = static fn (mixed $entry): array => is_array($entry) ? [$entry['path'] ?? null, $entry['index'] ?? null, $entry['homepage'] ?? null, $entry['rootPage'] ?? null, $entry['homeInstead'] ?? null] : [];
		$pages = self::json($this->call('GET', '/entries?type=page'));
		$root  = self::json($this->call('GET', $this->entryPath('index.md')));

		$this->assertSame(['index.md', false, false, true, 'The latest posts'], $marks($pages['index'] ?? null), 'The root page isn\'t shown.');
		$this->assertTrue(self::at($pages, 'index', 'can', 'makeHomepage'));
		$this->assertSame(['_posts/index.md', true, true, false, null], $marks(self::json($this->call('GET', '/entries?type=post'))['index'] ?? null));
		$this->assertSame(['The latest posts', true], [$root['homeInstead'] ?? null, self::at($root, 'can', 'makeHomepage')]);
	}

	public function testMakingTheRootPageTheHomepageNeedsSiteSettings(): void
	{
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->writeTemporaryFile('user/data/settings.json', '{"content": {"home": "post"}}');
		$this->site();

		$this->assertFalse(self::at(self::json($this->call('GET', '/entries?type=page')), 'index', 'can', 'makeHomepage'));
	}

	public function testATreeInAFolderHasAnIndexPage(): void
	{
		$this->writeTemporaryFile('user/content/_doc/index.md', "---\ntitle: Docs\n---\n");
		$this->writeTemporaryFile('user/content/_doc/install.md', "---\ntitle: Install\n---\n");
		$this->writeTemporaryFile('user/content/_doc/install/requirements.md', "---\ntitle: Requirements\n---\n");
		$this->site(types: ['doc' => ['kind' => 'tree']]);

		$this->assertSame('_doc/index.md', $this->pinned('/entries?type=doc'), 'Its folder\'s index is its index page (D-386).');

		$docs = self::json($this->call('GET', '/entries?type=doc'));
		$this->assertIsArray($docs['entries'] ?? null);
		$this->assertSame(['_doc/install.md', '_doc/install/requirements.md'], array_column($docs['entries'], 'path'), 'Listed as a tree.');
		$this->assertSame(2, $docs['total'] ?? null);
	}

	public function testEditsTheIndexPageWithoutTheTypesFieldsOrTheTrash(): void
	{
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\nstatus: draft\nauthors: [jane]\n---\n");
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->site();

		$index = $this->load('_posts/index.md');
		$this->assertTrue($index['index'] ?? null);
		$this->assertIsArray($index['type'] ?? null);
		$this->assertIsArray($index['type']['fields'] ?? null);
		$this->assertSame(['title', 'status'], array_column($index['type']['fields'], 'name'), 'Only its title and status are fields.');
		$this->assertSame(['title' => 'Writing', 'status' => 'draft'], $index['values'] ?? null);
		$this->assertSame(['authors' => ['jane']], $index['extra'] ?? null, 'The rest is kept as it is.');
		$this->assertIsArray($index['can'] ?? null);
		$this->assertFalse($index['can']['delete'] ?? null);

		$home = $this->load('index.md');
		$this->assertFalse($home['index'] ?? null, 'The homepage is a page like the others.');
		$this->assertTrue(is_array($home['can'] ?? null) && ($home['can']['delete'] ?? null) === true);

		$trashed = $this->call('DELETE', $this->entryPath('_posts/index.md') . '?revision=' . $this->revision('_posts/index.md'));
		$this->assertSame(422, $trashed->getStatusCode());
		$this->assertSame('"Writing" is the index page for posts, so it can\'t be moved to the trash.', self::json($trashed)['error'] ?? null);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/index.md');

		$scheduled = $this->call('PATCH', $this->entryPath('_posts/index.md'), ['revision' => $this->revision('_posts/index.md'), 'status' => 'scheduled', 'published' => '2999-01-01 08:00:00']);
		$this->assertSame(400, $scheduled->getStatusCode());

		$published = $this->call('PATCH', $this->entryPath('_posts/index.md'), ['revision' => $this->revision('_posts/index.md'), 'status' => 'published']);
		$this->assertSame(200, $published->getStatusCode());
		$this->assertSame('published', self::json($published)['status'] ?? null);
		$this->assertStringNotContainsString('published', $this->file('_posts/index.md'), 'Publishing doesn\'t date it.');
	}

	public function testDuplicatesAnEntryAsADraft(): void
	{
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\n---\n");
		$this->site();

		$list = self::json($this->call('GET', '/entries?type=post'));
		$this->assertIsArray($list['entries'] ?? null);
		$this->assertIsArray($list['index'] ?? null);
		$this->assertSame(['delete' => true, 'duplicate' => true, 'makeHomepage' => false], array_column($list['entries'], 'can', 'path')[self::FLAME] ?? null);
		$this->assertSame(['delete' => false, 'duplicate' => false, 'makeHomepage' => false], $list['index']['can'] ?? null, 'Not the index page.');

		$response = $this->call('POST', $this->entryPath(self::FLAME) . '/duplicate');
		$copy     = self::json($response);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertIsString($copy['path'] ?? null);
		$this->assertMatchesRegularExpression('#^_posts/flame-copy\.md$#', $copy['path'], 'Named by the slug alone (D-515).');
		$this->assertSame(['Rekindling the Flame (Copy)', 'draft'], [$copy['title'] ?? null, $copy['status'] ?? null]);
		$this->assertIsArray($copy['extra'] ?? null);
		$this->assertSame('hopeful', $copy['extra']['mood'] ?? null, 'Everything else is copied.');
		$this->assertIsArray($copy['values'] ?? null);
		$this->assertSame(['jane'], (array) ($copy['values']['authors'] ?? null), 'It keeps its authors.');
		$this->assertStringContainsString("\nThe body.\n", $this->file($copy['path']));
		$this->assertStringContainsString('Rekindling the Flame"', $this->file(self::FLAME), 'The original is untouched.');

		$again = self::json($this->call('POST', $this->entryPath(self::FLAME) . '/duplicate'));
		$this->assertSame('_posts/flame-copy-2.md', is_string($again['path'] ?? null) ? $again['path'] : '');

		$this->assertSame(422, $this->call('POST', $this->entryPath('_posts/index.md') . '/duplicate')->getStatusCode(), 'Not the index page.');
		$this->assertSame(404, $this->call('POST', '/entries/0199b6e2-7f3a-7c41-9d2e-000000000000/duplicate')->getStatusCode());
	}

	public function testDuplicatingNeedsCreateAndEdit(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->call('POST', $this->entryPath('_posts/2021-05-05.sams.md') . '/duplicate')->getStatusCode(), 'Not someone else\'s.');
		$this->assertSame(201, $this->call('POST', $this->entryPath(self::FLAME) . '/duplicate')->getStatusCode(), 'An author duplicates their own.');
	}

	/**
	 * The id of the index page a list pins, if any.
	 */
	private function pinned(string $path): ?string
	{
		$index = self::json($this->call('GET', $path))['index'] ?? null;

		return is_array($index) && is_string($index['path'] ?? null) ? $index['path'] : null;
	}

	public function testSavesChangesWithTheRevision(): void
	{
		$this->site();

		$revision = $this->revision(self::FLAME);
		$response = $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $revision, 'set' => ['title' => 'The Flame', 'published' => '2022-04-01 08:00:00 -05:00']]);
		$saved    = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame("---\ntitle     : \"The Flame\"\nauthors   : jane\ndate      : 2022-04-01 08:00:00 -05:00\nmood      : hopeful\nid        : " . self::FLAME_ID . "\n---\n\nThe body.\n", $this->file(self::FLAME));
		$this->assertSame('The Flame', $saved['title'] ?? null);
		$this->assertNotSame($revision, $saved['revision'] ?? null);

		$this->assertSame(409, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $revision, 'set' => ['title' => 'Stale']])->getStatusCode());
		$this->assertSame(428, $this->call('PATCH', $this->entryPath(self::FLAME), ['set' => ['title' => 'No revision']])->getStatusCode());
		$this->assertSame(400, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $saved['revision'], 'set' => ['a', 'list']])->getStatusCode());
		$this->assertStringContainsString('The Flame', $this->file(self::FLAME));
	}

	public function testChangesStatus(): void
	{
		$this->site();
		$idea = '_posts/2023-01-01.idea.md';

		$published = self::json($this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'published']));

		$this->assertSame('published', $published['status'] ?? null);
		$this->assertStringNotContainsString('status:', $this->file($idea));
		$this->assertMatchesRegularExpression('/\npublished: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [-+]\d{2}:\d{2}\n/', $this->file($idea), 'Publishing an undated draft dates it now.');

		$this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'draft']);
		$this->assertStringContainsString("\nstatus: draft\n", $this->file($idea));

		$scheduled = self::json($this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'scheduled', 'published' => '2099-01-01 09:00']));

		$this->assertSame('scheduled', $scheduled['status'] ?? null);
		$this->assertStringContainsString("\npublished: 2099-01-01 09:00:00 ", $this->file($idea));

		$this->assertSame(400, $this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'scheduled'])->getStatusCode());
		$this->assertSame(400, $this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'live'])->getStatusCode());
	}

	public function testBulkChangesMoveToDraftPublishAndTrash(): void
	{
		$this->site();
		$idea = '_posts/2023-01-01.idea.md';
		$sams = '_posts/2021-05-05.sams.md';

		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'draft', 'ids' => [$this->idOf(self::FLAME), $this->idOf($sams)]]));

		$this->assertSame([$this->idOf(self::FLAME), $this->idOf($sams)], $answer['done'] ?? null);
		$this->assertStringContainsString("\nstatus: draft\n", $this->file(self::FLAME));
		$this->assertStringContainsString("\nstatus: draft\n", $this->file($sams));

		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'publish', 'ids' => [$this->idOf($idea), $this->idOf(self::FLAME)]]));

		$this->assertSame([$this->idOf($idea), $this->idOf(self::FLAME)], $answer['done'] ?? null);
		$this->assertStringNotContainsString('status:', $this->file($idea));
		$this->assertStringContainsString('published:', $this->file($idea), 'An undated entry is dated as it\'s published.');
		$this->assertStringContainsString('date      : 2022-03-29 23:00:00 -6', $this->file(self::FLAME), 'A dated entry keeps its date.');

		$ideaId = $this->idOf($idea);
		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'trash', 'ids' => [$ideaId, '0199b6e2-7f3a-7c41-9d2e-000000000000']]));

		$this->assertSame([$ideaId], $answer['done'] ?? null);
		$this->assertSame([['id' => '0199b6e2-7f3a-7c41-9d2e-000000000000', 'title' => '', 'reason' => 'It\'s no longer there.']], $answer['skipped'] ?? null);
		$this->assertStringContainsString("\nstatus: trash\n", $this->file($idea));
		$this->assertSame([$idea, $ideaId], [$this->trash()[0]['path'] ?? null, $this->trash()[0]['id'] ?? null]);

		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'publish', 'ids' => [$ideaId]]));

		$this->assertSame('It\'s in the trash already; restore it to change it.', self::at($answer, 'skipped', '0', 'reason'));
	}

	public function testBulkChangesSkipWhatCantChange(): void
	{
		$this->writeTemporaryFile('user/data/types/review.json', '{"folder": "reviews", "fields": [{"name": "rating", "type": "number", "required": true, "label": "Rating"}]}');
		$this->writeTemporaryFile('user/content/reviews/rated.md', "---\ntitle: Rated\nauthors: jane\nrating: 4\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/reviews/unrated.md', "---\ntitle: Unrated\nauthors: jane\nstatus: draft\n---\n");
		$this->site(['author'], credits: ['post', 'review']);

		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'publish', 'ids' => [$this->idOf('reviews/rated.md'), $this->idOf('reviews/unrated.md')]]));

		$this->assertSame([$this->idOf('reviews/rated.md')], $answer['done'] ?? null);
		$this->assertSame([['id' => $this->idOf('reviews/unrated.md'), 'title' => 'Unrated', 'reason' => 'Rating is required to publish.']], $answer['skipped'] ?? null);
		$this->assertStringContainsString("\nstatus: draft\n", $this->file('reviews/unrated.md'));

		$answer = self::json($this->call('POST', '/entries/bulk', ['action' => 'trash', 'ids' => [$this->idOf('_posts/2021-05-05.sams.md')]]));

		$this->assertSame([], $answer['done'] ?? null);
		$this->assertSame([['id' => $this->idOf('_posts/2021-05-05.sams.md'), 'title' => 'Sam\'s Post', 'reason' => 'You aren\'t allowed to delete it.']], $answer['skipped'] ?? null, 'An author can\'t trash someone else\'s entry.');
	}

	public function testBulkChangesRefuseMalformedRequests(): void
	{
		$this->site();

		$requests = [
			['action' => 'archive', 'ids' => [self::FLAME]],
			['action' => 'draft', 'ids' => []],
			['action' => 'draft', 'ids' => [self::FLAME, 5]],
			['action' => 'draft', 'ids' => array_fill(0, 101, self::FLAME)],
			['action' => 'draft']
		];

		foreach ($requests as $request) {
			$this->assertSame(400, $this->call('POST', '/entries/bulk', $request)->getStatusCode(), (string) json_encode($request));
		}

		$this->assertSame(403, $this->send('POST', '/entries/bulk', (string) json_encode(['action' => 'draft', 'ids' => [self::FLAME]]))->getStatusCode(), 'It needs the CSRF token.');
	}

	public function testContributorsKeepEntriesAsDrafts(): void
	{
		$this->site(['contributor']);
		$idea = '_posts/2023-01-01.idea.md';

		$this->assertSame(200, $this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'body' => "Thinking.\n"])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'published'])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'remove' => ['status']])->getStatusCode());
		$this->assertSame(403, $this->call('GET', $this->entryPath(self::FLAME))->getStatusCode(), 'A contributor can\'t edit a live entry, even their own.');
		$this->assertStringContainsString("\nstatus: draft\n", $this->file($idea));
	}

	public function testAuthorsCanAddButNotLeave(): void
	{
		$this->site(['author']);

		$this->assertSame(200, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'set' => ['authors' => ['jane', 'lee']]])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'set' => ['authors' => ['lee']]])->getStatusCode());
		$this->assertSame(403, $this->call('GET', $this->entryPath('_posts/2021-05-05.sams.md'))->getStatusCode());
	}

	public function testCreatesDraftsCreditedToTheAccount(): void
	{
		$this->site(['author']);

		$response = $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Hello There', 'body' => "\nFirst words.\n"]);
		$entry    = self::json($response);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertIsString($entry['path'] ?? null);
		$this->assertMatchesRegularExpression('#^_posts/hello-there\.md$#', $entry['path'], 'Named by the slug alone (D-515).');
		$this->assertSame('draft', $entry['status'] ?? null);
		$this->assertIsArray($entry['values'] ?? null);
		$this->assertSame(['jane'], $entry['values']['authors'] ?? null);
		$this->assertStringContainsString("\nFirst words.\n", $this->file($entry['path']));

		$this->assertSame(422, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Hello There'])->getStatusCode(), 'The same slug on the same day already exists.');
		$this->assertSame(400, $this->call('POST', '/entries', ['type' => 'movie', 'title' => 'Nope'])->getStatusCode());
	}

	public function testCreatesAPageUnderAnother(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n");
		$this->site();

		$response = $this->call('POST', '/entries', ['type' => 'page', 'title' => 'Writing', 'parent' => 'services']);
		$entry    = self::json($response);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame('services/writing.md', $entry['path'] ?? null);
		$this->assertSame("---\ntitle: Services\nid: {$this->idOf('services/index.md')}\n---\n", $this->file('services/index.md'), 'The parent became its folder\'s page (D-408).');
		$this->assertSame('Services', $this->load('services/index.md')['title'] ?? null);

		$missing = $this->call('POST', '/entries', ['type' => 'page', 'title' => 'Lost', 'parent' => 'nowhere']);
		$this->assertSame(422, $missing->getStatusCode());
		$this->assertSame('parent', self::json($missing)['field'] ?? null);
		$this->assertSame(400, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Nested Post', 'parent' => 'services'])->getStatusCode(), 'Only a tree\'s pages nest.');
		$this->assertSame(422, $this->call('POST', '/entries', ['type' => 'page', 'title' => 'Writing', 'parent' => 'services'])->getStatusCode(), 'There\'s already a page there.');
	}

	public function testMovesAPageWithThePagesUnderIt(): void
	{
		$this->writeTemporaryFile('user/content/services.md', "---\ntitle: Services\n---\n");
		$this->writeTemporaryFile('user/content/work/index.md', "---\ntitle: Work\n---\n");
		$this->writeTemporaryFile('user/content/work/design.md', "---\ntitle: Design\n---\n");
		$this->writeTemporaryFile('user/content/work/_notes.md', "---\ntitle: Notes\n---\n");
		$this->site();

		$work = $this->load('work/index.md');
		$this->assertSame(['work', '', true], [$work['key'] ?? null, $work['parent'] ?? null, is_array($work['can'] ?? null) ? $work['can']['move'] ?? null : null]);

		$response = $this->call('PATCH', $this->entryPath('work/index.md'), ['revision' => $this->revision('work/index.md'), 'parent' => 'services', 'redirect' => true]);
		$moved    = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['services/work/index.md', 'services'], [$moved['path'] ?? null, $moved['parent'] ?? null]);
		$this->assertStringContainsString('/work', $this->file('services/work/index.md'), 'The page redirects from its old address (D-410).');
		$this->assertStringContainsString('/work/design', $this->file('services/work/design.md'), 'So does a published page under it.');
		$this->assertStringNotContainsString('redirect_from', $this->file('services/work/_notes.md'), 'A hidden one has no address to keep.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/services/index.md', 'The new parent became its folder\'s page.');

		$top = $this->call('PATCH', $this->entryPath('services/work/design.md'), ['revision' => $this->revision('services/work/design.md'), 'parent' => '']);
		$this->assertSame('design.md', self::json($top)['path'] ?? null, 'And to the top.');

		$under = $this->call('PATCH', $this->entryPath('services/index.md'), ['revision' => $this->revision('services/index.md'), 'parent' => 'services/work']);
		$this->assertSame(422, $under->getStatusCode(), 'Not under a page under it.');

		$missing = $this->call('PATCH', $this->entryPath('design.md'), ['revision' => $this->revision('design.md'), 'parent' => 'nowhere']);
		$this->assertSame('parent', self::json($missing)['field'] ?? null);

		$this->assertSame(201, $this->call('POST', '/entries', ['type' => 'page', 'title' => 'Another Design', 'slug' => 'design', 'parent' => 'services/work'])->getStatusCode());
		$taken = $this->call('PATCH', $this->entryPath('design.md'), ['revision' => $this->revision('design.md'), 'parent' => 'services/work']);
		$this->assertSame([422, 'parent'], [$taken->getStatusCode(), self::json($taken)['field'] ?? null], (string) $taken->getBody());

		$this->assertSame(400, $this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'parent' => ''])->getStatusCode(), 'Only a tree\'s pages move.');
	}

	public function testDescribesTheFieldSetsAttachedToTheType(): void
	{
		$this->writeTemporaryFile('user/data/fields/feelings.json', '{"description": "How it felt to write.", "targets": ["type:post"], "fields": {"mood": {"type": "enum", "options": ["hopeful", "glum"], "control": "radios"}}}');
		$this->site();

		$entry = $this->load(self::FLAME);
		$type  = is_array($entry['type'] ?? null) ? $entry['type'] : [];

		$this->assertSame([['name' => 'feelings', 'label' => 'Feelings', 'description' => 'How it felt to write.', 'slot' => 'details', 'fields' => ['mood']]], $type['sets'] ?? null);
		$this->assertSame('radios', array_column(is_array($type['fields'] ?? null) ? $type['fields'] : [], 'control', 'name')['mood'] ?? null);
		$this->assertSame('hopeful', is_array($entry['values'] ?? null) ? $entry['values']['mood'] ?? null : null, 'A set\'s field is a value, not other front matter.');
		$this->assertSame([], $entry['extra'] ?? null);
	}

	public function testDescribesANewEntryWithoutWritingIt(): void
	{
		$this->site(['author']);

		$response = $this->call('GET', '/entries/new?type=post');
		$entry    = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertArrayHasKey('path', $entry);
		$this->assertNull($entry['path'], 'It has no file yet.');
		$this->assertArrayHasKey('id', $entry);
		$this->assertNull($entry['id'], 'Nor an id (D-477).');
		$this->assertArrayHasKey('revision', $entry);
		$this->assertNull($entry['revision']);
		$this->assertSame('draft', $entry['status'] ?? null);
		$this->assertSame(['authors' => ['jane']], $entry['values'] ?? null, 'Credited to the account\'s author.');
		$this->assertSame('', $entry['body'] ?? null);
		$this->assertSame(['edit' => true, 'publish' => true, 'rename' => true, 'move' => false, 'delete' => false, 'duplicate' => false, 'makeHomepage' => false], $entry['can'] ?? null);
		$this->assertIsArray($entry['type'] ?? null);
		$this->assertSame('post', $entry['type']['name'] ?? null);
		$this->assertTrue($entry['type']['dated'] ?? null);
		$this->assertSame(['2021-05-05.sams.md', '2022-03-29.flame.md', '2023-01-01.idea.md'], array_values(array_diff((array) scandir($this->temporaryDirectory() . '/user/content/_posts'), ['.', '..'])), 'Nothing is written.');

		$this->assertSame(400, $this->call('GET', '/entries/new?type=movie')->getStatusCode());
		$this->assertSame(400, $this->call('GET', '/entries/new')->getStatusCode());
	}

	public function testANewEntrysBodyFollowsABlankLine(): void
	{
		$this->site();

		$entry = self::json($this->call('POST', '/entries', ['type' => 'post', 'title' => 'Straight In', 'body' => "First words.\n"]));

		$this->assertIsString($entry['path'] ?? null);
		$this->assertStringEndsWith("---\n\nFirst words.\n", $this->file($entry['path']));
		$this->assertSame("First words.\n", $entry['body'] ?? null);
	}

	public function testContributorsCantCreatePublishedEntries(): void
	{
		$this->site(['contributor']);

		$this->assertSame(403, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Live Now', 'status' => 'published'])->getStatusCode());
		$this->assertSame(201, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Just a Draft'])->getStatusCode());
		$this->assertSame(200, $this->call('GET', '/entries/new?type=post')->getStatusCode(), 'They may start one.');
	}

	public function testCapabilitiesAreEachTypes(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('pager', 'Pager', ['content.page.create', 'content.page.edit', 'content.page.publish'])]);\n");
		$this->site(['pager']);

		$this->assertSame(201, $this->call('POST', '/entries', ['type' => 'page', 'title' => 'A Page', 'status' => 'published'])->getStatusCode());
		$this->assertSame(403, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'A Post'])->getStatusCode(), 'Pages only (D-359).');
		$this->assertSame(403, $this->call('GET', '/entries/new?type=post')->getStatusCode());
		$this->assertSame(403, $this->call('GET', $this->entryPath(self::FLAME))->getStatusCode(), 'Even their own post.');
	}

	public function testRenamesWithANewSlug(): void
	{
		$this->site();

		$renamed = self::json($this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'slug' => 'the-flame']));

		$this->assertSame('_posts/2022-03-29.the-flame.md', $renamed['path'] ?? null);
		$this->assertSame('/archives/the-flame', $renamed['url'] ?? null);
		$this->assertSame('the-flame', $renamed['slug'] ?? null);
		$this->assertIsArray($renamed['can'] ?? null);
		$this->assertTrue($renamed['can']['rename'] ?? null);

		$id       = '_posts/2022-03-29.the-flame.md';
		$both     = self::json($this->call('PATCH', $this->entryPath($id), ['revision' => $this->revision($id), 'slug' => 'flame-again', 'set' => ['title' => 'Again']]));
		$this->assertSame(['_posts/2022-03-29.flame-again.md', 'Again'], [$both['path'] ?? null, $both['title'] ?? null], 'A rename and a change save together.');

		$id       = '_posts/2022-03-29.flame-again.md';
		$revision = $this->revision($id);
		$taken    = $this->call('PATCH', $this->entryPath($id), ['revision' => $revision, 'slug' => 'idea', 'set' => ['title' => 'Lost?']]);
		$this->assertSame(422, $taken->getStatusCode());
		$this->assertSame(['error' => 'Another post already has the slug "idea".', 'field' => 'slug'], self::json($taken));
		$this->assertSame($revision, $this->revision($id), 'A refused name changes nothing.');

		$bad = $this->call('PATCH', $this->entryPath($id), ['revision' => $revision, 'slug' => 'Not A Slug']);
		$this->assertSame(['error' => 'Slugs are lowercase letters, numbers, and hyphens; try "not-a-slug".', 'field' => 'slug'], self::json($bad));

		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\nid: 0199b6e2-0000-7000-8000-0000000000aa\n---\n");
		$this->app->container()->make(Indexer::class)->index();
		$index = $this->load('_posts/index.md');
		$this->assertIsArray($index['can'] ?? null);
		$this->assertFalse($index['can']['rename'] ?? null);
		$this->assertSame(422, $this->call('PATCH', $this->entryPath('_posts/index.md'), ['revision' => $this->revision('_posts/index.md'), 'slug' => 'blog'])->getStatusCode());
	}

	public function testRenamingKeepsOldLinksAndChangesASlugKey(): void
	{
		$this->writeTemporaryFile('user/content/_posts/2020-02-02.file-name.md', "---\ntitle: Keyed\nslug: keyed\nredirect_from: /older\n---\n");
		$this->site();

		$redirected = self::json($this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'slug' => 'the-flame', 'redirect' => true]));
		$this->assertSame('/archives/the-flame', $redirected['url'] ?? null);
		$this->assertStringContainsString("redirect_from: [/archives/flame]\n", $this->file('_posts/2022-03-29.the-flame.md'), 'The old address redirects.');

		$id    = '_posts/2020-02-02.file-name.md';
		$keyed = self::json($this->call('PATCH', $this->entryPath($id), ['revision' => $this->revision($id), 'slug' => 'keyed-again', 'redirect' => true]));
		$this->assertSame([$id, 'keyed-again', '/archives/keyed-again'], [$keyed['path'] ?? null, $keyed['slug'] ?? null, $keyed['url'] ?? null], 'A slug key is changed, not the file.');
		$this->assertStringContainsString("slug: keyed-again\n", $this->file($id));
		$this->assertStringContainsString('/older', $this->file($id));
		$this->assertStringContainsString('/archives/keyed', $this->file($id), 'Old addresses stay, and the new old one joins them.');

		$copy = self::json($this->call('POST', $this->entryPath($id) . '/duplicate'));
		$this->assertIsString($copy['path'] ?? null);
		$this->assertSame('_posts/keyed-again-copy.md', $copy['path']);
		$this->assertSame('keyed-again-copy', $copy['slug'] ?? null);
		$this->assertStringNotContainsString('slug:', $this->file($copy['path']), 'A copy is named by its file.');
		$this->assertStringNotContainsString('redirect_from', $this->file($copy['path']), 'The original keeps its old addresses.');
	}

	public function testDeletesToTheTrash(): void
	{
		$this->site(['author']);

		$this->assertSame(428, $this->call('DELETE', $this->entryPath(self::FLAME))->getStatusCode());
		$this->assertSame(200, $this->call('DELETE', $this->entryPath(self::FLAME) . '?revision=' . $this->revision(self::FLAME))->getStatusCode());
		$this->assertStringContainsString("\nstatus: trash\n", $this->file(self::FLAME), 'It stays where it is (D-484).');
		$this->assertSame(200, $this->call('DELETE', '/entries/' . self::FLAME_ID . '?permanently=1')->getStatusCode(), 'An author deletes their own for good.');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/' . self::FLAME);
		$this->assertSame(404, $this->call('GET', '/entries/' . self::FLAME_ID)->getStatusCode());

		$this->assertSame(403, $this->call('DELETE', $this->entryPath('_posts/2021-05-05.sams.md') . '?revision=x')->getStatusCode());
	}

	public function testANewDateRenamesAFileNamedByDate(): void
	{
		$this->site(types: ['post' => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives'], 'filename' => '{date}.{slug}']]);

		$moved = self::json($this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'set' => ['published' => '2022-04-02 10:00:00 -05:00']]));
		$path  = '_posts/2022-04-02.flame.md';

		$this->assertSame([$path, self::FLAME_ID], [$moved['path'] ?? null, $moved['id'] ?? null], 'Named by its new date (D-519).');
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/' . self::FLAME);
		$this->assertStringContainsString('date      : 2022-04-02 10:00:00 -05:00', $this->file($path));

		$this->call('DELETE', $this->entryPath($path) . '?revision=' . $this->revision($path));
		$this->call('POST', $this->entryPath($path) . '/restore');
		$published = self::json($this->call('PATCH', $this->entryPath($path), ['revision' => $this->revision($path), 'status' => 'published']));

		$this->assertSame($path, $published['path'] ?? null, 'Trashing, restoring, and publishing keep its date and name.');
		$this->assertStringContainsString('date      : 2022-04-02 10:00:00 -05:00', $this->file($path));

		$title = self::json($this->call('PATCH', $this->entryPath($path), ['revision' => $this->revision($path), 'set' => ['title' => 'Kept']]));
		$this->assertSame($path, $title['path'] ?? null, 'Only a new date renames it.');

		$idea  = '_posts/2023-01-01.idea.md';
		$dated = self::json($this->call('PATCH', $this->entryPath($idea), ['revision' => $this->revision($idea), 'status' => 'published']));
		$this->assertMatchesRegularExpression('#^_posts/\d{4}-\d{2}-\d{2}\.idea\.md$#', is_string($dated['path'] ?? null) ? $dated['path'] : '');
		$this->assertNotSame($idea, $dated['path'], 'Publishing an undated draft dates it, and names it by the date.');
	}

	public function testBulkPublishingRenamesAnUndatedDraft(): void
	{
		$this->site(types: ['post' => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives'], 'filename' => '{date}.{slug}']]);
		$id = $this->idOf('_posts/2023-01-01.idea.md');

		$this->assertSame([$id], self::json($this->call('POST', '/entries/bulk', ['action' => 'publish', 'ids' => [$id]]))['done'] ?? null);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/_posts/2023-01-01.idea.md');
		$this->assertCount(1, glob($this->temporaryDirectory() . '/user/content/_posts/*.idea.md') ?: []);
	}

	public function testANewDateKeepsANameWithoutADatedPattern(): void
	{
		$this->site();

		$moved = self::json($this->call('PATCH', $this->entryPath(self::FLAME), ['revision' => $this->revision(self::FLAME), 'set' => ['published' => '2022-04-02 10:00:00 -05:00']]));
		$this->assertSame(self::FLAME, $moved['path'] ?? null, 'A type without a pattern of its own is never renamed.');
	}
}
