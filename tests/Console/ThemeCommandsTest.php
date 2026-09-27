<?php

/**
 * Theme command tests.
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
use Blush\Console\Commands\ActivateTheme;
use Blush\Console\Commands\CheckTheme;
use Blush\Console\Commands\CreateTheme;
use Blush\Console\Commands\ExplainView;
use Blush\Console\Commands\ListThemes;
use Blush\Console\Commands\PublishThemes;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Tests\BootsScratchSite;
use Blush\Theme\ThemeChecker;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeReport;

#[CoversClass(ListThemes::class)]
#[CoversClass(ActivateTheme::class)]
#[CoversClass(CreateTheme::class)]
#[CoversClass(CheckTheme::class)]
#[CoversClass(ExplainView::class)]
#[CoversClass(PublishThemes::class)]
#[CoversClass(ThemeChecker::class)]
#[CoversClass(ThemeReport::class)]
final class ThemeCommandsTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Runs a command in a freshly booted site.
	 *
	 * @param string|list<string> $command
	 */
	private function command(string|array $command): CommandResult
	{
		$app = $this->scratchApplication(['APP_ENV' => 'production']);
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	private function root(): string
	{
		return $this->temporaryDirectory();
	}

	public function testListsThemes(): void
	{
		$this->writeTemporaryFile('user/themes/nova/theme.json', '{"name": "Nova", "version": "2.1.0", "parent": "default"}');
		$this->writeTemporaryFile('user/themes/broken/theme.json', '{broken');

		$result = $this->command('theme:list');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('/\* default\s*\|\s*Default\s*\|\s*1\.0\.0\s*\|\s*\|\s*framework/', $result->output);
		$this->assertMatchesRegularExpression('/  nova\s*\|\s*Nova\s*\|\s*2\.1\.0\s*\|\s*default\s*\|\s*local/', $result->output);
		$this->assertStringContainsString('broken:', $result->output . $result->errors);

		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'gone');\n");

		$this->assertSame(ExitCode::Failure, $this->command('theme:list')->exitCode);
	}

	public function testCreatesThemes(): void
	{
		$result = $this->command(['theme:new', 'nova', '--parent=default', '--name=Nova Theme']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertSame(
			['name' => 'Nova Theme', 'version' => '1.0.0', 'parent' => 'default', 'styles' => ['style.css']],
			json_decode((string) file_get_contents($this->root() . '/user/themes/nova/theme.json'), true)
		);
		$this->assertFileExists($this->root() . '/user/themes/nova/style.css');
		$this->assertStringContainsString('theme:activate nova', $result->output);
		$this->assertSame(ExitCode::Success, $this->command(['theme:new', 'dusk-mode'])->exitCode);
		$this->assertStringContainsString('"name": "Dusk Mode"', (string) file_get_contents($this->root() . '/user/themes/dusk-mode/theme.json'));

		$this->assertSame(ExitCode::Failure, $this->command(['theme:new', 'nova'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'Bad Slug'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'default'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:new', 'kid', '--parent=missing'])->exitCode);
	}

	public function testActivatesThemes(): void
	{
		$this->writeTemporaryFile('user/themes/nova/theme.json', '{"name": "Nova"}');
		$this->writeTemporaryFile('user/themes/dusk/theme.json', '{"name": "Dusk"}');

		$bootstrap = new Bootstrap(Paths::fromRoot($this->root()), ['APP_ENV' => 'production']);
		$bootstrap->compile();

		$result = $this->command(['theme:activate', 'nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertFileDoesNotExist($bootstrap->compiledPath(CompiledCache::Config));
		$this->assertFileDoesNotExist($bootstrap->compiledPath(CompiledCache::Themes));
		$this->assertSame('nova', $this->config()->active);

		$this->assertSame(ExitCode::Success, $this->command(['theme:activate', 'dusk'])->exitCode);
		$this->assertSame('dusk', $this->config()->active);

		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Theme\\ThemeConfig::fromArray(['active' => getenv('THEME') ?: 'nova']);\n");

		$this->assertSame(ExitCode::Failure, $this->command(['theme:activate', 'dusk'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:activate', 'missing'])->exitCode);
	}

	private function config(): ThemeConfig
	{
		$config = require $this->root() . '/config/theme.php';

		$this->assertInstanceOf(ThemeConfig::class, $config);

		return $config;
	}

	public function testChecksTheDefaultTheme(): void
	{
		$result = $this->command(['theme:check', '--strict']);

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->output);
		$this->assertStringContainsString('Checked the "default" theme: 0 error(s), 0 warning(s), 0 notice(s).', $result->output);
	}

	public function testChecksReportProblems(): void
	{
		$this->writeTemporaryFile('user/themes/rough/theme.json', json_encode([
			'name'     => 'Rough',
			'provider' => 'Nope\\Provider',
			'requires' => ['blush' => '^2.0'],
			'settings' => ['size' => ['type' => 'number', 'default' => 1]]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/themes/rough/theme.yaml', 'name: Shadowed');
		$this->writeTemporaryFile('user/themes/rough/views/layouts/base.php', '<!DOCTYPE html><html><body><div><?= $template->section("content") ?></div></body></html>');
		$this->writeTemporaryFile('user/data/theme.json', '{"settings": {"size": "big"}}');
		$this->writeTemporaryFile('user/themes/other/theme.json', '{"name": 1}');

		$result = $this->command(['theme:check', 'rough', '--strict']);
		$output = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode);

		$expected = [
			'warning theme other:',
			'warning manifest: theme.yaml is ignored; the "rough" theme\'s theme.json wins',
			'error   provider: The "rough" theme\'s provider Nope\\Provider isn\'t a service provider class',
			'notice  requires:',
			'warning setting size:',
			'error   layout: The base layout\'s <html> has no lang attribute.',
			'error   layout: The base layout needs one <main> landmark; it has 0.',
			'error   layout: The base layout needs a skip link',
			'warning layout: The base layout has no <header> landmark.',
			'warning layout: The base layout has no <footer> landmark.'
		];

		foreach ($expected as $line) {
			$this->assertStringContainsString($line, $output);
		}

		$this->assertStringNotContainsString('notice', $this->command(['theme:check', 'rough'])->output);
		$this->assertStringContainsString('error   manifest: The "missing" theme is not installed.', $this->command(['theme:check', 'missing'])->output);
	}

	public function testExplainsWhichViewWins(): void
	{
		$this->writeTemporaryFile('user/themes/nova/theme.json', '{"name": "Nova"}');
		$this->writeTemporaryFile('user/themes/nova/views/single.php', '');
		$this->writeTemporaryFile('resources/views/single.php', '');

		$result = $this->command(['theme:why', 'single', '--theme=nova']);

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#uses    resources/views/single.php\nshadows user/themes/nova/views/single.php\nshadows .+resources/themes/default/views/single.php#', $result->output);

		$missing = $this->command(['theme:why', 'nope']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('resources/views/themes/default/nope.php', $missing->output);
		$this->assertSame(ExitCode::Invalid, $this->command(['theme:why', '../x'])->exitCode);
	}

	public function testPublishesThemeAssets(): void
	{
		$this->writeTemporaryFile('user/themes/nova/theme.json', '{"name": "Nova"}');
		$this->writeTemporaryFile('user/themes/nova/style.css', 'nova');
		$this->writeTemporaryFile('user/themes/nova/fonts/a.woff2', 'font');
		$this->writeTemporaryFile('user/themes/nova/views/single.php', '<?php');
		$this->writeTemporaryFile('user/themes/nova/src/Provider.php', '<?php');
		$this->writeTemporaryFile('user/themes/nova/.hidden/x.css', '');
		$this->writeTemporaryFile('user/themes/other/theme.json', '{"name": "Other"}');
		$this->writeTemporaryFile('user/themes/other/o.css', '');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'nova');\n");

		$result = $this->command('theme:publish');
		$public = $this->root() . '/public/themes';

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('nova: copied 2, 0 already current, removed 0.', $result->output);
		$this->assertSame('nova', file_get_contents("{$public}/nova/style.css"));
		$this->assertFileExists("{$public}/nova/fonts/a.woff2");
		$this->assertFileExists("{$public}/default/style.css");
		$this->assertFileDoesNotExist("{$public}/nova/views/single.php");
		$this->assertFileDoesNotExist("{$public}/nova/src/Provider.php");
		$this->assertFileDoesNotExist("{$public}/nova/theme.json");
		$this->assertFileDoesNotExist("{$public}/nova/.hidden/x.css");
		$this->assertDirectoryDoesNotExist("{$public}/other");

		unlink($this->root() . '/user/themes/nova/fonts/a.woff2');

		$this->assertStringContainsString('nova: copied 0, 1 already current, removed 1.', $this->command('theme:publish')->output);
		$this->assertFileDoesNotExist("{$public}/nova/fonts/a.woff2");

		$this->command(['theme:publish', '--all']);

		$this->assertFileExists("{$public}/other/o.css");
	}
}
