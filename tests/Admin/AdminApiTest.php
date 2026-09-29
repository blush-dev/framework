<?php

/**
 * Admin API tests.
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
use Blush\Admin\AdminConfig;
use Blush\Admin\AdminRoutes;
use Blush\Admin\PreferencesController;
use Blush\Admin\SessionController;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\Authenticator;
use Blush\Auth\ColorScheme;
use Blush\Auth\Preferences;
use Blush\Auth\LoginThrottle;
use Blush\Auth\Middleware\Authenticate;
use Blush\Auth\Middleware\VerifyCsrf;
use Blush\Http\ClientIp;

#[CoversClass(AdminConfig::class)]
#[CoversClass(AdminRoutes::class)]
#[CoversClass(SessionController::class)]
#[CoversClass(PreferencesController::class)]
#[CoversClass(Preferences::class)]
#[CoversClass(Authenticator::class)]
#[CoversClass(LoginThrottle::class)]
#[CoversClass(VerifyCsrf::class)]
#[CoversClass(Authenticate::class)]
#[CoversClass(ClientIp::class)]
final class AdminApiTest extends TestCase
{
	use BootsAdmin;

	public function testTheApiOnlyExistsWhenTheAdminIsOn(): void
	{
		$this->boot(enabled: false);

		$this->assertSame(404, $this->send('GET', '/session')->getStatusCode());
	}

	public function testSignsInAndOut(): void
	{
		$this->boot();

		$anonymous = $this->send('GET', '/session');

		$this->assertSame(['account' => null], self::json($anonymous));
		$this->assertFalse($anonymous->hasHeader('Set-Cookie'));
		$this->assertSame('no-store', $anonymous->getHeaderLine('Cache-Control'));

		$response = $this->login();
		$state    = self::json($response);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertNotNull($this->cookie);
		$account  = self::account($response);

		$this->assertSame('jane', $account['username'] ?? null);
		$this->assertIsArray($account['capabilities'] ?? null);
		$this->assertContains('site.publish', $account['capabilities']);
		$this->assertNotContains('accounts.manage', $account['capabilities']);
		$this->assertStringNotContainsString('passwordHash', (string) $response->getBody());
		$this->assertIsString($state['csrfToken'] ?? null);
		$this->assertNotNull($this->app->container()->make(AccountStore::class)->find('jane')?->lastLogin);

		$this->assertSame('jane', self::account($this->send('GET', '/session'))['username'] ?? null);

		$this->assertSame(403, $this->send('POST', '/logout')->getStatusCode(), 'Signing out needs the CSRF token.');

		$logout = $this->send('POST', '/logout', headers: ['X-CSRF-Token' => $state['csrfToken']]);

		$this->assertSame(204, $logout->getStatusCode());
		$this->assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
		$this->assertNull($this->cookie);
		$this->assertSame(['account' => null], self::json($this->send('GET', '/session')));
	}

	public function testRefusesBadSignIns(): void
	{
		$this->boot();

		$this->assertSame(400, $this->send('POST', '/login', 'not json')->getStatusCode());
		$this->assertSame(401, $this->login('wrong password')->getStatusCode());
		$this->assertNull($this->cookie);
		$this->assertSame(401, $this->send('POST', '/logout')->getStatusCode());
	}

	public function testRefusesCrossSiteRequests(): void
	{
		$this->boot();
		$body = json_encode(['username' => 'jane', 'password' => self::PASSWORD]) ?: '';

		$this->assertSame(403, $this->send('POST', '/login', $body, ['Sec-Fetch-Site' => 'cross-site'])->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/login', $body, ['Origin' => 'https://evil.test'])->getStatusCode());
		$this->assertSame(200, $this->send('POST', '/login', $body)->getStatusCode(), 'Clients that send neither header are let through.');
	}

	public function testLocksOutRepeatedFailures(): void
	{
		$this->boot();

		for ($attempt = 0; $attempt < 5; $attempt++) {
			$this->assertSame(401, $this->login('wrong password')->getStatusCode());
		}

		$locked = $this->login();

		$this->assertSame(429, $locked->getStatusCode());
		$this->assertSame('900', $locked->getHeaderLine('Retry-After'));
		$this->assertSame(200, $this->login(ip: '198.51.100.7')->getStatusCode(), 'Another address isn\'t locked out.');
	}

	public function testChangingThePasswordSignsOutSessions(): void
	{
		$this->boot();
		$this->login();

		$container = $this->app->container();
		$account   = $container->make(AccountStore::class)->find('jane');
		$this->assertNotNull($account);
		$container->make(Accounts::class)->setPassword($account, 'a brand new password');

		$this->assertSame(['account' => null], self::json($this->send('GET', '/session')));
	}

	public function testAccountsSetTheirOwnPreferences(): void
	{
		$this->boot(roles: ['contributor']);
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		$this->assertSame(['colorScheme' => 'system'], self::account($this->send('GET', '/session'))['preferences'] ?? null);

		$answer = $this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', ['X-CSRF-Token' => $token]);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame(['preferences' => ['colorScheme' => 'dark']], self::json($answer));
		$this->assertSame(ColorScheme::Dark, $this->app->container()->make(AccountStore::class)->find('jane')?->preferences->colorScheme);
		$this->assertSame(['colorScheme' => 'dark'], self::account($this->send('GET', '/session'))['preferences'] ?? null, 'Saving doesn\'t sign the account out.');

		$this->assertSame(400, $this->send('PATCH', '/preferences', '{"colorScheme": "purple"}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(400, $this->send('PATCH', '/preferences', '"dark"', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('PATCH', '/preferences', '{"colorScheme": "light"}')->getStatusCode(), 'CSRF is checked.');

		$this->send('PATCH', '/preferences', '{"colorScheme": "system"}', ['X-CSRF-Token' => $token]);
		$file = (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json');

		$this->assertStringNotContainsString('preferences', $file, 'Defaults aren\'t stored.');
	}
}
