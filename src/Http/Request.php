<?php

/**
 * HTTP request.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * An immutable PSR-7 server request, the one request type in Blush. It's
 * built from PHP's globals by `RequestFactory::fromGlobals()`, or directly
 * with `Request::create('/path')` anywhere else: tests, the CLI, admin
 * previews, and plugins all hand one to `Kernel::handle()`.
 */
final readonly class Request extends Message implements ServerRequestInterface
{
	/**
	 * The request method, case preserved.
	 */
	private string $method;

	/**
	 * The request URI.
	 */
	private UriInterface $uri;

	/**
	 * An explicit request target, or `null` to derive it from the URI.
	 */
	private ?string $requestTarget;

	/**
	 * @param array<string, string|list<string>> $headers
	 * @param array<string, mixed>               $serverParams
	 * @param array<string, string>              $cookieParams
	 * @param array<array-key, mixed>            $queryParams
	 * @param array<array-key, mixed>            $uploadedFiles A tree of `UploadedFileInterface` instances.
	 * @param null|array<array-key, mixed>|object $parsedBody
	 * @param array<string, mixed>               $attributes
	 * @throws InvalidMessage
	 */
	public function __construct(
		string $method,
		UriInterface|string $uri,
		array $headers = [],
		StreamInterface|string|null $body = null,
		string $protocol = '1.1',
		private array $serverParams = [],
		private array $cookieParams = [],
		private array $queryParams = [],
		private array $uploadedFiles = [],
		private null|array|object $parsedBody = null,
		private array $attributes = []
	) {
		$uri = is_string($uri) ? new Uri($uri) : $uri;

		// PSR-7: without a Host header, the host comes from the URI.
		$hasHost = array_any(
			array_keys($headers),
			static fn (int|string $name): bool => strtolower((string) $name) === 'host'
		);

		if (! $hasHost && $uri->getHost() !== '') {
			$headers = ['Host' => self::hostHeader($uri), ...$headers];
		}

		parent::__construct(
			$headers,
			is_string($body) ? Stream::fromString($body) : $body,
			$protocol
		);

		self::assertUploadedFiles($uploadedFiles);

		$this->method        = self::assertMethod($method);
		$this->uri           = $uri;
		$this->requestTarget = null;
	}

	/**
	 * Creates a request for a path or URL, outside of PHP's globals. The
	 * query parameters are parsed from the URI.
	 *
	 * @param array<string, string|list<string>> $headers
	 * @param array<string, mixed>               $server
	 * @throws InvalidMessage
	 */
	public static function create(
		UriInterface|string $uri = '/',
		string $method = 'GET',
		array $headers = [],
		StreamInterface|string $body = '',
		array $server = []
	): self {
		$uri = is_string($uri) ? new Uri($uri) : $uri;

		parse_str($uri->getQuery(), $query);

		return new self(
			method: $method,
			uri: $uri,
			headers: $headers,
			body: $body,
			serverParams: [
				'REQUEST_METHOD' => $method,
				'REQUEST_URI'    => self::targetOf($uri),
				...$server
			],
			queryParams: $query
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getRequestTarget(): string
	{
		return $this->requestTarget ?? self::targetOf($this->uri);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withRequestTarget(string $requestTarget): static
	{
		if ($requestTarget === '' || preg_match('/\s/', $requestTarget) === 1) {
			throw new InvalidMessage('A request target must be non-empty and must not contain whitespace.');
		}

		return clone($this, ['requestTarget' => $requestTarget]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withMethod(string $method): static
	{
		return clone($this, ['method' => self::assertMethod($method)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getUri(): UriInterface
	{
		return $this->uri;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withUri(UriInterface $uri, bool $preserveHost = false): static
	{
		$request = clone($this, ['uri' => $uri]);

		if ($uri->getHost() === '' || ($preserveHost && $this->getHeaderLine('Host') !== '')) {
			return $request;
		}

		return $request->withHeader('Host', self::hostHeader($uri));
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed>
	 */
	#[Override]
	public function getServerParams(): array
	{
		return $this->serverParams;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, string>
	 */
	#[Override]
	public function getCookieParams(): array
	{
		return $this->cookieParams;
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<string, string> $cookies
	 */
	#[Override]
	#[\NoDiscard]
	public function withCookieParams(array $cookies): static
	{
		return clone($this, ['cookieParams' => $cookies]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<array-key, mixed>
	 */
	#[Override]
	public function getQueryParams(): array
	{
		return $this->queryParams;
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<array-key, mixed> $query
	 */
	#[Override]
	#[\NoDiscard]
	public function withQueryParams(array $query): static
	{
		return clone($this, ['queryParams' => $query]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<array-key, mixed>
	 */
	#[Override]
	public function getUploadedFiles(): array
	{
		return $this->uploadedFiles;
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<array-key, mixed> $uploadedFiles
	 */
	#[Override]
	#[\NoDiscard]
	public function withUploadedFiles(array $uploadedFiles): static
	{
		self::assertUploadedFiles($uploadedFiles);

		return clone($this, ['uploadedFiles' => $uploadedFiles]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return null|array<array-key, mixed>|object
	 */
	#[Override]
	public function getParsedBody(): null|array|object
	{
		return $this->parsedBody;
	}

	/**
	 * @inheritDoc
	 *
	 * @param null|array<array-key, mixed>|object $data Anything else is a `TypeError`.
	 */
	#[Override]
	#[\NoDiscard]
	public function withParsedBody($data): static
	{
		return clone($this, ['parsedBody' => $data]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, mixed>
	 */
	#[Override]
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getAttribute(string $name, $default = null): mixed
	{
		return array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withAttribute(string $name, $value): static
	{
		return clone($this, ['attributes' => [...$this->attributes, $name => $value]]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withoutAttribute(string $name): static
	{
		$attributes = $this->attributes;
		unset($attributes[$name]);

		return clone($this, ['attributes' => $attributes]);
	}

	/**
	 * Returns the origin-form target (path and query) of a URI.
	 */
	private static function targetOf(UriInterface $uri): string
	{
		$target = $uri->getPath();
		$target = $target === '' ? '/' : '/' . ltrim($target, '/');
		$query  = $uri->getQuery();

		return $query === '' ? $target : "{$target}?{$query}";
	}

	/**
	 * Returns the `Host` header value for a URI.
	 */
	private static function hostHeader(UriInterface $uri): string
	{
		$port = $uri->getPort();

		return $port === null ? $uri->getHost() : "{$uri->getHost()}:{$port}";
	}

	/**
	 * Validates a method name (an RFC 9110 token).
	 *
	 * @throws InvalidMessage
	 */
	private static function assertMethod(string $method): string
	{
		if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z\-]+$/', $method) !== 1) {
			throw new InvalidMessage(sprintf('Invalid HTTP method "%s".', $method));
		}

		return $method;
	}

	/**
	 * Validates that every leaf of an uploaded-files tree is an
	 * `UploadedFileInterface`.
	 *
	 * @param  array<array-key, mixed> $files
	 * @throws InvalidMessage
	 */
	private static function assertUploadedFiles(array $files): void
	{
		foreach ($files as $file) {
			if (is_array($file)) {
				self::assertUploadedFiles($file);
			} elseif (! $file instanceof UploadedFileInterface) {
				throw new InvalidMessage('Uploaded files must be UploadedFileInterface instances.');
			}
		}
	}
}
