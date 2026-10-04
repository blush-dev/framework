<?php

/**
 * Plugin command tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\CheckPlugins;
use Blush\Console\Commands\CreatePlugin;
use Blush\Console\Commands\ListPlugins;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Extension\InstalledExtensions;
use Blush\Plugin\Plugins;
use Blush\Tests\BootsScratchSite;

#[CoversClass(ListPlugins::class)]
#[CoversClass(CheckPlugins::class)]
#[CoversClass(CreatePlugin::class)]
#[CoversClass(InstalledExtensions::class)]
final class PluginCommandsTest extends TestCase
{
	use BootsScratchSite;

	private const string PROVIDER = 'Blush\\\\Tests\\\\Fixtures\\\\Plugin\\\\ComposerPluginProvider';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * Runs a command in a freshly booted site.
	 *
	 * @param string|list<string> $command
	 */
	private function command(string|array $command): CommandResult
	{
		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	/**
	 * Writes a local plugin's manifest.
	 *
	 * @param array<string, string> $requires
	 */
	private function plugin(string $folder, string $name, string $label, array $requires = []): void
	{
		$this->writeTemporaryFile("extensions/{$name}/plugin.json", sprintf(
			'{"name": "%s", "label": "%s", "namespace": "%s", "version": "1.0.0", "provider": "%s", "require": %s}',
			$name,
			$label,
			$folder,
			self::PROVIDER,
			json_encode((object) $requires)
		));
	}

	/**
	 * A plugin that runs, one that's off, one turned on that can't run,
	 * one that's off and couldn't be turned on, and a broken one.
	 */
	private function site(string $enabled = "'acme/on', 'acme/future'"): void
	{
		$this->plugin('on', 'acme/on', 'On');
		$this->plugin('off', 'acme/off', 'Off');
		$this->plugin('future', 'acme/future', 'Future', ['blush-dev/framework' => '^9.0']);
		$this->plugin('needy', 'acme/needy', 'Needy', ['acme/missing' => '^1.0']);
		$this->writeTemporaryFile('extensions/acme/broken/plugin.json', '{broken');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: [{$enabled}]);\n");
	}

	public function testListsPlugins(): void
	{
		$this->site();

		$result = $this->command('plugin:list');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertMatchesRegularExpression('#acme/on\s*\|\s*On\s*\|\s*on\s*\|\s*1\.0\.0\s*\|\s*local\s*\|\s*on#', $result->output);
		$this->assertMatchesRegularExpression('#acme/off\s*\|\s*Off\s*\|\s*off\s*\|\s*1\.0\.0\s*\|\s*local\s*\|\s*off#', $result->output);
		$this->assertMatchesRegularExpression('#acme/future\s*\|.*\|\s*can\'t run#', $result->output);
		$this->assertStringContainsString('extensions/acme/broken: The manifest extensions/acme/broken/plugin.json is invalid', $result->errors);
		$this->assertStringContainsString('Run plugin:check', $result->output);
	}

	public function testListsNoPlugins(): void
	{
		$result = $this->command('plugin:list');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertStringContainsString('No plugins are installed.', $result->output);
	}

	public function testChecksPlugins(): void
	{
		$this->site();

		$result = $this->command('plugin:check');
		$all    = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode, 'A plugin turned on can\'t run.');
		$this->assertMatchesRegularExpression('#ok\s+acme/on#', $all);
		$this->assertMatchesRegularExpression('#error\s+acme/future: Needs Blush \^9\.0 \(this site runs [^)]+\)\. It\'s turned on, but doesn\'t run\.#', $all);
		$this->assertMatchesRegularExpression('#warning\s+acme/needy: Needs the acme/missing plugin \^1\.0 \(isn\'t installed\)\.#', $all);
		$this->assertMatchesRegularExpression('#warning\s+extensions/acme/broken: #', $all);
		$this->assertStringContainsString('Checked 5 plugin(s): 1 error(s), 2 warning(s).', $all);
	}

	public function testPassesWithOnlyWarnings(): void
	{
		$this->site(enabled: "'acme/on'");

		$result = $this->command('plugin:check');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Checked 5 plugin(s): 0 error(s), 3 warning(s).', $result->output);
	}

	public function testChecksOnePlugin(): void
	{
		$this->site();

		$one = $this->command(['plugin:check', 'acme/on']);

		$this->assertSame(ExitCode::Success, $one->exitCode, $one->errors);
		$this->assertStringContainsString('Checked 1 plugin(s): 0 error(s), 0 warning(s).', $one->output);
		$this->assertStringNotContainsString('acme/future', $one->output);

		$missing = $this->command(['plugin:check', 'acme/missing']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('No plugin named "acme/missing" is installed.', $missing->errors);
	}

	public function testABrokenPluginTurnedOnIsAnError(): void
	{
		$this->writeTemporaryFile('extensions/acme/named/plugin.json', '{"name": "acme/named", "label": "Named", "namespace": "Not Valid"}');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/named']);\n");

		$result = $this->command(['plugin:check', 'acme/named']);

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertMatchesRegularExpression('#error\s+extensions/acme/named: .*It\'s turned on, but doesn\'t run\.#', $result->output . $result->errors);
	}

	public function testCreatesPlugins(): void
	{
		$result = $this->command(['plugin:new', 'acme/scaffold-test', '--label=Scaffold']);
		$folder = $this->temporaryDirectory() . '/extensions/acme/scaffold-test';

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertSame(
			[
				'$schema'   => '../../../vendor/blush-dev/framework/resources/schemas/plugin.schema.json',
				'name'      => 'acme/scaffold-test',
				'label'     => 'Scaffold',
				'namespace' => 'acme-scaffold-test',
				'version'   => '1.0.0',
				'provider'  => 'Acme\\ScaffoldTest\\ScaffoldTestServiceProvider',
				'autoload'  => ['psr-4' => ['Acme\\ScaffoldTest\\' => 'src/']],
				'require' => ['blush-dev/framework' => '^2.0']
			],
			json_decode((string) file_get_contents("{$folder}/plugin.json"), true)
		);
		$this->assertStringContainsString('config/plugins.php', $result->output);

		// It's off until named; named, it loads and runs.
		$this->assertMatchesRegularExpression('#acme/scaffold-test\s*\|\s*Scaffold\s*\|.*\|\s*off#', $this->command('plugin:list')->output);

		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/scaffold-test']);\n");

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$this->assertTrue($app->container()->make(Plugins::class)->has('acme/scaffold-test'));
		$this->assertTrue(class_exists('Acme\\ScaffoldTest\\ScaffoldTestServiceProvider', false));
		$this->assertSame(ExitCode::Success, $this->command(['plugin:check', 'acme/scaffold-test'])->exitCode);

		// Options, and what's refused.
		$this->assertSame(ExitCode::Success, $this->command(['plugin:new', 'acme/other', '--namespace=elsewhere', '--php-namespace=Shop\\Tools'])->exitCode);
		$this->assertStringContainsString('"provider": "Shop\\\\Tools\\\\OtherServiceProvider"', (string) file_get_contents($this->temporaryDirectory() . '/extensions/acme/other/plugin.json'));
		$this->assertStringContainsString('"namespace": "elsewhere"', (string) file_get_contents($this->temporaryDirectory() . '/extensions/acme/other/plugin.json'));
		$this->assertFileExists($this->temporaryDirectory() . '/extensions/acme/other/src/OtherServiceProvider.php');

		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');

		$this->writeTemporaryFile('extensions/other/stray/readme.md', 'Not an extension.');

		$this->assertSame(ExitCode::Failure, $this->command(['plugin:new', 'other/stray'])->exitCode, 'The folder is taken.');
		$this->assertSame(ExitCode::Success, $this->command(['plugin:new', 'other/scaffold-test', '--namespace=free', '--php-namespace=Other\\Scaffold'])->exitCode, 'Another vendor\'s has its own folder.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'acme/scaffold-test', '--namespace=free'])->exitCode, 'The name is taken.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'acme/new', '--namespace=elsewhere'])->exitCode, 'A plugin has the namespace.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'other/x', '--namespace=nova'])->exitCode, 'A theme has the namespace.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'acme/nova', '--namespace=free'])->exitCode, 'A theme has the name.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'hello'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'acme/x', '--namespace=app'])->exitCode, 'A reserved namespace.');
		$this->assertSame(ExitCode::Invalid, $this->command(['plugin:new', 'acme/2fa'])->exitCode, 'Not a PHP namespace.');
	}
}
