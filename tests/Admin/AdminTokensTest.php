<?php

/**
 * Admin token tests.
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
use Blush\Admin\Http\AccountEditController;
use Blush\Admin\Http\DashboardController;
use Blush\Admin\Http\TokensController;
use Blush\Auth\Accounts;
use Blush\Auth\Token;
use Blush\Auth\Tokens;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;

#[CoversClass(TokensController::class)]
#[CoversClass(AccountEditController::class)]
#[CoversClass(DashboardController::class)]
final class AdminTokensTest extends TestCase
{
	use BootsAdmin;

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->boot(roles: $roles);
		$this->app->container()->make(Accounts::class)->create('sam', 'another long password', ['author'], email: 'sam@example.test');
		$this->app->container()->make(Accounts::class)->createTokenOnly('agent', ['editor']);
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
	 * Returns a list in an answer, `[]` when it isn't one.
	 *
	 * @param  array<mixed> $answer
	 * @return array<mixed>
	 */
	private static function listAt(array $answer, string $key): array
	{
		return is_array($answer[$key] ?? null) ? $answer[$key] : [];
	}

	public function testMakesListsAndRevokesYourOwn(): void
	{
		$this->site();

		$made = $this->write('POST', '/accounts/jane/tokens', ['name' => 'Astro', 'expires' => 30, 'capabilities' => null]);
		$body = self::json($made);

		$this->assertSame(201, $made->getStatusCode(), (string) $made->getBody());
		$this->assertMatchesRegularExpression('/\Ablush_/', is_string($body['secret'] ?? null) ? $body['secret'] : '', 'Shown once.');
		$this->assertSame(['id', 'name', 'capabilities', 'created', 'createdBy', 'expires', 'expired', 'lastUsed'], array_keys(is_array($body['token'] ?? null) ? $body['token'] : []), 'Never the hash.');

		$list = self::json($this->send('GET', '/accounts/jane/tokens'));

		$this->assertTrue($list['make'] ?? null);
		$this->assertSame(['Astro'], array_column(self::listAt($list, 'tokens'), 'name'));
		$this->assertSame([['username' => 'jane', 'displayName' => 'jane']], array_column(self::listAt($list, 'tokens'), 'createdBy'));
		$this->assertContains('menus.edit', array_column(is_array($list['capabilities'] ?? null) ? $list['capabilities'] : [], 'name'), 'What a token may be narrowed to.');

		$id = is_array($body['token'] ?? null) && is_string($body['token']['id'] ?? null) ? $body['token']['id'] : '';
		$this->assertSame(204, $this->write('DELETE', "/accounts/jane/tokens/{$id}")->getStatusCode());
		$this->assertSame(404, $this->write('DELETE', "/accounts/jane/tokens/{$id}")->getStatusCode());

		$this->write('POST', '/accounts/jane/tokens', ['name' => 'One']);
		$this->write('POST', '/accounts/jane/tokens', ['name' => 'Two']);
		$this->assertSame(['revoked' => 2], self::json($this->write('DELETE', '/accounts/jane/tokens')));
	}

	public function testChecksWhatsAsked(): void
	{
		$this->site();

		$this->assertSame('name', self::json($this->write('POST', '/accounts/jane/tokens', ['name' => ' ']))['field'] ?? null);
		$this->assertSame('expires', self::json($this->write('POST', '/accounts/jane/tokens', ['name' => 'X', 'expires' => 7]))['field'] ?? null);
		$this->assertSame('capabilities', self::json($this->write('POST', '/accounts/jane/tokens', ['name' => 'X', 'capabilities' => ['Not a name']]))['field'] ?? null);
		$never = self::json($this->write('POST', '/accounts/jane/tokens', ['name' => 'X', 'expires' => null]))['token'] ?? null;
		$this->assertIsArray($never);
		$this->assertArrayHasKey('expires', $never);
		$this->assertNull($never['expires'], 'Never.');
	}

	public function testMakingNeedsTheCapability(): void
	{
		$this->site(['editor']);

		$this->assertFalse(self::json($this->send('GET', '/accounts/jane/tokens'))['make'] ?? null, 'Only the owner and administrator have api.tokens by default (D-749).');
		$this->assertSame(403, $this->write('POST', '/accounts/jane/tokens', ['name' => 'Mine'])->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/accounts/agent/tokens')->getStatusCode(), 'Another\'s need accounts.edit.');
	}

	public function testMakesTokensForTokenOnlyAccountsOnly(): void
	{
		$this->site();

		$this->assertSame(403, $this->write('POST', '/accounts/sam/tokens', ['name' => 'For Sam'])->getStatusCode(), 'Sam signs in, so Sam makes Sam\'s own.');
		$this->assertSame(201, $this->write('POST', '/accounts/agent/tokens', ['name' => 'Claude', 'capabilities' => ['content.*.edit']])->getStatusCode());
		$this->assertSame('capabilities', self::json($this->write('POST', '/accounts/agent/tokens', ['name' => 'More', 'capabilities' => ['site.health']]))['field'] ?? null, 'Never a capability you don\'t have.');

		$tokens = $this->app->container()->make(Tokens::class);
		$sam    = $this->app->container()->make(Accounts::class)->find('sam');
		$this->assertNotNull($sam);
		$tokens->make($sam, 'Sam\'s own');

		$this->assertSame(['Sam\'s own'], array_column(self::listAt(self::json($this->send('GET', '/accounts/sam/tokens')), 'tokens'), 'name'), 'Seen by whoever edits accounts.');
		$this->assertSame(['revoked' => 1], self::json($this->write('DELETE', '/accounts/sam/tokens')), 'And revoked.');

		$owner = $this->app->container()->make(Accounts::class)->createTokenOnly('boss', ['owner']);
		$tokens->make($owner, 'Owner\'s');
		$this->assertSame(403, $this->send('GET', '/accounts/boss/tokens')->getStatusCode(), 'An owner\'s only by an owner (D-500).');
	}

	public function testCreatesATokenOnlyAccountWithItsFirstToken(): void
	{
		$this->site();

		$made = $this->write('POST', '/accounts', ['username' => 'claude', 'email' => '', 'roles' => ['editor'], 'tokenOnly' => true, 'token' => ['name' => 'Claude', 'expires' => 90, 'capabilities' => ['content.*.edit']]]);
		$body = self::json($made);

		$this->assertSame(201, $made->getStatusCode(), (string) $made->getBody());
		$this->assertTrue(self::account($made)['tokenOnly'] ?? null);
		$this->assertArrayHasKey('email', self::account($made));
		$this->assertNull(self::account($made)['email'], 'An email is optional (D-750).');
		$this->assertArrayNotHasKey('link', $body, 'No password link.');
		$this->assertMatchesRegularExpression('/\Ablush_/', is_string($body['secret'] ?? null) ? $body['secret'] : '');
		$this->assertSame(422, $this->write('POST', '/accounts/claude/link')->getStatusCode(), 'It never has a password.');
	}

	public function testTheAdminApiIgnoresTokens(): void
	{
		$this->site();

		$secret  = self::json($this->write('POST', '/accounts/jane/tokens', ['name' => 'Mine']))['secret'] ?? '';
		$this->cookie = null;

		$this->assertSame(401, $this->send('GET', '/dashboard', headers: ['Authorization' => 'Bearer ' . (is_string($secret) ? $secret : '')])->getStatusCode(), 'Sessions only (D-746).');
	}

	public function testTheDashboardWarnsOfTokensAboutToExpire(): void
	{
		$this->site();

		$jane   = $this->app->container()->make(Accounts::class)->find('jane');
		$stores = $this->app->container()->make(RecordStores::class);
		$this->assertNotNull($jane);

		[$token] = $this->app->container()->make(Tokens::class)->make($jane, 'Soon', null, 30);
		$soon    = new Token($jane->id, 'Soon', $token->hash, expires: time() + 3 * 86400, id: $token->id);
		$stores->store(Tokens::table())->save(Tokens::table(), new Record($soon->id, $soon->toArray()));
		$this->app->container()->make(Tokens::class)->make($jane, 'Later', null, 90);

		$this->assertSame(['Soon'], array_column(self::listAt(self::json($this->send('GET', '/dashboard')), 'tokens'), 'name'));
	}
}
