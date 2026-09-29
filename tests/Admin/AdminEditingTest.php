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
use Blush\Admin\InvalidEdit;
use Blush\Content\Lint\Linter;

#[CoversClass(EntryController::class)]
#[CoversClass(InvalidEdit::class)]
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

	public function testLoadsAnEntryForEditing(): void
	{
		$this->site();

		$entry = $this->load(self::FLAME);

		$this->assertIsArray($entry['values'] ?? null);
		$this->assertSame('Rekindling the Flame', $entry['values']['title'] ?? null);
		$this->assertSame('2022-03-29T23:00:00-06:00', $entry['values']['published'] ?? null, 'The 1.x `date` is the `published` field, as parsed.');
		$this->assertSame(['mood' => 'hopeful'], $entry['extra'] ?? null);
		$this->assertSame("\nThe body.\n", $entry['body'] ?? null);
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
