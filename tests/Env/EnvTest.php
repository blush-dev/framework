<?php

/**
 * Env tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Env;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Core\Environment;
use Blush\Env\Env;
use Blush\Env\EnvException;
use Blush\Env\EnvParser;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Env::class)]
#[CoversClass(EnvParser::class)]
final class EnvTest extends TestCase
{
	use TemporaryDirectory;

	public function testParsesTheSupportedSyntax(): void
	{
		$contents = <<<'ENV'
			# A comment
			APP_ENV=production
			export APP_URL=https://example.com   # trailing comment
			APP_NAME="My ${APP_ENV} Site"
			SECRET='lit${eral}#not-a-comment'
			ESCAPES="a\tb\n\"c\" \$HOME"
			MULTI="line one
			line two"
			EMPTY=
			SPACED = value with spaces
			FROM_ENV=${HOST_VAR}
			ENV;

		$values = new EnvParser()->parse($contents, ['HOST_VAR' => 'host']);

		$this->assertSame([
			'APP_ENV'  => 'production',
			'APP_URL'  => 'https://example.com',
			'APP_NAME' => 'My production Site',
			'SECRET'   => 'lit${eral}#not-a-comment',
			'ESCAPES'  => "a\tb\n\"c\" \$HOME",
			'MULTI'    => "line one\nline two",
			'EMPTY'    => '',
			'SPACED'   => 'value with spaces',
			'FROM_ENV' => 'host'
		], $values);
	}

	/**
	 * @return iterable<string, array{string}>
	 */
	public static function invalidSyntax(): iterable
	{
		yield 'no equals'       => ["APP_ENV\n"];
		yield 'bad name'        => ["1APP=x\n"];
		yield 'unterminated'    => ["A=\"open\n"];
		yield 'text after quote' => ["A='x' y\n"];
	}

	#[DataProvider('invalidSyntax')]
	public function testRejectsInvalidSyntax(string $contents): void
	{
		$this->expectException(EnvException::class);

		new EnvParser()->parse($contents);
	}

	public function testReportsTheLineNumber(): void
	{
		$this->expectExceptionMessage('line 3');

		new EnvParser()->parse("A=1\n\nC\n");
	}

	public function testProcessEnvironmentWinsOverTheFile(): void
	{
		$file = $this->writeTemporaryFile('.env', "APP_ENV=development\nAPP_NAME=File\n");

		$env = Env::load($file, ['APP_ENV' => 'production']);

		$this->assertSame('production', $env->string('APP_ENV'));
		$this->assertSame('File', $env->string('APP_NAME'));
	}

	public function testMissingFileUsesTheEnvironmentOnly(): void
	{
		$env = Env::load($this->temporaryDirectory() . '/.env', ['A' => '1']);

		$this->assertSame(['A' => '1'], $env->all());
	}

	public function testTypedAccessors(): void
	{
		$env = new Env([
			'NAME'  => 'Blush',
			'YES'   => 'on',
			'NO'    => '',
			'COUNT' => '42',
			'RATIO' => '1.5',
			'LIST'  => 'a, b,,c ',
			'ENV'   => 'staging'
		]);

		$this->assertSame('Blush', $env->string('NAME'));
		$this->assertSame('fallback', $env->string('MISSING', 'fallback'));
		$this->assertNull($env->get('MISSING'));
		$this->assertTrue($env->bool('YES'));
		$this->assertFalse($env->bool('NO'));
		$this->assertTrue($env->bool('MISSING', true));
		$this->assertSame(42, $env->int('COUNT'));
		$this->assertSame(1.5, $env->float('RATIO'));
		$this->assertSame(['a', 'b', 'c'], $env->list('LIST'));
		$this->assertSame(Environment::Staging, $env->enum('ENV', Environment::class));
	}

	public function testMissingWithoutDefaultThrows(): void
	{
		$this->expectExceptionMessage('"MISSING" is not set');

		new Env()->string('MISSING');
	}

	public function testInvalidValuesThrowWithoutRevealingThem(): void
	{
		try {
			new Env(['SECRET' => 'hunter2'])->int('SECRET');
			$this->fail('Expected an exception.');
		} catch (EnvException $e) {
			$this->assertStringNotContainsString('hunter2', $e->getMessage());
		}
	}
}
