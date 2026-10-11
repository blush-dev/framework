<?php

/**
 * Tool command tests.
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
use Blush\Console\Commands\RunTool;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tool\Tools;

#[CoversClass(RunTool::class)]
#[CoversClass(Tools::class)]
final class ToolCommandTest extends TestCase
{
	use BuildsContentSite;

	private function tester(): CommandTester
	{
		return new CommandTester($this->site()->container()->make(Console::class));
	}

	public function testListsTheTools(): void
	{
		$this->standardContent();

		$list = $this->tester()->run('tool');

		$this->assertTrue($list->isSuccessful());
		$this->assertMatchesRegularExpression('#core/get-entry\s+Get an Entry\s+reads\s+anyone#', $list->output);
		$this->assertMatchesRegularExpression('#core/publish\s+Publish\s+writes\s+site\.publish#', $list->output);
	}

	public function testRunsAToolWithItsInput(): void
	{
		$this->standardContent();
		$tester = $this->tester();

		$found = $tester->run(['tool', 'core/search-entries', 'type=post', 'limit=1', '--json']);

		$this->assertTrue($found->isSuccessful(), $found->errors);

		$data = json_decode($found->output, true);

		$this->assertIsArray($data);
		$this->assertIsArray($data['entries'] ?? null);
		$this->assertCount(1, $data['entries'], 'A number and a list, read by the schema.');

		$this->assertSame(ExitCode::Invalid, $tester->run(['tool', 'core/search-entries', 'limit=many'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $tester->run(['tool', 'core/search-entries', 'limit'])->exitCode);
		$this->assertSame(ExitCode::Invalid, $tester->run(['tool', 'core/nothing'])->exitCode);

		$refused = $tester->run(['tool', 'core/search-entries', 'else=1']);

		$this->assertSame(ExitCode::Failure, $refused->exitCode);
		$this->assertStringContainsString('has no "else"', $refused->errors);

		$asked = $tester->run(['tool', 'core/trash-entry']);

		$this->assertSame(ExitCode::Failure, $asked->exitCode);
		$this->assertStringContainsString('Move an Entry to the Trash takes:', $asked->output, 'A tool run without its input says what it takes.');
	}

	public function testRunsAQueuedToolStraightAway(): void
	{
		$this->standardContent();

		$reindex = $this->tester()->run(['tool', 'core/reindex']);

		$this->assertTrue($reindex->isSuccessful(), $reindex->errors);
		$this->assertStringContainsString('Indexed 19 entries', $reindex->output);
	}
}
