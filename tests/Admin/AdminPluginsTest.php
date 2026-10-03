<?php

/**
 * Admin Plugins screen's API tests.
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
use Blush\Admin\PluginEditController;
use Blush\Admin\PluginsController;
use Blush\Plugin\Plugins;

#[CoversClass(PluginsController::class)]
#[CoversClass(PluginEditController::class)]
final class AdminPluginsTest extends TestCase
{
	use BootsAdmin;

	private const string PROVIDER = 'Blush\\Tests\\Fixtures\\Plugin\\ComposerPluginProvider';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * A site with a plugin that runs, one that's turned off, one that
	 * needs the one that's off, and one that needs a later Blush.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $config = "disabled: ['acme/off']"): void
	{
		$this->writeTemporaryFile('user/plugins/recipes/plugin.json', json_encode([
			'name'        => 'fixture/recipes',
			'label'       => 'Recipes',
			'namespace'   => 'fixture',
			'version'     => '1.4.0',
			'description' => 'Recipes and cuisines.',
			'provider'    => 'Blush\\Tests\\Fixtures\\Admin\\Recipes\\RecipesProvider',
			'requires'    => ['blush' => '^2.0'],
			'authors'     => [['name' => 'Jane Doe', 'homepage' => 'https://example.test']],
			'license'     => 'MIT'
		]) ?: '');
		$this->writeTemporaryFile('user/plugins/off/plugin.json', json_encode(['name' => 'acme/off', 'label' => 'Off', 'namespace' => 'off', 'version' => '1.2.0', 'provider' => self::PROVIDER]) ?: '');
		$this->writeTemporaryFile('user/plugins/off/composer.json', '{"license": ["MIT", "GPL-2.0-or-later"], "authors": [{"name": "Acme"}]}');
		$this->writeTemporaryFile('user/plugins/needy/plugin.json', json_encode(['name' => 'acme/needy', 'label' => 'Needy', 'namespace' => 'needy', 'provider' => self::PROVIDER, 'requires' => ['acme/off' => '^1.0']]) ?: '');
		$this->writeTemporaryFile('user/plugins/future/plugin.json', json_encode(['name' => 'acme/future', 'label' => 'Future', 'namespace' => 'future', 'provider' => self::PROVIDER, 'requires' => ['blush' => '^9.0']]) ?: '');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig({$config});\n");

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Boots the site again, as the next request would.
	 */
	private function reboot(): void
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', 'APP_SECRET' => str_repeat('s', 64)]);
		$this->app->boot();
	}

	/**
	 * Returns a plugin from `GET plugins`, by name.
	 *
	 * @param  array<mixed> $answer
	 * @return array<mixed>
	 */
	private function plugin(array $answer, string $name): array
	{
		$plugin = array_find(is_array($answer['plugins'] ?? null) ? $answer['plugins'] : [], static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name);
		$this->assertIsArray($plugin, $name);

		return $plugin;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, $data === [] ? '' : (json_encode($data) ?: ''), ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	public function testListsPluginsCheckedAgainstTheSite(): void
	{
		$this->site();

		$answer  = self::json($this->send('GET', '/plugins'));
		$recipes = $this->plugin($answer, 'fixture/recipes');

		$this->assertTrue($answer['config'] ?? null);
		$this->assertFalse($answer['saved'] ?? null);
		$this->assertSame(['acme/future', 'acme/needy', 'acme/off', 'fixture/recipes'], array_column(is_array($answer['plugins'] ?? null) ? $answer['plugins'] : [], 'name'), 'By label.');
		$this->assertSame([
			'name'         => 'fixture/recipes',
			'label'        => 'Recipes',
			'namespace'    => 'fixture',
			'version'      => '1.4.0',
			'description'  => 'Recipes and cuisines.',
			'authors'      => [['name' => 'Jane Doe', 'homepage' => 'https://example.test']],
			'license'      => 'MIT',
			'source'       => 'local',
			'path'         => 'user/plugins/recipes',
			'folder'       => 'user/plugins/recipes',
			'enabled'      => true,
			'running'      => true,
			'requirements' => [['name' => 'blush', 'constraint' => '^2.0', 'kind' => 'blush', 'met' => true, 'note' => 'this site runs 2.0.0-dev', 'label' => '']],
			'blocked'      => null,
			'requiredBy'   => [],
			'locked'       => null,
			'deletable'    => false
		], $recipes);

		$off = $this->plugin($answer, 'acme/off');
		$this->assertFalse($off['enabled'] ?? null);
		$this->assertSame('MIT or GPL-2.0-or-later', $off['license'] ?? null, 'From its composer.json.');
		$this->assertSame([['name' => 'Acme']], $off['authors'] ?? null);
		$this->assertSame(['acme/needy'], $off['requiredBy'] ?? null);
		$this->assertTrue($off['deletable'] ?? null);

		$needy = $this->plugin($answer, 'acme/needy');
		$this->assertTrue($needy['enabled'] ?? null);
		$this->assertFalse($needy['running'] ?? null, 'What it needs is off, so it doesn\'t run.');
		$this->assertSame('Needs Off ^1.0 (is turned off).', $needy['blocked'] ?? null);

		$this->assertSame('Needs Blush ^9.0 (this site runs 2.0.0-dev).', $this->plugin($answer, 'acme/future')['blocked'] ?? null);
		$this->assertSame(['fixture/recipes'], array_map(static fn ($plugin): string => $plugin->name, $this->app->container()->make(Plugins::class)->all()));
	}

	public function testTurnsPluginsOnAndOff(): void
	{
		$this->site();

		$response = $this->write('PUT', '/plugins/acme/off', ['enabled' => true]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['enabled' => true, 'started' => ['Needy'], 'stopped' => [], 'refresh' => true], self::json($response));
		$this->assertSame(['plugins' => ['disabled' => []]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true), 'Saved over config/plugins.php.');

		$this->reboot();
		$this->assertSame(['acme/needy', 'acme/off', 'fixture/recipes'], array_map(static fn ($plugin): string => $plugin->name, $this->app->container()->make(Plugins::class)->all()));

		$answer = self::json($this->write('PUT', '/plugins/acme/off', ['enabled' => false]));
		$this->assertSame(['Needy'], $answer['stopped'] ?? null);

		$this->reboot();
		$this->assertTrue(self::json($this->send('GET', '/plugins'))['saved'] ?? null);
		$this->assertSame(200, $this->write('PATCH', '/settings', ['unset' => ['plugins.disabled']])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json');
	}

	public function testRefusesPluginsThatCantBeTurnedOn(): void
	{
		$this->site();

		$future = $this->write('PUT', '/plugins/acme/future', ['enabled' => true]);
		$this->assertSame(422, $future->getStatusCode());
		$this->assertSame('Future can\'t be turned on. Needs Blush ^9.0 (this site runs 2.0.0-dev).', self::json($future)['error'] ?? null);

		$this->assertSame(404, $this->write('PUT', '/plugins/acme/missing', ['enabled' => true])->getStatusCode());
		$this->assertSame(400, $this->write('PUT', '/plugins/acme/off', ['enabled' => 'yes'])->getStatusCode());
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/settings.json');
	}

	public function testConfigsEnabledListLocksTheRest(): void
	{
		$this->site(config: "enabled: ['fixture/recipes']");

		$this->assertNotNull($this->plugin(self::json($this->send('GET', '/plugins')), 'acme/off')['locked'] ?? null);
		$this->assertSame(409, $this->write('PUT', '/plugins/acme/off', ['enabled' => true])->getStatusCode());
	}

	public function testDeletesPluginsThatAreOff(): void
	{
		$this->site();

		$running = $this->write('DELETE', '/plugins/recipes');
		$this->assertSame(409, $running->getStatusCode());
		$this->assertSame('Recipes is on. Turn it off before deleting it.', self::json($running)['error'] ?? null);

		$response = $this->write('DELETE', '/plugins/off');
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['deleted' => 'user/plugins/off'], self::json($response));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/user/plugins/off');

		$this->assertSame(200, $this->write('DELETE', '/plugins/needy')->getStatusCode(), 'A plugin that\'s on but can\'t run isn\'t running.');
		$this->assertSame(404, $this->write('DELETE', '/plugins/missing')->getStatusCode());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/user/plugins/recipes');
	}

	public function testKeepsPluginsConfigTurnsOnByName(): void
	{
		$this->site(config: "enabled: ['fixture/recipes', 'acme/future']");

		$this->assertSame(409, $this->write('DELETE', '/plugins/future')->getStatusCode(), 'The site would fail without it.');
	}

	public function testNeedsItsCapabilities(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/plugins')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/plugins/acme/off', ['enabled' => true])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/plugins/off')->getStatusCode());
	}

	public function testEachActionNeedsItsOwn(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('switcher', 'Switcher', ['extensions.plugins.view', 'extensions.plugins.activate'])]);\n");
		$this->site(['switcher']);

		$this->assertSame(200, $this->send('GET', '/plugins')->getStatusCode());
		$this->assertSame(200, $this->write('PUT', '/plugins/acme/off', ['enabled' => true])->getStatusCode(), 'Turning on and off (D-389).');
		$this->assertSame(403, $this->write('DELETE', '/plugins/off')->getStatusCode(), 'Deleting is its own.');
		$this->assertSame(200, $this->write('PATCH', '/settings', ['unset' => ['plugins.disabled']])->getStatusCode(), 'The setting the Plugins screen saves.');
		$this->assertSame(403, $this->write('PATCH', '/settings', ['set' => ['app.name' => 'Mine']])->getStatusCode(), 'Other settings need site.settings.');
		$this->assertSame(200, $this->write('POST', '/settings/refresh')->getStatusCode());
	}

	public function testEveryActionNeedsSeeing(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('blind', 'Blind', ['extensions.plugins.activate', 'extensions.plugins.delete'])]);\n");
		$this->site(['blind']);

		$this->assertSame(403, $this->write('PUT', '/plugins/acme/off', ['enabled' => false])->getStatusCode(), 'Every action needs seeing.');
		$this->assertSame(403, $this->write('DELETE', '/plugins/off')->getStatusCode());
	}
}
