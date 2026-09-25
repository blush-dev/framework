<?php

/**
 * Uploaded file tests.
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
use Blush\Http\InvalidMessage;
use Blush\Http\Stream;
use Blush\Http\UploadedFile;
use Blush\Http\UploadedFileException;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
	use TemporaryDirectory;

	public function testMovesAStreamOnce(): void
	{
		$file   = new UploadedFile(Stream::fromString('uploaded'), 8, UPLOAD_ERR_OK, 'photo.jpg', 'image/jpeg');
		$target = $this->temporaryDirectory() . '/moved.jpg';

		$this->assertSame(8, $file->getSize());
		$this->assertSame('photo.jpg', $file->getClientFilename());
		$this->assertSame('image/jpeg', $file->getClientMediaType());
		$this->assertSame(UPLOAD_ERR_OK, $file->getError());
		$this->assertSame('uploaded', (string) $file->getStream());

		$file->moveTo($target);

		$this->assertSame('uploaded', file_get_contents($target));

		$this->expectException(UploadedFileException::class);
		$file->moveTo($target);
	}

	public function testMovesATemporaryFile(): void
	{
		$source = $this->writeTemporaryFile('php-upload', 'temp');
		$target = $this->temporaryDirectory() . '/final.txt';

		new UploadedFile($source, 4)->moveTo($target);

		$this->assertFileDoesNotExist($source);
		$this->assertSame('temp', file_get_contents($target));
	}

	public function testFailedUploadsCantBeRead(): void
	{
		$file = new UploadedFile('', null, UPLOAD_ERR_NO_FILE);

		$this->assertSame(UPLOAD_ERR_NO_FILE, $file->getError());

		$this->expectException(UploadedFileException::class);
		$file->getStream();
	}

	public function testRejectsUnknownErrorCodes(): void
	{
		$this->expectException(InvalidMessage::class);

		new UploadedFile('', null, 99);
	}
}
