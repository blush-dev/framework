<?php

/**
 * Admin backups test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use ZipArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;
use Blush\Admin\Http\BackupsController;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\Role;
use Blush\Auth\Roles;
use Blush\Backup\BackupMaker;
use Blush\Backup\Backups;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\UploadedFile;

/**
 * Tools → Backups' API (D-884 to D-886): the owner's by default, a
 * backup started as a job and run to its end the way the admin follows
 * one, its note, its download, and deleting it with Undo.
 */
#[CoversClass(BackupsController::class)]
final class AdminBackupsTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Signs in and returns the CSRF token.
	 */
	private function token(): string
	{
		$token = self::json($this->login())['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	public function testIsTheOwnersByDefault(): void
	{
		$this->boot(roles: ['administrator']);
		$token = $this->token();

		$this->assertSame(403, $this->send('GET', '/backups')->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/backups', '{"parts": ["settings"]}', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testMakesNotesDownloadsAndDeletesABackup(): void
	{
		$this->boot(roles: ['owner']);
		$token = $this->token();

		$listed = self::json($this->send('GET', '/backups'));

		$this->assertSame([], $listed['backups'] ?? null);
		$this->assertContains('content', array_column(is_array($listed['parts'] ?? null) ? $listed['parts'] : [], 'name'));
		$this->assertSame(['content', 'site', 'users', 'plugins'], array_column(is_array($listed['groups'] ?? null) ? $listed['groups'] : [], 'key'));
		$this->assertSame('storage/backups/site', $listed['folder'] ?? null);

		$this->assertSame(422, $this->send('POST', '/backups', '{"parts": []}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Something to back up.');

		$started = $this->send('POST', '/backups', '{"parts": ["content", "accounts"], "note": "Before the move"}', ['X-CSRF-Token' => $token]);
		$backup  = self::json($started)['backup'] ?? null;

		$this->assertSame(201, $started->getStatusCode());
		$this->assertIsArray($backup);
		$this->assertSame('running', $backup['status'] ?? null);
		$this->assertSame('jane', $backup['by'] ?? null, 'Her display name: her username, with no name or profile.');
		$this->assertSame(409, $this->send('POST', '/backups', '{"parts": ["roles"]}', ['X-CSRF-Token' => $token])->getStatusCode(), 'One at a time.');

		$id = $backup['job'] ?? null;

		$this->assertIsString($id);

		for ($runs = 0; $runs < 20; $runs++) {
			$job = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

			if (! is_array($job) || $job['status'] !== 'queued') {
				break;
			}
		}

		$this->assertIsArray($job ?? null);
		$this->assertSame('done', $job['status'] ?? null);

		$all  = self::json($this->send('GET', '/backups'))['backups'] ?? null;
		$done = is_array($all) ? ($all[0] ?? null) : null;

		$this->assertIsArray($done);
		$this->assertSame('done', $done['status'] ?? null);
		$this->assertSame(['content', 'accounts'], $done['parts'] ?? null);
		$this->assertGreaterThan(0, $done['size'] ?? 0);

		$name = $done['name'] ?? null;

		$this->assertIsString($name);

		$noted = self::json($this->send('PATCH', "/backups/{$name}", '{"note": "Kept for good"}', ['X-CSRF-Token' => $token]))['backup'] ?? null;

		$this->assertSame('Kept for good', is_array($noted) ? ($noted['note'] ?? null) : null);

		$download = $this->send('GET', "/backups/{$name}/download");

		$this->assertSame(200, $download->getStatusCode());
		$this->assertSame('application/zip', $download->getHeaderLine('Content-Type'));
		$this->assertStringContainsString("attachment; filename=\"{$name}.zip\"", $download->getHeaderLine('Content-Disposition'));

		$this->assertSame(['deleted' => true], self::json($this->send('DELETE', "/backups/{$name}", headers: ['X-CSRF-Token' => $token])));
		$this->assertSame([], self::json($this->send('GET', '/backups'))['backups'] ?? null);
		$this->assertSame(404, $this->send('GET', "/backups/{$name}/download")->getStatusCode());

		$back = self::json($this->send('POST', "/backups/{$name}/undelete", headers: ['X-CSRF-Token' => $token]))['backup'] ?? null;

		$this->assertSame($name, is_array($back) ? ($back['name'] ?? null) : null, 'Undo puts it back.');
		$this->assertSame(404, $this->send('POST', "/backups/{$name}/undelete", headers: ['X-CSRF-Token' => $token])->getStatusCode(), 'Only once.');
	}

	public function testRestoresPartsOfABackupWithTheCapability(): void
	{
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/old-about", "to": "/about"}]');
		$this->boot(roles: ['owner']);
		$token  = $this->token();
		$backup = $this->app->container()->make(BackupMaker::class)->make(['redirects', 'settings']);

		$this->writeTemporaryFile('user/data/redirects.json', '[]');

		$check = self::json($this->send('POST', "/backups/{$backup->name}/check", '{"parts": ["redirects", "accounts"]}', ['X-CSRF-Token' => $token]));

		$this->assertNotSame([], $check['problems'] ?? [], 'It doesn\'t hold accounts.');

		$started = $this->send('POST', "/backups/{$backup->name}/restore", '{"parts": ["redirects"]}', ['X-CSRF-Token' => $token]);
		$id      = self::json($started)['job'] ?? null;

		$this->assertSame(202, $started->getStatusCode());
		$this->assertIsString($id);
		$restoring = self::json($this->send('GET', '/backups'))['restoring'] ?? null;

		$this->assertSame($id, is_array($restoring) ? ($restoring['job'] ?? null) : null);
		$this->assertSame(409, $this->send('POST', '/backups', '{"parts": ["roles"]}', ['X-CSRF-Token' => $token])->getStatusCode(), 'No backup while it restores.');

		for ($runs = 0; $runs < 30; $runs++) {
			$job = self::json($this->send('POST', "/jobs/{$id}/run", headers: ['X-CSRF-Token' => $token]))['job'] ?? null;

			if (! is_array($job) || $job['status'] !== 'queued') {
				break;
			}
		}

		$this->assertIsArray($job ?? null);
		$this->assertSame('done', $job['status'] ?? null);
		$this->assertIsString(is_array($job['result'] ?? null) ? ($job['result']['safety'] ?? null) : null, 'It names the backup made first, for Undo.');
		$this->assertStringContainsString('/old-about', (string) file_get_contents($this->temporaryDirectory() . '/user/data/redirects.json'));
		$after = self::json($this->send('GET', '/backups'));

		$this->assertArrayHasKey('restoring', $after);
		$this->assertNull($after['restoring']);
	}

	public function testRestoringNeedsItsOwnCapability(): void
	{
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/old-about", "to": "/about"}]');
		$this->boot(roles: ['owner']);

		$container = $this->app->container();
		$roles     = $container->make(Roles::class);
		$roles->save([...$roles->stored(), new Role('keeper', 'Keeper', [Capability::SiteBackups->value])]);

		$jane = $container->make(Accounts::class)->find('jane');
		$this->assertNotNull($jane);
		$container->make(Accounts::class)->setRoles($jane, ['keeper']);

		$token  = $this->token();
		$backup = $container->make(BackupMaker::class)->make(['redirects']);

		$this->assertSame(200, $this->send('GET', '/backups')->getStatusCode());
		$this->assertSame(403, $this->send('POST', "/backups/{$backup->name}/check", '{"parts": ["redirects"]}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('POST', "/backups/{$backup->name}/restore", '{"parts": ["redirects"]}', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	/**
	 * Uploads a file, as the browser would.
	 */
	private function upload(string $contents): ResponseInterface
	{
		$token     = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';
		$temporary = $this->writeTemporaryFile('uploads/' . bin2hex(random_bytes(4)), $contents);
		$request   = Request::create('https://example.test/admin/api/backups/upload', 'POST', ['Origin' => 'https://example.test', 'Sec-Fetch-Site' => 'same-origin', 'X-CSRF-Token' => is_string($token) ? $token : ''], '', ['REMOTE_ADDR' => '203.0.113.5'])
			->withCookieParams(['__Host-blush_session' => (string) $this->cookie])
			->withUploadedFiles(['file' => new UploadedFile($temporary, strlen($contents), UPLOAD_ERR_OK, 'backup.zip', 'application/zip')]);

		return $this->app->container()->make(Kernel::class)->handle($request);
	}

	public function testUploadsABackupFromElsewhere(): void
	{
		$this->writeTemporaryFile('user/data/redirects.json', '[{"from": "/old-about", "to": "/about"}]');
		$this->boot(roles: ['owner']);
		$this->login();

		$backups  = $this->app->container()->make(Backups::class);
		$made     = $this->app->container()->make(BackupMaker::class)->make(['redirects']);
		$archive  = $backups->archive($made);
		$this->assertIsString($archive);
		$contents = (string) file_get_contents($archive);

		$this->assertSame(409, $this->upload($contents)->getStatusCode(), 'One with a kept backup\'s name.');

		$backups->delete($made);

		$uploaded = $this->upload($contents);
		$backup   = self::json($uploaded)['backup'] ?? null;

		$this->assertSame(201, $uploaded->getStatusCode());
		$this->assertIsArray($backup);
		$this->assertSame($made->name, $backup['name'] ?? null);
		$this->assertSame('uploaded', $backup['kind'] ?? null);
		$this->assertSame('done', $backup['status'] ?? null);
		$this->assertSame([], glob($backups->folder() . '/.*.upload') ?: [], 'Nothing left behind.');

		$this->assertSame(422, $this->upload('not a zip')->getStatusCode());

		$zip  = new ZipArchive();
		$path = $this->temporaryDirectory() . '/escape.zip';
		$zip->open($path, ZipArchive::CREATE);
		$zip->addFromString('backup.json', (string) json_encode(['name' => 'blush-2026-01-01-0000', 'created' => '2026-01-01T00:00:00+00:00', 'kind' => 'manual', 'formatVersion' => 1]));
		$zip->addFromString('filesystem/../../evil.php', '<?php');
		$zip->close();

		$this->assertSame(422, $this->upload((string) file_get_contents($path))->getStatusCode(), 'A path outside a backup.');
		$this->assertNull($backups->find('blush-2026-01-01-0000'));
	}
}
