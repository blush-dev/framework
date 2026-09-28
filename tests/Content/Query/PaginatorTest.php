<?php

/**
 * Paginator tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Query\PageLink;
use Blush\Content\Query\PageLinkKind;
use Blush\Content\Query\Paginator;

#[CoversClass(Paginator::class)]
#[CoversClass(PageLink::class)]
final class PaginatorTest extends TestCase
{
	/**
	 * A paginator of `$pages` pages of one entry each, on page `$page`.
	 */
	private function paginator(int $pages, int $page): Paginator
	{
		return new Paginator(new EntryCollection([], $pages), 1, $page);
	}

	/**
	 * Writes links compactly: `<` and `>` for previous and next, `[n]`
	 * for the current page, `…` for dots.
	 *
	 * @param list<PageLink> $links
	 */
	private function sketch(array $links): string
	{
		return implode(' ', array_map(
			static fn (PageLink $link): string => match ($link->kind) {
				PageLinkKind::Previous => '<',
				PageLinkKind::Next     => '>',
				PageLinkKind::Dots     => '…',
				PageLinkKind::Current  => "[{$link->number}]",
				PageLinkKind::Number   => (string) $link->number
			},
			$links
		));
	}

	/**
	 * @return iterable<string, array{int, int, int, int, string}>
	 */
	public static function windows(): iterable
	{
		yield 'first page'            => [10, 1, 1, 1, '[1] 2 … 10 >'];
		yield 'middle page'           => [10, 5, 1, 1, '< 1 … 4 [5] 6 … 10 >'];
		yield 'last page'             => [10, 10, 1, 1, '< 1 … 9 [10]'];
		yield 'a one-page gap fills'  => [10, 4, 1, 1, '< 1 2 3 [4] 5 … 10 >'];
		yield 'few pages show all'    => [3, 2, 1, 1, '< 1 [2] 3 >'];
		yield 'wider ends and middle' => [20, 10, 2, 2, '< 1 2 … 8 9 [10] 11 12 … 19 20 >'];
		yield 'no ends'               => [10, 5, 0, 1, '< … 4 [5] 6 … >'];
	}

	#[DataProvider('windows')]
	public function testLinksShowTheEndsAndAWindowAroundTheCurrentPage(int $pages, int $page, int $endSize, int $midSize, string $expected): void
	{
		$this->assertSame($expected, $this->sketch($this->paginator($pages, $page)->links(endSize: $endSize, midSize: $midSize)));
	}

	public function testLinksCarryTheirPageUrls(): void
	{
		$links = $this->paginator(3, 2)->links(static fn (int $page): string => $page === 1 ? '/blog' : "/blog/page/{$page}");

		$this->assertSame(
			[['prev', 1, '/blog'], ['number', 1, '/blog'], ['current', 2, null], ['number', 3, '/blog/page/3'], ['next', 3, '/blog/page/3']],
			array_map(static fn (PageLink $link): array => [$link->kind->value, $link->number, $link->url], $links)
		);
		$this->assertTrue($links[2]->isCurrent());
		$this->assertFalse($links[1]->isCurrent());
	}

	public function testLinksCanLeaveOutPreviousAndNext(): void
	{
		$this->assertSame('1 [2] 3', $this->sketch($this->paginator(3, 2)->links(adjacent: false)));
	}

	public function testOnePageOrAPagePastTheLastHasNoLinks(): void
	{
		$this->assertSame([], $this->paginator(1, 1)->links());
		$this->assertSame([], $this->paginator(3, 4)->links());
	}

	public function testPageNumbersPadWithZeros(): void
	{
		$this->assertSame('07', new PageLink(PageLinkKind::Number, 7)->padded());
		$this->assertSame('012', new PageLink(PageLinkKind::Number, 12)->padded(3));
		$this->assertSame('', new PageLink(PageLinkKind::Dots)->padded());
	}
}
