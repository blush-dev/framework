<?php

/**
 * Stream.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Stringable;
use Throwable;
use Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 stream over a PHP stream resource. The named constructors cover
 * the kinds the HTTP layer needs: an in-memory string (`php://temp`, which
 * spills to disk past 2 MB), a file, and an existing resource.
 */
final class Stream implements StreamInterface, Stringable
{
	/**
	 * `fopen()` modes that allow reading.
	 */
	private const string READ_MODES = '/r|\+/';

	/**
	 * `fopen()` modes that allow writing.
	 */
	private const string WRITE_MODES = '/[waxc]|\+/';

	/**
	 * The underlying resource, or `null` once detached or closed.
	 *
	 * @var ?resource
	 */
	private mixed $resource;

	/**
	 * @param resource $resource
	 * @throws StreamException When `$resource` is not a stream.
	 */
	public function __construct(mixed $resource)
	{
		if (! is_resource($resource) || get_resource_type($resource) !== 'stream') {
			throw new StreamException('A stream must wrap a stream resource.');
		}

		$this->resource = $resource;
	}

	/**
	 * Creates a readable, writable in-memory stream holding `$contents`,
	 * positioned at the start.
	 *
	 * @throws StreamException
	 */
	public static function fromString(string $contents = ''): self
	{
		$resource = fopen('php://temp', 'r+');

		if ($resource === false) {
			throw new StreamException('Unable to open a temporary stream.');
		}

		if ($contents !== '') {
			fwrite($resource, $contents);
			rewind($resource);
		}

		return new self($resource);
	}

	/**
	 * Opens a file as a stream.
	 *
	 * @throws StreamException When the file can't be opened.
	 */
	public static function fromFile(string $path, string $mode = 'r'): self
	{
		if ($mode === '' || preg_match('/^[rwaxc]b?\+?b?$/', $mode) !== 1) {
			throw new StreamException(sprintf('Invalid file mode "%s".', $mode));
		}

		$resource = @fopen($path, $mode);

		if ($resource === false) {
			throw new StreamException(sprintf('Unable to open "%s".', $path));
		}

		return new self($resource);
	}

	/**
	 * Closes the resource when the stream is released.
	 */
	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Reads the whole stream from the start. Per PSR-7, this never throws;
	 * an unreadable stream yields an empty string.
	 */
	#[Override]
	public function __toString(): string
	{
		try {
			if ($this->isSeekable()) {
				$this->rewind();
			}

			return $this->getContents();
		} catch (Throwable) {
			return '';
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function close(): void
	{
		$resource = $this->detach();

		if ($resource !== null) {
			fclose($resource);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function detach()
	{
		$resource       = $this->resource;
		$this->resource = null;

		return $resource;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getSize(): ?int
	{
		if ($this->resource === null) {
			return null;
		}

		$uri = $this->getMetadata('uri');

		if (is_string($uri)) {
			clearstatcache(true, $uri);
		}

		$stats = fstat($this->resource);

		return $stats === false ? null : $stats['size'];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tell(): int
	{
		$position = ftell($this->attached());

		return $position === false ? throw new StreamException('Unable to determine the stream position.') : $position;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function eof(): bool
	{
		return $this->resource === null || feof($this->resource);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isSeekable(): bool
	{
		return $this->resource !== null && $this->getMetadata('seekable') === true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function seek(int $offset, int $whence = SEEK_SET): void
	{
		$resource = $this->attached();

		if (! $this->isSeekable() || fseek($resource, $offset, $whence) === -1) {
			throw new StreamException(sprintf('Unable to seek to offset %d.', $offset));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function rewind(): void
	{
		$this->seek(0);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isWritable(): bool
	{
		$mode = $this->getMetadata('mode');

		return is_string($mode) && preg_match(self::WRITE_MODES, $mode) === 1;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(string $string): int
	{
		$resource = $this->attached();

		if (! $this->isWritable()) {
			throw new StreamException('The stream is not writable.');
		}

		$written = fwrite($resource, $string);

		return $written === false ? throw new StreamException('Unable to write to the stream.') : $written;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isReadable(): bool
	{
		$mode = $this->getMetadata('mode');

		return is_string($mode) && preg_match(self::READ_MODES, $mode) === 1;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(int $length): string
	{
		$resource = $this->attached();

		if (! $this->isReadable()) {
			throw new StreamException('The stream is not readable.');
		}

		if ($length < 1) {
			return '';
		}

		$data = fread($resource, $length);

		return $data === false ? throw new StreamException('Unable to read from the stream.') : $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getContents(): string
	{
		$resource = $this->attached();

		if (! $this->isReadable()) {
			throw new StreamException('The stream is not readable.');
		}

		$contents = stream_get_contents($resource);

		return $contents === false ? throw new StreamException('Unable to read the stream contents.') : $contents;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getMetadata(?string $key = null): mixed
	{
		if ($this->resource === null) {
			return $key === null ? [] : null;
		}

		$metadata = stream_get_meta_data($this->resource);

		return $key === null ? $metadata : $metadata[$key] ?? null;
	}

	/**
	 * Returns the resource, or throws when the stream has been detached.
	 *
	 * @return resource
	 * @throws StreamException
	 */
	private function attached(): mixed
	{
		return $this->resource ?? throw new StreamException('The stream has been detached.');
	}
}
