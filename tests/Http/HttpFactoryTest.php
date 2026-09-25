<?php

/**
 * HTTP factory tests.
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
use Blush\Http\HttpFactory;
use Blush\Http\Request;
use Blush\Http\Response;
use Blush\Http\Uri;
use Blush\Http\UploadedFile;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(HttpFactory::class)]
final class HttpFactoryTest extends TestCase
{
	use TemporaryDirectory;

	public function testCreatesBlushMessages(): void
	{
		$factory = new HttpFactory();

		$request = $factory->createRequest('POST', 'https://example.com/form');
		$this->assertInstanceOf(Request::class, $request);
		$this->assertSame('example.com', $request->getHeaderLine('Host'));

		$server = $factory->createServerRequest('GET', new Uri('/'), ['REMOTE_ADDR' => '127.0.0.1']);
		$this->assertSame(['REMOTE_ADDR' => '127.0.0.1'], $server->getServerParams());

		$response = $factory->createResponse(201, 'Made');
		$this->assertInstanceOf(Response::class, $response);
		$this->assertSame('Made', $response->getReasonPhrase());

		$this->assertSame('/path', (string) $factory->createUri('/path'));
	}

	public function testCreatesStreamsAndUploads(): void
	{
		$factory = new HttpFactory();
		$path    = $this->writeTemporaryFile('data.txt', 'file data');

		$this->assertSame('text', (string) $factory->createStream('text'));
		$this->assertSame('file data', (string) $factory->createStreamFromFile($path));

		$resource = fopen('php://memory', 'r+');
		$this->assertNotFalse($resource);
		$this->assertTrue($factory->createStreamFromResource($resource)->isWritable());

		$upload = $factory->createUploadedFile($factory->createStream('abc'), clientFilename: 'a.txt');
		$this->assertInstanceOf(UploadedFile::class, $upload);
		$this->assertSame(3, $upload->getSize());
	}
}
