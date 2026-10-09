<?php

/**
 * Entry folders tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Entries;
use Blush\Content\Index\EntryFiles;
use Blush\Content\EntryFolders;
use Blush\Content\FileNames;
use Blush\Content\Lint\Linter;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Core\Application;
use Blush\Field\Severity;

#[CoversClass(EntryFolders::class)]
#[CoversClass(Linter::class)]
final class EntryFoldersTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('_posts/2024/old.md', "title: Old\npublished: 2024-01-01");
		$this->entry('_posts/_drafts/idea.md', 'title: Idea');
		$this->entry('_posts/_drafts/sketch/index.md', 'title: Sketch');
	}

	/**
	 * Keeps posts in a folder for each year (D-629), with the standard
	 * content's other types.
	 */
	private function byYear(string $pattern = '{year}'): void
	{
		$this->contentConfig([
			'types' => [
				'post'     => ['folder' => "_posts/{$pattern}", 'routing' => ['prefix' => 'archives'], 'date_archives' => true],
				'category' => ['path' => 'topics', 'order' => 'position']
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true]
			],
			'home' => 'post'
		]);
	}

	/**
	 * Returns the content lint's messages by path, at a severity.
	 *
	 * @return array<string, list<string>>
	 */
	private function lint(Application $app, Severity $severity): array
	{
		$report = $app->container()->make(Linter::class)->lint();

		return array_map(static fn (array $violations): array => array_map(static fn ($violation): string => $violation->message, $violations), $report->violations($severity));
	}

	public function testFindsWhereEachFileBelongs(): void
	{
		$folders = $this->site()->container()->make(EntryFolders::class);

		$this->assertSame([
			'_posts/2024/old.md'             => '_posts/old.md',
			'_posts/_drafts/sketch/index.md' => '_posts/_drafts/sketch.md',
			'_posts/hello/index.md'          => '_posts/hello.md'
		], $folders->report(), 'Folder entries, and folders below; a `_` folder stays, and so do pages\' folders (D-514).');
	}

	public function testLintReportsThem(): void
	{
		$errors = $this->lint($this->site(), Severity::Error);

		$this->assertContains('is a folder entry; its type\'s entries are files. Move it with content:folders, or on Site Health in the admin.', $errors['_posts/hello/index.md'] ?? []);
		$this->assertContains('is in a folder below its type\'s, which keeps its files directly in its folder. Move it with content:folders, or on Site Health in the admin.', $errors['_posts/2024/old.md'] ?? []);
		$this->assertArrayNotHasKey('_posts/_drafts/idea.md', $errors);
		$this->assertArrayNotHasKey('about/biography.md', $errors, 'Trees nest by folder.');
	}

	public function testMovesThemAndRemovesFoldersLeftEmpty(): void
	{
		$app     = $this->site();
		$content = $this->temporaryDirectory() . '/user/content/_posts';
		$moved   = $app->container()->make(EntryFolders::class)->move();

		$this->assertSame('_posts/old.md', $moved->renamed['_posts/2024/old.md'] ?? null);
		$this->assertFileExists("{$content}/hello.md");
		$this->assertDirectoryDoesNotExist("{$content}/2024", 'An emptied folder is removed.');
		$this->assertDirectoryExists("{$content}/hello", 'One with other files in it stays.');
		$this->assertDirectoryExists("{$content}/_drafts");
		$this->assertSame([], $app->container()->make(EntryFolders::class)->report());
	}

	public function testAFolderBelowACollectionIsNoPartOfAKey(): void
	{
		$entry = $this->repository()->named('post', 'old');

		$this->assertSame('_posts/2024/old.md', $entry?->path, 'Found by its slug alone (D-629).');
		$this->assertSame('idea', $this->entryAt('_posts/_drafts/idea.md')?->key, 'Nor is a `_` folder: a key is what records say (D-656).');
	}

	public function testAPatternPlacesFilesByTheirWrittenDate(): void
	{
		$this->byYear();
		$this->entry('_posts/2024/2024-01-01.kept.md', "title: Kept\npublished: 2024-01-01");

		$folders = $this->site()->container()->make(EntryFolders::class)->report();

		$this->assertSame('_posts/2003/2003-04-15.welcome.md', $folders['_posts/2003-04-15.welcome.md'] ?? null, 'By the date as written, in its offset.');
		$this->assertSame('_posts/2010/hello.md', $folders['_posts/hello/index.md'] ?? null, 'A folder entry becomes a file in its year.');
		$this->assertSame('_posts/2024/old.md', $folders['_posts/2024/old.md'] ?? '_posts/2024/old.md', 'One in its year stays.');
		$this->assertArrayNotHasKey('_posts/2024/2024-01-01.kept.md', $folders);
		$this->assertArrayNotHasKey('_posts/index.md', $folders, 'A landing page stays.');
	}

	public function testLintWarnsOfAFileOutsideAPatternsFolders(): void
	{
		$this->byYear('{year}/{month}');

		$app      = $this->site();
		$warnings = $this->lint($app, Severity::Warning);
		$errors   = $this->lint($app, Severity::Error);

		$this->assertContains('is in a folder its type\'s folder pattern ({year}/{month}) doesn\'t give. Move it with content:folders, or on Site Health in the admin.', $warnings['_posts/2024/old.md'] ?? []);
		$this->assertContains('is in a folder its type\'s folder pattern ({year}/{month}) doesn\'t give. Move it with content:folders, or on Site Health in the admin.', $warnings['_posts/2008-04-05.spring.md'] ?? [], 'Older files keep working, so it\'s a warning.');
		$this->assertArrayNotHasKey('_posts/2008-04-05.spring.md', $errors);
	}

	public function testNewFilesAndCopiesGoInTheirYear(): void
	{
		$this->byYear();

		$app     = $this->site();
		$type    = $app->container()->make(ContentTypes::class)->get('post');
		$writer  = $app->container()->make(FilesystemWriter::class);
		$created = $writer->create($type, 'brand-new', new EntryChanges(['title' => 'Brand New']));
		$copied  = $writer->duplicate('_posts/2024/old.md', 'old-copy', new EntryChanges(), new DateTimeImmutable('2025-03-01'));

		$this->assertSame('_posts/2026/brand-new.md', $created->path, 'Now, from the frozen clock.');
		$this->assertSame('_posts/2025/old-copy.md', $copied->path);
		$this->assertSame('brand-new', $app->container()->make(EntryFiles::class)->at($created->path)?->key);
	}

	public function testHiddenFilesStayOutsideAPattern(): void
	{
		$this->byYear();
		$this->entry('_posts/_authors.md', 'title: Authors');

		$folders = $this->site()->container()->make(EntryFolders::class)->report();

		$this->assertArrayNotHasKey('_posts/_authors.md', $folders, 'A hidden file stays (D-630).');
		$this->assertArrayNotHasKey('_posts/_drafts/idea.md', $folders);
		$this->assertSame('_posts/_drafts/sketch.md', $folders['_posts/_drafts/sketch/index.md'] ?? null, 'One kept as a folder still becomes a file there.');
	}

	public function testANewDateMovesAFileToItsYear(): void
	{
		$this->byYear();
		$this->entry('_posts/2024/2024-05-01.moving.md', "title: Moving\npublished: 2024-05-01 09:00:00");

		$app = $this->site();
		$app->container()->make(FilesystemWriter::class)->update('_posts/2024/2024-05-01.moving.md', new EntryChanges(['published' => '2025-02-03 09:00:00']));

		$this->assertSame('_posts/2025/2024-05-01.moving.md', $app->container()->make(FileNames::class)->follow('_posts/2024/2024-05-01.moving.md'), 'Its folder follows the date; its name has no pattern of its own to follow.');
	}

	public function testInitialsKeepTermsInFoldersByLetter(): void
	{
		$this->contentConfig([
			'types' => ['category' => ['folder' => 'topics/{initial}', 'order' => 'position']]
		]);

		$folders = $this->site()->container()->make(EntryFolders::class)->report();

		$this->assertSame('topics/a/art.md', $folders['topics/art.md'] ?? null);
		$this->assertSame('topics/b/book-reviews.md', $folders['topics/book-reviews.md'] ?? null);
	}
}
