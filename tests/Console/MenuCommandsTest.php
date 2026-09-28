<?php

/**
 * Menu command tests.
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
use Blush\Console\Commands\ListMenus;
use Blush\Console\Commands\ShowMenu;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Tests\BootsScratchSite;

#[CoversClass(ListMenus::class)]
#[CoversClass(ShowMenu::class)]
final class MenuCommandsTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Runs a command in a freshly booted site.
	 *
	 * @param string|list<string> $command
	 */
	private function command(string|array $command): CommandResult
	{
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\n---\nAbout.");
		$this->writeTemporaryFile('user/content/team.md', "---\ntitle: Team\n---\nTeam.");

		$app = $this->scratchApplication();
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	public function testListsLocationsAndMenus(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.yaml', "- entry: page/about\n- entry: page/gone\n");
		$this->writeTemporaryFile('user/data/menus/footer.yaml', "- url: /x\n  label: X\n");

		$result = $this->command('menu:list');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*primary\s*\|\s*1\s*\|\s*user/data/menus/primary\.yaml#', $result->output);
		$this->assertMatchesRegularExpression('#\(none\)\s*\|\s*\|\s*footer\s*\|\s*1\s*\|\s*user/data/menus/footer\.yaml#', $result->output);
		$this->assertStringContainsString('menu primary: item 2: No entry "page/gone".', $result->output . $result->errors);
		$this->assertStringContainsString('menu footer: No location of the "default" theme shows it.', $result->output . $result->errors);
	}

	public function testListsAnEmptySite(): void
	{
		$result = $this->command('menu:list');

		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*\(none\)#', $result->output);
	}

	public function testShowsAResolvedMenu(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.yaml', <<<'YAML'
			label: { en: Main, fr: Principal }
			items:
			  - entry: page/about
			    children:
			      - entry: page/team
			  - entry: page/gone
			YAML);

		$result = $this->command('menu:show primary');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertStringContainsString("Main (primary: user/data/menus/primary)\n  About  /about\n    Team  /team\n", $result->output);
		$this->assertStringContainsString('item 2: No entry "page/gone".', $result->output . $result->errors);
		$this->assertStringContainsString('Principal (primary', $this->command(['menu:show', 'primary', '--locale=fr'])->output);
	}

	public function testFailsForALocationWithNoMenu(): void
	{
		$result = $this->command('menu:show social');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('The "social" location shows no menu', $result->output . $result->errors);
	}
}
