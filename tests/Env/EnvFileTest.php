<?php

/**
 * Env file tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Env;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Env\EnvException;
use Blush\Env\EnvFile;
use Blush\Env\EnvParser;
use Blush\Support\Filesystem;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(EnvFile::class)]
final class EnvFileTest extends TestCase
{
	use TemporaryDirectory;

	public function testAMissingFileLoadsAsEmpty(): void
	{
		$file = EnvFile::load($this->temporaryDirectory() . '/.env');

		$this->assertFalse($file->exists());
		$this->assertSame('', $file->contents);
		$this->assertNull($file->get('APP_NAME'));
	}

	public function testReadsValues(): void
	{
		$file = new EnvFile('.env', "APP_NAME=\"My Site\"\nEMPTY=\n");

		$this->assertSame('My Site', $file->get('APP_NAME'));
		$this->assertTrue($file->filled('APP_NAME'));
		$this->assertFalse($file->filled('EMPTY'));
		$this->assertFalse($file->filled('MISSING'));
	}

	public function testRewritesOnlyTheLineThatSetsTheVariable(): void
	{
		$file = new EnvFile('.env', "# The environment.\nAPP_ENV=production\n\n# Name.\nAPP_NAME=\"Blush\"\n")
			->with('APP_ENV', 'development')
			->with('APP_NAME', 'Mine');

		$this->assertSame("# The environment.\nAPP_ENV=development\n\n# Name.\nAPP_NAME=\"Mine\"\n", $file->contents);
	}

	public function testKeepsAnExportPrefixAndRewritesTheLastAssignment(): void
	{
		$file = new EnvFile('.env', "export KEY=one\nexport KEY=two\n")->with('KEY', 'three');

		$this->assertSame("export KEY=one\nexport KEY=three\n", $file->contents);
		$this->assertSame('three', $file->get('KEY'));
	}

	public function testAppendsAMissingVariable(): void
	{
		$this->assertSame("A=1\nB=2\n", new EnvFile('.env', 'A=1')->with('B', '2')->contents);
		$this->assertSame("B=2\n", new EnvFile('.env')->with('B', '2')->contents);
	}

	public function testQuotesValuesSoTheParserReadsThemBack(): void
	{
		$value = "It's \"quoted\" \\ with \$HOME and\na new line";
		$file  = new EnvFile('.env', "OTHER=x\n")->with('TRICKY', $value)->with('SIMPLE', 'https://example.com/a');

		$this->assertStringContainsString("SIMPLE=https://example.com/a\n", $file->contents);
		$this->assertSame($value, new EnvParser()->parse($file->contents, ['HOME' => '/home/me'])['TRICKY']);
	}

	public function testRefusesToRewriteAMultiLineValue(): void
	{
		$this->expectException(EnvException::class);

		(void) new EnvFile('.env', "GREETING=\"Hello\nworld\"\n")->with('GREETING', 'Hi');
	}

	public function testRejectsAnInvalidName(): void
	{
		$this->expectException(EnvException::class);

		(void) new EnvFile('.env')->with('1BAD', 'x');
	}

	public function testSaves(): void
	{
		$path = $this->temporaryDirectory() . '/.env';

		new EnvFile($path)->with('APP_NAME', 'Saved')->save(new Filesystem());

		$this->assertSame('Saved', EnvFile::load($path)->get('APP_NAME'));
	}
}
