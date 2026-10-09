<?php

/**
 * Missing parents test.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Commands\CreateMissingParents;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Content\CreatedEntries;
use Blush\Content\Entries;
use Blush\Content\MissingParents;
use Blush\Content\Status;

#[CoversClass(MissingParents::class)]
#[CoversClass(CreatedEntries::class)]
#[CoversClass(CreateMissingParents::class)]
final class MissingParentsTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('guide/setup/install.md', 'title: Install');
	}

	public function testFindsFoldersWithNoPage(): void
	{
		$this->assertSame([
			'page' => [
				'guide'       => ['title' => 'Guide', 'pages' => 1],
				'guide/setup' => ['title' => 'Setup', 'pages' => 1]
			]
		], $this->site()->container()->make(MissingParents::class)->report(), 'Titled by its folder\'s name; `__drafts` can\'t be a page\'s key, so it\'s left.');
	}

	public function testWritesThemAsDrafts(): void
	{
		$app     = $this->site();
		$content = $app->container()->make(Entries::class);

		$this->assertSame('install', $content->named('page', 'install')?->key, 'At the top until its parents are written (D-656).');

		$created = $app->container()->make(MissingParents::class)->create(only: ['page/guide/setup']);

		$this->assertSame(['page/guide/setup'], array_keys($created->created), 'One row; its own missing parent is written with it.');
		$this->assertSame([], $created->failed);
		$this->assertSame(Status::Draft, $content->named('page', 'guide')?->status);
		$this->assertSame('Setup', $content->named('page', 'guide/setup')?->title);
		$this->assertSame('guide/setup/install', $content->named('page', 'guide/setup/install')?->key, 'Back at its address.');
		$this->assertSame([], $app->container()->make(MissingParents::class)->report());
	}

	public function testTheCommandListsAndWritesThem(): void
	{
		$tester = new CommandTester($this->site()->container()->make(Console::class));
		$check  = $tester->run('content:parents');

		$this->assertFalse($check->isSuccessful());
		$this->assertStringContainsString('missing  page/guide/setup (1 page under it)', $check->output);
		$this->assertStringContainsString('2 parent folders have no page', $check->errors);

		$fixed = $tester->run('content:parents --write');

		$this->assertTrue($fixed->isSuccessful(), $fixed->errors);
		$this->assertStringContainsString('Every page is under a parent page that exists.', $fixed->output);
	}
}
