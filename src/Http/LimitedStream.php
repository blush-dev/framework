<?php

/**
 * Limited stream.
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
 * A read-only window onto part of a seekable stream: `$length` bytes from
 * `$offset`. Positions are relative to the window, so rewinding goes back
 * to its start. `Response::file()` uses it for a byte range, which the
 * emitter then streams without reading the rest of the file.
 */
final class LimitedStream implements StreamInterface, Stringable
{
	private int $position = 0;

	/**
	 * @throws StreamException When the stream isn't seekable and readable.
	 */
	public function __construct(
		private readonly StreamInterface $stream,
		private readonly int $offset,
		private readonly int $length
	) {
		if (! $stream->isSeekable() || ! $stream->isReadable()) {
			throw new StreamException('A limited stream needs a seekable, readable stream.');
		}

		$this->rewind();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		try {
			$this->rewind();

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
		$this->stream->close();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function detach()
	{
		return $this->stream->detach();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getSize(): int
	{
		return $this->length;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tell(): int
	{
		return $this->position;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function eof(): bool
	{
		return $this->position >= $this->length;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isSeekable(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function seek(int $offset, int $whence = SEEK_SET): void
	{
		$position = match ($whence) {
			SEEK_CUR => $this->position + $offset,
			SEEK_END => $this->length + $offset,
			default  => $offset
		};

		if ($position < 0 || $position > $this->length) {
			throw new StreamException(sprintf('Unable to seek to %d in a %d-byte stream.', $position, $this->length));
		}

		$this->stream->seek($this->offset + $position);
		$this->position = $position;
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
		return false;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(string $string): int
	{
		throw new StreamException('A limited stream is read-only.');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function isReadable(): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(int $length): string
	{
		$length = min($length, $this->length - $this->position);

		if ($length < 1) {
			return '';
		}

		$data = $this->stream->read($length);

		$this->position += strlen($data);

		return $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getContents(): string
	{
		$contents = '';

		while (! $this->eof()) {
			$chunk = $this->read(8192);

			if ($chunk === '') {
				break;
			}

			$contents .= $chunk;
		}

		return $contents;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getMetadata(?string $key = null): mixed
	{
		return $this->stream->getMetadata($key);
	}
}
