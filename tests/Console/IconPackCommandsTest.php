<?php

/**
 * Icon pack command tests.
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
use Blush\Console\Commands\CheckIconPacks;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Tests\BootsScratchSite;

#[CoversClass(CheckIconPacks::class)]
final class IconPackCommandsTest extends TestCase
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

	/**
	 * A pack that loads, one turned on that can't load, one that's off and
	 * couldn't be turned on, one with a version Composer can't read, and a
	 * broken one.
	 *
	 * @param list<string> $enabled
	 */
	private function site(array $enabled = ['acme/brands', 'acme/future', 'acme/odd']): void
	{
		$this->writeTemporaryFile('extensions/acme/brands/icons.json', '{"name": "acme/brands", "version": "1.0.0"}');
		$this->writeTemporaryFile('extensions/acme/future/icons.json', '{"name": "acme/future", "require": {"blush-dev/framework": "^9.0"}}');
		$this->writeTemporaryFile('extensions/acme/needy/icons.json', '{"name": "acme/needy", "require": {"acme/missing": "^1.0"}}');
		$this->writeTemporaryFile('extensions/acme/odd/icons.json', '{"name": "acme/odd", "version": "1.0-final"}');
		$this->writeTemporaryFile('extensions/acme/broken/icons.json', '{"name": 5}');
		$this->writeTemporaryFile('config/icons.php', sprintf("<?php\n\nreturn new Blush\\Icon\\IconConfig(enabled: %s);\n", var_export($enabled, true)));
	}

	public function testChecksIconPacks(): void
	{
		$this->site();

		$result = $this->command('icon-pack:check');
		$all    = $result->output . $result->errors;

		$this->assertSame(ExitCode::Failure, $result->exitCode, 'A pack turned on can\'t load.');
		$this->assertMatchesRegularExpression('#ok\s+acme/brands 1\.0\.0#', $all);
		$this->assertMatchesRegularExpression('#error\s+acme/future: Needs Blush \^9\.0 \(this site runs [^)]+\)\. It\'s turned on, but its icons don\'t load\.#', $all);
		$this->assertMatchesRegularExpression('#warning\s+acme/needy: Needs acme/missing \^1\.0 \(isn\'t installed\)\.#', $all);
		$this->assertMatchesRegularExpression('#warning\s+acme/odd: Its version, "1\.0-final", isn\'t one Composer can read#', $all);
		$this->assertMatchesRegularExpression('#warning\s+extensions/acme/broken: #', $all);
		$this->assertStringContainsString('Checked 5 icon pack(s): 1 error(s), 3 warning(s).', $all);
	}

	public function testPassesWithOnlyWarnings(): void
	{
		$this->site(enabled: ['acme/brands']);

		$result = $this->command('icon-pack:check');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('Checked 5 icon pack(s): 0 error(s), 4 warning(s).', $result->output);
	}

	public function testChecksOnePack(): void
	{
		$this->site();

		$one = $this->command(['icon-pack:check', 'acme/brands']);

		$this->assertSame(ExitCode::Success, $one->exitCode, $one->errors);
		$this->assertStringContainsString('Checked 1 icon pack(s): 0 error(s), 0 warning(s).', $one->output);

		$missing = $this->command(['icon-pack:check', 'acme/missing']);

		$this->assertSame(ExitCode::Failure, $missing->exitCode);
		$this->assertStringContainsString('No icon pack named "acme/missing" is installed.', $missing->errors);
	}
}
