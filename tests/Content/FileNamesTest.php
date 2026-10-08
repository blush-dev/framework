<?php

/**
 * File names tests.
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
use Blush\Content\FileNameRename;
use Blush\Content\FileNameReport;
use Blush\Content\FileNames;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\RenamedFiles;
use Blush\Core\Application;

#[CoversClass(FileNames::class)]
#[CoversClass(FileNameReport::class)]
#[CoversClass(FileNameRename::class)]
#[CoversClass(RenamedFiles::class)]
#[CoversClass(FilesystemWriter::class)]
final class FileNamesTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Writes the standard content with posts named by a pattern, and
	 * categories by another when given.
	 */
	private function posts(string $pattern, ?string $categories = null): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post'     => ['path' => '_posts', 'date_archives' => true, 'routing' => ['prefix' => 'archives'], 'filename' => $pattern],
				'category' => ['path' => 'topics', 'order' => 'position', 'filename' => $categories]
			],
			'relations' => ['category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true]],
			'home' => 'post'
		]);
	}

	private function names(Application $app): FileNames
	{
		return $app->container()->make(FileNames::class);
	}

	public function testRenamesEntriesToTheirTypesPattern(): void
	{
		$this->posts('{date}-{time}.{slug}', 'term.{slug}');
		$this->entry('_posts/_hidden.md', "title: Hidden\npublished: 2009-01-01");
		$this->entry('_posts/undated.md', "title: Undated\nupdated: 2011-02-03 04:05:06");
		$this->entry('_posts/2008-04-20-100000.taken.md', "title: Taken\nslug: taken\npublished: 2001-01-01");
		$this->entry('_posts/2008-04-20-100000.rainy.md', "title: In the Way\npublished: 2008-04-20 10:00:00");

		$app    = $this->site();
		$report = $this->names($app)->report();
		$moves  = array_column(array_map(static fn (FileNameRename $rename): array => [$rename->path, $rename->to], $report->renames()), 1, 0);

		$this->assertSame('_posts/2003-04-15-173900.welcome.md', $moves['_posts/2003-04-15.welcome.md'] ?? null, 'The publish date, in the site\'s time zone.');
		$this->assertSame('_posts/2011-02-03-040506.undated.md', $moves['_posts/undated.md'] ?? null, 'Without one, when it was updated.');
		$this->assertSame('_posts/2001-01-01-000000.taken.md', $moves['_posts/2008-04-20-100000.taken.md'] ?? null, 'The slug is the file name\'s, after its last dot.');
		$this->assertSame('topics/term.art.md', $moves['topics/art.md'] ?? null, 'Any kind of type (D-514).');
		$this->assertArrayNotHasKey('_posts/index.md', $moves, 'Not a landing page.');
		$this->assertArrayNotHasKey('_posts/_hidden.md', $moves, 'Not a hidden name, which a pattern would unhide.');
		$this->assertArrayNotHasKey('about/biography.md', $moves, 'Not a type without a pattern of its own.');
		$this->assertSame(['_posts/hello/index.md' => 'it\'s kept as a folder, which a pattern never names'], $report->skipped['post'] ?? null, 'Never a folder (D-513).');

		$renamed = $this->names($app)->rename('post');

		$this->assertSame('_posts/2003-04-15-173900.welcome.md', $renamed->renamed['_posts/2003-04-15.welcome.md'] ?? null);
		$this->assertSame(['_posts/2008-04-20.rainy.md' => '_posts/2008-04-20-100000.rainy.md already exists.'], $renamed->failed, 'A taken name is left, and said.');
		$this->assertFileExists($this->temporaryDirectory() . '/user/content/topics/art.md', 'Only the type asked for.');

		$snapshot = $app->container()->make(ContentIndex::class)->snapshot();

		$this->assertSame('_posts/2003-04-15-173900.welcome.md', $snapshot->find('en', 'post', 'welcome'), 'Its key, and so its address, stays.');
		$this->assertSame(['_posts/2008-04-20.rainy.md', 'topics/art.md', 'topics/book-reviews.md', 'topics/old-posts.md'], array_map(static fn (FileNameRename $rename): string => $rename->path, $this->names($app)->report()->renames()));
	}

	public function testNamesByTheDateAsWritten(): void
	{
		$this->posts('{date}.{slug}');
		$this->entry('_posts/horace.md', "title: Horace\ndate: 2013-02-09 00:00:00 -5");
		$this->entry('_posts/plain.md', "title: Plain\npublished: 2013-02-09 00:00:00");

		$moves = array_column(array_map(static fn (FileNameRename $rename): array => [$rename->path, $rename->to], $this->names($this->site())->report()->renames()), 1, 0);

		$this->assertSame('_posts/2013-02-09.horace.md', $moves['_posts/horace.md'] ?? null, 'The day written, in its offset: in Chicago it\'s the 8th, 23:00.');
		$this->assertSame('_posts/2013-02-09.plain.md', $moves['_posts/plain.md'] ?? null, 'Without an offset, the site\'s time zone.');
	}

	public function testLeavesNamesWhoseDateIsntReal(): void
	{
		$this->posts('{date}.{slug}');
		$this->writeTemporaryFile('user/content/_posts/2007-03-05.weird.md', "---\ndate     : 2007-00-00 23:22:00 -5\ntitle    : Weird\nid       : " . self::idFor('weird') . "\n---\n");

		$report = $this->names($this->site())->report();

		$this->assertSame('its date, 2007-00-00, isn\'t a real date; fix it first', $report->skipped['post']['_posts/2007-03-05.weird.md'] ?? null, 'Not the day PHP rolls it to.');
	}

	public function testTranslationsLinkedByNameMoveWithTheirOriginal(): void
	{
		$this->posts('{year}.{slug}');
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Core\\AppConfig::fromArray(['timezone' => 'America/Chicago', 'languages' => ['fr' => 'fr_FR', 'pt-br' => 'pt_BR']]);\n");
		$this->entry('_posts/2008-04-05.spring.fr.md', "title: Printemps\nslug: printemps");
		$this->entry('_posts/primavera.pt-br.md', "title: Primavera\ntranslation_of: " . self::idFor('_posts/2008-04-05.spring.md'));
		$this->entry('_posts/2003-04-15.welcome/index.fr.md', 'title: Bienvenue');

		$app     = $this->site();
		$renamed = $this->names($app)->rename();

		$this->assertSame([], $renamed->failed);
		$this->assertSame('_posts/2008.spring.md', $renamed->renamed['_posts/2008-04-05.spring.md'] ?? null);

		$snapshot = $app->container()->make(ContentIndex::class)->snapshot();
		$report   = $this->names($app)->report();

		$this->assertEquals(['en' => '_posts/2008.spring.md', 'fr' => '_posts/2008.spring.fr.md', 'pt-br' => '_posts/primavera.pt-br.md'], $snapshot->translations('_posts/2008.spring.md'), 'Linked by name, it moves; by id, it stays.');
		$this->assertSame('a translation of it is kept as a folder, so renaming it would break their link', $report->skipped['post']['_posts/2003-04-15.welcome.md'] ?? null);
		$this->assertTrue($report->isClean());
	}

	public function testAnEntryMovesWithItsTranslationsOrNotAtAll(): void
	{
		$this->posts('{year}.{slug}');
		$this->writeTemporaryFile('config/app.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Core\\AppConfig::fromArray(['timezone' => 'America/Chicago', 'languages' => ['fr' => 'fr_FR']]);\n");
		$this->entry('_posts/2008-04-05.spring.fr.md', 'title: Printemps');
		$this->entry('_posts/2008.spring.fr.md', 'title: In the way');

		$renamed = $this->names($this->site())->rename();
		$content = $this->temporaryDirectory() . '/user/content/_posts';

		$this->assertSame(['_posts/2008-04-05.spring.md' => '_posts/2008.spring.fr.md already exists.'], $renamed->failed);
		$this->assertFileExists("{$content}/2008-04-05.spring.md", 'Its own move is undone.');
		$this->assertFileDoesNotExist("{$content}/2008.spring.md");
		$this->assertArrayHasKey('_posts/2003-04-15.welcome.md', $renamed->renamed, 'The others are still renamed.');
	}

	public function testLeavesCollectionsWithoutAPatternOfTheirOwn(): void
	{
		$this->standardContent();
		$this->entry('_posts/2008-04-05-2.second.md', "title: Second\npublished: 2008-04-05 10:00:00");

		$this->assertTrue($this->names($this->site())->report()->isClean(), 'Automatic states no intent; same-day counters stay.');
	}
}
