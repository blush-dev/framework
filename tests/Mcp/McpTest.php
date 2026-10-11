<?php

/**
 * MCP server tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Activity\ActivityFilter;
use Blush\Activity\Actor;
use Blush\Activity\ActivityLog;
use Blush\Activity\Via;
use Blush\Auth\Accounts;
use Blush\Auth\Tokens;
use Blush\Auth\TokenScope;
use Blush\Console\Output;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Mcp\Http\McpAuthentication;
use Blush\Mcp\Http\McpController;
use Blush\Mcp\McpServer;
use Blush\Mcp\McpStdio;
use Blush\Mcp\McpTools;
use Blush\Tool\InputCheck;
use Blush\Tool\ToolContexts;
use Blush\Tool\ToolEntries;
use Blush\Tool\ToolRegistry;
use Blush\Tool\Tools;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\Fixtures\Mcp\DraftsTool;
use Blush\Tests\Fixtures\Mcp\OpenTool;
use Blush\Tests\Fixtures\Mcp\WritingTool;
use Blush\Tests\Fixtures\Tool\GreetTool;

#[CoversClass(McpServer::class)]
#[CoversClass(McpTools::class)]
#[CoversClass(Tools::class)]
#[CoversClass(ToolRegistry::class)]
#[CoversClass(ToolEntries::class)]
#[CoversClass(ToolContexts::class)]
#[CoversClass(McpController::class)]
#[CoversClass(McpAuthentication::class)]
#[CoversClass(McpStdio::class)]
#[CoversClass(InputCheck::class)]
final class McpTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private int $id = 0;

	private function boot(string $config = 'new Blush\\Mcp\\McpConfig(enabled: true)'): void
	{
		$this->standardContent();
		$this->entry('_post/2021-01-01.mine.md', "title: Mine\npublished: 2021-01-01\nstatus: draft\nauthor: justintadlock", "First line.\n\nA typo hree.");
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");
		$this->writeTemporaryFile('config/mcp.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$config};\n");

		$this->app = $this->site();
	}

	/**
	 * Makes a token-only account with roles, linked to a profile when one
	 * is named, and a token for it, and returns the token's text.
	 *
	 * @param list<string>  $roles
	 * @param ?list<string> $capabilities
	 */
	private function token(string $username, array $roles, ?string $profile = null, ?array $capabilities = null): string
	{
		$accounts = $this->app->container()->make(Accounts::class);
		$account  = $accounts->createTokenOnly($username, $roles);

		if ($profile !== null) {
			$account = $accounts->setProfile($account, self::idFor("_profile/{$profile}.md"));
		}

		return $this->app->container()->make(Tokens::class)->make($account, 'Agent', $capabilities)[1];
	}

	/**
	 * @param array<string, string> $headers
	 */
	private function post(string $body, ?string $token, array $headers = []): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create('/api/mcp', 'POST', [
			'Content-Type' => 'application/json',
			...($token === null ? [] : ['Authorization' => "Bearer {$token}"]),
			...$headers
		], $body, ['REMOTE_ADDR' => '203.0.113.9']));
	}

	/**
	 * Sends a JSON-RPC request, and returns the answer.
	 *
	 * @param  array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private function rpc(string $method, array $params, string $token): array
	{
		$response = $this->post((string) json_encode(['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => $method, 'params' => (object) $params]), $token);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

		$data = json_decode((string) $response->getBody(), true);
		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * Calls a tool, and returns its result.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function call(string $tool, array $arguments, string $token): array
	{
		$result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments], $token)['result'] ?? null;
		$this->assertIsArray($result, "{$tool} answers a result.");

		/** @var array<string, mixed> $result */
		return $result;
	}

	/**
	 * Calls a tool that should work, and returns its data.
	 *
	 * @param  array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function data(string $tool, array $arguments, string $token): array
	{
		$result = $this->call($tool, $arguments, $token);

		$this->assertFalse($result['isError'] ?? true, "{$tool}: " . json_encode($result['content'] ?? null));
		$this->assertIsArray($result['structuredContent'] ?? null);

		/** @var array<string, mixed> */
		return $result['structuredContent'];
	}

	/**
	 * Calls a tool that should refuse, and returns what it says.
	 *
	 * @param array<string, mixed> $arguments
	 */
	private function refusal(string $tool, array $arguments, string $token): string
	{
		$result = $this->call($tool, $arguments, $token);

		$this->assertTrue($result['isError'] ?? false, "{$tool} refuses.");

		$content = $result['content'] ?? [];

		return is_array($content) && is_array($content[0] ?? null) && is_string($content[0]['text'] ?? null) ? $content[0]['text'] : '';
	}

	/**
	 * @return list<string>
	 */
	private function tools(string $token): array
	{
		$tools = self::dig($this->rpc('tools/list', [], $token), 'result', 'tools');

		return array_values(array_filter(array_column(is_array($tools) ? $tools : [], 'name'), is_string(...)));
	}

	public function testIsOffUntilItsTurnedOn(): void
	{
		$this->boot('new Blush\\Mcp\\McpConfig()');
		$token = $this->token('agent', ['editor']);

		$elsewhere = $this->app->container()->make(Kernel::class)->handle(Request::create('/api/elsewhere', 'POST', [], '{}'))->getStatusCode();

		$this->assertSame($elsewhere, $this->post('{}', $token)->getStatusCode(), 'Off, its address is any address the site doesn\'t have (D-754).');
		$this->assertSame(404, $this->app->container()->make(Kernel::class)->handle(Request::create('/.well-known/oauth-authorization-server'))->getStatusCode());
	}

	public function testAsksForATokenAndSaysWhereToSignIn(): void
	{
		$this->boot();

		$response = $this->post('{}', null);

		$this->assertSame(401, $response->getStatusCode());
		$this->assertStringContainsString('resource_metadata="http://localhost/.well-known/oauth-protected-resource/api/mcp"', $response->getHeaderLine('WWW-Authenticate'));
		$this->assertSame(401, $this->post('{}', 'blush_nope')->getStatusCode());
		$this->assertSame(405, $this->app->container()->make(Kernel::class)->handle(Request::create('/api/mcp'))->getStatusCode(), 'No streams (D-751).');

		$metadata = json_decode((string) $this->app->container()->make(Kernel::class)->handle(Request::create('/.well-known/oauth-protected-resource/api/mcp'))->getBody(), true);

		$this->assertIsArray($metadata);
		$this->assertSame('http://localhost/api/mcp', $metadata['resource'] ?? null);
		$this->assertSame(['read', 'drafts', 'account'], $metadata['scopes_supported'] ?? null);
	}

	public function testInitializesAndAnswersNotificationsWithNothing(): void
	{
		$this->boot();
		$token = $this->token('agent', ['editor']);

		$init = $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'Test']], $token)['result'] ?? [];

		$this->assertIsArray($init);
		$this->assertSame('2025-06-18', $init['protocolVersion'] ?? null, 'The client\'s version, when it\'s spoken here.');
		$this->assertSame(['tools' => ['listChanged' => false]], $init['capabilities'] ?? null, 'Tools only (D-753).');
		$this->assertStringContainsString('New entries start as drafts', is_string($init['instructions'] ?? null) ? $init['instructions'] : '');
		$this->assertSame(McpServer::VERSIONS[0], self::dig($this->rpc('initialize', ['protocolVersion' => '1999-01-01'], $token), 'result', 'protocolVersion'), 'Its newest, else.');

		$this->assertSame(202, $this->post('{"jsonrpc":"2.0","method":"notifications/initialized"}', $token)->getStatusCode());
		$this->assertSame(400, $this->post('{"jsonrpc":"2.0","id":1,"method":"ping"}', $token, ['MCP-Protocol-Version' => '1999-01-01'])->getStatusCode());
		$this->assertSame(-32700, self::dig(json_decode((string) $this->post('nope', $token)->getBody(), true), 'error', 'code'));
		$this->assertSame(-32601, self::dig($this->rpc('resources/list', [], $token), 'error', 'code'), 'No resources (D-753).');
		$this->assertSame(-32602, self::dig($this->rpc('tools/call', ['name' => 'nope'], $token), 'error', 'code'));
	}

	public function testOffersToolsByCapabilityAndScope(): void
	{
		$this->boot();

		$editor = $this->token('editor', ['editor']);
		$drafts = $this->token('drafter', ['editor'], capabilities: TokenScope::Drafts->capabilities());
		$reader = $this->token('reader', ['editor'], capabilities: []);

		$this->assertSame(['list_types', 'get_type', 'search_entries', 'get_entry', 'search_media', 'get_media', 'create_entry', 'update_entry', 'edit_body', 'set_status', 'trash_entry', 'restore_entry', 'update_media', 'upload_media'], $this->tools($editor));
		$this->assertSame(['list_types', 'get_type', 'search_entries', 'get_entry', 'search_media', 'get_media', 'create_entry', 'update_entry', 'edit_body', 'update_media', 'upload_media'], $this->tools($drafts), 'Never publishing or trashing.');
		$this->assertSame(['list_types', 'get_type', 'search_entries', 'get_entry', 'search_media', 'get_media'], $this->tools($reader));

		$list = self::dig($this->rpc('tools/list', [], $editor), 'result', 'tools');
		$this->assertIsArray($list);
		$trash = array_find($list, static fn (mixed $tool): bool => is_array($tool) && ($tool['name'] ?? null) === 'trash_entry');
		$this->assertIsArray($trash);
		$this->assertTrue(self::dig($trash, 'annotations', 'destructiveHint') ?? false, 'Honest annotations (D-753).');
	}

	public function testOffersAPluginsToolsByTheirAnnotations(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(ToolRegistry::class);
		$registry->register('acme/test-open', OpenTool::class);
		$registry->register('acme/test-writing', WritingTool::class);
		$registry->register('acme/test-drafts', DraftsTool::class);

		$editor = $this->token('editor', ['editor']);
		$drafts = $this->token('drafter', ['editor'], capabilities: TokenScope::Drafts->capabilities());

		$this->assertSame(['acme_test_writing', 'acme_test_drafts'], array_values(array_filter($this->tools($editor), static fn (string $name): bool => str_starts_with($name, 'acme_'))), 'One without a capability is never offered (D-757).');
		$this->assertSame(['acme_test_drafts'], array_values(array_filter($this->tools($drafts), static fn (string $name): bool => str_starts_with($name, 'acme_'))), 'A write for drafts only when it says so.');
		$this->assertStringContainsString('times must be at most 3', $this->refusal('acme_test_writing', ['times' => 9], $editor), 'Input is checked against the schema first.');
		$this->assertStringContainsString('has no "else"', $this->refusal('acme_test_writing', ['else' => 1], $editor));
		$this->assertSame(['wrote' => 2], $this->data('acme_test_writing', ['times' => 2], $editor));

		$this->assertNotContains('publish', $this->tools($editor), 'Tools for looking after the site aren\'t offered to apps (D-761).');

		$this->expectExceptionMessage('already a tool "acme/test-open"');
		$registry->register('acme-test/open', WritingTool::class);
	}

	public function testReadsWhatTheAccountMaySee(): void
	{
		$this->boot();

		$reader = $this->token('reader', ['member']);
		$author = $this->token('writer', ['author'], 'justintadlock');
		$editor = $this->token('editor', ['editor']);

		$titles = static fn (array $data): array => array_column(is_array($data['entries'] ?? null) ? $data['entries'] : [], 'title');

		$this->assertNotContains('Unfinished', $titles($this->data('search_entries', ['type' => ['post']], $reader)), 'Drafts only for those who may edit them (D-755).');
		$this->assertContains('Mine', $titles($this->data('search_entries', ['type' => ['post']], $author)), 'Their own draft.');
		$this->assertNotContains('Unfinished', $titles($this->data('search_entries', ['type' => ['post']], $author)), 'Not someone else\'s.');
		$this->assertContains('Unfinished', $titles($this->data('search_entries', ['type' => ['post']], $editor)));
		$this->assertContains('Future', $titles($this->data('search_entries', ['type' => ['post'], 'status' => ['scheduled']], $editor)));
		$this->assertSame(['Spring'], array_map(static fn (mixed $title): string => ucfirst(is_string($title) ? $title : ''), $titles($this->data('search_entries', ['related' => ['category' => ['book-reviews']]], $editor))));

		$page = $this->data('search_entries', ['type' => ['post'], 'limit' => 2], $editor);
		$this->assertSame('2', $page['next_cursor'] ?? null);
		$this->assertStringContainsString('next_cursor', $this->refusal('search_entries', ['cursor' => 'x'], $editor));

		$entry = $this->data('get_entry', ['url' => '/about/biography'], $reader);
		$this->assertSame('Biography', $entry['title'] ?? null);
		$this->assertArrayNotHasKey('revision', $entry, 'Only for one the account may edit.');

		$mine = $this->data('get_entry', ['id' => self::idFor('_post/2021-01-01.mine.md')], $author);
		$this->assertIsString($mine['revision'] ?? null);
		$this->assertStringContainsString('A typo hree.', is_string($mine['body'] ?? null) ? $mine['body'] : '');
		$this->assertStringContainsString('no entry with the id', $this->refusal('get_entry', ['id' => self::idFor('_post/2021-01-01.mine.md')], $reader));

		$types = array_column(is_array($this->data('list_types', [], $editor)['types'] ?? null) ? $this->data('list_types', [], $editor)['types'] : [], 'name');
		$this->assertContains('post', $types);
		$this->assertSame('post', $this->data('get_type', ['name' => 'post'], $editor)['name'] ?? null);
	}

	public function testWritesThroughTheOneWritePathAndLogsIt(): void
	{
		$this->boot();
		$author = $this->token('writer', ['author'], 'justintadlock');
		$id     = self::idFor('_post/2021-01-01.mine.md');

		$created = $this->data('create_entry', ['type' => 'post', 'title' => 'Fresh', 'body' => 'Hello.', 'fields' => ['category' => ['art']]], $author);
		$this->assertSame('draft', $created['status'] ?? null, 'Always a draft (D-756).');
		$this->assertStringContainsString('leave "status" out', $this->refusal('create_entry', ['type' => 'post', 'title' => 'X', 'fields' => ['status' => 'published']], $author));

		$revision = $this->data('get_entry', ['id' => $id], $author)['revision'] ?? '';
		$this->assertIsString($revision);

		$edited = $this->data('edit_body', ['id' => $id, 'revision' => $revision, 'find' => 'hree', 'replace' => 'here'], $author);
		$this->assertNotSame($revision, $edited['revision'] ?? null);
		$this->assertStringContainsString('Read it again', $this->refusal('update_entry', ['id' => $id, 'revision' => $revision, 'fields' => ['title' => 'Stale']], $author), 'A stale revision is refused, never overwritten.');
		$this->assertStringContainsString('doesn\'t have that passage', $this->refusal('edit_body', ['id' => $id, 'revision' => self::text($edited, 'revision'), 'find' => 'hree', 'replace' => 'x'], $author));

		$this->clock->advance('PT1M');

		$published = $this->data('set_status', ['id' => $id, 'revision' => self::text($edited, 'revision'), 'status' => 'published'], $author);
		$this->assertSame('published', $published['status'] ?? null);

		[$log] = $this->app->container()->make(ActivityLog::class)->list(new ActivityFilter(subject: $id));

		$this->assertSame(['entry.status', 'entry.update'], array_map(static fn ($activity): string => $activity->action->value, $log), 'Each write is logged (D-758).');
		$this->assertSame(Via::Mcp, $log[0]->via);
		$this->assertSame('Agent', $log[0]->client);
		$this->assertStringContainsString('A typo hree.', is_string($log[1]->before['body'] ?? null) ? $log[1]->before['body'] : '', 'What it replaced, for Undo.');
	}

	public function testTrashingNamesWhatLinksFirstAndRestoresAsADraft(): void
	{
		$this->boot();
		$editor = $this->token('editor', ['editor']);
		$art    = self::idFor('_category/art.md');
		$entry  = $this->data('get_entry', ['id' => $art, 'include' => ['linked_from']], $editor);

		$this->assertContains('spring', array_column(is_array($entry['linked_from'] ?? null) ? $entry['linked_from'] : [], 'title'));
		$this->assertStringContainsString('confirm: true', $this->refusal('trash_entry', ['id' => $art, 'revision' => self::text($entry, 'revision')], $editor), 'Live links are named first (D-608).');
		$this->assertSame('trash', $this->data('trash_entry', ['id' => $art, 'revision' => self::text($entry, 'revision'), 'confirm' => true], $editor)['status'] ?? null);
		$this->assertSame('draft', $this->data('restore_entry', ['id' => $art], $editor)['status'] ?? null);
	}

	public function testLimitsCallsPerToken(): void
	{
		$this->boot('new Blush\\Mcp\\McpConfig(enabled: true, maxCalls: 2)');
		$editor = $this->token('editor', ['editor']);

		$this->data('list_types', [], $editor);
		$this->data('list_types', [], $editor);

		$this->assertStringContainsString('the most the site allows', $this->refusal('list_types', [], $editor), 'D-758.');
	}

	public function testAnAppsTokenWorksOnlyOnTheMcpServer(): void
	{
		$this->boot();
		$this->writeTemporaryFile('config/api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Api\\ApiConfig(enabled: true);\n");
		$this->app = $this->site();

		$account = $this->app->container()->make(Accounts::class)->createTokenOnly('app', ['editor']);
		[, $text] = $this->app->container()->make(Tokens::class)->issue($account, self::idFor('client'), 'Claude', null, 3600);

		$this->assertSame(401, $this->app->container()->make(Kernel::class)->handle(Request::create('/api/v1/entries', 'GET', ['Authorization' => "Bearer {$text}"]))->getStatusCode(), 'D-752.');
		$this->assertSame('list_types', $this->tools($text)[0] ?? null);
	}

	public function testATextOnlyResultIsItsText(): void
	{
		$this->boot();

		$container = $this->app->container();
		$container->make(ToolRegistry::class)->register('shop/greet', GreetTool::class);

		$result = $container->make(McpTools::class)->call('shop_greet', null, $container->make(ToolContexts::class)->forActor(Actor::commandLine()));

		$this->assertSame(
			['content' => [['type' => 'text', 'text' => "Hello.\nfrom PHP"]], 'isError' => false],
			$result,
			'No empty structuredContent for a client to show in place of the message.'
		);
	}

	public function testServesOverStdio(): void
	{
		$this->boot('new Blush\\Mcp\\McpConfig()');

		$account = $this->app->container()->make(Accounts::class)->createTokenOnly('local', ['editor']);
		$input   = fopen('php://memory', 'r+');
		$out     = fopen('php://memory', 'r+');
		$err     = fopen('php://memory', 'r+');

		$this->assertIsResource($input);
		$this->assertIsResource($out);
		$this->assertIsResource($err);

		fwrite($input, "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"ping\"}\n{\"jsonrpc\":\"2.0\",\"method\":\"notifications/initialized\"}\nnot json\n");
		rewind($input);

		$context = $this->app->container()->make(ToolContexts::class)->forAccount($account);
		$this->app->container()->make(McpStdio::class)->serve($input, new Output($out, $err), $context);

		rewind($out);
		$lines = array_values(array_filter(explode("\n", (string) stream_get_contents($out))));

		$this->assertCount(2, $lines, 'One answer a request; none for a notification; works with the server off for the web (D-754).');
		$this->assertSame(['jsonrpc' => '2.0', 'id' => 1, 'result' => []], json_decode($lines[0], true));
		$this->assertSame(-32700, self::dig(json_decode($lines[1], true), 'error', 'code'));
		$this->assertSame(Via::Mcp, $context->actor->via);
	}

	/**
	 * Returns what's at a path of keys in decoded JSON, or `null`.
	 */
	private static function dig(mixed $value, string|int ...$path): mixed
	{
		foreach ($path as $key) {
			if (! is_array($value) || ! array_key_exists($key, $value)) {
				return null;
			}

			$value = $value[$key];
		}

		return $value;
	}

	/**
	 * Returns text at a path in decoded JSON, or `''`.
	 */
	private static function text(mixed $value, string|int ...$path): string
	{
		$found = self::dig($value, ...$path);

		return is_string($found) ? $found : '';
	}
}
