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
use Blush\Console\Commands\AssignMenu;
use Blush\Console\Commands\FileMenuRefs;
use Blush\Console\Commands\ListMenus;
use Blush\Console\Commands\ShowMenu;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandResult;
use Blush\Console\Testing\CommandTester;
use Blush\Menu\MenuRefs;
use Blush\Tests\BootsScratchSite;

#[CoversClass(ListMenus::class)]
#[CoversClass(ShowMenu::class)]
#[CoversClass(AssignMenu::class)]
#[CoversClass(FileMenuRefs::class)]
#[CoversClass(MenuRefs::class)]
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

	/**
	 * Assigns menus to the default theme's locations (D-676).
	 *
	 * @param array<string, string> $menus
	 */
	private function assign(array $menus): void
	{
		$this->writeTemporaryFile('user/data/settings/blush__default.json', json_encode(['menus' => $menus], JSON_THROW_ON_ERROR));
	}

	public function testListsLocationsAndMenus(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.json', '{"items":[{"entry":"page/about"},{"entry":"page/gone"}]}');
		$this->writeTemporaryFile('user/data/menus/footer.json', '{"items":[{"url":"/x","label":"X"}]}');
		$this->assign(['primary' => 'primary']);

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
		$this->writeTemporaryFile('user/data/menus/main.json', '{"label":{"en":"Main","fr":"Principal"},"items":[{"entry":"page/about","children":[{"entry":"page/team"}]},{"entry":"page/gone"}]}');
		$this->assign(['primary' => 'main']);

		$result = $this->command('menu:show primary');

		$this->assertSame(ExitCode::Success, $result->exitCode);
		$this->assertStringContainsString("Main (primary: main, user/data/menus/main.json)\n  About  /about\n    Team  /team\n", $result->output);
		$this->assertStringContainsString('item 2: No entry "page/gone".', $result->output . $result->errors);
		$this->assertStringContainsString('Principal (primary', $this->command(['menu:show', 'primary', '--locale=fr'])->output);
	}

	public function testFailsForALocationWithNoMenu(): void
	{
		$result = $this->command('menu:show social');

		$this->assertSame(ExitCode::Failure, $result->exitCode);
		$this->assertStringContainsString('The "social" location shows no menu', $result->output . $result->errors);
	}

	public function testAssignsAndClearsALocationsMenu(): void
	{
		$this->writeTemporaryFile('user/data/menus/main.json', '{"items":[{"entry":"page/about"}]}');

		$this->assertSame(ExitCode::Success, $this->command('menu:assign primary main')->exitCode);
		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*main\s*\|\s*1#', $this->command('menu:list')->output);

		$this->assertSame(ExitCode::Success, $this->command(['menu:assign', 'primary', '--clear'])->exitCode);
		$this->assertMatchesRegularExpression('#primary\s*\|\s*Primary\s*\|\s*\(none\)#', $this->command('menu:list')->output);

		$this->assertStringContainsString('has no menu location "aside"', $this->failure('menu:assign aside main'));
		$this->assertStringContainsString('The site has no menu "gone"', $this->failure('menu:assign primary gone'));
		$this->assertStringContainsString('Name a menu to assign, or use --clear', $this->failure('menu:assign primary'));
	}

	public function testFilesMenuLinksWithTheirIds(): void
	{
		$this->writeTemporaryFile('user/data/menus/main.json', <<<'JSON'
			{
			    "$schema": "menu.schema.json",
			    "items": [
			        {"entry": "page/about", "label": "Us"},
			        {"label": "More", "children": [{"entry": "page/old-team", "ref": "0032aa43-ab4d-0a52-da10-e3dd5643e52b"}]},
			        {"entry": "page/gone"},
			        {"url": "/x", "label": "X"}
			    ]
			}
			JSON);

		$check = $this->command('menu:refs');

		$this->assertSame(ExitCode::Failure, $check->exitCode);
		$this->assertStringContainsString('main (items 1, 2.1)', $check->output);

		$this->assertSame(ExitCode::Success, $this->command(['menu:refs', '--write'])->exitCode);

		$file = json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/menus/main.json'), true);

		$this->assertIsArray($file);
		$this->assertSame('$schema', array_key_first($file), 'The file keeps its $schema first.');
		$items = $file['items'] ?? null;

		$this->assertIsArray($items);

		$more = $items[1] ?? null;

		$this->assertIsArray($more);

		$children = $more['children'] ?? null;

		$this->assertIsArray($children);
		$this->assertSame(['entry' => 'page/about', 'ref' => '6083a88e-e341-1b0d-17ce-02d738f69d47', 'label' => 'Us'], $items[0] ?? null, 'The id is filed right after the link.');
		$this->assertSame(['entry' => 'page/team', 'ref' => '0032aa43-ab4d-0a52-da10-e3dd5643e52b'], $children[0] ?? null, 'A renamed entry gets its readable form back.');
		$this->assertSame(['entry' => 'page/gone'], $items[2] ?? null, 'An entry that can\'t be found is left for the checks.');
		$this->assertSame(ExitCode::Success, $this->command('menu:refs')->exitCode);
	}

	/**
	 * Runs a command expected to refuse its input, returning what it said.
	 *
	 * @param string|list<string> $command
	 */
	private function failure(string|array $command): string
	{
		$result = $this->command($command);

		$this->assertSame(ExitCode::Invalid, $result->exitCode);

		return $result->output . $result->errors;
	}
}
