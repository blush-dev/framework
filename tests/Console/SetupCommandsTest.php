<?php

/**
 * Setup command tests.
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
use Blush\Console\Commands\CheckSite;
use Blush\Console\Commands\SetUpSite;
use Blush\Console\ExitCode;
use Blush\Console\Console;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Env\EnvFile;
use Blush\Setup\SetupChecks;
use Blush\Tests\BootsScratchSite;

#[CoversClass(SetUpSite::class)]
#[CoversClass(CheckSite::class)]
final class SetupCommandsTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Runs a command in a freshly booted scratch site.
	 *
	 * @param list<string>          $answers
	 * @param array<string, string> $environment
	 */
	private function command(string $command, array $answers = [], array $environment = []): CommandResult
	{
		$app = $this->scratchApplication($environment);
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command, $answers);
	}

	private function env(): EnvFile
	{
		return EnvFile::load($this->temporaryDirectory() . '/.env');
	}

	public function testCreatesEnvFromTheExampleAndTheStorageFolders(): void
	{
		$this->writeTemporaryFile('.env.example', "# Name.\nAPP_NAME=\"Example\"\n");

		$result = $this->command('init -n');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Created .env.', $result->output);
		$this->assertStringStartsWith("# Name.\nAPP_NAME=\"Example\"\n", $this->env()->contents);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->env()->get('APP_SECRET') ?? '', 'Every site gets a secret for signed links.');

		foreach (SetupChecks::STORAGE as $name) {
			$this->assertDirectoryExists($this->temporaryDirectory() . '/' . ($name === 'storage' ? 'storage' : "storage/{$name}"));
		}
	}

	public function testUsesDefaultsWithoutAnExample(): void
	{
		$this->command('init -n');

		$this->assertSame('production', $this->env()->get('APP_ENV'));
		$this->assertFalse($this->env()->filled('PUBLISH_SECRET'));
	}

	public function testAsksForTheBasicsInATerminal(): void
	{
		$this->writeTemporaryFile('.env.example', "APP_ENV=production\nAPP_DEBUG=false\nAPP_NAME=\"Blush\"\nAPP_URL=\"http://localhost\"\nAPP_TIMEZONE=\"UTC\"\n");

		$result = $this->command('init', ['My Site', 'not a url', 'https://example.test', 'Nowhere/Town', 'America/Chicago', '1', 'no', 'no']);
		$env    = $this->env();

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertSame('My Site', $env->get('APP_NAME'));
		$this->assertSame('https://example.test', $env->get('APP_URL'));
		$this->assertSame('America/Chicago', $env->get('APP_TIMEZONE'));
		$this->assertSame('development', $env->get('APP_ENV'));
		$this->assertSame('true', $env->get('APP_DEBUG'));
		$this->assertFalse($env->filled('PUBLISH_SECRET'));
		$this->assertStringContainsString('Enter a full http:// or https:// address.', $result->errors);
	}

	public function testAddsAPublishSecretWhenAsked(): void
	{
		$this->writeTemporaryFile('.env', "APP_NAME=Mine\nPUBLISH_SECRET=\n");

		$result = $this->command('init -n --webhook');
		$secret = $this->env()->get('PUBLISH_SECRET') ?? '';

		$this->assertStringContainsString('the publish webhook is on', $result->output);
		$this->assertStringContainsString('Updated .env.', $result->output);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
		$this->assertSame('Mine', $this->env()->get('APP_NAME'));

		$result = $this->command('init -n --webhook');

		$this->assertStringContainsString('PUBLISH_SECRET is already set', $result->output);
		$this->assertSame($secret, $this->env()->get('PUBLISH_SECRET'));
	}

	public function testLeavesAnExistingEnvAlone(): void
	{
		$env = "APP_NAME=Mine\nAPP_SECRET=" . str_repeat('a', 64) . "\n";
		$this->writeTemporaryFile('.env', $env);

		$result = $this->command('init', ['no', 'no']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('.env already exists; left as it is.', $result->output);
		$this->assertSame($env, $this->env()->contents);
	}

	public function testReportsStorageItCantCreate(): void
	{
		$this->writeTemporaryFile('storage', '');

		$result = $this->command('init -n');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('storage/ This is a file, not a folder.', $result->errors);
	}

	public function testDoctorReportsEveryCheck(): void
	{
		$this->writeTemporaryFile('.env', '');
		$this->writeTemporaryFile('public/index.php', '<?php');
		$this->writeTemporaryFile('public/.htaccess', '');

		$result = $this->command('doctor', environment: ['APP_URL' => 'https://example.com']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->output . $result->errors);
		$this->assertMatchesRegularExpression('/ok\s+PHP: 8\.5/', $result->output);
		$this->assertStringContainsString('storage/: Created when it\'s needed.', $result->output);
		$this->assertStringContainsString('ok      Extensions: Everything that\'s on runs.', $result->output);
		$this->assertStringContainsString('warning Background jobs: Cron isn\'t set up', $result->output, 'A new site has no cron yet.');
		$this->assertStringContainsString('0 failure(s), 1 warning(s).', $result->output);
	}

	public function testDoctorFailsWithHints(): void
	{
		$result = $this->command('doctor', environment: ['APP_DEBUG' => 'true']);

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertMatchesRegularExpression('/failure APP_DEBUG: Debugging is on in production/', $result->output);
		$this->assertStringContainsString('        Set APP_DEBUG=false.', $result->output);
		$this->assertStringContainsString('2 failure(s)', $result->errors);
	}

	public function testDoctorWarnsOfExtensionsThatCantRun(): void
	{
		$this->writeTemporaryFile('extensions/acme/future/theme.json', '{"name": "acme/future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->writeTemporaryFile('extensions/acme/later/plugin.json', '{"name": "acme/later", "require": {"php": ">=9.0"}}');
		$this->writeTemporaryFile('extensions/acme/glyphs/icons.json', '{"name": "acme/glyphs", "require": {"acme/missing": "^1.0"}}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/future');\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['acme/later']);\n");
		$this->writeTemporaryFile('config/icons.php', "<?php\n\nreturn new Blush\\Icon\\IconConfig(enabled: ['acme/glyphs']);\n");

		$result = $this->command('doctor', environment: ['APP_URL' => 'https://example.com']);

		$this->assertMatchesRegularExpression('/warning Theme: The "acme\/future" theme can\'t run, so the default theme runs in its place\. Needs Blush \^9\.0/', $result->output);
		$this->assertStringContainsString('warning Plugins: 1 plugin is turned on but can\'t run: acme/later.', $result->output);
		$this->assertStringContainsString('warning Icon packs: 1 icon pack is turned on but can\'t run: acme/glyphs.', $result->output);
		$this->assertStringContainsString('Run "blush icon-pack:check" to see why.', $result->output);
		$this->assertStringNotContainsString('ok      Extensions', $result->output);
	}

	public function testOffersTheFirstAccount(): void
	{
		$this->writeTemporaryFile('.env', "APP_NAME=Mine\n");

		$result = $this->command('init', ['no', 'yes', '', 'not an email', 'admin@example.test', 'a long enough password', 'a long enough password']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Created the "admin" account.', $result->output);
		$this->assertFileExists($this->temporaryDirectory() . '/storage/accounts/admin.json');
		$this->assertStringContainsString('"email": "admin@example.test"', (string) file_get_contents($this->temporaryDirectory() . '/storage/accounts/admin.json'), 'It asks for an email address (D-370).');

		$result = $this->command('init', ['no']);

		$this->assertSame(ExitCode::Success, $result->exitCode, 'Once there\'s an account, it doesn\'t ask again.');
	}
}
