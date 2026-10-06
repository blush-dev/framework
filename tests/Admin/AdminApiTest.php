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
use Blush\Admin\PasswordController;
use Blush\Admin\PreferencesController;
use Blush\Admin\ProfileController;
use Blush\Admin\SessionController;
use Blush\Auth\Accounts;
use Blush\Auth\AccountStore;
use Blush\Auth\Authenticator;
use Blush\Auth\AdminTheme;
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
#[CoversClass(ProfileController::class)]
#[CoversClass(PasswordController::class)]
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
		$this->assertNotContains('accounts.view', $account['capabilities']);
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

		$this->assertSame(['colorScheme' => 'system', 'adminTheme' => 'neutral', 'lastEdited' => null, 'setupSkipped' => false, 'shortcuts' => null], self::account($this->send('GET', '/session'))['preferences'] ?? null);

		$answer = $this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', ['X-CSRF-Token' => $token]);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame(['preferences' => ['colorScheme' => 'dark', 'adminTheme' => 'neutral', 'lastEdited' => null, 'setupSkipped' => false, 'shortcuts' => null]], self::json($answer));
		$this->assertSame(ColorScheme::Dark, $this->app->container()->make(AccountStore::class)->find('jane')?->preferences->colorScheme);
		$this->assertSame(['colorScheme' => 'dark', 'adminTheme' => 'neutral', 'lastEdited' => null, 'setupSkipped' => false, 'shortcuts' => null], self::account($this->send('GET', '/session'))['preferences'] ?? null, 'Saving doesn\'t sign the account out.');

		$this->send('PATCH', '/preferences', '{"adminTheme": "editorial"}', ['X-CSRF-Token' => $token]);

		$saved = $this->app->container()->make(AccountStore::class)->find('jane');

		$this->assertNotNull($saved);
		$this->assertSame(AdminTheme::Editorial, $saved->preferences->adminTheme);
		$this->assertSame(ColorScheme::Dark, $saved->preferences->colorScheme, 'One preference leaves the other.');
		$this->assertSame(400, $this->send('PATCH', '/preferences', '{"adminTheme": "loud"}', ['X-CSRF-Token' => $token])->getStatusCode());

		$this->assertSame(400, $this->send('PATCH', '/preferences', '{"colorScheme": "purple"}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(400, $this->send('PATCH', '/preferences', '"dark"', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('PATCH', '/preferences', '{"colorScheme": "light"}')->getStatusCode(), 'CSRF is checked.');

		$this->send('PATCH', '/preferences', '{"colorScheme": "system", "adminTheme": "neutral"}', ['X-CSRF-Token' => $token]);
		$file = (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json');

		$this->assertStringNotContainsString('preferences', $file, 'Defaults aren\'t stored.');
	}

	public function testAccountsNameThemselves(): void
	{
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane Author\n---\n");
		$this->boot(roles: ['contributor']);
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		$session = self::account($this->send('GET', '/session'));

		$this->assertArrayHasKey('name', $session);
		$this->assertNull($session['name']);
		$this->assertSame('Jane Author', $session['displayName'] ?? null, 'Without a name, the author page\'s title.');
		$this->assertSame([['name' => 'contributor', 'label' => 'Contributor']], $session['roles'] ?? null, 'Roles carry their labels.');

		$answer = $this->send('PATCH', '/profile', '{"name": "  Jane\n Doe "}', ['X-CSRF-Token' => $token]);

		$this->assertSame(200, $answer->getStatusCode());
		$this->assertSame(['name' => 'Jane Doe', 'email' => 'jane@example.test', 'displayName' => 'Jane Doe'], self::json($answer), 'Its own name comes first (D-370).');
		$this->assertSame('Jane Doe', $this->app->container()->make(AccountStore::class)->find('jane')?->name);
		$this->assertSame('Jane Doe', self::account($this->send('GET', '/session'))['displayName'] ?? null);

		$this->assertSame(422, $this->send('PATCH', '/profile', json_encode(['name' => str_repeat('a', 101)]) ?: '', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(400, $this->send('PATCH', '/profile', '{"name": 5}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(400, $this->send('PATCH', '/profile', '{}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('PATCH', '/profile', '{"name": "Mallory"}')->getStatusCode(), 'CSRF is checked.');

		$this->assertSame(['name' => null, 'email' => 'jane@example.test', 'displayName' => 'Jane Author'], self::json($this->send('PATCH', '/profile', '{"name": " "}', ['X-CSRF-Token' => $token])), 'Without one, its profile\'s title.');
		$this->assertStringNotContainsString('"name"', (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/jane.json'), 'No name isn\'t stored.');

		$this->assertSame('jane@new.example', self::json($this->send('PATCH', '/profile', '{"email": "jane@new.example"}', ['X-CSRF-Token' => $token]))['email'] ?? null, 'Its own email address (D-370).');
		$this->assertSame('jane@new.example', self::account($this->send('GET', '/session'))['email'] ?? null);

		$bad = $this->send('PATCH', '/profile', '{"email": "nope"}', ['X-CSRF-Token' => $token]);

		$this->assertSame(422, $bad->getStatusCode());
		$this->assertSame('email', self::json($bad)['field'] ?? null);
		$this->assertSame(422, $this->send('PATCH', '/profile', '{"email": ""}', ['X-CSRF-Token' => $token])->getStatusCode(), 'It can\'t be taken away.');
	}

	public function testAccountsChangeTheirOwnPassword(): void
	{
		$this->boot(roles: ['contributor']);
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		// A second browser, signed in as the same account.
		$first        = $this->cookie;
		$this->cookie = null;
		$this->login();
		$second       = $this->cookie;
		$this->cookie = $first;

		$headers = ['X-CSRF-Token' => $token];
		$new     = 'a brand new long password';

		$wrong = $this->send('POST', '/password', (string) json_encode(['current' => 'not it', 'password' => $new]), $headers);

		$this->assertSame(422, $wrong->getStatusCode());
		$this->assertSame('current', self::json($wrong)['field'] ?? null);

		$short = $this->send('POST', '/password', (string) json_encode(['current' => self::PASSWORD, 'password' => 'short']), $headers);

		$this->assertSame(422, $short->getStatusCode());
		$this->assertSame('password', self::json($short)['field'] ?? null);
		$this->assertSame('Passwords must be at least 12 characters.', self::json($short)['error'] ?? null);

		$this->assertSame(400, $this->send('POST', '/password', '{"password": "a brand new long password"}', $headers)->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/password', (string) json_encode(['current' => self::PASSWORD, 'password' => $new]))->getStatusCode(), 'CSRF is checked.');

		$changed = $this->send('POST', '/password', (string) json_encode(['current' => self::PASSWORD, 'password' => $new]), $headers);

		$this->assertSame(204, $changed->getStatusCode());
		$this->assertNotSame($first, $this->cookie, 'The session has a new id.');
		$this->assertSame('jane', self::account($this->send('GET', '/session'))['username'] ?? null, 'This session stays signed in.');
		$this->assertSame(200, $this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', $headers)->getStatusCode(), 'Its CSRF token still works.');

		$this->cookie = $second;
		$this->assertSame(401, $this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', ['X-CSRF-Token' => 'stale'])->getStatusCode(), 'Other sessions are signed out.');
		$this->assertSame(['account' => null], self::json($this->send('GET', '/session')));

		$this->assertSame(401, $this->login()->getStatusCode(), 'The old password no longer signs in.');
		$this->assertSame(200, $this->login($new)->getStatusCode(), 'A signed-out browser can sign in again.');
	}

	public function testWrongCurrentPasswordsAreThrottled(): void
	{
		$this->boot();
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		$body   = (string) json_encode(['current' => 'not it', 'password' => 'a brand new long password']);
		$status = 0;

		for ($i = 0; $i < 20 && $status !== 429; $i++) {
			$status = $this->send('POST', '/password', $body, ['X-CSRF-Token' => $token])->getStatusCode();
		}

		$this->assertSame(429, $status);
	}

	public function testTheAdminIgnoresTheTrailingSlashSetting(): void
	{
		$this->writeTemporaryFile('config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Routing\\RouteConfig(trailingSlash: true);\n");
		$this->boot();

		$this->assertSame(200, $this->login()->getStatusCode(), 'Signing in isn\'t redirected.');
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';
		$this->assertIsString($token);
		$this->assertSame(200, $this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(200, $this->visit('GET', '/admin/settings')->getStatusCode(), 'Screens aren\'t redirected.');
		$this->assertSame(200, $this->visit('GET', '/admin')->getStatusCode());
	}
}
