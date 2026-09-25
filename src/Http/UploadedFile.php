<?php

/**
 * Uploaded file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * A PSR-7 uploaded file, from `$_FILES` (a temporary file path) or from a
 * stream. A file can be moved once. Where uploads may be written, and which
 * types are allowed, is decided by whoever moves it (D-039).
 */
final class UploadedFile implements UploadedFileInterface
{
	/**
	 * Whether the file has been moved.
	 */
	private bool $moved = false;

	/**
	 * @param string|StreamInterface $file A temporary file path or a stream.
	 * @throws InvalidMessage When `$error` isn't an `UPLOAD_ERR_*` constant.
	 */
	public function __construct(
		private readonly string|StreamInterface $file,
		private readonly ?int $size = null,
		private readonly int $error = UPLOAD_ERR_OK,
		private readonly ?string $clientFilename = null,
		private readonly ?string $clientMediaType = null
	) {
		if ($error < UPLOAD_ERR_OK || $error > UPLOAD_ERR_EXTENSION) {
			throw new InvalidMessage(sprintf('Invalid upload error code %d.', $error));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getStream(): StreamInterface
	{
		$this->assertUsable();

		return $this->file instanceof StreamInterface ? $this->file : Stream::fromFile($this->file);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function moveTo(string $targetPath): void
	{
		$this->assertUsable();

		if ($targetPath === '' || str_contains($targetPath, "\0")) {
			throw new UploadedFileException('The target path must be a non-empty file path.');
		}

		if ($this->file instanceof StreamInterface) {
			$this->copyStream($this->file, $targetPath);
		} else {
			$moved = PHP_SAPI === 'cli'
				? @rename($this->file, $targetPath)
				: @move_uploaded_file($this->file, $targetPath);

			if (! $moved) {
				throw new UploadedFileException(sprintf('Unable to move the uploaded file to "%s".', $targetPath));
			}
		}

		$this->moved = true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getSize(): ?int
	{
		return $this->size;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getError(): int
	{
		return $this->error;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getClientFilename(): ?string
	{
		return $this->clientFilename;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getClientMediaType(): ?string
	{
		return $this->clientMediaType;
	}

	/**
	 * Throws when the upload failed or the file was already moved.
	 *
	 * @throws UploadedFileException
	 */
	private function assertUsable(): void
	{
		if ($this->error !== UPLOAD_ERR_OK) {
			throw new UploadedFileException(sprintf('The file was not uploaded (error %d).', $this->error));
		}

		if ($this->moved) {
			throw new UploadedFileException('The uploaded file has already been moved.');
		}
	}

	/**
	 * Copies a stream to a file.
	 *
	 * @throws UploadedFileException
	 */
	private function copyStream(StreamInterface $stream, string $targetPath): void
	{
		try {
			$target = Stream::fromFile($targetPath, 'w');
		} catch (StreamException $exception) {
			throw new UploadedFileException(sprintf('Unable to write "%s".', $targetPath), 0, $exception);
		}

		if ($stream->isSeekable()) {
			$stream->rewind();
		}

		while (! $stream->eof()) {
			$target->write($stream->read(65536));
		}

		$target->close();
	}
}
