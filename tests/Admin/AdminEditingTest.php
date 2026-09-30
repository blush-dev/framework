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
use Blush\Admin\TrashController;
use Blush\Content\Lint\Linter;

#[CoversClass(EntryController::class)]
#[CoversClass(EntryHandles::class)]
#[CoversClass(IndexPage::class)]
#[CoversClass(InvalidEdit::class)]
#[CoversClass(TrashController::class)]
#[CoversClass(Linter::class)]
final class AdminEditingTest extends TestCase
{
	use BootsAdmin;

	private const string FLAME = '_posts/2022-03-29.flame.md';

	private string $token = '';

	/**
	 * Boots a site with dated posts: Jane's published post (with 1.x's
	 * `date`, and an undeclared key), her draft, and Sam's post.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$this->writeTemporaryFile('config/content.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Content\\Type\\ContentConfig::fromArray(['types' => ['post' => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives']]]]);\n");
		$this->writeTemporaryFile('user/content/' . self::FLAME, "---\ntitle     : \"Rekindling the Flame\"\nauthors   : jane\ndate      : 2022-03-29 23:00:00 -6\nmood      : hopeful\n---\n\nThe body.\n");
		$this->writeTemporaryFile('user/content/_posts/2023-01-01.idea.md', "---\ntitle: An Idea\nauthors: jane\nstatus: draft\n---\n");
		$this->writeTemporaryFile('user/content/_posts/2021-05-05.sams.md', "---\ntitle: Sam's Post\nauthors: sam\npublished: 2021-05-05 09:00:00 -05:00\n---\n");

		$this->boot(roles: $roles);
		$this->login();

		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);
		$this->token = $token;
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
		$response = $this->call('GET', "/entries/{$id}");
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
		$trash = self::json($this->call('GET', "/trash{$query}"))['trash'] ?? null;
		$this->assertIsArray($trash);

		/** @var list<array<mixed>> $trash */
		return $trash;
	}

	private function trashId(string $entry): string
	{
		$id = array_find($this->trash(), static fn (array $trashed): bool => $trashed['entry'] === $entry)['id'] ?? null;
		$this->assertIsString($id);

		return $id;
	}

	public function testTrashRestoresAsADraftDeletesAndEmpties(): void
	{
		$this->site();
		$sams = '_posts/2021-05-05.sams.md';

		$this->assertSame(200, $this->call('DELETE', '/entries/' . self::FLAME . '?revision=' . $this->revision(self::FLAME))->getStatusCode());
		$this->assertSame(200, $this->call('DELETE', "/entries/{$sams}?revision=" . $this->revision($sams))->getStatusCode());

		$this->assertEqualsCanonicalizing(['Rekindling the Flame', "Sam's Post"], array_column($this->trash(), 'title'));
		$this->assertSame(['post', 'post'], array_column($this->trash('?type=post'), 'type'));
		$this->assertSame([], $this->trash('?type=page'));
		$this->assertSame(400, $this->call('GET', '/trash?type=missing')->getStatusCode());

		$restored = $this->call('POST', '/trash/restore', ['id' => $this->trashId(self::FLAME)]);

		$this->assertSame(['id' => self::FLAME], self::json($restored));
		$this->assertSame('draft', $this->load(self::FLAME)['status'] ?? null, 'A restored entry is never live again by itself.');
		$this->assertStringContainsString("mood      : hopeful\nstatus: draft\n", $this->file(self::FLAME));

		$this->assertSame(204, $this->call('POST', '/trash/delete', ['id' => $this->trashId($sams)])->getStatusCode());
		$this->assertSame([], $this->trash());
		$this->assertSame(404, $this->call('POST', '/trash/restore', ['id' => 'nothing'])->getStatusCode());

		$idea = '_posts/2023-01-01.idea.md';
		$this->call('DELETE', "/entries/{$idea}?revision=" . $this->revision($idea));

		$this->assertSame(['deleted' => 1], self::json($this->call('POST', '/trash/empty', ['type' => 'post'])));
		$this->assertSame([], $this->trash());
	}

	public function testAuthorsHandleOnlyTheirOwnTrash(): void
	{
		$this->writeTemporaryFile('storage/trash/20250101-090000/user/content/_posts/2020-01-01.old.md', "---\ntitle: Old\nauthors: sam\n---\n");
		$this->site(['author']);

		$this->call('DELETE', '/entries/_posts/2023-01-01.idea.md?revision=' . $this->revision('_posts/2023-01-01.idea.md'));

		$this->assertSame(['An Idea'], array_column($this->trash(), 'title'), 'Sam\'s trash is his.');
		$this->assertSame(404, $this->call('POST', '/trash/restore', ['id' => '20250101-090000/_posts/2020-01-01.old.md'])->getStatusCode());
		$this->assertSame(['deleted' => 1], self::json($this->call('POST', '/trash/empty', ['type' => 'post'])));
		$this->assertFileExists($this->temporaryDirectory() . '/storage/trash/20250101-090000/user/content/_posts/2020-01-01.old.md');
	}

	public function testLoadsAnEntryForEditing(): void
	{
		$this->site();

		$entry = $this->load(self::FLAME);

		$this->assertIsArray($entry['values'] ?? null);
		$this->assertSame('Rekindling the Flame', $entry['values']['title'] ?? null);
		$this->assertSame('2022-03-29T23:00:00-06:00', $entry['values']['published'] ?? null, 'The 1.x `date` is the `published` field, as parsed.');
		$this->assertSame(['mood' => 'hopeful'], $entry['extra'] ?? null);
		$this->assertSame("\nThe body.\n", $entry['body'] ?? null);
		$this->assertIsString($entry['modified'] ?? null, 'When the file was last written, for the editor\'s conflict notice.');
		$this->assertSame(filemtime($this->temporaryDirectory() . '/user/content/' . self::FLAME), strtotime($entry['modified']));
		$this->assertSame('/archives/flame', $entry['url'] ?? null);
		$this->assertTrue($entry['own'] ?? null);
		$this->assertSame(['edit' => true, 'publish' => true, 'delete' => true], $entry['can'] ?? null);
		$this->assertIsArray($entry['type'] ?? null);
		$this->assertTrue($entry['type']['dated'] ?? null);
		$this->assertIsArray($entry['type']['fields'] ?? null);
		$this->assertContains('title', array_column($entry['type']['fields'], 'name'));
		$this->assertIsArray($entry['violations'] ?? null);

		$this->assertSame(404, $this->call('GET', '/entries/_posts/missing.md')->getStatusCode());
	}

	public function testFindsAnEntryByItsHandle(): void
	{
		$this->writeTemporaryFile('user/content/about/team.md', "---\ntitle: The Team\n---\n");
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->site();

		$this->assertSame('post/flame', $this->load(self::FLAME)['handle'] ?? null, 'A type and the key, without the date or extension.');

		$response = $this->call('GET', '/content/post/flame');
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(self::FLAME, self::json($response)['id'] ?? null);

		$page = self::json($this->call('GET', '/content/page/about/team'));
		$this->assertSame(['about/team.md', 'page/about/team'], [$page['id'] ?? null, $page['handle'] ?? null], 'A page\'s key has its folders.');

		$home = self::json($this->call('GET', '/content/page/index'));
		$this->assertSame(['index.md', 'page/index'], [$home['id'] ?? null, $home['handle'] ?? null], 'A landing page is `index`.');

		$listed = self::json($this->call('GET', '/entries?type=post'))['entries'] ?? null;
		$this->assertIsArray($listed);
		$this->assertContains('post/flame', array_column($listed, 'handle'));

		$this->assertSame(404, $this->call('GET', '/content/post/missing')->getStatusCode());
		$this->assertSame(404, $this->call('GET', '/content/nope/flame')->getStatusCode());

		$renamed = self::json($this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $this->revision(self::FLAME), 'slug' => 'the-flame']));
		$this->assertSame('post/the-flame', $renamed['handle'] ?? null, 'A rename moves the handle.');
	}

	public function testListsEachEntrysAddressAndWhetherItCanBeTrashed(): void
	{
		$this->site(['author']);

		$listed = self::json($this->call('GET', '/entries?type=post'))['entries'] ?? null;
		$this->assertIsArray($listed);

		$byId  = array_column($listed, null, 'id');
		$flame = $byId[self::FLAME] ?? null;
		$idea  = $byId['_posts/2023-01-01.idea.md'] ?? null;
		$this->assertIsArray($flame);
		$this->assertIsArray($idea);
		$this->assertSame('/archives/flame', $flame['url'] ?? null);
		$this->assertSame('/archives/idea', $idea['url'] ?? null, 'A draft has the address it will have.');
		$this->assertSame(['delete' => true], $flame['can'] ?? null, 'An author trashes their own.');
		$this->assertArrayNotHasKey('_posts/2021-05-05.sams.md', $byId, 'An author lists only their own.');
	}

	public function testPinsTheIndexPageApartFromTheEntries(): void
	{
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\n---\n");
		$this->writeTemporaryFile('user/content/index.md', "---\ntitle: Home\n---\n");
		$this->site();

		$list = self::json($this->call('GET', '/entries?type=post'));
		$this->assertIsArray($list['entries'] ?? null);
		$this->assertNotContains('_posts/index.md', array_column($list['entries'], 'id'), 'The index page isn\'t one of the posts.');
		$this->assertSame(3, $list['total'] ?? null, 'Nor is it counted.');
		$index = $list['index'] ?? null;
		$this->assertIsArray($index);
		$this->assertSame(['_posts/index.md', true], [$index['id'] ?? null, $index['index'] ?? null]);
		$this->assertSame(['delete' => false], $index['can'] ?? null, 'It can\'t be trashed from the list.');

		$this->assertNull($this->pinned('/entries?type=post&per=1&page=2'), 'It\'s pinned on the first page only.');

		$drafts = self::json($this->call('GET', '/entries?type=post&status=draft'));
		$this->assertArrayHasKey('index', $drafts);
		$this->assertNull($drafts['index'], 'A tab it isn\'t in hides it.');

		$found = self::json($this->call('GET', '/entries?type=post&search=flame'));
		$this->assertArrayHasKey('index', $found);
		$this->assertNull($found['index'], 'A search it doesn\'t match hides it.');
		$this->assertSame('_posts/index.md', $this->pinned('/entries?type=post&search=writ'));

		$pages = self::json($this->call('GET', '/entries?type=page'));
		$this->assertArrayHasKey('index', $pages);
		$this->assertNull($pages['index'], 'Pages have no index page.');
		$this->assertIsArray($pages['entries'] ?? null);
		$this->assertContains('index.md', array_column($pages['entries'], 'id'), 'The home page is a page like the others.');
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
		$this->assertFalse($home['index'] ?? null, 'The home page is a page like the others.');
		$this->assertTrue(is_array($home['can'] ?? null) && ($home['can']['delete'] ?? null) === true);

		$trashed = $this->call('DELETE', '/entries/_posts/index.md?revision=' . $this->revision('_posts/index.md'));
		$this->assertSame(422, $trashed->getStatusCode());
		$this->assertSame('"Writing" is the index page for Posts, so it can\'t be moved to the trash.', self::json($trashed)['error'] ?? null);
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/_posts/index.md');

		$scheduled = $this->call('PATCH', '/entries/_posts/index.md', ['revision' => $this->revision('_posts/index.md'), 'status' => 'scheduled', 'published' => '2999-01-01 08:00:00']);
		$this->assertSame(400, $scheduled->getStatusCode());

		$published = $this->call('PATCH', '/entries/_posts/index.md', ['revision' => $this->revision('_posts/index.md'), 'status' => 'published']);
		$this->assertSame(200, $published->getStatusCode());
		$this->assertSame('published', self::json($published)['status'] ?? null);
		$this->assertStringNotContainsString('published', $this->file('_posts/index.md'), 'Publishing doesn\'t date it.');
	}

	/**
	 * The id of the index page a list pins, if any.
	 */
	private function pinned(string $path): ?string
	{
		$index = self::json($this->call('GET', $path))['index'] ?? null;

		return is_array($index) && is_string($index['id'] ?? null) ? $index['id'] : null;
	}

	public function testSavesChangesWithTheRevision(): void
	{
		$this->site();

		$revision = $this->revision(self::FLAME);
		$response = $this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $revision, 'set' => ['title' => 'The Flame', 'published' => '2022-04-01 08:00:00 -05:00']]);
		$saved    = self::json($response);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame("---\ntitle     : \"The Flame\"\nauthors   : jane\ndate      : 2022-04-01 08:00:00 -05:00\nmood      : hopeful\n---\n\nThe body.\n", $this->file(self::FLAME));
		$this->assertSame('The Flame', $saved['title'] ?? null);
		$this->assertNotSame($revision, $saved['revision'] ?? null);

		$this->assertSame(409, $this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $revision, 'set' => ['title' => 'Stale']])->getStatusCode());
		$this->assertSame(428, $this->call('PATCH', '/entries/' . self::FLAME, ['set' => ['title' => 'No revision']])->getStatusCode());
		$this->assertSame(400, $this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $saved['revision'], 'set' => ['a', 'list']])->getStatusCode());
		$this->assertStringContainsString('The Flame', $this->file(self::FLAME));
	}

	public function testChangesStatus(): void
	{
		$this->site();
		$idea = '_posts/2023-01-01.idea.md';

		$published = self::json($this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'published']));

		$this->assertSame('published', $published['status'] ?? null);
		$this->assertStringNotContainsString('status:', $this->file($idea));
		$this->assertMatchesRegularExpression('/\npublished: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [-+]\d{2}:\d{2}\n/', $this->file($idea), 'Publishing an undated draft dates it now.');

		$this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'draft']);
		$this->assertStringContainsString("\nstatus: draft\n", $this->file($idea));

		$scheduled = self::json($this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'scheduled', 'published' => '2099-01-01 09:00']));

		$this->assertSame('scheduled', $scheduled['status'] ?? null);
		$this->assertStringContainsString("\npublished: 2099-01-01 09:00:00 ", $this->file($idea));

		$this->assertSame(400, $this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'scheduled'])->getStatusCode());
		$this->assertSame(400, $this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'live'])->getStatusCode());
	}

	public function testContributorsKeepEntriesAsDrafts(): void
	{
		$this->site(['contributor']);
		$idea = '_posts/2023-01-01.idea.md';

		$this->assertSame(200, $this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'body' => "Thinking.\n"])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'status' => 'published'])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', "/entries/{$idea}", ['revision' => $this->revision($idea), 'remove' => ['status']])->getStatusCode());
		$this->assertSame(403, $this->call('GET', '/entries/' . self::FLAME)->getStatusCode(), 'A contributor can\'t edit a live entry, even their own.');
		$this->assertStringContainsString("\nstatus: draft\n", $this->file($idea));
	}

	public function testAuthorsCanAddButNotLeave(): void
	{
		$this->site(['author']);

		$this->assertSame(200, $this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $this->revision(self::FLAME), 'set' => ['authors' => ['jane', 'lee']]])->getStatusCode());
		$this->assertSame(403, $this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $this->revision(self::FLAME), 'set' => ['authors' => ['lee']]])->getStatusCode());
		$this->assertSame(403, $this->call('GET', '/entries/_posts/2021-05-05.sams.md')->getStatusCode());
	}

	public function testCreatesDraftsCreditedToTheAccount(): void
	{
		$this->site(['author']);

		$response = $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Hello There', 'body' => "\nFirst words.\n"]);
		$entry    = self::json($response);

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertIsString($entry['id'] ?? null);
		$this->assertMatchesRegularExpression('#^_posts/\d{4}-\d{2}-\d{2}\.hello-there\.md$#', $entry['id']);
		$this->assertSame('draft', $entry['status'] ?? null);
		$this->assertIsArray($entry['values'] ?? null);
		$this->assertSame(['jane'], $entry['values']['authors'] ?? null);
		$this->assertStringContainsString("\nFirst words.\n", $this->file($entry['id']));

		$this->assertSame(422, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Hello There'])->getStatusCode(), 'The same slug on the same day already exists.');
		$this->assertSame(400, $this->call('POST', '/entries', ['type' => 'movie', 'title' => 'Nope'])->getStatusCode());
	}

	public function testContributorsCantCreatePublishedEntries(): void
	{
		$this->site(['contributor']);

		$this->assertSame(403, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Live Now', 'status' => 'published'])->getStatusCode());
		$this->assertSame(201, $this->call('POST', '/entries', ['type' => 'post', 'title' => 'Just a Draft'])->getStatusCode());
	}

	public function testRenamesWithANewSlug(): void
	{
		$this->site();

		$renamed = self::json($this->call('PATCH', '/entries/' . self::FLAME, ['revision' => $this->revision(self::FLAME), 'slug' => 'the-flame']));

		$this->assertSame('_posts/2022-03-29.the-flame.md', $renamed['id'] ?? null);
		$this->assertSame('/archives/the-flame', $renamed['url'] ?? null);
	}

	public function testDeletesToTheTrash(): void
	{
		$this->site(['author']);

		$this->assertSame(428, $this->call('DELETE', '/entries/' . self::FLAME)->getStatusCode());
		$this->assertSame(200, $this->call('DELETE', '/entries/' . self::FLAME . '?revision=' . $this->revision(self::FLAME))->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/content/' . self::FLAME);
		$this->assertSame(404, $this->call('GET', '/entries/' . self::FLAME)->getStatusCode());

		$this->assertSame(403, $this->call('DELETE', '/entries/_posts/2021-05-05.sams.md?revision=x')->getStatusCode());
	}
}
