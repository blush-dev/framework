<?php

/**
 * Stream tests.
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
use Blush\Http\Stream;
use Blush\Http\StreamException;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
	use TemporaryDirectory;

	public function testStringStreamsReadAndWrite(): void
	{
		$stream = Stream::fromString('Hello');

		$this->assertSame(5, $stream->getSize());
		$this->assertTrue($stream->isReadable());
		$this->assertTrue($stream->isWritable());
		$this->assertTrue($stream->isSeekable());
		$this->assertSame('He', $stream->read(2));
		$this->assertSame(2, $stream->tell());
		$this->assertSame('llo', $stream->getContents());
		$this->assertTrue($stream->eof());

		$stream->write(', world');

		$this->assertSame('Hello, world', (string) $stream);
		$this->assertSame(12, $stream->getSize());
	}

	public function testFileStreams(): void
	{
		$path   = $this->writeTemporaryFile('file.txt', 'contents');
		$stream = Stream::fromFile($path);

		$this->assertSame('contents', (string) $stream);
		$this->assertFalse($stream->isWritable());
		$this->assertSame($path, $stream->getMetadata('uri'));

		$this->expectException(StreamException::class);
		$stream->write('nope');
	}

	public function testMissingFilesThrow(): void
	{
		$this->expectException(StreamException::class);

		Stream::fromFile($this->temporaryDirectory() . '/missing.txt');
	}

	public function testDetachedStreamsAreUnusable(): void
	{
		$stream   = Stream::fromString('data');
		$resource = $stream->detach();

		$this->assertIsResource($resource);
		$this->assertNull($stream->getSize());
		$this->assertTrue($stream->eof());
		$this->assertSame([], $stream->getMetadata());
		$this->assertSame('', (string) $stream);

		$this->expectException(StreamException::class);
		$stream->read(1);
	}

	public function testRejectsClosedResources(): void
	{
		$resource = fopen('php://memory', 'r');
		$this->assertNotFalse($resource);
		fclose($resource);

		$this->expectException(StreamException::class);

		new Stream($resource);
	}
}
