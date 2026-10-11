<?php

/**
 * API token tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Api;

use DateInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Api\ApiAccess;
use Blush\Api\ApiAuthentication;
use Blush\Api\ApiConfig;
use Blush\Api\ApiEntries;
use Blush\Api\ApiResponses;
use Blush\Api\Http\EntriesController;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\Tokens;
use Blush\Auth\TokenThrottle;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ApiAccess::class)]
#[CoversClass(ApiAuthentication::class)]
#[CoversClass(ApiConfig::class)]
#[CoversClass(ApiEntries::class)]
#[CoversClass(ApiResponses::class)]
#[CoversClass(EntriesController::class)]
#[CoversClass(TokenThrottle::class)]
final class ApiTokensTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private function boot(string $config = 'new Blush\\Api\\ApiConfig(enabled: true)'): void
	{
		$this->standardContent();
		$this->entry('_post/2021-01-01.mine.md', "title: Mine\npublished: 2021-01-01\nstatus: draft\nauthor: justintadlock");
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");
		$this->writeTemporaryFile('config/api.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn {$config};\n");

		$this->app = $this->site();
	}

	/**
	 * Makes an account with roles, linked to a profile when one is
	 * named, and a token for it, and returns the token's text.
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

		return $this->app->container()->make(Tokens::class)->make($account, 'Test', $capabilities)[1];
	}

	private function account(string $username): Account
	{
		$account = $this->app->container()->make(Accounts::class)->find($username);
		$this->assertNotNull($account);

		return $account;
	}

	private function get(string $uri, ?string $token = null, string $ip = '203.0.113.9'): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri, 'GET', $token === null ? [] : ['Authorization' => "Bearer {$token}"], server: ['REMOTE_ADDR' => $ip]));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function json(string $uri, ?string $token = null, int $status = 200): array
	{
		$response = $this->get($uri, $token);

		$this->assertSame($status, $response->getStatusCode(), $uri . ': ' . $response->getBody());

		$data = json_decode((string) $response->getBody(), true);
		$this->assertIsArray($data);

		/** @var array<string, mixed> $data */
		return $data;
	}

	/**
	 * @return list<mixed>
	 */
	private function slugs(string $uri, ?string $token = null): array
	{
		$items = $this->json($uri, $token)['items'] ?? [];

		return array_column(is_array($items) ? $items : [], 'slug');
	}

	private function code(string $uri, ?string $token, int $status): mixed
	{
		$error = $this->json($uri, $token, $status)['error'] ?? null;

		return is_array($error) ? ($error['code'] ?? null) : null;
	}

	public function testATokenSeesWhatAnyoneDoesUntilItAsks(): void
	{
		$this->boot();
		$token = $this->token('agent', ['editor']);

		$this->assertSame($this->slugs('/api/v1/entries?type=post'), $this->slugs('/api/v1/entries?type=post', $token), 'Without status, the anonymous answer (D-745).');
		$this->assertSame('public, max-age=60', $this->get('/api/v1/entries')->getHeaderLine('Cache-Control'));
		$this->assertSame('private, no-store', $this->get('/api/v1/entries', $token)->getHeaderLine('Cache-Control'), 'Never kept, never shared.');
		$this->assertStringContainsString('Authorization', $this->app->container()->make(Kernel::class)->handle(Request::create('/api/v1/entries', 'OPTIONS'))->getHeaderLine('Access-Control-Allow-Headers'));
	}

	public function testStatusFindsWhatTheTokenMayEdit(): void
	{
		$this->boot();
		$editor = $this->token('agent', ['editor']);
		$author = $this->token('writer', ['author'], 'justintadlock');
		$draft  = self::idFor('_post/2020-01-01.unfinished.md');

		$this->assertSame(['mine', 'unfinished'], $this->slugs('/api/v1/entries?type=post&status=draft&sort=title', $editor));
		$this->assertSame(['mine'], $this->slugs('/api/v1/entries?type=post&status=draft', $author), 'An author edits only their own (content.post.edit, not .edit.others).');
		$this->assertSame('Unfinished', $this->json("/api/v1/entries/{$draft}?status=draft", $editor)['title'] ?? null);
		$this->assertSame('not_found', $this->code("/api/v1/entries/{$draft}?status=draft", $author, 404));
		$this->assertSame('not_found', $this->code("/api/v1/entries/{$draft}", $editor, 404), 'Without status, a draft isn\'t there.');
		$this->assertContains('_private', $this->slugs('/api/v1/entries?status=published&per_page=100', $editor), 'Hidden pages too, when asked.');
		$this->assertNotContains('_private', $this->slugs('/api/v1/entries?per_page=100', $editor));
		$this->assertSame('invalid_query', $this->code('/api/v1/entries?status=nope', $editor, 400));
	}

	public function testANarrowedTokenSeesLess(): void
	{
		$this->boot();
		$read   = $this->token('reader', ['editor'], capabilities: []);
		$drafts = $this->token('drafter', ['editor'], capabilities: ['content.*.edit', 'content.*.edit.others']);

		$this->assertSame([], $this->slugs('/api/v1/entries?type=post&status=draft', $read), 'Read is what anyone may read.');
		$this->assertSame(['mine', 'unfinished'], $this->slugs('/api/v1/entries?type=post&status=draft&sort=title', $drafts));
	}

	public function testStatusNeedsAToken(): void
	{
		$this->boot();

		$response = $this->get('/api/v1/entries?status=draft');

		$this->assertSame(401, $response->getStatusCode());
		$this->assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
		$this->assertSame('token_required', $this->code('/api/v1/entries?status=draft', null, 401));
	}

	public function testRefusesATokenThatIsntOne(): void
	{
		$this->boot();
		$token  = $this->token('agent', ['editor']);
		$wrong  = substr($token, 0, -4) . 'abcd';
		$tokens = $this->app->container()->make(Tokens::class);

		$this->assertSame('invalid_token', $this->code('/api/v1/entries', $wrong, 401));
		$this->assertSame('Bearer error="invalid_token"', $this->get('/api/v1/entries', 'nope')->getHeaderLine('WWW-Authenticate'));

		$accounts = $this->app->container()->make(Accounts::class);
		$accounts->setSuspended($this->account('agent'), true);
		$this->assertSame('invalid_token', $this->code('/api/v1/entries', $token, 401), 'A suspended account\'s tokens stop (D-744).');

		$accounts->setSuspended($this->account('agent'), false);
		$this->assertSame(200, $this->get('/api/v1/entries', $token)->getStatusCode(), 'And work again once it\'s reinstated.');

		$this->clock->advance(new DateInterval('P91D'));
		$this->assertSame('invalid_token', $this->code('/api/v1/entries', $token, 401), 'Expired after 90 days by default.');

		$fresh = $this->token('other', ['editor']);
		$tokens->revokeAll($this->account('other'));
		$this->assertSame('invalid_token', $this->code('/api/v1/entries', $fresh, 401), 'Revoked.');
	}

	public function testRefusesATokenInTheAddressOrOverPlainHttp(): void
	{
		$this->boot();
		$token = $this->token('agent', ['editor']);

		$this->assertSame('invalid_query', $this->code("/api/v1/entries?access_token={$token}", null, 400));
		$this->assertSame('https_required', $this->code('http://example.com/api/v1/entries', $token, 403));
		$this->assertSame(200, $this->get('https://example.com/api/v1/entries', $token)->getStatusCode());
		$this->assertSame(200, $this->get('http://localhost/api/v1/entries', $token)->getStatusCode());
	}

	public function testCanBeForTokensOnly(): void
	{
		$this->boot('new Blush\\Api\\ApiConfig(enabled: true, access: Blush\\Api\\ApiAccess::Token)');
		$token = $this->token('agent', ['editor']);

		$this->assertSame('token_required', $this->code('/api/v1/site', null, 401));
		$this->assertSame('Blush', $this->json('/api/v1/site', $token)['name'] ?? null);
		$this->assertSame(['enabled' => true, 'path' => '/api', 'maxAge' => 60, 'access' => 'token', 'maxWrites' => 600], ApiConfig::fromArray(['enabled' => true, 'access' => 'token'])->toArray());
	}

	public function testThrottlesTokensThatArent(): void
	{
		$this->boot();
		$token = $this->token('agent', ['editor']);

		for ($i = 0; $i < 20; $i++) {
			$this->get('/api/v1/entries', 'nope');
		}

		$this->assertSame('too_many_attempts', $this->code('/api/v1/entries', $token, 429), 'Even a good token, from that address, until the lockout passes.');
		$this->assertSame(200, $this->get('/api/v1/entries', $token, '203.0.113.10')->getStatusCode(), 'Another address is fine.');
		$this->assertSame(200, $this->get('/api/v1/entries')->getStatusCode(), 'Without a token, nothing\'s throttled.');
	}

	/**
	 * Returns when a token was last used, as kept.
	 *
	 * @phpstan-impure
	 */
	private function lastUsed(string $id): ?int
	{
		return $this->app->container()->make(Tokens::class)->find($id)?->lastUsed;
	}

	public function testWritesWhenATokenWasUsedHourly(): void
	{
		$this->boot();
		$token  = $this->token('agent', ['editor']);
		$id     = Tokens::parse($token)[0] ?? '';

		$this->assertNull($this->lastUsed($id));

		$this->get('/api/v1/site', $token);
		$first = $this->lastUsed($id);
		$this->assertSame($this->clock->now()->getTimestamp(), $first);

		$this->clock->advance(new DateInterval('PT30M'));
		$this->get('/api/v1/site', $token);
		$this->assertSame($first, $this->lastUsed($id), 'Not again within the hour.');
	}
}
