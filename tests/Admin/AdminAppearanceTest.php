<?php

/**
 * Admin Appearance screen's API tests.
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
use Blush\Admin\AppearanceController;
use Blush\Admin\ThemeEditController;
use Blush\Theme\ThemeConfig;

#[CoversClass(AppearanceController::class)]
#[CoversClass(ThemeEditController::class)]
final class AdminAppearanceTest extends TestCase
{
	use BootsAdmin;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site on a child theme of `notebook`, with a third theme installed
	 * (with a preview), one falling back to a theme that isn't installed,
	 * and one broken.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator']): void
	{
		$this->writeTemporaryFile('user/themes/notebook/theme.json', '{"name": "acme/notebook", "label": "Notebook", "namespace": "notebook", "version": "1.2.0", "description": "Lined paper."}');
		$this->writeTemporaryFile('user/themes/notebook/composer.json', '{"name": "acme/notebook", "authors": [{"name": "Jane Doe", "homepage": "https://example.test", "role": "Designer"}]}');
		$this->writeTemporaryFile('user/themes/pocket/theme.json', '{"name": "acme/pocket", "label": "Pocket", "namespace": "pocket", "parent": "acme/notebook"}');
		$this->writeTemporaryFile('user/themes/plate/theme.json', '{"name": "acme/plate", "label": "Plate", "namespace": "plate", "authors": [{"name": "Sam"}], "preview": {"layout": "wide", "type": "Sans throughout", "palette": {"background": ["#FFF", "#111111"], "surface": "#fafafa", "text": "#111", "muted": "#666", "accent": ["#0f6d8c", "#5fb8d8"], "border": "#ddd"}}}');
		$this->writeTemporaryFile('user/themes/plate/style.css', 'body {}');
		$this->writeTemporaryFile('user/themes/orphan/theme.json', '{"name": "acme/orphan", "label": "Orphan", "namespace": "orphan", "parent": "acme/gone"}');
		$this->writeTemporaryFile('user/themes/broken/theme.json', '{"name": 5}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/pocket');\n");
		$this->boot(roles: $roles);
		$this->login();
	}

	public function testDescribesThemesAndTheActiveChain(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/appearance'));

		$this->assertSame('acme/pocket', $answer['active'] ?? null);
		$this->assertSame(['acme/pocket', 'acme/notebook', 'blush/default'], $answer['chain'] ?? null);
		$this->assertTrue($answer['config'] ?? null);
		$this->assertTrue($answer['preview'] ?? null, 'Previews work in development.');

		$themes = is_array($answer['themes'] ?? null) ? $answer['themes'] : [];
		$first  = $themes[0] ?? null;
		$this->assertIsArray($first);
		$this->assertSame('acme/pocket', $first['name'] ?? null, 'The active theme comes first.');
		$this->assertTrue($first['active'] ?? null);
		$this->assertSame('acme/notebook', $first['parent'] ?? null);
		$this->assertSame(['acme/pocket', 'blush/default', 'acme/notebook', 'acme/orphan', 'acme/plate'], array_column($themes, 'name'), 'Then by label.');
		$this->assertArrayHasKey('problem', $answer);
		$this->assertNull($answer['problem']);
		$this->assertFalse($answer['saved'] ?? null);

		$notebook = self::theme($themes, 'acme/notebook');
		$this->assertSame(['name' => 'acme/notebook', 'label' => 'Notebook', 'namespace' => 'notebook', 'version' => '1.2.0', 'description' => 'Lined paper.', 'parent' => null, 'source' => 'local', 'active' => false, 'folder' => 'user/themes/notebook', 'preview' => null, 'authors' => [['name' => 'Jane Doe', 'homepage' => 'https://example.test', 'role' => 'Designer']], 'blocked' => null, 'deletable' => false], $notebook, 'The active theme falls back to it, so it can\'t be deleted.');

		$plate = self::theme($themes, 'acme/plate');
		$this->assertTrue($plate['deletable'] ?? null);
		$this->assertSame([['name' => 'Sam']], $plate['authors'] ?? null, 'A manifest\'s own authors.');
		$this->assertSame([
			'layout'  => 'wide',
			'type'    => 'Sans throughout',
			'palette' => [
				'background' => ['#ffffff', '#111111'],
				'surface'    => ['#fafafa', '#fafafa'],
				'text'       => ['#111111', '#111111'],
				'muted'      => ['#666666', '#666666'],
				'accent'     => ['#0f6d8c', '#5fb8d8'],
				'border'     => ['#dddddd', '#dddddd']
			]
		], $plate['preview'] ?? null, 'Colors are six-digit lowercase hex, and one color is both halves.');

		$blocked = self::theme($themes, 'acme/orphan')['blocked'] ?? null;
		$this->assertIsString($blocked);
		$this->assertStringContainsString('"acme/gone" is not installed', $blocked);

		$default = self::theme($themes, 'blush/default');
		$this->assertArrayHasKey('folder', $default);
		$this->assertNull($default['folder']);
		$this->assertFalse($default['deletable'] ?? null);
		$this->assertIsArray($default['preview'] ?? null, 'The default theme declares its preview.');

		$this->assertSame([], self::theme($themes, 'acme/pocket')['authors'] ?? null, 'Neither file names any.');
		$this->assertSame([['where' => 'user/themes/broken', 'reason' => 'The theme in ' . $this->temporaryDirectory() . '/user/themes/broken needs a "name": vendor/name, such as "acme/nova".', 'deletable' => true]], $answer['invalid'] ?? null);
	}

	public function testActivatesAThemeOverTheConfig(): void
	{
		$this->site();

		$answer = self::json($this->write('PATCH', '/settings', ['set' => ['theme.active' => 'acme/plate']]));

		$this->assertTrue($answer['refresh'] ?? null, 'The theme\'s provider runs at boot, so what\'s compiled is built again.');
		$this->assertSame(['theme' => ['active' => 'acme/plate']], $answer['saved'] ?? null);

		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();

		$this->assertSame('acme/plate', $this->app->container()->make(ThemeConfig::class)->active, 'The saved theme wins over config/theme.php.');

		$answer = self::json($this->send('GET', '/appearance'));
		$this->assertSame('acme/plate', $answer['active'] ?? null);
		$this->assertTrue($answer['saved'] ?? null);
	}

	public function testRefusesThemesThatCantBeActivated(): void
	{
		$this->site();

		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['theme.active' => 'acme/missing']])->getStatusCode());
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['theme.active' => 'acme/orphan']])->getStatusCode(), 'It falls back to a theme that isn\'t installed.');
		$this->assertSame(422, $this->write('PATCH', '/settings', ['set' => ['theme.active' => 'plate']])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json');
	}

	public function testDeletesThemeFolders(): void
	{
		$this->site();

		$response = $this->write('DELETE', '/themes/plate');

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['deleted' => 'user/themes/plate'], self::json($response));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/themes/plate');

		$this->assertSame(200, $this->write('DELETE', '/themes/broken')->getStatusCode(), 'A broken theme can be deleted.');
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/themes/broken');
	}

	public function testKeepsThemesTheSiteUses(): void
	{
		$this->site();

		$active = $this->write('DELETE', '/themes/pocket');
		$this->assertSame(409, $active->getStatusCode());
		$this->assertStringContainsString('Pocket is the active theme', (string) $active->getBody());

		$parent = $this->write('DELETE', '/themes/notebook');
		$this->assertSame(409, $parent->getStatusCode());
		$this->assertStringContainsString('The active theme, Pocket, falls back to Notebook', (string) $parent->getBody());

		$this->assertSame(404, $this->write('DELETE', '/themes/missing')->getStatusCode());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/user/themes/pocket');
		$this->assertDirectoryExists($this->temporaryDirectory() . '/user/themes/notebook');
	}

	public function testNeedsSiteSettings(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/appearance')->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/themes/plate')->getStatusCode());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/user/themes/plate');
	}

	/**
	 * @param  array<mixed>        $themes
	 * @return array<string, mixed>
	 */
	private static function theme(array $themes, string $name): array
	{
		$theme = array_find($themes, static fn (mixed $theme): bool => is_array($theme) && ($theme['name'] ?? null) === $name);

		self::assertIsArray($theme);

		/** @var array<string, mixed> $theme */
		return $theme;
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}
}
