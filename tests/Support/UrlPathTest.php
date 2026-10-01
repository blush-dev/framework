<?php

/**
 * URL path tests.
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
use Blush\Support\UrlPath;

#[CoversClass(UrlPath::class)]
final class UrlPathTest extends TestCase
{
	/**
	 * @return array<string, array{string, string}>
	 */
	public static function paths(): array
	{
		return [
			'empty'           => ['', ''],
			'plain'           => ['photos/cat.jpg', 'photos/cat.jpg'],
			'spaces'          => ['my photos/my cat.jpg', 'my%20photos/my%20cat.jpg'],
			'reserved'        => ['a?b#c/d&e+f', 'a%3Fb%23c/d%26e%2Bf'],
			'percent'         => ['100%/done', '100%25/done'],
			'unicode'         => ['café/über.png', 'caf%C3%A9/%C3%BCber.png'],
			'slashes kept'    => ['/a//b/', '/a//b/'],
			'unreserved kept' => ['a-b_c.d~e', 'a-b_c.d~e']
		];
	}

	#[DataProvider('paths')]
	public function testEncodesEachSegment(string $path, string $encoded): void
	{
		$this->assertSame($encoded, UrlPath::encode($path));
	}
}
