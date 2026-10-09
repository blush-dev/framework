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
use Blush\Admin\LogController;
use Blush\Admin\ShellController;
use Blush\Auth\AccountStore;
use Blush\Auth\Capabilities;
use Blush\Content\Entries;
use Blush\Content\Index\EntryFiles;
use Blush\Tests\Fixtures\Admin\GreetAction;

#[CoversClass(AdminApp::class)]
#[CoversClass(ShellController::class)]
#[CoversClass(AssetController::class)]
#[CoversClass(DashboardController::class)]
#[CoversClass(ActionController::class)]
#[CoversClass(LogController::class)]
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
		$this->writeTemporaryFile('custom-admin/.vite/manifest.json', '{"js/app.ts": {"file": "js/app.js", "isEntry": true, "css": ["css/app.css"]}}');
		$this->writeTemporaryFile('custom-admin/js/app.js', 'console.log("custom");');
		$this->writeTemporaryFile('custom-admin/css/app.css', 'body{}');
		$this->writeTemporaryFile('custom-admin/notes.txt', 'not servable');

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

	/**
	 * Returns the titles in one of a dashboard list's groups.
	 *
	 * @param  array<mixed> $groups
	 * @return list<mixed>
	 */
	private static function titles(array $groups, string $group): array
	{
		return is_array($groups[$group] ?? null) ? array_column($groups[$group], 'title') : [];
	}

	public function testServesTheBundledAppAtEveryScreen(): void
	{
		$this->boot();

		$page = $this->visit('GET', '/admin');
		$body = (string) $page->getBody();

		$this->assertSame(200, $page->getStatusCode());
		$this->assertMatchesRegularExpression('#<script type="module" src="/admin/assets/js/admin\.js\?v=[0-9a-f]{8}"></script>#', $body);
		$this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/admin/assets/css/admin\.css\?v=[0-9a-f]{8}">#', $body);
		$this->assertStringContainsString('"base":"/admin","api":"/admin/api"', $body);
		$this->assertStringContainsString("script-src 'self'", $page->getHeaderLine('Content-Security-Policy'));
		$this->assertSame('DENY', $page->getHeaderLine('X-Frame-Options'));
		$this->assertSame('no-store', $page->getHeaderLine('Cache-Control'));
		$this->assertFalse($page->hasHeader('Set-Cookie'));

		$this->assertSame(200, $this->visit('GET', '/admin/sign-in')->getStatusCode());
		$this->assertSame(200, $this->visit('GET', '/admin/settings/site')->getStatusCode());
		$this->assertSame(200, $this->visit('GET', '/admin/entries/0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74')->getStatusCode(), 'Editor screens name entries by id.');
		$this->assertSame(404, $this->visit('GET', '/admin/api/nothing')->getStatusCode(), 'The API\'s paths never fall through to the app.');

		preg_match('#/admin/assets/js/admin\.js\?v=[0-9a-f]{8}#', $body, $match);
		$asset = $this->visit('GET', $match[0] ?? '');

		$this->assertSame(200, $asset->getStatusCode());
		$this->assertStringStartsWith('text/javascript', $asset->getHeaderLine('Content-Type'));
		$this->assertStringContainsString('immutable', $asset->getHeaderLine('Cache-Control'));
		$this->assertSame('no-cache', $this->visit('GET', '/admin/assets/js/admin.js')->getHeaderLine('Cache-Control'), 'Unversioned URLs are checked every time.');
	}

	public function testTheShellCarriesTheAccountsColorScheme(): void
	{
		$this->boot();

		$signedOut = (string) $this->visit('GET', '/admin')->getBody();

		$this->assertStringContainsString('<html lang="en">', $signedOut);
		$this->assertStringContainsString('"colorScheme":null', $signedOut);

		$token = $this->token();
		$this->send('PATCH', '/preferences', '{"colorScheme": "dark"}', ['X-CSRF-Token' => $token]);

		$page = $this->visit('GET', '/admin/drafts');
		$body = (string) $page->getBody();

		$this->assertStringContainsString('<html lang="en" data-color-scheme="dark">', $body);
		$this->assertStringContainsString('"colorScheme":"dark"', $body);
		$this->assertFalse($page->hasHeader('Set-Cookie'), 'The shell only reads the session.');

		$this->send('PATCH', '/preferences', '{"colorScheme": "system"}', ['X-CSRF-Token' => $token]);
		$body = (string) $this->visit('GET', '/admin')->getBody();

		$this->assertStringContainsString('<html lang="en">', $body, 'System sets no attribute.');
		$this->assertStringContainsString('"colorScheme":"system"', $body);

		$this->send('PATCH', '/preferences', '{"adminTheme": "editorial"}', ['X-CSRF-Token' => $token]);
		$body = (string) $this->visit('GET', '/admin')->getBody();

		$this->assertStringContainsString('<html lang="en" data-admin-theme="editorial">', $body);
		$this->assertStringContainsString('"adminTheme":"editorial"', $body);
	}

	public function testServesACustomApp(): void
	{
		$this->boot(config: ", app: '{$this->customApp()}'");

		$body = (string) $this->visit('GET', '/admin')->getBody();

		$this->assertStringContainsString('<link rel="stylesheet" href="/admin/assets/css/app.css?v=' . hash('crc32b', 'body{}') . '">', $body);
		$this->assertStringContainsString('src="/admin/assets/js/app.js?v=' . hash('crc32b', 'console.log("custom");') . '"', $body);
		$this->assertSame('console.log("custom");', (string) $this->visit('GET', '/admin/assets/js/app.js')->getBody());
		$this->assertSame(404, $this->visit('GET', '/admin/assets/notes.txt')->getStatusCode());
		$this->assertSame(404, $this->visit('GET', '/admin/assets/.vite/manifest.json')->getStatusCode());
		$this->assertSame(404, $this->visit('GET', '/admin/assets/../.vite/manifest.json')->getStatusCode());
	}

	public function testSaysWhenThereIsNoBuild(): void
	{
		$this->boot(config: ", app: '{$this->temporaryDirectory()}/missing'");

		$this->assertSame(503, $this->visit('GET', '/admin')->getStatusCode());
	}

	public function testTheDashboardListsWhatNeedsTheAccountFirst(): void
	{
		// Pages don't credit authors unless the site says so (D-329).
		$this->writeTemporaryFile('user/data/types/page.json', '{"kind": "tree"}');
		$this->writeTemporaryFile('user/data/relations/authors.json', '{"kind": "credit", "from": ["page"], "to": ["profile"], "aliases": ["author"]}');
		$this->profiles('jane', 'sam');
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/idea.md', "---\ntitle: Idea\nstatus: draft\nauthors: jane\nupdated: 2026-01-02\n---\n");
		$this->writeTemporaryFile('user/content/notes.md', "---\ntitle: Notes\nstatus: draft\nauthors: sam\nupdated: 2026-01-01\n---\n");
		$this->writeTemporaryFile('user/content/soon.md', "---\ntitle: Soon\npublished: 2099-01-01 09:00:00\nauthors: jane\n---\n");
		$this->boot();

		$this->assertSame(401, $this->send('GET', '/dashboard')->getStatusCode());

		$this->login();
		$dashboard = self::json($this->send('GET', '/dashboard'));

		$this->assertIsArray($dashboard['site'] ?? null);
		$this->assertSame('development', $dashboard['site']['environment'] ?? null);
		$this->assertSame(3, $dashboard['published'] ?? null, 'About, and Jane\'s and Sam\'s profiles.');
		$this->assertArrayHasKey('resume', $dashboard);
		$this->assertNull($dashboard['resume']);
		$this->assertArrayNotHasKey('actions', $dashboard, 'Actions are on the Tools screen.');

		$yours    = $dashboard['yours'] ?? null;
		$everyone = $dashboard['everyone'] ?? null;
		$this->assertIsArray($yours);
		$this->assertIsArray($everyone);
		$this->assertSame(['Idea'], self::titles($yours, 'draft'));
		$this->assertSame(['Soon'], self::titles($yours, 'scheduled'));
		$this->assertSame(['About'], self::titles($yours, 'published'));
		$this->assertSame(['Idea', 'Notes'], self::titles($everyone, 'draft'));
		$this->assertIsArray($everyone['draft']);
		$this->assertSame([true, false], array_column($everyone['draft'], 'yours'));

		$setup = $dashboard['setup'] ?? null;
		$this->assertIsArray($setup);
		$this->assertSame('page', $setup['type'] ?? null);
		$this->assertIsArray($setup['page'] ?? null);
		$this->assertFalse($setup['ownTypes'] ?? null);
	}

	public function testTheDashboardResumesTheEntryLastSaved(): void
	{
		$this->writeTemporaryFile('user/content/idea.md', "---\ntitle: Idea\nstatus: draft\nauthors: jane\n---\n");
		$this->boot();
		$token = $this->token();
		$id    = $this->app->container()->make(EntryFiles::class)->at('idea.md')?->id;
		$this->assertIsString($id);

		$revision = self::json($this->send('GET', "/entries/{$id}"))['revision'] ?? null;
		$this->assertIsString($revision);

		$this->assertSame(200, $this->send('PATCH', "/entries/{$id}", json_encode(['revision' => $revision, 'set' => ['title' => 'A Better Idea']]) ?: '', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame($id, $this->app->container()->make(AccountStore::class)->find('jane')?->preferences->lastEdited);

		$dashboard = self::json($this->send('GET', '/dashboard'));

		$this->assertIsArray($dashboard['resume'] ?? null);
		$this->assertSame('A Better Idea', $dashboard['resume']['title'] ?? null);
		$this->assertIsArray($dashboard['yours'] ?? null);
		$this->assertSame([], $dashboard['yours']['draft'] ?? null, 'The entry being resumed isn\'t listed again.');
	}

	public function testTheSetupPathCanBeSkipped(): void
	{
		$this->boot();
		$token = $this->token();

		$this->assertSame(400, $this->send('PATCH', '/preferences', '{"setupSkipped": "yes"}', ['X-CSRF-Token' => $token])->getStatusCode());

		$answer = self::json($this->send('PATCH', '/preferences', '{"setupSkipped": true}', ['X-CSRF-Token' => $token]));

		$this->assertIsArray($answer['preferences'] ?? null);
		$this->assertTrue($answer['preferences']['setupSkipped'] ?? null);
		$this->assertTrue($this->app->container()->make(AccountStore::class)->find('jane')?->preferences->setupSkipped);
	}

	public function testKeepsTheAccountsShortcuts(): void
	{
		$this->boot();
		$token = $this->token();

		foreach (['"yes"', '["a", "a"]', '["Not An Id"]', json_encode(array_map(static fn (int $n): string => "type:t{$n}", range(1, 31)))] as $bad) {
			$this->assertSame(400, $this->send('PATCH', '/preferences', "{\"shortcuts\": {$bad}}", ['X-CSRF-Token' => $token])->getStatusCode(), (string) $bad);
		}

		$answer = self::json($this->send('PATCH', '/preferences', '{"shortcuts": ["type:post", "media", "settings:general"]}', ['X-CSRF-Token' => $token]));

		$this->assertIsArray($answer['preferences'] ?? null);
		$this->assertSame(['type:post', 'media', 'settings:general'], $answer['preferences']['shortcuts'] ?? null);
		$this->assertSame(['type:post', 'media', 'settings:general'], $this->app->container()->make(AccountStore::class)->find('jane')?->preferences->shortcuts);

		$reset = self::json($this->send('PATCH', '/preferences', '{"shortcuts": null}', ['X-CSRF-Token' => $token]));

		$this->assertIsArray($reset['preferences'] ?? null);
		$this->assertArrayHasKey('shortcuts', $reset['preferences']);
		$this->assertNull($reset['preferences']['shortcuts'], 'Back to the default.');
	}

	public function testListsTheAccountsActionsBySource(): void
	{
		$this->boot();
		$this->login();

		$groups = self::json($this->send('GET', '/actions'))['groups'] ?? null;

		$this->assertIsArray($groups);
		$this->assertSame([['kind' => 'core', 'label' => 'Blush']], array_column($groups, 'source'));
		$this->assertIsArray($groups[0] ?? null);
		$this->assertIsArray($groups[0]['actions'] ?? null);
		$this->assertSame(['publish', 'reindex', 'clear-caches'], array_column($groups[0]['actions'], 'name'));
		$this->assertSame(3, self::json($this->send('GET', '/counts'))['actions'] ?? null);
	}

	public function testReadsTheLogWithTheCapability(): void
	{
		$this->writeTemporaryFile('storage/logs/blush.log', "Cut off\n[2026-10-06T09:00:00+00:00] blush.WARNING: First\n[2026-10-06T09:01:00+00:00] blush.ERROR: Second\nTrace line one\nTrace line two\n");
		$this->boot(roles: ['administrator']);
		$this->login();

		$log = self::json($this->send('GET', '/logs'));

		$this->assertSame('file', $log['driver'] ?? null);
		$this->assertSame('storage/logs/blush.log', $log['file'] ?? null);
		$this->assertSame([
			['time' => '2026-10-06T09:01:00+00:00', 'channel' => 'blush', 'level' => 'error', 'message' => 'Second', 'details' => "Trace line one\nTrace line two"],
			['time' => '2026-10-06T09:00:00+00:00', 'channel' => 'blush', 'level' => 'warning', 'message' => 'First', 'details' => '']
		], $log['entries'] ?? null);

		$download = $this->send('GET', '/logs/download');

		$this->assertSame(200, $download->getStatusCode());
		$this->assertStringContainsString('attachment; filename="blush.log"', $download->getHeaderLine('Content-Disposition'));
	}

	public function testRefusesTheLogWithoutTheCapability(): void
	{
		$this->boot();
		$this->login();

		$this->assertSame(403, $this->send('GET', '/logs')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/logs/download')->getStatusCode());
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

		$queued = self::json($this->send('POST', '/actions/reindex', headers: ['X-CSRF-Token' => $token]));

		$this->assertIsString($queued['job'] ?? null, 'Reindex is a job.');
		$this->assertSame($queued, self::json($this->send('POST', '/actions/reindex', headers: ['X-CSRF-Token' => $token])), 'Asking again while it waits queues it once.');

		$job = self::json($this->send('POST', "/jobs/{$queued['job']}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

		$this->assertIsArray($job);
		$this->assertSame('done', $job['status'] ?? null);
		$this->assertSame('Reindex content', $job['label'] ?? null);
		$this->assertIsString($job['message'] ?? null);
		$this->assertStringStartsWith('Indexed', $job['message']);
		$this->assertSame(404, $this->send('POST', '/actions/nothing', headers: ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testRefusesActionsTheAccountMayNot(): void
	{
		$this->boot(roles: ['author']);
		$token = $this->token();

		$this->assertSame([], self::json($this->send('GET', '/actions'))['groups'] ?? null);
		$this->assertSame(403, $this->send('POST', '/actions/publish', headers: ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testExtensionsAddActionsInPhp(): void
	{
		$this->boot(roles: ['owner']);

		$container = $this->app->container();
		$container->make(Capabilities::class)->register('shop.greet', 'Greet people');
		$container->make(AdminActionRegistry::class)->register('greet', GreetAction::class);

		$token   = $this->token();
		$groups  = self::json($this->send('GET', '/actions'))['groups'] ?? [];
		$this->assertIsArray($groups);
		$actions = array_merge(...array_filter(array_column($groups, 'actions'), is_array(...)));

		$this->assertContains(['name' => 'greet', 'label' => 'Greet', 'description' => 'Says hello.', 'confirm' => 'Say hello?'], $actions);
		$this->assertSame(
			['successful' => true, 'message' => 'Hello.', 'details' => ['from PHP']],
			self::json($this->send('POST', '/actions/greet', headers: ['X-CSRF-Token' => $token]))
		);
	}
}
