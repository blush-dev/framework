<?php

/**
 * Request factory tests.
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
use Blush\Http\RequestFactory;
use Blush\Http\Stream;
use Blush\Http\UploadedFile;

#[CoversClass(RequestFactory::class)]
final class RequestFactoryTest extends TestCase
{
	public function testBuildsTheRequestFromServerVariables(): void
	{
		$request = RequestFactory::fromArrays(
			server: [
				'REQUEST_METHOD'  => 'POST',
				'REQUEST_URI'     => '/contact?ref=home',
				'SERVER_PROTOCOL' => 'HTTP/2.0',
				'HTTPS'           => 'on',
				'HTTP_HOST'       => 'example.com:8443',
				'SERVER_PORT'     => '8443',
				'HTTP_ACCEPT'     => 'text/html',
				'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
				'CONTENT_TYPE'    => 'application/x-www-form-urlencoded',
				'CONTENT_LENGTH'  => '11'
			],
			query: ['ref' => 'home'],
			post: ['name' => 'Blush'],
			cookies: ['theme' => 'dark'],
			body: Stream::fromString('name=Blush')
		);

		$this->assertSame('POST', $request->getMethod());
		$this->assertSame('https://example.com:8443/contact?ref=home', (string) $request->getUri());
		$this->assertSame('2.0', $request->getProtocolVersion());
		$this->assertSame('example.com:8443', $request->getHeaderLine('Host'));
		$this->assertSame('text/html', $request->getHeaderLine('Accept'));
		$this->assertSame('10.0.0.1', $request->getHeaderLine('X-Forwarded-For'));
		$this->assertSame('11', $request->getHeaderLine('Content-Length'));
		$this->assertSame(['ref' => 'home'], $request->getQueryParams());
		$this->assertSame(['name' => 'Blush'], $request->getParsedBody());
		$this->assertSame(['theme' => 'dark'], $request->getCookieParams());
		$this->assertSame('name=Blush', (string) $request->getBody());
	}

	public function testFallsBackToServerNameAndPort(): void
	{
		$request = RequestFactory::fromArrays([
			'REQUEST_URI' => '/',
			'SERVER_NAME' => 'localhost',
			'SERVER_PORT' => '8000'
		]);

		$this->assertSame('GET', $request->getMethod());
		$this->assertSame('http://localhost:8000/', (string) $request->getUri());
		$this->assertNull($request->getParsedBody());
	}

	public function testOnlyFormBodiesAreParsed(): void
	{
		$request = RequestFactory::fromArrays(
			server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
			post: ['ignored' => 'yes']
		);

		$this->assertNull($request->getParsedBody());
	}

	public function testNormalizesUploadedFiles(): void
	{
		$request = RequestFactory::fromArrays(
			server: ['REQUEST_METHOD' => 'POST'],
			files: [
				'avatar' => [
					'tmp_name' => '/tmp/php1',
					'size'     => 10,
					'error'    => UPLOAD_ERR_OK,
					'name'     => 'me.png',
					'type'     => 'image/png'
				],
				'gallery' => [
					'tmp_name' => ['/tmp/php2', '/tmp/php3'],
					'size'     => [20, 0],
					'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
					'name'     => ['a.jpg', ''],
					'type'     => ['image/jpeg', '']
				]
			]
		);

		$files = $request->getUploadedFiles();

		$this->assertInstanceOf(UploadedFile::class, $files['avatar']);
		$this->assertSame('me.png', $files['avatar']->getClientFilename());
		$this->assertIsArray($files['gallery']);
		$this->assertCount(2, $files['gallery']);
		$this->assertInstanceOf(UploadedFile::class, $files['gallery'][1]);
		$this->assertSame(UPLOAD_ERR_NO_FILE, $files['gallery'][1]->getError());
	}
}
