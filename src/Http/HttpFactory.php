<?php

/**
 * HTTP factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * Every PSR-17 factory in one class, building Blush's own messages. The
 * container binds it under each PSR-17 interface, so third-party PSR-15
 * middleware can create responses without knowing about Blush.
 */
final readonly class HttpFactory implements
	RequestFactoryInterface,
	ResponseFactoryInterface,
	ServerRequestFactoryInterface,
	StreamFactoryInterface,
	UploadedFileFactoryInterface,
	UriFactoryInterface
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createRequest(string $method, $uri): RequestInterface
	{
		return new Request($method, $uri);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
	{
		return new Response(status: $code, reason: $reasonPhrase);
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, mixed> $serverParams
	 */
	#[Override]
	public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
	{
		return new Request(method: $method, uri: $uri, serverParams: $serverParams);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createStream(string $content = ''): StreamInterface
	{
		return Stream::fromString($content);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
	{
		return Stream::fromFile($filename, $mode);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createStreamFromResource($resource): StreamInterface
	{
		return new Stream($resource);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createUploadedFile(
		StreamInterface $stream,
		?int $size = null,
		int $error = UPLOAD_ERR_OK,
		?string $clientFilename = null,
		?string $clientMediaType = null
	): UploadedFileInterface {
		return new UploadedFile($stream, $size ?? $stream->getSize(), $error, $clientFilename, $clientMediaType);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function createUri(string $uri = ''): UriInterface
	{
		return new Uri($uri);
	}
}
