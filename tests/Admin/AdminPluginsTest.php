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
	 * A site with a plugin that runs, one that isn't turned on, one that
	 * needs the one that's off, and one that needs a later Blush.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['administrator'], string $config = "enabled: ['fixture/recipes', 'acme/needy', 'acme/future']"): void
	{
		$this->writeTemporaryFile('extensions/fixture/recipes/plugin.json', json_encode([
			'name'        => 'fixture/recipes',
			'label'       => 'Recipes',
			'namespace'   => 'fixture',
			'version'     => '1.4.0',
			'description' => 'Recipes and cuisines.',
			'provider'    => 'Blush\\Tests\\Fixtures\\Admin\\Recipes\\RecipesProvider',
			'require' => ['blush-dev/framework' => '^2.0'],
			'authors'     => [['name' => 'Jane Doe', 'homepage' => 'https://example.test']],
			'license'     => 'MIT'
		]) ?: '');
		$this->writeTemporaryFile('extensions/acme/off/plugin.json', json_encode(['name' => 'acme/off', 'label' => 'Off', 'namespace' => 'off', 'version' => '1.2.0', 'provider' => self::PROVIDER]) ?: '');
		$this->writeTemporaryFile('extensions/acme/off/composer.json', '{"license": ["MIT", "GPL-2.0-or-later"], "authors": [{"name": "Acme"}]}');
		$this->writeTemporaryFile('extensions/acme/needy/plugin.json', json_encode(['name' => 'acme/needy', 'label' => 'Needy', 'namespace' => 'needy', 'provider' => self::PROVIDER, 'require' => ['acme/off' => '^1.0']]) ?: '');
		$this->writeTemporaryFile('extensions/acme/future/plugin.json', json_encode(['name' => 'acme/future', 'label' => 'Future', 'namespace' => 'future', 'provider' => self::PROVIDER, 'require' => ['blush-dev/framework' => '^9.0']]) ?: '');
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
			'licenses'     => [['text' => 'MIT', 'url' => 'https://spdx.org/licenses/MIT.html', 'operator' => false]],
			'links'        => [],
			'funding'      => [],
			'source'       => 'local',
			'path'         => 'extensions/fixture/recipes',
			'folder'       => 'extensions/fixture/recipes',
			'enabled'      => true,
			'running'      => true,
			'requirements' => [['name' => 'blush-dev/framework', 'constraint' => '^2.0', 'kind' => 'blush', 'met' => true, 'note' => 'this site runs 2.0.0-dev', 'label' => '']],
			'blocked'      => null,
			'requiredBy'   => [],
			'abandoned'    => false,
			'replacement'  => null,
			'deletable'    => false,
			'backup'       => null
		], $recipes);

		$off = $this->plugin($answer, 'acme/off');
		$this->assertFalse($off['enabled'] ?? null);
		$this->assertSame('MIT or GPL-2.0-or-later', $off['license'] ?? null, 'From its composer.json.');
		$this->assertSame([['name' => 'Acme']], $off['authors'] ?? null);
		$this->assertSame([['name' => 'acme/needy', 'label' => 'Needy', 'kind' => 'plugin']], $off['requiredBy'] ?? null);
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
		$this->assertSame(['plugins' => ['enabled' => ['acme/future', 'acme/needy', 'acme/off', 'fixture/recipes']]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true), 'Saved over config/plugins.php.');

		$this->reboot();
		$this->assertSame(['acme/needy', 'acme/off', 'fixture/recipes'], array_map(static fn ($plugin): string => $plugin->name, $this->app->container()->make(Plugins::class)->all()));

		$answer = self::json($this->write('PUT', '/plugins/acme/off', ['enabled' => false]));
		$this->assertSame(['Needy'], $answer['stopped'] ?? null);

		$this->reboot();
		$this->assertTrue(self::json($this->send('GET', '/plugins'))['saved'] ?? null);
		$this->assertSame(200, $this->write('PATCH', '/settings', ['unset' => ['plugins.enabled']])->getStatusCode());
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

	public function testTurnsComposerPluginsOffOnceAListIsSaved(): void
	{
		$this->writeTemporaryFile('vendor/composer/installed.json', (string) json_encode(['packages' => [[
			'name'         => 'acme/packaged',
			'version'      => '1.0.0',
			'type'         => 'blush-plugin',
			'extra'        => ['blush' => ['label' => 'Packaged', 'namespace' => 'packaged', 'provider' => self::PROVIDER]],
			'install-path' => '../acme/packaged'
		]]]));
		$this->writeTemporaryFile('vendor/acme/packaged/composer.json', '{"name": "acme/packaged"}');
		$this->site();

		$this->assertTrue($this->plugin(self::json($this->send('GET', '/plugins')), 'acme/packaged')['running'] ?? null, 'On by default (D-390).');

		$response = $this->write('PUT', '/plugins/acme/packaged', ['enabled' => false]);
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['plugins' => ['enabled' => ['acme/future', 'acme/needy', 'fixture/recipes']]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true), 'The first save starts from what was on (D-391).');

		$this->reboot();
		$this->assertFalse($this->app->container()->make(Plugins::class)->has('acme/packaged'));
		$this->assertSame(['fixture/recipes'], array_map(static fn ($plugin): string => $plugin->name, $this->app->container()->make(Plugins::class)->all()));

		$this->assertSame(200, $this->write('PUT', '/plugins/acme/packaged', ['enabled' => true])->getStatusCode());
		$this->reboot();
		$this->assertTrue($this->app->container()->make(Plugins::class)->has('acme/packaged'));
	}

	public function testNothingLocalIsOnUntilNamed(): void
	{
		$this->site(config: '');

		$recipes = $this->plugin(self::json($this->send('GET', '/plugins')), 'fixture/recipes');
		$this->assertFalse($recipes['enabled'] ?? null, 'D-390.');
		$this->assertFalse($recipes['running'] ?? null);
		$this->assertSame([], $this->app->container()->make(Plugins::class)->all());

		$this->assertSame(200, $this->write('PUT', '/plugins/fixture/recipes', ['enabled' => true])->getStatusCode());
		$this->reboot();
		$this->assertTrue($this->app->container()->make(Plugins::class)->has('fixture/recipes'));
	}

	public function testDeletesPluginsThatAreOff(): void
	{
		$this->site();

		$running = $this->write('DELETE', '/plugins/fixture/recipes');
		$this->assertSame(409, $running->getStatusCode());
		$this->assertSame('Recipes is on. Turn it off before deleting it.', self::json($running)['error'] ?? null);

		$response = $this->write('DELETE', '/plugins/acme/off');
		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$this->assertSame(['deleted' => 'extensions/acme/off'], self::json($response));
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/extensions/acme/off');

		$this->assertSame(409, $this->write('DELETE', '/plugins/acme/needy')->getStatusCode(), 'config/plugins.php names it, though it can\'t run.');
		$this->assertSame(404, $this->write('DELETE', '/plugins/acme/missing')->getStatusCode());
		$this->assertDirectoryExists($this->temporaryDirectory() . '/extensions/fixture/recipes');
	}

	public function testListsBrokenPlugins(): void
	{
		$this->writeTemporaryFile('extensions/acme/broken/plugin.json', '{broken');
		$this->writeTemporaryFile('extensions/acme/named/plugin.json', '{"name": "acme/named", "label": "Named", "namespace": "Not Valid"}');
		$this->site(config: "enabled: ['fixture/recipes', 'acme/named']");

		$answer = self::json($this->send('GET', '/plugins'));

		$this->assertSame(['acme/future', 'acme/needy', 'acme/off', 'fixture/recipes'], array_column(is_array($answer['plugins'] ?? null) ? $answer['plugins'] : [], 'name'), 'The site boots, and lists the rest.');

		$invalid = is_array($answer['invalid'] ?? null) ? $answer['invalid'] : [];
		$this->assertSame(['extensions/acme/broken', 'extensions/acme/named'], array_column($invalid, 'where'));
		$this->assertSame([null, 'acme/named'], array_column($invalid, 'name'));
		$this->assertSame([false, true], array_column($invalid, 'enabled'), 'Config names one, though it doesn\'t run.');
		$this->assertSame([true, false], array_column($invalid, 'deletable'), 'One config names stays (D-394).');
		$this->assertStringContainsString('extensions/acme/broken/plugin.json', is_array($invalid[0] ?? null) && is_string($invalid[0]['reason'] ?? null) ? $invalid[0]['reason'] : '');
		$this->assertSame(['fixture/recipes'], array_map(static fn ($plugin): string => $plugin->name, $this->app->container()->make(Plugins::class)->all()));

		$this->assertSame(422, $this->write('PUT', '/plugins/acme/named', ['enabled' => true])->getStatusCode(), 'A broken one can\'t be turned on.');

		$off = $this->write('PUT', '/plugins/acme/named', ['enabled' => false]);
		$this->assertSame(200, $off->getStatusCode(), (string) $off->getBody());
		$this->assertSame(['plugins' => ['enabled' => ['fixture/recipes']]], json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/settings.json'), true), 'But it can be turned off.');

		$this->reboot();
		$this->assertSame(200, $this->write('DELETE', '/plugins/acme/broken')->getStatusCode());
		$this->assertDirectoryDoesNotExist($this->temporaryDirectory() . '/extensions/acme/broken');
	}

	public function testKeepsPluginsConfigTurnsOnByName(): void
	{
		$this->site(config: "enabled: ['fixture/recipes', 'acme/future']");

		$this->assertSame(409, $this->write('DELETE', '/plugins/acme/future')->getStatusCode(), 'The site would fail without it.');
	}

	public function testNeedsItsCapabilities(): void
	{
		$this->site(['editor']);

		$this->assertSame(403, $this->send('GET', '/plugins')->getStatusCode());
		$this->assertSame(403, $this->write('PUT', '/plugins/acme/off', ['enabled' => true])->getStatusCode());
		$this->assertSame(403, $this->write('DELETE', '/plugins/acme/off')->getStatusCode());
	}

	public function testEachActionNeedsItsOwn(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('switcher', 'Switcher', ['extensions.plugins.view', 'extensions.plugins.activate'])]);\n");
		$this->site(['switcher']);

		$this->assertSame(200, $this->send('GET', '/plugins')->getStatusCode());
		$this->assertSame(200, $this->write('PUT', '/plugins/acme/off', ['enabled' => true])->getStatusCode(), 'Turning on and off (D-389).');
		$this->assertSame(403, $this->write('DELETE', '/plugins/acme/off')->getStatusCode(), 'Deleting is its own.');
		$this->assertSame(200, $this->write('PATCH', '/settings', ['unset' => ['plugins.enabled']])->getStatusCode(), 'The setting the Plugins screen saves.');
		$this->assertSame(403, $this->write('PATCH', '/settings', ['set' => ['app.name' => 'Mine']])->getStatusCode(), 'Other settings need site.settings.');
		$this->assertSame(200, $this->write('POST', '/settings/refresh')->getStatusCode());
	}

	public function testEveryActionNeedsSeeing(): void
	{
		$this->writeTemporaryFile('config/auth.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Auth\\AuthConfig(roles: [new Blush\\Auth\\Role('blind', 'Blind', ['extensions.plugins.activate', 'extensions.plugins.delete'])]);\n");
		$this->site(['blind']);

		$this->assertSame(403, $this->write('PUT', '/plugins/acme/off', ['enabled' => false])->getStatusCode(), 'Every action needs seeing.');
		$this->assertSame(403, $this->write('DELETE', '/plugins/acme/off')->getStatusCode());
	}
}
