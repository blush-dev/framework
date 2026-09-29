<?php

/**
 * Admin app and dashboard tests.
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
use Blush\Admin\Action\ActionResult;
use Blush\Admin\Action\AdminActionRegistry;
use Blush\Admin\Action\AdminActions;
use Blush\Admin\Action\ClearCachesAction;
use Blush\Admin\Action\PublishAction;
use Blush\Admin\Action\ReindexAction;
use Blush\Admin\ActionController;
use Blush\Admin\AdminApp;
use Blush\Admin\AssetController;
use Blush\Admin\DashboardController;
use Blush\Admin\ShellController;
use Blush\Auth\Capabilities;
use Blush\Tests\Fixtures\Admin\GreetAction;

#[CoversClass(AdminApp::class)]
#[CoversClass(ShellController::class)]
#[CoversClass(AssetController::class)]
#[CoversClass(DashboardController::class)]
#[CoversClass(ActionController::class)]
#[CoversClass(AdminActions::class)]
#[CoversClass(ActionResult::class)]
#[CoversClass(PublishAction::class)]
#[CoversClass(ReindexAction::class)]
#[CoversClass(ClearCachesAction::class)]
final class AdminAppTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Writes a small built app to a scratch folder and returns its path.
	 */
	private function customApp(): string
	{
		$this->writeTemporaryFile('custom-admin/.vite/manifest.json', '{"src/main.ts": {"file": "assets/app-1.js", "isEntry": true, "css": ["assets/app-1.css"]}}');
		$this->writeTemporaryFile('custom-admin/assets/app-1.js', 'console.log("custom");');
		$this->writeTemporaryFile('custom-admin/assets/app-1.css', 'body{}');
		$this->writeTemporaryFile('custom-admin/assets/notes.txt', 'not servable');

		return $this->temporaryDirectory() . '/custom-admin';
	}

	/**
	 * Signs in and returns the CSRF token.
	 */
	private function token(): string
	{
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	public function testServesTheBundledAppAtEveryScreen(): void
	{
		$this->boot();

		$page = $this->visit('GET', '/admin');
		$body = (string) $page->getBody();

		$this->assertSame(200, $page->getStatusCode());
		$this->assertMatchesRegularExpression('#<script type="module" src="/admin/assets/main-[\w-]+\.js"></script>#', $body);
		$this->assertStringContainsString('"base":"/admin","api":"/admin/api"', $body);
		$this->assertStringContainsString("script-src 'self'", $page->getHeaderLine('Content-Security-Policy'));
		$this->assertSame('DENY', $page->getHeaderLine('X-Frame-Options'));
		$this->assertSame('no-store', $page->getHeaderLine('Cache-Control'));
		$this->assertFalse($page->hasHeader('Set-Cookie'));

		$this->assertSame(200, $this->visit('GET', '/admin/sign-in')->getStatusCode());
		$this->assertSame(200, $this->visit('GET', '/admin/settings/site')->getStatusCode());
		$this->assertSame(404, $this->visit('GET', '/admin/api/nothing')->getStatusCode(), 'The API\'s paths never fall through to the app.');

		preg_match('#/admin/assets/(main-[\w-]+\.js)#', $body, $match);
		$asset = $this->visit('GET', '/admin/assets/' . ($match[1] ?? ''));

		$this->assertSame(200, $asset->getStatusCode());
		$this->assertStringStartsWith('text/javascript', $asset->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('immutable', $asset->getHeaderLine('Cache-Control'));
	}

	public function testServesACustomApp(): void
	{
		$this->boot(config: ", app: '{$this->customApp()}'");

		$body = (string) $this->visit('GET', '/admin')->getBody();

		$this->assertStringContainsString('<link rel="stylesheet" href="/admin/assets/app-1.css">', $body);
		$this->assertStringContainsString('src="/admin/assets/app-1.js"', $body);
		$this->assertSame('console.log("custom");', (string) $this->visit('GET', '/admin/assets/app-1.js')->getBody());
		$this->assertSame(404, $this->visit('GET', '/admin/assets/notes.txt')->getStatusCode());
		$this->assertSame(404, $this->visit('GET', '/admin/assets/../.vite/manifest.json')->getStatusCode());
	}

	public function testSaysWhenThereIsNoBuild(): void
	{
		$this->boot(config: ", app: '{$this->temporaryDirectory()}/missing'");

		$this->assertSame(503, $this->visit('GET', '/admin')->getStatusCode());
	}

	public function testTheDashboardDescribesTheSiteAndTheAccountsActions(): void
	{
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\n");
		$this->writeTemporaryFile('user/content/idea.md', "---\ntitle: Idea\nstatus: draft\n---\n");
		$this->boot();

		$this->assertSame(401, $this->send('GET', '/dashboard')->getStatusCode());

		$this->login();
		$dashboard = self::json($this->send('GET', '/dashboard'));

		$this->assertIsArray($dashboard['site'] ?? null);
		$this->assertIsArray($dashboard['content'] ?? null);
		$this->assertSame('development', $dashboard['site']['environment'] ?? null);
		$this->assertSame(1, $dashboard['content']['draft'] ?? null);
		$this->assertIsArray($dashboard['actions'] ?? null);
		$this->assertSame(['publish', 'reindex', 'clear-caches'], array_column($dashboard['actions'], 'name'));
	}

	public function testRunsActionsTheAccountMay(): void
	{
		$this->boot();
		$token = $this->token();

		$this->assertSame(403, $this->send('POST', '/actions/clear-caches')->getStatusCode(), 'Actions need the CSRF token.');

		$result = self::json($this->send('POST', '/actions/clear-caches', headers: ['X-CSRF-Token' => $token]));

		$this->assertTrue($result['successful'] ?? null);
		$this->assertIsString($result['message'] ?? null);
		$this->assertStringStartsWith('Cleared the caches', $result['message']);

		$reindex = self::json($this->send('POST', '/actions/reindex', headers: ['X-CSRF-Token' => $token]));

		$this->assertTrue($reindex['successful'] ?? null);
		$this->assertSame(404, $this->send('POST', '/actions/nothing', headers: ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testRefusesActionsTheAccountMayNot(): void
	{
		$this->boot(roles: ['author']);
		$token = $this->token();

		$this->assertSame([], self::json($this->send('GET', '/dashboard'))['actions'] ?? null);
		$this->assertSame(403, $this->send('POST', '/actions/publish', headers: ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testExtensionsAddActionsInPhp(): void
	{
		$this->boot(roles: ['administrator']);

		$container = $this->app->container();
		$container->make(Capabilities::class)->register('shop.greet', 'Greet people');
		$container->make(AdminActionRegistry::class)->register('greet', GreetAction::class);

		$token   = $this->token();
		$actions = self::json($this->send('GET', '/dashboard'))['actions'] ?? [];

		$this->assertIsArray($actions);
		$this->assertContains(['name' => 'greet', 'label' => 'Greet', 'description' => 'Says hello.', 'confirm' => 'Say hello?'], $actions);
		$this->assertSame(
			['successful' => true, 'message' => 'Hello.', 'details' => ['from PHP']],
			self::json($this->send('POST', '/actions/greet', headers: ['X-CSRF-Token' => $token]))
		);
	}
}
