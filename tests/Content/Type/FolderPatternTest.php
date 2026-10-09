<?php

/**
 * Folder pattern tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Type;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Content\Type\Collection;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\FolderPattern;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\InvalidContentType;
use Blush\Content\Type\Profiles;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;

#[CoversClass(FolderPattern::class)]
#[CoversClass(ContentType::class)]
final class FolderPatternTest extends TestCase
{
	public function testGivesFoldersForASlugAndDate(): void
	{
		$date = new DateTimeImmutable('2026-10-08');

		$this->assertSame('2026', new FolderPattern('{year}')->path('hello', $date));
		$this->assertSame('2026/10', new FolderPattern('{year}/{month}')->path('hello', $date));
		$this->assertSame('h', new FolderPattern('{initial}')->path('Hello', $date));
		$this->assertSame('9', new FolderPattern('{initial}')->path('99-problems', $date));
		$this->assertSame('é', new FolderPattern('{initial}')->path('été', $date));
		$this->assertTrue(new FolderPattern('{year}/{initial}')->isDated());
		$this->assertFalse(new FolderPattern('{initial}')->isDated());
	}

	public function testExplainsFoldersOfItsShape(): void
	{
		$pattern = new FolderPattern('{year}/{month}');

		$this->assertTrue($pattern->explains(['2024', '01']));
		$this->assertFalse($pattern->explains(['2024']));
		$this->assertFalse($pattern->explains(['2024', '13']));
		$this->assertFalse($pattern->explains(['archive', '01']));
		$this->assertTrue(new FolderPattern('{initial}')->explains(['h']));
		$this->assertFalse(new FolderPattern('{initial}')->explains(['hello']));
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalidPatterns(): iterable
	{
		yield 'not a token'     => ['archive'];
		yield 'two in a folder' => ['{year}-{month}'];
		yield 'unknown'         => ['{day}'];
		yield 'twice'           => ['{year}/{year}'];
		yield 'month alone'     => ['{month}'];
		yield 'month first'     => ['{month}/{year}'];
	}

	#[DataProvider('invalidPatterns')]
	public function testRefusesWhatIsntOne(string $pattern): void
	{
		$this->expectException(InvalidContentType::class);

		(void) new FolderPattern($pattern);
	}

	public function testATypeKeepsItsPatternBelowItsFolder(): void
	{
		$type = new Collection('post', folders: '{year}');

		$this->assertSame('_post', $type->folder, 'Every type is kept in _ and its name (D-683).');
		$this->assertSame('{year}', $type->folders?->pattern);
		$this->assertSame('{year}', $type->toArray()['folders'] ?? null);
		$this->assertArrayNotHasKey('folder', $type->toArray());
		$this->assertSame('_post/2026', $type->directoryFor('hello', new DateTimeImmutable('2026-01-01')));
		$this->assertSame('_post/_drafts', $type->directoryFor('hello', new DateTimeImmutable('2026-01-01'), ['_drafts']), 'A hidden file stays outside the pattern.');
		$this->assertSame(['_drafts'], $type->hiddenFolders('_post/_drafts/2026/hello/index.md'));
	}

	public function testProfilesHaveNoPatternUnlessTheyGiveOne(): void
	{
		$this->assertNull(new Profiles()->folders, 'D-684.');
		$this->assertSame('_profile', BuiltInType::Profile->type()->folder);
		$this->assertNull(BuiltInType::Profile->type()->folders);
		$this->assertSame('{initial}', new Profiles(folders: '{initial}')->folders?->pattern);
		$this->assertNull(new Collection('tag')->folders, 'A collection doesn\'t know it\'s terms.');
	}

	public function testATreeTakesNoPattern(): void
	{
		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('a tree\'s folders are its pages\'');

		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();

		ContentType::fromArray(['name' => 'doc', 'kind' => 'tree', 'folders' => '{year}'], new FieldFactory($registry));
	}
}
