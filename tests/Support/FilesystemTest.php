<?php

/**
 * Filesystem tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;
use Blush\Support\PhpArrayFile;
use Blush\Tests\Fixtures\Container\Status;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Filesystem::class)]
#[CoversClass(PhpArrayFile::class)]
final class FilesystemTest extends TestCase
{
	use TemporaryDirectory;

	/**
	 * @return iterable<string, array{string, string}>
	 */
	public static function normalizeCases(): iterable
	{
		yield 'plain'          => ['/a/b/c', '/a/b/c'];
		yield 'dot segments'   => ['/a/./b/../c', '/a/c'];
		yield 'double slashes' => ['/a//b///c/', '/a/b/c'];
		yield 'above root'     => ['/../a', '/a'];
		yield 'relative'       => ['a/../../b', '../b'];
		yield 'backslashes'    => ['/a\\b', '/a/b'];
	}

	#[DataProvider('normalizeCases')]
	public function testNormalize(string $path, string $expected): void
	{
		$this->assertSame($expected, new Filesystem()->normalize($path));
	}

	public function testConfineJoinsInsideTheRoot(): void
	{
		$this->assertSame('/site/user/content/post.md', new Filesystem()->confine('/site/user', 'content/./post.md'));
	}

	public function testConfineRejectsEscapes(): void
	{
		$this->expectException(FilesystemException::class);

		new Filesystem()->confine('/site/user', '../config/app.php');
	}

	public function testConfineRejectsSiblingPrefixes(): void
	{
		$this->expectException(FilesystemException::class);

		new Filesystem()->confine('/site/user', '../user-other/file');
	}

	public function testWriteAtomicCreatesDirectories(): void
	{
		$path = $this->temporaryDirectory() . '/nested/dir/file.txt';

		new Filesystem()->writeAtomic($path, 'hello');

		$this->assertSame('hello', file_get_contents($path));
		$this->assertSame([], glob(dirname($path) . '/.blush-*'));
	}

	public function testPhpArrayFileRoundTrips(): void
	{
		$file = new PhpArrayFile($this->temporaryDirectory() . '/data.php');
		$data = ['a' => 1, 'b' => [true, null, 1.5], 'status' => Status::Inactive];

		$this->assertNull($file->read());

		$file->write($data);

		$this->assertSame($data, $file->read());
	}

	public function testPhpArrayFileRejectsNonArrays(): void
	{
		$path = $this->writeTemporaryFile('bad.php', "<?php\n\nreturn 'nope';\n");

		$this->expectException(FilesystemException::class);

		new PhpArrayFile($path)->read();
	}
}
