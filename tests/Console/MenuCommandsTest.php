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
		$this->writeTemporaryFile('user/content/about.md', "---\nid: 6083a88e-e341-1b0d-17ce-02d738f69d47\ntitle: About\n---\nAbout.");
		$this->writeTemporaryFile('user/content/team.md', "---\nid: 0032aa43-ab4d-0a52-da10-e3dd5643e52b\ntitle: Team\n---\nTeam.");

		$app = $this->scratchApplication();
		$app->boot();

		return new CommandTester($app->container()->make(Console::class))->run($command);
	}

	public function testListsLocationsAndMenus(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', '[{"entry":"page/about"},{"entry":"page/gone"}]');
		$this->writeTemporaryFile('user/data/menus/footer.json', '[{"url":"/x","label":"X"}]');

		$result = $this->command('menu:list');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*primary\s*\|\s*1\s*\|\s*user/data/menus/primary\.json#', $result->output);
		$this->assertMatchesRegularExpression('#\(none\)\s*\|\s*\|\s*footer\s*\|\s*1\s*\|\s*user/data/menus/footer\.json#', $result->output);
		$this->assertStringContainsString('menu primary: item 2: No entry "page/gone".', $result->output . $result->errors);
		$this->assertStringContainsString('menu footer: No location of the "blush/default" theme shows it.', $result->output . $result->errors);
	}

	public function testListsAnEmptySite(): void
	{
		$result = $this->command('menu:list');

		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*\(none\)#', $result->output);
	}

	public function testShowsAResolvedMenu(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', '{"label":{"en":"Main","fr":"Principal"},"items":[{"entry":"page/about","children":[{"entry":"page/team"}]},{"entry":"page/gone"}]}');

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
