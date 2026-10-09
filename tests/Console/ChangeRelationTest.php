<?php

/**
 * Change relation command tests.
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
use Blush\Console\Commands\ChangeRelation;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Relation\RelationChanges;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(ChangeRelation::class)]
#[CoversClass(RelationChanges::class)]
final class ChangeRelationTest extends TestCase
{
	use BuildsContentSite;

	private function tester(): CommandTester
	{
		return new CommandTester($this->site('development')->container()->make(Console::class));
	}

	private function file(string $path): string
	{
		return (string) file_get_contents($this->temporaryDirectory() . "/{$path}");
	}

	protected function setUp(): void
	{
		$this->contentConfig(['types' => ['recipe' => ['urls' => ['prefix' => 'recipes']]]]);
		$this->writeTemporaryFile('user/data/relations/pairs.json', '{"kind": "reference", "from": ["recipe"], "to": ["recipe"]}');
		$this->entry('_recipe/soup.md', 'title: Soup');
		$this->entry('_recipe/stew.md', "title: Stew\npairs: [soup]");
	}

	public function testSaysHowManyEntriesUseIt(): void
	{
		$shown = $this->tester()->run('content:relation pairs');

		$this->assertTrue($shown->isSuccessful(), $shown->errors);
		$this->assertStringContainsString('"pairs" is written under `pairs`; 1 entry has values in it.', $shown->output);
		$this->assertSame(1, $this->tester()->run('content:relation nope')->exitCode->value, 'A relation that isn\'t there fails.');
	}

	public function testGivesItANewKey(): void
	{
		$kept = $this->tester()->run('content:relation pairs --key=goes_with');

		$this->assertTrue($kept->isSuccessful(), $kept->errors);
		$this->assertStringContainsString('`pairs` is still read', $kept->output, 'An alias, no file changed (D-600).');
		$this->assertStringContainsString('"aliases": [', $this->file('user/data/relations/pairs.json'));
		$this->assertStringContainsString('pairs: [soup]', $this->file('user/content/_recipe/stew.md'));

		$this->assertTrue($this->tester()->run('content:relation pairs --key=served_with --rewrite')->isSuccessful());
		$this->assertStringContainsString('served_with: [soup]', $this->file('user/content/_recipe/stew.md'));
	}

	public function testRemovesItAndWhatEntriesHave(): void
	{
		$removed = $this->tester()->run('content:relation pairs --remove --strip');

		$this->assertTrue($removed->isSuccessful(), $removed->errors);
		$this->assertStringContainsString('Removed its values from 1 entry.', $removed->output);
		$this->assertFileDoesNotExist($this->temporaryDirectory() . '/user/data/relations/pairs.json');
		$this->assertStringNotContainsString('pairs', $this->file('user/content/_recipe/stew.md'));
	}
}
