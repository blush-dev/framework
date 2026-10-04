<?php

/**
 * Admin extension install tests.
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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\ExtensionBackupController;
use Blush\Admin\ExtensionInstallController;
use Blush\Extension\Install\ExtensionArchive;
use Blush\Extension\Install\ExtensionInstaller;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Http\UploadedFile;
use Blush\Plugin\Plugins;
use Blush\Theme\Themes;

#[CoversClass(ExtensionInstallController::class)]
#[CoversClass(ExtensionInstaller::class)]
#[CoversClass(ExtensionArchive::class)]
#[CoversClass(ExtensionBackupController::class)]
final class AdminInstallTest extends TestCase
{
	use BootsAdmin;

	private const string PROVIDER = 'Blush\\Tests\\Fixtures\\Plugin\\ComposerPluginProvider';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with one plugin installed, and roles that may only install
	 * or only see.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('extensions/fixture/recipes/plugin.json', (string) json_encode(['name' => 'fixture/recipes', 'label' => 'Recipes', 'namespace' => 'recipes', 'version' => '1.0.0', 'provider' => self::PROVIDER]));
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('installer', 'Installer', ['extensions.plugins.view', 'extensions.plugins.install'])]);\n");
		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Makes a zip of files, by their paths in it.
	 *
	 * @param array<string, string> $files
	 */
	private function zip(array $files): string
	{
		$path = $this->temporaryDirectory() . '/zips/' . bin2hex(random_bytes(4)) . '.zip';
		@mkdir(dirname($path), 0777, true);

		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE);

		foreach ($files as $name => $contents) {
			$zip->addFromString($name, $contents);
		}

		$zip->close();

		return (string) file_get_contents($path);
	}

	/**
	 * A plugin's files, in the one folder GitHub's zips have.
	 *
	 * @param  array<string, mixed>  $manifest
	 * @param  array<string, string> $files
	 * @return array<string, string>
	 */
	private static function plugin(array $manifest = [], array $files = []): array
	{
		$files = [
			'plugin.json'       => (string) json_encode([...['name' => 'acme/hello', 'label' => 'Hello', 'namespace' => 'hello', 'version' => '1.0.0', 'provider' => self::PROVIDER], ...$manifest]),
			'src/Provider.php'  => "<?php\n\ndeclare(strict_types=1);\n",
			...$files
		];

		$wrapped = [];

		foreach ($files as $name => $contents) {
			$wrapped["acme-hello-1a2b3c/{$name}"] = $contents;
		}

		return $wrapped;
	}

	/**
	 * Uploads an archive, as the browser would.
	 */
	private function upload(string $path, string $name, string $contents, bool $replace = false): ResponseInterface
	{
		$token     = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';
		$temporary = $this->writeTemporaryFile('uploads/' . bin2hex(random_bytes(4)), $contents);
		$request   = Request::create("https://example.test/admin/api{$path}", 'POST', ['Origin' => 'https://example.test', 'Sec-Fetch-Site' => 'same-origin', 'X-CSRF-Token' => is_string($token) ? $token : ''], '', ['REMOTE_ADDR' => '203.0.113.5'])
			->withCookieParams(['__Host-blush_session' => (string) $this->cookie])
			->withParsedBody($replace ? ['replace' => '1'] : [])
			->withUploadedFiles(['file' => new UploadedFile($temporary, strlen($contents), UPLOAD_ERR_OK, $name, 'application/zip')]);

		return $this->app->container()->make(Kernel::class)->handle($request);
	}

	private function reboot(): void
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();
	}

	public function testInstallsAPluginTurnedOff(): void
	{
		$this->site();

		$upload = self::json($this->send('GET', '/plugins'))['upload'] ?? null;
		$this->assertSame(['limit' => ExtensionInstallController::limit(), 'problem' => null], $upload, 'The screen learns the limit before anything is chosen.');

		$response = $this->upload('/plugins', 'hello.zip', $this->zip(self::plugin()));

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame([
			'installed' => ['name' => 'acme/hello', 'label' => 'Hello', 'version' => '1.0.0', 'folder' => 'extensions/acme/hello'],
			'replaced'  => null,
			'backup'    => null,
			'refresh'   => false
		], self::json($response));
		$this->assertFileExists($this->temporaryDirectory() . '/extensions/acme/hello/src/Provider.php', 'Unpacked from inside the zip\'s one folder.');
		$this->assertSame([], glob($this->temporaryDirectory() . '/extensions/.*-*') ?: [], 'No hidden folder is left.');

		$this->reboot();
		$this->assertTrue($this->app->container()->make(Plugins::class)->installed() !== [] && ! $this->app->container()->make(Plugins::class)->has('acme/hello'), 'It arrives off (D-390).');
	}

	public function testOffersToReplaceOneWithTheSameName(): void
	{
		$this->site();
		$this->upload('/plugins', 'hello.zip', $this->zip(self::plugin()));

		$clash = $this->upload('/plugins', 'hello-1.1.zip', $this->zip(self::plugin(['version' => '1.1.0'])));

		$this->assertSame(409, $clash->getStatusCode());
		$this->assertSame([
			'installed' => ['name' => 'acme/hello', 'label' => 'Hello', 'version' => '1.0.0', 'folder' => 'extensions/acme/hello'],
			'incoming'  => ['name' => 'acme/hello', 'label' => 'Hello', 'version' => '1.1.0', 'folder' => 'extensions/acme/hello']
		], self::json($clash)['clash'] ?? null, 'Nothing is written until replacing is asked for.');

		$replaced = self::json($this->upload('/plugins', 'hello-1.1.zip', $this->zip(self::plugin(['version' => '1.1.0'])), replace: true));

		$this->assertSame('1.0.0', $replaced['replaced'] ?? null);
		$this->assertSame('storage/backups/acme/hello', $replaced['backup'] ?? null);
		$this->assertStringContainsString('1.1.0', (string) file_get_contents($this->temporaryDirectory() . '/extensions/acme/hello/plugin.json'));
		$this->assertStringContainsString('1.0.0', (string) file_get_contents($this->temporaryDirectory() . '/storage/backups/acme/hello/plugin.json'));
	}

	public function testTakesTheNameFromComposerJson(): void
	{
		$this->site();

		$files = self::plugin(files: ['composer.json' => '{"name": "acme/composed", "version": "2.0.0", "license": "MIT"}']);

		$files['acme-hello-1a2b3c/plugin.json'] = (string) json_encode(['label' => 'Hello', 'namespace' => 'hello', 'provider' => self::PROVIDER]);

		$response = $this->upload('/plugins', 'whatever-main.zip', $this->zip($files));

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['name' => 'acme/composed', 'label' => 'Hello', 'version' => '2.0.0', 'folder' => 'extensions/acme/composed'], self::json($response)['installed'] ?? null, 'Its folder is its name, not the zip\'s.');
	}

	public function testRefusesWhatIsntAPluginOrIsntSafe(): void
	{
		$this->site();

		$theme = $this->upload('/plugins', 'notebook.zip', $this->zip(['theme.json' => '{"name": "acme/notebook", "label": "Notebook", "namespace": "notebook"}']));
		$this->assertSame(422, $theme->getStatusCode());
		$this->assertSame(['error' => 'notebook.zip is a theme, not a plugin.', 'kind' => 'theme'], self::json($theme), 'The admin offers the Themes screen.');

		$this->writeTemporaryFile('extensions/other/plate/theme.json', '{"name": "other/plate", "label": "Plate", "namespace": "plate"}');

		$cases = [
			'readme.zip has no plugin.json in it, and no composer.json of type "blush-plugin", so it isn\'t a plugin.' => ['readme.zip', $this->zip(['README.md' => 'Hi.', 'composer.json' => '{"type": "library"}'])],
			'odd.zip holds plugin.json and a composer.json of type "blush-theme", but an extension is one kind.'   => ['odd.zip', $this->zip(['plugin.json' => '{}', 'composer.json' => '{"name": "acme/odd", "type": "blush-theme"}'])],
			'hello.png isn\'t a .zip file.'                                                                          => ['hello.png', 'PNG'],
			'fake.zip isn\'t a .zip file Blush can read.'                                                           => ['fake.zip', 'not a zip'],
			'evil.zip has a file that would land outside its folder (../evil.php), so it wasn\'t unpacked.'          => ['evil.zip', $this->zip(['plugin.json' => '{}', '../evil.php' => '<?php'])],
			'hello.zip can\'t be installed: its namespace, "recipes", is the plugin fixture/recipes\'s.'             => ['hello.zip', $this->zip(self::plugin(['namespace' => 'recipes']))],
			'hello.zip can\'t be installed: other/plate is installed as a theme.'                                    => ['hello.zip', $this->zip(self::plugin(['name' => 'other/plate', 'namespace' => 'other']))],
			'hello.zip needs Composer packages (guzzlehttp/guzzle), so it has to be installed with Composer.'        => ['hello.zip', $this->zip(self::plugin(files: ['composer.json' => '{"require": {"php": ">=8.5", "ext-zip": "*", "guzzlehttp/guzzle": "^7.0"}}']))],
			'hello.zip can\'t be installed: src/Broken.php has a PHP error on line 1 (Unclosed \'(\').' => ['hello.zip', $this->zip(self::plugin(files: ['src/Broken.php' => '<?php function (']))]
		];

		foreach ($cases as $message => [$name, $contents]) {
			$response = $this->upload('/plugins', $name, $contents);

			$this->assertSame(422, $response->getStatusCode(), $message);
			$this->assertSame($message, self::json($response)['error'] ?? null);
		}

		$this->assertSame(['fixture', 'other'], array_map('basename', glob($this->temporaryDirectory() . '/extensions/*') ?: []), 'Nothing was written.');
		$this->assertSame([], glob($this->temporaryDirectory() . '/extensions/.*-*') ?: []);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/evil.php');
	}

	public function testInstallsAPluginWhoseManifestIsItsComposerJson(): void
	{
		$this->site();

		$response = $this->upload('/plugins', 'hello.zip', $this->zip([
			'composer.json'    => (string) json_encode(['name' => 'acme/hello', 'type' => 'blush-plugin', 'version' => '1.0.0', 'extra' => ['blush' => ['label' => 'Hello', 'namespace' => 'hello']]]),
			'src/Provider.php' => "<?php\n\ndeclare(strict_types=1);\n"
		]));

		$this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['name' => 'acme/hello', 'label' => 'Hello', 'version' => '1.0.0', 'folder' => 'extensions/acme/hello'], self::json($response)['installed'] ?? null, 'Its type says it\'s a plugin (D-432).');

		$theme = $this->upload('/plugins', 'nova.zip', $this->zip(['composer.json' => '{"name": "acme/nova", "type": "blush-theme"}']));

		$this->assertSame(['error' => 'nova.zip is a theme, not a plugin.', 'kind' => 'theme'], self::json($theme));
	}

	public function testInstallsThemesAndIconPacks(): void
	{
		$this->site();

		$theme = $this->upload('/themes', 'notebook.zip', $this->zip(['theme.json' => '{"name": "acme/notebook", "label": "Notebook", "namespace": "notebook", "version": "2.0.0"}', 'style.css' => 'body {}']));
		$pack  = $this->upload('/icon-packs', 'weather.zip', $this->zip(['icons.json' => '{"name": "acme/weather", "label": "Weather", "namespace": "weather"}', 'sun.svg' => '<svg/>']));

		$this->assertSame(201, $theme->getStatusCode(), (string) $theme->getBody());
		$this->assertSame(201, $pack->getStatusCode(), (string) $pack->getBody());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/extensions/acme/notebook');
		$this->assertDirectoryExists($this->temporaryDirectory() . '/extensions/acme/weather');

		$this->reboot();
		$themes = self::json($this->send('GET', '/themes'));
		$this->assertSame(Themes::DEFAULT, $themes['active'] ?? null, 'Nothing is activated.');
		$packs = self::json($this->send('GET', '/icon-packs'))['packs'] ?? [];
		$this->assertSame([false], array_column(is_array($packs) ? $packs : [], 'enabled'), 'It arrives off.');
	}

	/**
	 * Sends a request with the CSRF token.
	 */
	private function write(string $method, string $path): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	/**
	 * Installs 1.0.0, then replaces it with 1.1.0, then boots again, as the
	 * next request would.
	 *
	 * @param array<string, mixed> $first What 1.0.0's manifest says besides.
	 */
	private function replaced(array $first = []): void
	{
		$this->upload('/plugins', 'hello.zip', $this->zip(self::plugin($first)));
		$this->upload('/plugins', 'hello-1.1.zip', $this->zip(self::plugin(['version' => '1.1.0'])), replace: true);
		$this->reboot();
	}

	private function backup(): mixed
	{
		$plugins = self::json($this->send('GET', '/plugins'))['plugins'] ?? [];
		$hello   = array_find(is_array($plugins) ? $plugins : [], static fn (mixed $plugin): bool => is_array($plugin) && ($plugin['name'] ?? null) === 'acme/hello');

		return is_array($hello) ? $hello['backup'] ?? null : null;
	}

	public function testRollsBackAndForth(): void
	{
		$this->site();
		$this->replaced();

		$this->assertSame(['version' => '1.0.0'], $this->backup(), 'The list says what replacing kept (D-393).');

		$back = $this->write('POST', '/plugins/acme/hello/rollback');
		$this->assertSame(200, $back->getStatusCode(), (string) $back->getBody());
		$this->assertSame(['rolledBack' => ['name' => 'acme/hello', 'label' => 'Hello', 'version' => '1.0.0', 'folder' => 'extensions/acme/hello'], 'from' => '1.1.0', 'refresh' => false], self::json($back));
		$this->assertSame(['version' => '1.1.0'], $this->backup(), 'The version it replaced is kept, so it can be undone.');

		$this->reboot();
		$forth = self::json($this->write('POST', '/plugins/acme/hello/rollback'))['rolledBack'] ?? null;
		$this->assertSame('1.1.0', is_array($forth) ? $forth['version'] ?? null : null);

		$this->assertSame(200, $this->write('DELETE', '/plugins/acme/hello/backup')->getStatusCode());
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/storage/backups/acme/hello');
		$this->reboot();
		$this->assertNull($this->backup());
		$this->assertSame(404, $this->write('POST', '/plugins/acme/hello/rollback')->getStatusCode());
	}

	public function testRefusesAnEarlierVersionThatWouldntRun(): void
	{
		$this->site();
		$this->replaced(['require' => ['blush-dev/framework' => '^9.0']]);

		$response = $this->write('POST', '/plugins/acme/hello/rollback');

		$this->assertSame(422, $response->getStatusCode());
		$this->assertSame('Hello 1.0.0 can\'t be rolled back to: Needs Blush ^9.0 (this site runs 2.0.0-dev).', self::json($response)['error'] ?? null);
		$this->assertStringContainsString('1.1.0', (string) file_get_contents($this->temporaryDirectory() . '/extensions/acme/hello/plugin.json'));
	}

	public function testDeletingAnExtensionDeletesItsBackup(): void
	{
		$this->site();
		$this->replaced();

		$this->assertSame(200, $this->write('DELETE', '/plugins/acme/hello')->getStatusCode());
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/storage/backups/acme/hello');
	}

	public function testInstallingAndReplacingEachNeedTheirCapability(): void
	{
		$this->site(['installer']);

		$this->assertSame(201, $this->upload('/plugins', 'hello.zip', $this->zip(self::plugin()))->getStatusCode());
		$this->assertSame(403, $this->upload('/plugins', 'hello.zip', $this->zip(self::plugin()), replace: true)->getStatusCode(), 'Replacing needs update (D-389).');
		$this->assertSame(403, $this->upload('/themes', 'notebook.zip', $this->zip(['theme.json' => '{}']))->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/plugins/acme/hello/rollback')->getStatusCode(), 'Rolling back needs update.');
		$this->assertSame(403, $this->write('DELETE', '/plugins/acme/hello/backup')->getStatusCode(), 'Discarding needs delete.');
	}
}
