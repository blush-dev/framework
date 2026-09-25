<?php

/**
 * HTTP response.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * An immutable PSR-7 response. The named constructors cover what the
 * framework sends: `html()`, `xml()`, `json()`, `text()`, `redirect()`,
 * `file()`, and `notModified()`.
 */
final readonly class Response extends Message implements ResponseInterface
{
	/**
	 * The status code.
	 */
	private int $status;

	/**
	 * The reason phrase; empty means the standard one.
	 */
	private string $reason;

	/**
	 * @param array<string, string|list<string>> $headers
	 * @throws InvalidMessage
	 */
	public function __construct(
		int|Status $status = Status::Ok,
		array $headers = [],
		StreamInterface|string|null $body = null,
		string $protocol = '1.1',
		string $reason = ''
	) {
		parent::__construct(
			$headers,
			is_string($body) ? Stream::fromString($body) : $body,
			$protocol
		);

		$this->status = self::assertStatus($status);
		$this->reason = $reason;
	}

	/**
	 * Creates an HTML response.
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	public static function html(string $html, int|Status $status = Status::Ok, array $headers = []): self
	{
		return new self($status, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers], $html);
	}

	/**
	 * Creates an XML response (feeds and sitemaps).
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	public static function xml(
		string $xml,
		int|Status $status = Status::Ok,
		array $headers = [],
		string $contentType = 'application/xml'
	): self {
		return new self($status, ['Content-Type' => "{$contentType}; charset=UTF-8", ...$headers], $xml);
	}

	/**
	 * Creates a JSON response from any JSON-encodable value.
	 *
	 * @param array<string, string|list<string>> $headers
	 * @throws InvalidMessage When the data can't be encoded.
	 */
	public static function json(
		mixed $data,
		int|Status $status = Status::Ok,
		array $headers = [],
		int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	): self {
		try {
			$json = json_encode($data, $flags | JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidMessage('Unable to encode the response as JSON: ' . $exception->getMessage(), 0, $exception);
		}

		return new self($status, ['Content-Type' => 'application/json', ...$headers], $json);
	}

	/**
	 * Creates a plain text response.
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	public static function text(string $text, int|Status $status = Status::Ok, array $headers = []): self
	{
		return new self($status, ['Content-Type' => 'text/plain; charset=UTF-8', ...$headers], $text);
	}

	/**
	 * Creates a redirect.
	 *
	 * @param array<string, string|list<string>> $headers
	 * @throws InvalidMessage When `$status` isn't a 3xx code.
	 */
	public static function redirect(
		string|UriInterface $url,
		int|Status $status = Status::Found,
		array $headers = []
	): self {
		$code = $status instanceof Status ? $status->value : $status;

		if ($code < 300 || $code > 399) {
			throw new InvalidMessage(sprintf('A redirect needs a 3xx status; %d given.', $code));
		}

		return new self($status, [...$headers, 'Location' => (string) $url]);
	}

	/**
	 * Creates a response streaming a file, with its type, length, and
	 * modification time. The type is detected from the file's contents
	 * unless given. (Range requests arrive with media serving in M4.)
	 *
	 * @param array<string, string|list<string>> $headers
	 * @throws StreamException When the file can't be read.
	 */
	public static function file(string $path, ?string $contentType = null, array $headers = []): self
	{
		if (! is_file($path) || ! is_readable($path)) {
			throw new StreamException(sprintf('Unable to read "%s".', $path));
		}

		$type     = $contentType ?? (mime_content_type($path) ?: 'application/octet-stream');
		$size     = filesize($path);
		$modified = filemtime($path);

		$defaults = ['Content-Type' => $type];

		if ($size !== false) {
			$defaults['Content-Length'] = (string) $size;
		}

		if ($modified !== false) {
			$defaults['Last-Modified'] = gmdate('D, d M Y H:i:s', $modified) . ' GMT';
		}

		return new self(Status::Ok, [...$defaults, ...$headers], Stream::fromFile($path));
	}

	/**
	 * Creates an empty 304 response.
	 *
	 * @param array<string, string|list<string>> $headers
	 */
	public static function notModified(array $headers = []): self
	{
		return new self(Status::NotModified, $headers);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getStatusCode(): int
	{
		return $this->status;
	}

	/**
	 * Returns the status as an enum case, or `null` for an unregistered
	 * code.
	 */
	public function status(): ?Status
	{
		return Status::tryFrom($this->status);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withStatus(int $code, string $reasonPhrase = ''): static
	{
		return clone($this, ['status' => self::assertStatus($code), 'reason' => $reasonPhrase]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getReasonPhrase(): string
	{
		return $this->reason !== '' ? $this->reason : $this->status()?->reasonPhrase() ?? '';
	}

	/**
	 * Validates a status code.
	 *
	 * @throws InvalidMessage
	 */
	private static function assertStatus(int|Status $status): int
	{
		$code = $status instanceof Status ? $status->value : $status;

		if ($code < 100 || $code > 599) {
			throw new InvalidMessage(sprintf('Invalid HTTP status code %d.', $code));
		}

		return $code;
	}
}
