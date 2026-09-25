<?php

/**
 * Console tests.
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
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\GlobalOptions;
use Blush\Console\HelpFormatter;
use Blush\Console\Output;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Console\Verbosity;
use Blush\Core\Framework;

#[CoversClass(Console::class)]
#[CoversClass(GlobalOptions::class)]
#[CoversClass(HelpFormatter::class)]
#[CoversClass(CommandTester::class)]
#[CoversClass(CommandResult::class)]
final class ConsoleTest extends TestCase
{
	use BuildsConsole;

	public function testRunsACommand(): void
	{
		$result = $this->tester()->run('greet Ada 2 --loud -t x');

		$this->assertTrue($result->isSuccessful());
		$this->assertSame("HELLO, ADA\nHELLO, ADA\nlevel=low ratio=NULL tags=x dry=no\n", $result->output);
		$this->assertSame('', $result->errors);
	}

	public function testGlobalOptionsMayComeBeforeTheCommand(): void
	{
		$result = $this->tester()->run(['-q', 'hi', 'Ada']);

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertSame('', $result->output);
	}

	public function testListsCommandsWhenNoneIsGiven(): void
	{
		$result = $this->tester()->run('');

		$this->assertTrue($result->isSuccessful());
		$this->assertStringContainsString(Framework::NAME, $result->output);
		$this->assertStringContainsString('greet', $result->output);
		$this->assertStringContainsString('cache:clear', $result->output);
		$this->assertStringContainsString(' cache', $result->output);
		$this->assertStringNotContainsString('explode', $result->output);
	}

	public function testShowsHelp(): void
	{
		$tester = $this->tester();

		$viaOption  = $tester->run('greet --help');
		$viaCommand = $tester->run('help greet');

		$this->assertTrue($viaOption->isSuccessful());
		$this->assertSame($viaOption->output, $viaCommand->output);
		$this->assertStringContainsString('blush greet [options] [--] <name> [<times>]', $viaOption->output);
		$this->assertStringContainsString('-l, --level=LEVEL', $viaOption->output);
		$this->assertStringContainsString("[one of: low, high] [default: 'low']", $viaOption->output);
		$this->assertStringContainsString('--no-interaction', $viaOption->output);
		$this->assertSame(ExitCode::Invalid, $tester->run('help nope')->exitCode);
	}

	public function testShowsTheVersion(): void
	{
		$result = $this->tester()->run('-V');

		$this->assertSame(Framework::NAME . ' ' . Framework::VERSION . "\n", $result->output);
	}

	public function testSuggestsCloseNamesForUnknownCommands(): void
	{
		$result = $this->tester()->run('gret');

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('Command "gret" is not defined.', $result->errors);
		$this->assertStringContainsString('    greet', $result->errors);
	}

	public function testReportsInvalidInputWithUsage(): void
	{
		$result = $this->tester()->run('greet');

		$this->assertSame(ExitCode::Invalid, $result->exitCode);
		$this->assertStringContainsString('Missing required argument "name".', $result->errors);
		$this->assertStringContainsString('Usage: blush greet [options] [--] <name> [<times>]', $result->errors);
	}

	public function testReportsAndRendersExceptions(): void
	{
		$result = $this->tester()->run('explode');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('RuntimeException: Kaboom', $result->errors);
		$this->assertCount(1, $this->logger->records);
		$this->assertSame('critical', $this->logger->records[0]['level']);
	}

	public function testPromptsTakeDefaultsWhenNotInteractive(): void
	{
		$result = $this->tester()->run('interview');

		$this->assertSame("Anon|red|yes\n", $result->output);
	}

	public function testPromptsReadAnswers(): void
	{
		$tester = $this->tester();

		$this->assertStringEndsWith("Grace|blue|no\n", $tester->run('interview', ['Grace', '2', 'n'])->output);
		$this->assertSame("Anon|red|yes\n", $tester->run('interview -n', ['Grace', '2', 'n'])->output);
	}

	public function testBindsOutputForCommands(): void
	{
		$app = $this->application();
		new CommandTester($app->container()->make(Console::class))->run('-vv --ansi list');

		$output = $app->container()->make(Output::class);

		$this->assertSame(Verbosity::VeryVerbose, $output->verbosity);
		$this->assertTrue($output->ansi);
	}
}
