<?php

/**
 * Flat entries tests.
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
use Blush\Content\FlatEntries;
use Blush\Content\Lint\Linter;
use Blush\Field\Severity;

#[CoversClass(FlatEntries::class)]
#[CoversClass(Linter::class)]
final class FlatEntriesTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('_posts/2024/old.md', "title: Old\npublished: 2024-01-01");
		$this->entry('_posts/_drafts/idea.md', 'title: Idea');
		$this->entry('_posts/_drafts/sketch/index.md', 'title: Sketch');
	}

	public function testFindsWhereEachFileBelongs(): void
	{
		$flat = $this->site()->container()->make(FlatEntries::class);

		$this->assertSame([
			'_posts/2024/old.md'             => '_posts/old.md',
			'_posts/_drafts/sketch/index.md' => '_posts/_drafts/sketch.md',
			'_posts/hello/index.md'          => '_posts/hello.md'
		], $flat->report(), 'Folder entries, and folders below; a `_` folder stays, and so do pages\' folders (D-514).');
	}

	public function testLintReportsThem(): void
	{
		$report = $this->site()->container()->make(Linter::class)->lint();
		$errors = array_map(static fn (array $violations): array => array_map(static fn ($violation): string => $violation->message, $violations), $report->violations(Severity::Error));

		$this->assertContains('is a folder entry; a collection\'s entries are files in its folder. Move it to _posts/hello.md with content:flatten, or on Site Health in the admin.', $errors['_posts/hello/index.md'] ?? []);
		$this->assertContains('is in a folder below its collection\'s; a collection\'s entries are files in its folder. Move it to _posts/old.md with content:flatten, or on Site Health in the admin.', $errors['_posts/2024/old.md'] ?? []);
		$this->assertArrayNotHasKey('_posts/_drafts/idea.md', $errors);
		$this->assertArrayNotHasKey('about/biography.md', $errors, 'Trees nest by folder.');
	}

	public function testMovesThemAndRemovesFoldersLeftEmpty(): void
	{
		$app     = $this->site();
		$content = $this->temporaryDirectory() . '/user/content/_posts';
		$moved   = $app->container()->make(FlatEntries::class)->flatten();

		$this->assertSame('_posts/old.md', $moved->renamed['_posts/2024/old.md'] ?? null);
		$this->assertFileExists("{$content}/hello.md");
		$this->assertDirectoryDoesNotExist("{$content}/2024", 'An emptied folder is removed.');
		$this->assertDirectoryExists("{$content}/hello", 'One with other files in it stays.');
		$this->assertDirectoryExists("{$content}/_drafts");
		$this->assertSame([], $app->container()->make(FlatEntries::class)->report());
	}
}
