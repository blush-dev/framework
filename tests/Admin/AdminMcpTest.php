<?php

/**
 * Admin activity and connected app tests.
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
use Blush\Admin\Http\ActivityController;
use Blush\Admin\Http\AuthorizeController;
use Blush\Admin\Http\TokensController;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthServer;
use Blush\Tests\WritesContentConfig;

#[CoversClass(ActivityController::class)]
#[CoversClass(AuthorizeController::class)]
#[CoversClass(TokensController::class)]
final class AdminMcpTest extends TestCase
{
	use BootsAdmin;
	use WritesContentConfig;

	private const string POST = '_post/2022-03-29.flame.md';

	private const string POST_ID = '0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74';

	private const string REDIRECT = 'https://app.example/callback';

	private string $token = '';

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor'], bool $mcp = true): void
	{
		$this->contentConfig(['types' => ['post' => ['date_archives' => true]], 'relations' => ['authors' => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'aliases' => ['author']]]]);
		$this->profiles('jane');
		$this->writeTemporaryFile('user/content/' . self::POST, "---\ntitle: Rekindling the Flame\nauthors: jane\ndate: 2022-03-29 23:00:00 -06:00\nid: " . self::POST_ID . "\n---\n\nThe body.\n");
		$this->writeTemporaryFile('config/mcp.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Mcp\\McpConfig(enabled: " . ($mcp ? 'true' : 'false') . ");\n");

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
	 * Returns a value inside a JSON answer by its keys, or `null`.
	 */
	private static function at(mixed $value, string|int ...$keys): mixed
	{
		foreach ($keys as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		return $value;
	}

	private function edit(string $title): void
	{
		$revision = self::at(self::json($this->call('GET', '/entries/' . self::POST_ID)), 'revision');

		$this->assertSame(200, $this->call('PATCH', '/entries/' . self::POST_ID, ['revision' => $revision, 'set' => ['title' => $title]])->getStatusCode());
	}

	/**
	 * The app's request to connect, as its query.
	 *
	 * @return array<string, string>
	 */
	private function request(string $client): array
	{
		return [
			'response_type'         => 'code',
			'client_id'             => $client,
			'redirect_uri'          => self::REDIRECT,
			'code_challenge'        => OAuthServer::challenge(str_repeat('v', 50)),
			'code_challenge_method' => 'S256',
			'scope'                 => 'drafts',
			'state'                 => 'abc'
		];
	}

	public function testListsChangesAndUndoesOne(): void
	{
		$this->site();
		$this->edit('A New Flame');

		$page    = self::json($this->send('GET', '/activity'));
		$changes = self::at($page, 'changes');

		$this->assertIsArray($changes);
		$this->assertSame('Edited title of “A New Flame”', self::at($changes, 0, 'summary'));
		$this->assertSame('admin', self::at($changes, 0, 'via'));
		$this->assertSame('/content/post/' . self::POST_ID, self::at($changes, 0, 'link'));
		$this->assertTrue(self::at($changes, 0, 'undo', 'available'));

		$id = self::at($changes, 0, 'id');
		$this->assertIsString($id);

		$undone = $this->call('POST', "/activity/{$id}/undo");

		$this->assertSame(200, $undone->getStatusCode(), (string) $undone->getBody());
		$this->assertTrue(self::at(self::json($undone), 'change', 'undone'));
		$this->assertSame('Rekindling the Flame', self::at(self::json($this->call('GET', '/entries/' . self::POST_ID)), 'title'));
		$this->assertSame(422, $this->call('POST', "/activity/{$id}/undo")->getStatusCode(), 'Undone once.');
	}

	public function testShowsAChangeWithWhatItChanged(): void
	{
		$this->site();
		$this->edit('A New Flame');

		$id = self::at(self::json($this->send('GET', '/activity')), 'changes', 0, 'id');
		$this->assertIsString($id);

		$change = self::json($this->send('GET', "/activity/{$id}"));

		$this->assertSame('Edited', self::at($change, 'actionLabel'));
		$this->assertSame('shown', self::at($change, 'diff', 'state'));
		$this->assertSame([['name' => 'title', 'before' => 'Rekindling the Flame', 'after' => 'A New Flame']], self::at($change, 'diff', 'fields'));
		$this->assertNull(self::at($change, 'diff', 'body'), 'The body didn\'t change.');
		$this->assertTrue(self::at($change, 'change', 'undo', 'available'));
		$this->assertSame(404, $this->send('GET', '/activity/0199b6e2-0000-7000-8000-000000000000')->getStatusCode());
	}

	public function testTheWholeLogTakesItsCapability(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->send('GET', '/activity')->getStatusCode(), 'site.activity is an editor\'s, not an author\'s (D-758).');
		$this->assertSame(200, $this->send('GET', '/activity?subject=' . self::POST_ID)->getStatusCode(), 'An entry they may edit.');

		$this->edit('A New Flame');
		$id = self::at(self::json($this->send('GET', '/activity?subject=' . self::POST_ID)), 'changes', 0, 'id');
		$this->assertIsString($id);
		$this->assertSame(200, $this->send('GET', "/activity/{$id}")->getStatusCode(), 'A change to an entry they may edit.');
	}

	public function testApprovesAnAppAndListsItAsConnected(): void
	{
		$this->site();

		$client = $this->app->container()->make(OAuthClients::class)->register(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]]);
		$query  = http_build_query($this->request($client->clientId));
		$shown  = self::json($this->send('GET', "/oauth/authorize?{$query}"));

		$this->assertSame('Claude', self::at($shown, 'client', 'name'));
		$this->assertTrue(self::at($shown, 'mayConnect'));
		$this->assertSame('drafts', self::at($shown, 'asked'));
		$this->assertFalse(self::at($shown, 'scopes', 2, 'available'), 'Never wider than asked (D-752).');

		$denied = self::at(self::json($this->call('POST', '/oauth/authorize', ['params' => $this->request($client->clientId), 'decision' => 'deny'])), 'redirect');
		$this->assertIsString($denied);
		$this->assertStringContainsString('error=access_denied', $denied);

		$allowed = self::at(self::json($this->call('POST', '/oauth/authorize', ['params' => $this->request($client->clientId), 'decision' => 'allow', 'scope' => 'read'])), 'redirect');
		$this->assertIsString($allowed);
		$this->assertStringContainsString('code=', $allowed);
		$this->assertStringContainsString('state=abc', $allowed);

		$apps = self::at(self::json($this->send('GET', '/accounts/jane/tokens')), 'apps');
		$this->assertIsArray($apps);
		$this->assertSame(['Claude', 'read'], [self::at($apps, 0, 'name'), self::at($apps, 0, 'scope')]);

		$app = self::at($apps, 0, 'id');
		$this->assertIsString($app);

		$this->assertSame(204, $this->call('DELETE', "/accounts/jane/apps/{$app}")->getStatusCode());
		$this->assertSame([], self::at(self::json($this->send('GET', '/accounts/jane/tokens')), 'apps'));
	}

	public function testRefusesABadRequestWithoutSendingItOn(): void
	{
		$this->site();

		$client   = $this->app->container()->make(OAuthClients::class)->register(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT]]);
		$refused  = $this->send('GET', '/oauth/authorize?' . http_build_query([...$this->request($client->clientId), 'redirect_uri' => 'https://evil.example/']));

		$this->assertSame(400, $refused->getStatusCode());
		$this->assertNull(self::at(self::json($refused), 'redirect'), 'Never sent to an address the app didn\'t register.');
	}

	public function testConnectingWaitsForTheServer(): void
	{
		$this->site(mcp: false);

		$this->assertSame(404, $this->send('GET', '/oauth/authorize?client_id=x')->getStatusCode());
	}
}
