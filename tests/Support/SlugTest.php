<?php

/**
 * Slug tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Support\Slug;

#[CoversClass(Slug::class)]
final class SlugTest extends TestCase
{
	/**
	 * @return array<string, array{string, string}>
	 */
	public static function slugs(): array
	{
		return [
			'already a slug'  => ['book-reviews', 'book-reviews'],
			'spaces and case' => ['Book Reviews', 'book-reviews'],
			'underscores'     => ['short_story', 'short-story'],
			'punctuation'     => ['  What?! Now... ', 'what-now'],
			'numbers'         => ['2025: The Year', '2025-the-year'],
			'unicode'         => ['Café Über', 'café-über']
		];
	}

	#[DataProvider('slugs')]
	public function testMakesSlugs(string $value, string $slug): void
	{
		$this->assertSame($slug, Slug::from($value));
	}

	public function testUsesAnotherSeparator(): void
	{
		$this->assertSame('short_story_time', Slug::from('Short-Story time', '_'));
	}

	public function testRecognizesSlugs(): void
	{
		$this->assertTrue(Slug::isSlug('about-me'));
		$this->assertFalse(Slug::isSlug('About Me'));
		$this->assertFalse(Slug::isSlug(''));
	}
}
