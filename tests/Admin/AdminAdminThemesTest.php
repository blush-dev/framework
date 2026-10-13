<?php

/**
 * Admin Admin Themes screen's API tests.
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
use Blush\Admin\AdminThemeEditController;
use Blush\Admin\AdminThemesController;
use Blush\Tests\SavedSettings;

#[CoversClass(AdminThemesController::class)]
#[CoversClass(AdminThemeEditController::class)]
final class AdminAdminThemesTest extends TestCase
{
	use BootsAdmin;
	use SavedSettings;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with a local theme config turns on (unless told not to), one
	 * it doesn't, and a broken one.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], bool $enabled = true): void
	{
		if ($enabled) {
			$this->writeTemporaryFile('config/admin-themes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\AdminTheme\\AdminThemeConfig(enabled: ['acme/harbor']);\n");
		}

		$this->writeTemporaryFile('extensions/acme/harbor/admin-theme.json', '{"name": "acme/harbor", "label": "Harbor", "version": "1.2.0", "description": "Sea blues"}');
		$this->writeTemporaryFile('extensions/acme/harbor/tokens.json', '{"color": {"accent": "light-dark(#1d4e89, #8fb8e8)", "text": "light-dark(#cccccc, #e9ebf0)"}, "typography": {"fontFace": [{"fontFamily": "Inter", "src": "inter.woff2"}]}}');
		$this->writeTemporaryFile('extensions/acme/harbor/inter.woff2', 'wOF2');
		$this->writeTemporaryFile('extensions/acme/dusk/admin-theme.json', '{"name": "acme/dusk", "label": "Dusk"}');
		$this->writeTemporaryFile('extensions/acme/dusk/tokens.json', '{}');
		$this->writeTemporaryFile('extensions/acme/broken/admin-theme.json', '{"name": "acme/broken"}');

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Returns a theme as the list describes it.
	 *
	 * @return array<array-key, mixed>
	 */
	private function theme(string $name): array
	{
		$themes = self::json($this->send('GET', '/admin-themes'))['themes'] ?? null;
		$theme  = array_find(is_array($themes) ? $themes : [], static fn (mixed $theme): bool => is_array($theme) && ($theme['name'] ?? null) === $name);

		$this->assertIsArray($theme);

		return $theme;
	}

	public function testListsThemesNeutralFirst(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/admin-themes'));
		$themes = is_array($answer['themes'] ?? null) ? $answer['themes'] : [];

		$this->assertSame(['blush/neutral', 'acme/dusk', 'blush/editorial', 'acme/harbor'], array_column($themes, 'name'));
		$this->assertSame('blush/neutral', $answer['default'] ?? null);
		$this->assertFalse($answer['saved'] ?? null);
		$this->assertTrue($answer['config'] ?? null);
		$this->assertSame(['extensions/acme/broken'], array_column(is_array($answer['invalid'] ?? null) ? $answer['invalid'] : [], 'where'));

		$harbor = $this->theme('acme/harbor');

		$this->assertTrue($harbor['enabled']);
		$this->assertTrue($harbor['offered']);
		$this->assertFalse($harbor['base']);
		$this->assertFalse($harbor['default']);
		$this->assertTrue($harbor['deletable']);
		$this->assertSame(['color.text.default', 'color.accent.default'], $harbor['changes']);
		$this->assertSame(['Inter'], $harbor['fonts']);
		$this->assertSame('light-dark(#1d4e89, #8fb8e8)', is_array($harbor['preview']) ? $harbor['preview']['accent'] ?? null : null);
		$this->assertCount(2, is_array($harbor['contrast']) ? $harbor['contrast'] : [], 'Faint text, on the background and the surface.');

		$neutral = $this->theme('blush/neutral');

		$this->assertTrue($neutral['base']);
		$this->assertTrue($neutral['default']);
		$this->assertFalse($neutral['deletable'], 'The framework\'s can\'t be deleted.');
		$this->assertFalse($this->theme('acme/dusk')['enabled'], 'A local one is off until it\'s named (D-390).');
	}

	public function testShowsOneTheme(): void
	{
		$this->site();

		$theme = self::json($this->send('GET', '/admin-themes/acme/harbor'))['theme'] ?? null;

		$this->assertIsArray($theme);
		$this->assertSame('Harbor', $theme['label'] ?? null);
		$this->assertSame(404, $this->send('GET', '/admin-themes/acme/missing')->getStatusCode());
	}

	public function testTurnsThemesOnAndOff(): void
	{
		$this->site();

		$response = $this->write('PUT', '/admin-themes/acme/dusk', ['enabled' => true]);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['enabled' => true, 'started' => [], 'stopped' => [], 'refresh' => false], self::json($response));
		$this->assertSame(['admin-themes' => ['enabled' => ['acme/dusk', 'acme/harbor', 'blush/editorial', 'blush/neutral']]], $this->savedSettings());

		$this->reboot();

		$this->assertTrue($this->theme('acme/dusk')['offered']);
		$this->assertSame(200, $this->write('PUT', '/admin-themes/blush/editorial', ['enabled' => false])->getStatusCode());

		$this->reboot();

		$this->assertFalse($this->theme('blush/editorial')['enabled'], 'Once a list is saved, it\'s all of what\'s on.');

		$neutral = $this->write('PUT', '/admin-themes/blush/neutral', ['enabled' => false]);

		$this->assertSame(422, $neutral->getStatusCode());
		$this->assertStringContainsString('always on', (string) $neutral->getBody());
		$this->assertSame(404, $this->write('PUT', '/admin-themes/acme/missing', ['enabled' => true])->getStatusCode());
	}

	public function testSetsTheSitesDefault(): void
	{
		$this->site();

		$response = $this->write('PUT', '/admin-themes/default', ['name' => 'acme/harbor']);

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['admin-themes' => ['default' => 'acme/harbor']], $this->savedSettings());

		$this->reboot();

		$this->assertTrue($this->theme('acme/harbor')['default']);

		// The sign-in screen shows it.
		$this->cookie = null;
		$this->assertStringContainsString('data-admin-theme="acme/harbor"', (string) $this->visit('GET', '/admin')->getBody());
		$this->login();

		$off = $this->write('PUT', '/admin-themes/acme/harbor', ['enabled' => false]);

		$this->assertSame(422, $off->getStatusCode());
		$this->assertStringContainsString('the site\'s default, so it can\'t be turned off', (string) $off->getBody());

		$deleted = $this->write('DELETE', '/admin-themes/acme/harbor');

		$this->assertSame(422, $deleted->getStatusCode());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/extensions/acme/harbor');

		$this->assertSame(422, $this->write('PUT', '/admin-themes/default', ['name' => 'acme/dusk'])->getStatusCode(), 'Only a theme that\'s on.');
		$this->assertSame(400, $this->write('PUT', '/admin-themes/default', ['name' => 'acme/missing'])->getStatusCode());
	}

	public function testEnforcesAThemesRequirements(): void
	{
		$this->writeTemporaryFile('extensions/acme/future/admin-theme.json', '{"name": "acme/future", "label": "Future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->writeTemporaryFile('extensions/acme/future/tokens.json', '{}');
		$this->site();

		$response = $this->write('PUT', '/admin-themes/acme/future', ['enabled' => true]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertStringContainsString('Future can\'t be turned on. Needs Blush ^9.0', (string) $response->getBody());
	}

	public function testDeletesThemeFolders(): void
	{
		$this->site();

		$response = $this->write('DELETE', '/admin-themes/acme/harbor');

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['deleted' => 'extensions/acme/harbor'], self::json($response));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/extensions/acme/harbor');
		$this->assertSame(200, $this->write('DELETE', '/admin-themes/acme/broken')->getStatusCode(), 'A broken theme can be deleted.');
		$this->assertSame(404, $this->write('DELETE', '/admin-themes/blush/editorial')->getStatusCode(), 'The framework\'s aren\'t in extensions/.');
	}

	public function testNeedsItsCapabilities(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/admin-themes')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/admin-themes/acme/harbor', ['enabled' => false])->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/admin-themes/default', ['name' => 'blush/editorial'])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/admin-themes/acme/harbor')->getStatusCode());
	}

	public function testSeeingIsItsOwn(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('looker', 'Looker', ['extensions.admin-themes.view'])]);\n");
		$this->site(['looker']);

		$this->assertSame(200, $this->send('GET', '/admin-themes')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/admin-themes/default', ['name' => 'blush/editorial'])->getStatusCode(), 'Choosing the default is activating (D-389).');
	}

	/**
	 * Boots the site again, as the next request would, with what was saved.
	 */
	private function reboot(): void
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}
}
