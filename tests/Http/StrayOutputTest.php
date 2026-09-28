<?php

/**
 * Stray output tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Http\Response;
use Blush\Http\StrayOutput;

#[CoversClass(StrayOutput::class)]
final class StrayOutputTest extends TestCase
{
	public function testOutputGoesJustInsideTheBodyOfHtml(): void
	{
		$response = StrayOutput::insert(
			Response::html('<!doctype html><html><head><title>Hi</title></head><body class="home"><p>Hi</p></body></html>', headers: ['Content-Length' => '90']),
			'<pre>dump</pre>'
		);

		$this->assertSame('<!doctype html><html><head><title>Hi</title></head><body class="home"><pre>dump</pre><p>Hi</p></body></html>', (string) $response->getBody());
		$this->assertFalse($response->hasHeader('Content-Length'));
		$this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
	}

	public function testOutputGoesFirstInOtherResponses(): void
	{
		$this->assertSame('dump{"a":1}', (string) StrayOutput::insert(Response::json(['a' => 1]), 'dump')->getBody());
		$this->assertSame('dump<p>No body tag</p>', (string) StrayOutput::insert(Response::html('<p>No body tag</p>'), 'dump')->getBody());
	}

	public function testNoOutputLeavesTheResponseAlone(): void
	{
		$response = Response::html('<body></body>');

		$this->assertSame($response, StrayOutput::insert($response, ''));
	}
}
