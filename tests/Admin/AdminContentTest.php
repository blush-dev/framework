<?php

/**
 * Admin content screens' API tests.
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
use Blush\Admin\EntriesController;
use Blush\Admin\HealthController;
use Blush\Admin\PreviewLinkController;
use Blush\Content\ContentRepository;

#[CoversClass(EntriesController::class)]
#[CoversClass(HealthController::class)]
#[CoversClass(PreviewLinkController::class)]
final class AdminContentTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Writes drafts by two authors, a scheduled entry, a published one,
	 * and one with a problem, then boots the admin.
	 *
	 * @param list<string>           $roles
	 * @param array<string, ?string> $environment
	 */
	private function site(array $roles, array $environment = []): void
	{
		$this->writeTemporaryFile('user/content/jane-draft.md', "---\ntitle: Jane's draft\nstatus: draft\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/sam-draft.md', "---\ntitle: Sam's draft\nstatus: draft\nauthors: sam\n---\n");
		$this->writeTemporaryFile('user/content/soon.md', "---\ntitle: Soon\npublished: 2099-01-01 09:00:00\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/live.md', "---\ntitle: Live\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/broken.md', "---\ntitle: Broken\nstatus: pending\nsurprise: yes\n---\n");

		$this->boot(roles: $roles, environment: $environment);
		$this->login();
	}

	/**
	 * Asks for a preview link to an entry, by title.
	 */
	private function preview(string $title, string $token): ResponseInterface
	{
		$entry = array_find([...$this->entries('draft'), ...$this->entries('scheduled')], static fn (array $entry): bool => $entry['title'] === $title);
		$id    = is_array($entry) ? $entry['id'] : 'missing';

		return $this->send('POST', '/previews', json_encode(['entry' => $id]) ?: '', ['X-CSRF-Token' => $token]);
	}

	/**
	 * Returns the signed-in session's CSRF token.
	 */
	private function token(): string
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	/**
	 * @return list<array<mixed>>
	 */
	private function entries(string $status): array
	{
		$entries = self::json($this->send('GET', "/entries?status={$status}"))['entries'] ?? null;
		$this->assertIsArray($entries);

		/** @var list<array<mixed>> $entries */
		return $entries;
	}

	public function testEditorsSeeEveryDraftAndScheduledEntry(): void
	{
		$this->site(['editor']);

		$drafts = $this->entries('draft');

		$this->assertEqualsCanonicalizing(["Jane's draft", "Sam's draft"], array_column($drafts, 'title'));

		$jane = array_find($drafts, static fn (array $entry): bool => $entry['title'] === "Jane's draft");

		$this->assertIsArray($jane);
		$this->assertTrue($jane['own']);
		$this->assertSame(['jane'], $jane['authors']);
		$this->assertSame('jane-draft.md', $jane['path']);
		$this->assertSame('draft', $jane['status']);

		$scheduled = $this->entries('scheduled');

		$this->assertSame(['Soon'], array_column($scheduled, 'title'));
		$this->assertIsString($scheduled[0]['published'] ?? null);
		$this->assertStringStartsWith('2099-01-01T09:00:00', $scheduled[0]['published']);
	}

	public function testAuthorsSeeTheirOwn(): void
	{
		$this->site(['author']);

		$this->assertSame(["Jane's draft"], array_column($this->entries('draft'), 'title'));
		$this->assertSame(['Soon'], array_column($this->entries('scheduled'), 'title'));
	}

	public function testContributorsSeeOnlyTheirDrafts(): void
	{
		$this->site(['contributor']);

		$this->assertSame(["Jane's draft"], array_column($this->entries('draft'), 'title'));
		$this->assertSame([], $this->entries('scheduled'), 'A scheduled entry isn\'t a draft, so a contributor can\'t edit it.');
	}

	public function testAsksForAKnownStatus(): void
	{
		$this->site(['editor']);

		$this->assertSame(400, $this->send('GET', '/entries?status=published')->getStatusCode());
		$this->assertSame(400, $this->send('GET', '/entries')->getStatusCode());
	}

	public function testReportsContentHealth(): void
	{
		$this->site(['editor']);

		$health = self::json($this->send('GET', '/health'));

		$this->assertIsArray($health['counts'] ?? null);
		$this->assertGreaterThanOrEqual(1, $health['counts']['error'] ?? 0);
		$this->assertNull($health['counts']['notice'] ?? null);
		$this->assertIsArray($health['files'] ?? null);
		$this->assertSame(['broken.md'], array_column($health['files'], 'path'));

		$strict = self::json($this->send('GET', '/health?strict=1'));

		$this->assertTrue($strict['strict'] ?? null);
		$this->assertIsArray($strict['counts'] ?? null);
		$this->assertIsInt($strict['counts']['notice'] ?? null);
	}

	public function testOnlyEditorsSeeContentHealth(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->send('GET', '/health')->getStatusCode());
	}

	public function testMakesPreviewLinksForEntriesTheAccountMayEdit(): void
	{
		$this->site(['author']);
		$token = $this->token();

		$response = $this->preview("Jane's draft", $token);
		$link     = self::json($response);

		$this->assertSame(201, $response->getStatusCode());
		$this->assertIsString($link['url'] ?? null);
		$this->assertStringStartsWith('https://example.test/_blush/preview?entry=', $link['url']);
		$this->assertIsString($link['expires'] ?? null);

		$this->assertSame(200, $this->visit('GET', substr($link['url'], strlen('https://example.test')))->getStatusCode());

		$this->assertSame(404, $this->preview("Sam's draft", $token)->getStatusCode(), 'An author can\'t find, so can\'t preview, someone else\'s draft.');
		$this->assertSame(400, $this->send('POST', '/previews', '{}', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testRefusesPreviewsOfOthersEntries(): void
	{
		$this->site(['author']);
		$token = $this->token();

		$sam = $this->app->container()->make(ContentRepository::class)->named('page', 'sam-draft');
		$this->assertNotNull($sam);

		$this->assertSame(403, $this->send('POST', '/previews', json_encode(['entry' => $sam->id]) ?: '', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testSaysWhenPreviewLinksAreOff(): void
	{
		$this->site(['editor'], ['APP_SECRET' => null]);

		$this->assertSame(503, $this->preview("Jane's draft", $this->token())->getStatusCode());
	}
}
