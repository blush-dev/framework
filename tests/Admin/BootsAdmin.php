<?php

/**
 * Admin test helpers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use Psr\Http\Message\ResponseInterface;
use Blush\Auth\Accounts;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;

/**
 * Boots a scratch site with the admin on and sends it requests through
 * the kernel, carrying the session cookie like a browser.
 */
trait BootsAdmin
{
	use BootsScratchSite;

	private const string PASSWORD = 'a long enough password';

	private Application $app;

	/**
	 * The session cookie, carried between requests like a browser would.
	 */
	private ?string $cookie = null;

	/**
	 * Boots a scratch site with the admin on (or off) and an editor
	 * account, `jane`. `$config` is more arguments for `AdminConfig`, and
	 * `$environment` replaces or adds variables (`null` removes one).
	 *
	 * @param list<string>                $roles
	 * @param array<string, ?string>      $environment
	 */
	private function boot(bool $enabled = true, string $config = '', array $roles = ['editor'], array $environment = []): void
	{
		$this->writeTemporaryFile('config/admin.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Admin\\AdminConfig(enabled: " . ($enabled ? 'true' : 'false') . "{$config});\n");

		$environment = array_filter(
			[...['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)], ...$environment],
			static fn (?string $value): bool => $value !== null
		);

		$this->app = $this->scratchApplication($environment);
		$this->app->boot();
		$this->app->container()->make(Accounts::class)->create('jane', self::PASSWORD, $roles, 'jane', email: 'jane@example.test');
	}

	/**
	 * @param array<string, string> $headers
	 */
	private function send(string $method, string $path, string $body = '', array $headers = [], string $ip = '203.0.113.5'): ResponseInterface
	{
		return $this->visit($method, "/admin/api{$path}", $body, $headers, $ip);
	}

	/**
	 * Sends a request to any path, carrying the session cookie.
	 *
	 * @param array<string, string> $headers
	 */
	private function visit(string $method, string $path, string $body = '', array $headers = [], string $ip = '203.0.113.5'): ResponseInterface
	{
		$request = Request::create("https://example.test{$path}", $method, $headers, $body, ['REMOTE_ADDR' => $ip]);
		$request = $this->cookie === null ? $request : $request->withCookieParams(['__Host-blush_session' => $this->cookie]);

		$response = $this->app->container()->make(Kernel::class)->handle($request);

		if (preg_match('/__Host-blush_session=([0-9a-f]*);/', $response->getHeaderLine('Set-Cookie'), $match) === 1) {
			$this->cookie = $match[1] === '' ? null : $match[1];
		}

		return $response;
	}

	private function login(string $password = self::PASSWORD, string $ip = '203.0.113.5'): ResponseInterface
	{
		return $this->send('POST', '/login', json_encode(['username' => 'Jane', 'password' => $password]) ?: '', ['Origin' => 'https://example.test', 'Sec-Fetch-Site' => 'same-origin'], $ip);
	}

	/**
	 * @return array<mixed>
	 */
	private static function json(ResponseInterface $response): array
	{
		$data = json_decode((string) $response->getBody(), true);

		return is_array($data) ? $data : [];
	}

	/**
	 * @return array<mixed>
	 */
	private static function account(ResponseInterface $response): array
	{
		$account = self::json($response)['account'] ?? null;

		return is_array($account) ? $account : [];
	}
}
