<?php

/**
 * Admin theme command tests.
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
use Blush\Console\Commands\CheckAdminThemes;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Tests\BootsScratchSite;

#[CoversClass(CheckAdminThemes::class)]
final class AdminThemeCommandsTest extends TestCase
{
	use BootsScratchSite;

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

	public function testChecksAdminThemes(): void
	{
		$this->writeTemporaryFile('extensions/acme/faint/admin-theme.json', '{"name": "acme/faint", "version": "1.0.0"}');
		$this->writeTemporaryFile('extensions/acme/faint/tokens.json', '{"color": {"text": "light-dark(#cccccc, #e9ebf0)"}}');
		$this->writeTemporaryFile('extensions/acme/future/admin-theme.json', '{"name": "acme/future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->writeTemporaryFile('extensions/acme/future/tokens.json', '{}');
		$this->writeTemporaryFile('extensions/acme/broken/admin-theme.json', '{"name": "acme/broken"}');
		$this->writeTemporaryFile('extensions/acme/broken/tokens.json', '{"spacing": {"gutter": "40px"}}');
		$this->writeTemporaryFile('config/admin-themes.php', "<?php\n\nreturn new Blush\\AdminTheme\\AdminThemeConfig(enabled: ['acme/faint', 'acme/future']);\n");

		$result = $this->command('admin-theme:check');
		$all    = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode, 'A theme turned on can\'t be offered.');
		$this->assertMatchesRegularExpression('#ok\s+blush/editorial#', $all);
		$this->assertMatchesRegularExpression('#ok\s+blush/neutral#', $all);
		$this->assertMatchesRegularExpression('#warning\s+acme/faint\s+In the light scheme, color\.text\.default on color\.background is#', $all);
		$this->assertMatchesRegularExpression('#error\s+acme/future\s+Needs Blush \^9\.0 \(this site runs [^)]+\)\. It\'s turned on, but no one can choose it\.#', $all);
		$this->assertMatchesRegularExpression('#warning\s+extensions/acme/broken\s+.*"spacing\.gutter", which isn\'t a token#', $all);
		$this->assertStringContainsString('5 admin themes, 1 error, 3 warnings', $all);

		$missing = $this->command(['admin-theme:check', 'acme/missing']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('No admin theme named "acme/missing" is installed.', $missing->errors);
	}
}
