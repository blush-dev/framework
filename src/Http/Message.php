<?php

/**
 * HTTP message base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * The parts requests and responses share: protocol version, headers, and
 * body. Messages are immutable; every `with*()` method returns a clone.
 *
 * Header names are matched case-insensitively but keep the case they were
 * first given in. Names and values are validated, so a header can't smuggle
 * in a line break.
 */
abstract readonly class Message implements MessageInterface
{
	/**
	 * Protocol versions a message may declare.
	 */
	private const array PROTOCOLS = ['1.0', '1.1', '2', '2.0', '3', '3.0'];

	/**
	 * Header values keyed by the header's name as first given.
	 *
	 * @var array<string, list<string>>
	 */
	protected array $headers;

	/**
	 * Maps each lowercased header name to its name as first given.
	 *
	 * @var array<string, string>
	 */
	protected array $headerNames;

	/**
	 * The message body.
	 */
	protected StreamInterface $body;

	/**
	 * @param array<string, string|list<string>> $headers
	 * @param ?StreamInterface                   $body    Defaults to an empty stream.
	 * @throws InvalidMessage
	 */
	public function __construct(
		array $headers = [],
		?StreamInterface $body = null,
		protected string $protocol = '1.1'
	) {
		self::assertProtocol($protocol);

		$this->body = $body ?? Stream::fromString();

		$names  = [];
		$values = [];

		foreach ($headers as $name => $value) {
			$name  = self::normalizeName((string) $name);
			$lower = strtolower($name);
			$value = self::normalizeValues($value);

			if (isset($names[$lower])) {
				$values[$names[$lower]] = [...$values[$names[$lower]], ...$value];
				continue;
			}

			$names[$lower] = $name;
			$values[$name] = $value;
		}

		$this->headers     = $values;
		$this->headerNames = $names;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getProtocolVersion(): string
	{
		return $this->protocol;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withProtocolVersion(string $version): static
	{
		self::assertProtocol($version);

		return clone($this, ['protocol' => $version]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return array<string, list<string>>
	 */
	#[Override]
	public function getHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hasHeader(string $name): bool
	{
		return isset($this->headerNames[strtolower($name)]);
	}

	/**
	 * @inheritDoc
	 *
	 * @return list<string>
	 */
	#[Override]
	public function getHeader(string $name): array
	{
		$original = $this->headerNames[strtolower($name)] ?? null;

		return $original === null ? [] : $this->headers[$original];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getHeaderLine(string $name): string
	{
		return implode(', ', $this->getHeader($name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withHeader(string $name, $value): static
	{
		$name   = self::normalizeName($name);
		$lower  = strtolower($name);
		$values = self::normalizeValues($value);

		$headers = $this->headers;
		unset($headers[$this->headerNames[$lower] ?? $name]);

		$headers[$name] = $values;

		return clone($this, [
			'headers'     => $headers,
			'headerNames' => [...$this->headerNames, $lower => $name]
		]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withAddedHeader(string $name, $value): static
	{
		$original = $this->headerNames[strtolower(self::normalizeName($name))] ?? null;

		if ($original === null) {
			return $this->withHeader($name, $value);
		}

		$headers            = $this->headers;
		$headers[$original] = [...$headers[$original], ...self::normalizeValues($value)];

		return clone($this, ['headers' => $headers]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withoutHeader(string $name): static
	{
		$lower    = strtolower($name);
		$original = $this->headerNames[$lower] ?? null;

		if ($original === null) {
			return $this;
		}

		$headers = $this->headers;
		$names   = $this->headerNames;
		unset($headers[$original], $names[$lower]);

		return clone($this, ['headers' => $headers, 'headerNames' => $names]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getBody(): StreamInterface
	{
		return $this->body;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withBody(StreamInterface $body): static
	{
		return clone($this, ['body' => $body]);
	}

	/**
	 * Validates a protocol version.
	 *
	 * @throws InvalidMessage
	 */
	private static function assertProtocol(string $version): void
	{
		if (! in_array($version, self::PROTOCOLS, true)) {
			throw new InvalidMessage(sprintf('Unsupported HTTP protocol version "%s".', $version));
		}
	}

	/**
	 * Validates a header name (an RFC 9110 token).
	 *
	 * @throws InvalidMessage
	 */
	private static function normalizeName(string $name): string
	{
		if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z\-]+$/', $name) !== 1) {
			throw new InvalidMessage(sprintf('Invalid header name "%s".', $name));
		}

		return $name;
	}

	/**
	 * Validates header values and trims surrounding whitespace. A value may
	 * hold visible characters, spaces, tabs, and obs-text, but never a
	 * line break.
	 *
	 * @return list<string>
	 * @throws InvalidMessage
	 */
	private static function normalizeValues(mixed $value): array
	{
		$values = is_array($value) ? array_values($value) : [$value];

		if ($values === []) {
			throw new InvalidMessage('A header value must not be an empty list.');
		}

		$normalized = [];

		foreach ($values as $item) {
			if (! is_string($item) && ! is_int($item) && ! is_float($item)) {
				throw new InvalidMessage(sprintf('Header values must be strings or numbers; %s given.', get_debug_type($item)));
			}

			$item = trim((string) $item, " \t");

			if (preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/', $item) !== 1) {
				throw new InvalidMessage('A header value contains invalid characters.');
			}

			$normalized[] = $item;
		}

		return $normalized;
	}
}
