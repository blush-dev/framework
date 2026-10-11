<?php

/**
 * OAuth tests.
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
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\TokenScope;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Mcp\Http\OAuthController;
use Blush\Mcp\OAuth\AuthorizationRequest;
use Blush\Mcp\OAuth\OAuthClient;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthError;
use Blush\Mcp\OAuth\OAuthGrants;
use Blush\Mcp\OAuth\OAuthServer;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(OAuthServer::class)]
#[CoversClass(OAuthClients::class)]
#[CoversClass(OAuthClient::class)]
#[CoversClass(OAuthGrants::class)]
#[CoversClass(OAuthController::class)]
#[CoversClass(AuthorizationRequest::class)]
#[CoversClass(OAuthError::class)]
#[CoversClass(TokenScope::class)]
final class OAuthTest extends TestCase
{
	use BuildsContentSite;

	private const string VERIFIER = 'a-verifier-that-is-long-enough-for-pkce-0123456789';

	private const string REDIRECT = 'https://app.example/callback';

	private Application $app;

	private function boot(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");
		$this->writeTemporaryFile('config/mcp.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Mcp\\McpConfig(enabled: true);\n");

		$this->app = $this->site();
	}

	private function server(): OAuthServer
	{
		return $this->app->container()->make(OAuthServer::class);
	}

	/**
	 * @param list<string> $roles
	 */
	private function person(string $username, array $roles): Account
	{
		return $this->app->container()->make(Accounts::class)->create($username, 'a long enough password', $roles, email: "{$username}@example.test");
	}

	/**
	 * Registers an app over HTTP, and returns its `client_id`.
	 */
	private function register(): string
	{
		$response = $this->app->container()->make(Kernel::class)->handle(Request::create('/oauth/register', 'POST', ['Content-Type' => 'application/json'], (string) json_encode(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT], 'client_uri' => 'https://claude.ai']), ['REMOTE_ADDR' => '203.0.113.9']));

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

		$data = json_decode((string) $response->getBody(), true);
		$this->assertIsArray($data);
		$this->assertIsString($data['client_id'] ?? null);

		return $data['client_id'];
	}

	/**
	 * @return array<string, string>
	 */
	private function params(string $client, string $scope = 'drafts'): array
	{
		return [
			'response_type'         => 'code',
			'client_id'             => $client,
			'redirect_uri'          => self::REDIRECT,
			'code_challenge'        => OAuthServer::challenge(self::VERIFIER),
			'code_challenge_method' => 'S256',
			'scope'                 => $scope,
			'state'                 => 'xyz',
			'resource'              => 'http://localhost/api/mcp'
		];
	}

	/**
	 * Returns a query value from an address.
	 */
	private static function query(string $address, string $key): ?string
	{
		parse_str((string) substr($address, (int) strpos($address, '?') + 1), $query);

		return is_string($query[$key] ?? null) ? $query[$key] : null;
	}

	/**
	 * Posts to the token endpoint, as a form.
	 *
	 * @param  array<string, string> $form
	 * @return array{int, array<string, mixed>}
	 */
	private function token(array $form): array
	{
		$response = $this->app->container()->make(Kernel::class)->handle(Request::create('/oauth/token', 'POST', ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($form)));
		$data     = json_decode((string) $response->getBody(), true);

		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return [$response->getStatusCode(), $data];
	}

	public function testDescribesItselfForDiscovery(): void
	{
		$this->boot();

		$metadata = $this->server()->serverMetadata();

		$this->assertSame('http://localhost/admin/authorize', $metadata['authorization_endpoint'], 'The approval screen is the admin\'s (D-752).');
		$this->assertSame(['S256'], $metadata['code_challenge_methods_supported']);
		$this->assertTrue($metadata['client_id_metadata_document_supported']);
		$this->assertSame('*', $this->app->container()->make(Kernel::class)->handle(Request::create('/.well-known/oauth-authorization-server'))->getHeaderLine('Access-Control-Allow-Origin'));
	}

	public function testConnectsAnAppWithPkceAndRotatesRefreshTokens(): void
	{
		$this->boot();
		$jane   = $this->person('jane', ['author']);
		$client = $this->register();
		$asked  = $this->server()->authorization($this->params($client));

		$this->assertSame('Claude', $asked->client->name);
		$this->assertFalse($asked->client->isVerified(), 'It only says where it\'s from.');
		$this->assertSame(TokenScope::Drafts, $asked->scope);

		$back = $this->server()->approve($jane, $asked, TokenScope::Read->capabilities());
		$code = self::query($back, 'code');

		$this->assertStringStartsWith(self::REDIRECT . '?', $back);
		$this->assertSame('xyz', self::query($back, 'state'));
		$this->assertIsString($code);

		[$status, $error] = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client, 'redirect_uri' => self::REDIRECT, 'code_verifier' => 'not-the-verifier-not-the-verifier-not-the-verifier']);
		$this->assertSame([400, 'invalid_grant'], [$status, $error['error'] ?? null], 'The wrong verifier.');

		$back = $this->server()->approve($jane, $this->server()->authorization($this->params($client)), TokenScope::Drafts->capabilities());
		$code = (string) self::query($back, 'code');

		[$status, $tokens] = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client, 'redirect_uri' => self::REDIRECT, 'code_verifier' => self::VERIFIER, 'resource' => 'http://localhost/api/mcp']);

		$this->assertSame(200, $status, json_encode($tokens) ?: '');
		$this->assertSame(['Bearer', 3600, 'drafts'], [$tokens['token_type'] ?? null, $tokens['expires_in'] ?? null, $tokens['scope'] ?? null]);
		$this->assertSame([400, 'invalid_grant'], (function () use ($code, $client): array {
			[$status, $data] = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $client, 'redirect_uri' => self::REDIRECT, 'code_verifier' => self::VERIFIER]);

			return [$status, $data['error'] ?? null];
		})(), 'A code is used once.');

		$mcp = $this->app->container()->make(Kernel::class)->handle(Request::create('/api/mcp', 'POST', ['Authorization' => 'Bearer ' . self::text($tokens, 'access_token')], '{"jsonrpc":"2.0","id":1,"method":"ping"}'));
		$this->assertSame(200, $mcp->getStatusCode(), 'The access token works on the MCP server.');

		[$status, $fresh] = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => self::text($tokens, 'refresh_token'), 'client_id' => $client]);
		$this->assertSame(200, $status);
		$this->assertNotSame($tokens['refresh_token'], $fresh['refresh_token'] ?? null, 'Replaced whenever it\'s used.');

		[$status, $reused] = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => self::text($tokens, 'refresh_token'), 'client_id' => $client]);
		$this->assertSame([400, 'invalid_grant'], [$status, $reused['error'] ?? null]);

		$this->assertSame([], $this->app->container()->make(OAuthGrants::class)->forAccount($jane->id), 'Used twice, it disconnects the app.');
		$this->assertSame(401, $this->app->container()->make(Kernel::class)->handle(Request::create('/api/mcp', 'POST', ['Authorization' => 'Bearer ' . self::text($fresh, 'access_token')], '{}'))->getStatusCode());
	}

	public function testNeverWidensWhatAnAppAskedFor(): void
	{
		$this->boot();
		$jane   = $this->person('jane', ['editor']);
		$asked  = $this->server()->authorization($this->params($this->register(), 'read'));

		$this->expectException(OAuthError::class);
		$this->server()->approve($jane, $asked, TokenScope::Drafts->capabilities());
	}

	public function testRefusesWhoMayNotConnectApps(): void
	{
		$this->boot();
		$member = $this->person('sam', ['member']);
		$asked  = $this->server()->authorization($this->params($this->register()));

		$this->assertFalse($this->server()->mayConnect($member), 'api.connect isn\'t a member\'s (D-752).');

		$this->expectExceptionMessage('may not connect apps');
		$this->server()->approve($member, $asked, TokenScope::Read->capabilities());
	}

	public function testNeverSendsSomeoneToAnAddressTheAppDidntRegister(): void
	{
		$this->boot();
		$client = $this->register();

		try {
			$this->server()->authorization([...$this->params($client), 'redirect_uri' => 'https://evil.example/']);
			$this->fail('Refused.');
		} catch (OAuthError $error) {
			$this->assertFalse($error->redirectable, 'Shown, never sent there.');
		}

		try {
			$this->server()->authorization([...$this->params($client), 'code_challenge_method' => 'plain']);
			$this->fail('Refused.');
		} catch (OAuthError $error) {
			$this->assertTrue($error->redirectable);
			$this->assertSame('invalid_request', $error->error);
		}

		$this->expectExceptionMessage('isn\'t one this site knows');
		$this->server()->authorization([...$this->params($client), 'client_id' => 'nope']);
	}

	public function testSkipsTheScreenForAnAppApprovedBefore(): void
	{
		$this->boot();
		$jane   = $this->person('jane', ['author']);
		$client = $this->register();
		$asked  = $this->server()->authorization($this->params($client));

		$this->assertNull($this->server()->approvedBefore($jane, $asked));

		$this->server()->approve($jane, $asked, TokenScope::Drafts->capabilities());

		$this->assertNotNull($this->server()->approvedBefore($jane, $this->server()->authorization($this->params($client, 'read'))), 'A narrower ask needs no screen.');
		$this->assertNull($this->server()->approvedBefore($jane, $this->server()->authorization($this->params($client, 'account'))), 'A wider one asks again.');
	}

	public function testReadsTheWidestScopeAsked(): void
	{
		$this->assertSame(TokenScope::Account, OAuthServer::scopeOf(null), 'None asked is the account\'s.');
		$this->assertSame(TokenScope::Drafts, OAuthServer::scopeOf('read drafts'));
		$this->assertNull(OAuthServer::scopeOf('admin'));
		$this->assertSame(TokenScope::Drafts, TokenScope::of(['media.edit', 'media.*.upload', 'content.*.edit.others', 'content.*.edit', 'content.*.create']));
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
