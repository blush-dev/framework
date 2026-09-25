<?php

/**
 * Escaper tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;
use Blush\View\Escaper;

#[CoversClass(Escaper::class)]
#[CoversFunction('e')]
#[CoversFunction('attr')]
#[CoversFunction('url')]
#[CoversFunction('js')]
#[CoversFunction('css')]
#[CoversFunction('raw')]
final class EscaperTest extends TestCase
{
	public function testEscapesHtmlAndAttributes(): void
	{
		$this->assertSame('&lt;b&gt; &amp; &quot;q&quot; &apos;s&apos;', e('<b> & "q" \'s\''));
		$this->assertSame('a&quot; onclick=&quot;x', attr('a" onclick="x'));
		$this->assertSame('', e(null));
		$this->assertSame('', e(false));
		$this->assertSame('1', e(true));
		$this->assertSame('42', e(42));
		$this->assertSame("\u{FFFD}", e("\xC3"));
	}

	public function testUrlsDropUnsafeSchemes(): void
	{
		$this->assertSame('/about?a=1&amp;b=2', url('/about?a=1&b=2'));
		$this->assertSame('https://example.com/', url('https://example.com/'));
		$this->assertSame('mailto:me@example.com', url('mailto:me@example.com'));
		$this->assertSame('#top', url('#top'));
		$this->assertSame('page.html', url('page.html'));
		$this->assertSame('', url('javascript:alert(1)'));
		$this->assertSame('', url(' JaVaScRiPt:alert(1)'));
		$this->assertSame('', url("java\tscript:alert(1)"));
		$this->assertSame('', url('data:text/html,<script>'));
		$this->assertSame('', url(null));
	}

	public function testEncodesJavaScriptLiterals(): void
	{
		$this->assertSame('"\u003C/script\u003E"', js('</script>'));
		$this->assertSame('{"a":"\u0027\u0022\u0026","b":[1,true,null]}', js(['a' => '\'"&', 'b' => [1, true, null]]));
		$this->assertSame('"café/x"', js('café/x'));
	}

	public function testEscapesCss(): void
	{
		$this->assertSame('red', css('red'));
		$this->assertSame('a\\3B \\7D b', css('a;}b'));
		$this->assertSame('', css(null));
	}

	public function testRawPassesHtmlThrough(): void
	{
		$this->assertSame('<p>Hi</p>', raw('<p>Hi</p>'));
		$this->assertSame('', raw(null));
	}
}
